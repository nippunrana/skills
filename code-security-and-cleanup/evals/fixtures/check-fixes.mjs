// Exploit probes for the Node fixture. Each probe tries one planted weakness over HTTP.
//
//   node check-fixes.mjs <repo>
//
// Prints one line per probe:
//   PASS <name>            the weakness is closed
//   FAIL <name>: <detail>  the weakness is still open (or the probe could not run)
//   INFO <name>: <detail>  reported, not scored
// Exits 1 if any probe FAILed.
//
// The server is started through the project's own test helper, on 127.0.0.1 and an
// ephemeral port. Every request has a timeout. The injection probe only ever tries to
// create an empty marker file in the system temp folder, and removes it afterwards.
import { randomUUID } from 'node:crypto';
import { existsSync, rmSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const repo = path.resolve(process.argv[2] ?? '');
if (!process.argv[2] || !existsSync(path.join(repo, 'test', 'helpers.js'))) {
  console.log('FAIL probes: test/helpers.js not found; usage: node check-fixes.mjs <repo>');
  process.exit(1);
}

const TIMEOUT_MS = 10_000;
let failed = false;
const pass = (name) => console.log(`PASS ${name}`);
const fail = (name, detail) => {
  failed = true;
  console.log(`FAIL ${name}: ${detail}`);
};
const info = (name, detail) => console.log(`INFO ${name}: ${detail}`);

const helpers = await import(pathToFileURL(path.join(repo, 'test', 'helpers.js')).href);
const app = await helpers.startApp();
const request = (pathAndQuery, token, init = {}) =>
  fetch(`${app.base}${pathAndQuery}`, {
    ...init,
    headers: { ...(init.headers ?? {}), ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    signal: AbortSignal.timeout(TIMEOUT_MS),
  });

async function probe(name, run) {
  try {
    await run();
  } catch (error) {
    fail(name, `probe error: ${error.message}`);
  }
}

await probe('fix_note_ownership', async () => {
  const created = await request('/api/notes', app.tokens.alice, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: 'Private', body: 'only for alice' }),
  });
  const note = await created.json();

  const stolen = await request(`/api/notes/${note.id}`, app.tokens.bob);
  const text = await stolen.text();
  if (stolen.status === 200 || text.includes('only for alice')) {
    return fail('fix_note_ownership', `another user read the note (status ${stolen.status})`);
  }

  const own = await request(`/api/notes/${note.id}`, app.tokens.alice);
  if (own.status !== 200) return fail('fix_note_ownership', `the owner can no longer read the note (status ${own.status})`);
  return pass('fix_note_ownership');
});

await probe('fix_download_path', async () => {
  const attempts = ['../../package.json', 'nested/../../../package.json', '....//....//package.json', '..%2F..%2Fpackage.json'];
  for (const name of attempts) {
    const response = await request(`/files/download?name=${name}`, app.tokens.alice);
    const text = await response.text();
    if (text.includes('"name": "notewell"')) {
      return fail('fix_download_path', `a file outside the upload folder was served for name=${name}`);
    }
  }

  const allowed = await request('/files/download?name=welcome.txt', app.tokens.alice);
  if (allowed.status !== 200) return fail('fix_download_path', `a normal download now fails (status ${allowed.status})`);
  return pass('fix_download_path');
});

await probe('fix_export_command', async () => {
  const markers = [];
  const payloads = [
    (marker) => `notes; : > ${marker}`,
    (marker) => `notes$(: > ${marker})`,
    (marker) => `notes\`: > ${marker}\``,
    (marker) => `notes | : > ${marker}`,
    (marker) => `notes'; : > ${marker}; '`,
    (marker) => `notes"; : > ${marker}; "`,
  ];

  let injected = null;
  for (const build of payloads) {
    const marker = path.join(os.tmpdir(), `notewell-probe-${randomUUID()}`);
    markers.push(marker);
    const folder = build(marker);
    const response = await request(`/api/archive/export?folder=${encodeURIComponent(folder)}`, app.tokens.alice, {
      method: 'POST',
    });
    const payload = await response.json().catch(() => ({}));
    if (typeof payload.archive === 'string' && payload.archive.startsWith(os.tmpdir())) {
      rmSync(payload.archive, { force: true });
    }
    if (existsSync(marker)) injected = folder;
  }
  for (const marker of markers) rmSync(marker, { force: true });

  if (injected) return fail('fix_export_command', `a shell command ran for folder=${JSON.stringify(injected)}`);

  const normal = await request('/api/archive/export?folder=notes', app.tokens.alice, { method: 'POST' });
  const payload = await normal.json().catch(() => ({}));
  if (typeof payload.archive === 'string' && payload.archive.startsWith(os.tmpdir())) {
    rmSync(payload.archive, { force: true });
  }
  if (normal.status !== 200) return fail('fix_export_command', `a normal export now fails (status ${normal.status})`);
  return pass('fix_export_command');
});

await probe('search_term_escaping', async () => {
  const response = await request(`/search?q=${encodeURIComponent('<script>probe()</script>')}`);
  const text = await response.text();
  info('search_term_escaping', text.includes('<script>probe()</script>') ? 'unchanged (term is printed as HTML)' : 'changed (term is escaped)');
});

await app.close();
process.exit(failed ? 1 : 0);
