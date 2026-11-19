import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { after, before, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { startApp } from './helpers.js';

const root = fileURLToPath(new URL('..', import.meta.url));
let app;

before(async () => {
  app = await startApp();
});

after(async () => {
  await app.close();
});

test('health check answers without a login', async () => {
  const response = await fetch(`${app.base}/health`);
  assert.equal(response.status, 200);
  assert.deepEqual(await response.json(), { status: 'ok' });
});

test('search page shows the search term', async () => {
  const response = await fetch(`${app.base}/search?q=milk`);
  assert.equal(response.status, 200);
  assert.match(await response.text(), /Results for milk/);
});

test('home page and its script are served', async () => {
  const page = await fetch(`${app.base}/`);
  assert.equal(page.status, 200);
  assert.match(await page.text(), /<h1>Notewell<\/h1>/);

  const script = await fetch(`${app.base}/app.js`);
  assert.equal(script.status, 200);
});

test('every inline handler in the page exists in the page script', () => {
  const page = readFileSync(`${root}public/index.html`, 'utf8');
  const script = readFileSync(`${root}public/app.js`, 'utf8');
  const names = [...page.matchAll(/\son\w+="(\w+)\(/g)].map((match) => match[1]);

  assert.ok(names.length > 0);
  for (const name of names) {
    assert.match(script, new RegExp(`function ${name}\\(`), `${name} is missing from public/app.js`);
  }
});

test('every file named in a package script exists', () => {
  const { scripts } = JSON.parse(readFileSync(`${root}package.json`, 'utf8'));
  for (const command of Object.values(scripts)) {
    for (const match of command.matchAll(/node (\S+\.js)/g)) {
      assert.ok(existsSync(`${root}${match[1]}`), `${match[1]} is missing`);
    }
  }
});

test('unknown paths give 404', async () => {
  const response = await fetch(`${app.base}/nope`);
  assert.equal(response.status, 404);
});
