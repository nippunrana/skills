import http from 'node:http';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import './register-handlers.js';
import { userForToken } from './lib/auth.js';
import { send } from './lib/http.js';
import { loadPlugins } from './plugins.js';
import { getHandler } from './registry.js';

const ROOT = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const PUBLIC_DIR = path.join(ROOT, 'public');
const STATIC_FILES = new Map([
  ['/', ['index.html', 'text/html; charset=utf-8']],
  ['/app.js', ['app.js', 'text/javascript; charset=utf-8']],
]);
const MAX_BODY_BYTES = 64 * 1024;

const routes = [
  ['GET', '/health', 'health.check'],
  ['POST', '/api/session', 'session.create'],
  ['GET', '/api/notes', 'notes.list'],
  ['POST', '/api/notes', 'notes.create'],
  ['GET', '/api/notes/:id', 'notes.get'],
  ['GET', '/search', 'search.page'],
  ['GET', '/files/download', 'files.download'],
  ['GET', '/api/archive/usage', 'archive.usage'],
  ['POST', '/api/archive/export', 'archive.exportArchive'],
];

function matchPath(pattern, pathname) {
  const want = pattern.split('/');
  const got = pathname.split('/');
  if (want.length !== got.length) return null;

  const params = {};
  for (let i = 0; i < want.length; i += 1) {
    if (want[i].startsWith(':')) {
      params[want[i].slice(1)] = decodeURIComponent(got[i]);
    } else if (want[i] !== got[i]) {
      return null;
    }
  }
  return params;
}

function matchRoute(method, pathname) {
  for (const [routeMethod, pattern, name] of routes) {
    if (routeMethod !== method) continue;
    const params = matchPath(pattern, pathname);
    if (params) return { name, params };
  }
  return null;
}

async function readBody(req) {
  const chunks = [];
  let size = 0;
  for await (const chunk of req) {
    size += chunk.length;
    if (size > MAX_BODY_BYTES) throw Object.assign(new Error('body too large'), { status: 413 });
    chunks.push(chunk);
  }
  if (chunks.length === 0) return {};

  try {
    const parsed = JSON.parse(Buffer.concat(chunks).toString('utf8'));
    return parsed && typeof parsed === 'object' ? parsed : {};
  } catch {
    throw Object.assign(new Error('invalid JSON'), { status: 400 });
  }
}

async function handle(req, res) {
  const url = new URL(req.url, 'http://localhost');

  if (req.method === 'GET' && STATIC_FILES.has(url.pathname)) {
    const [file, type] = STATIC_FILES.get(url.pathname);
    return send(res, 200, await readFile(path.join(PUBLIC_DIR, file), 'utf8'), type);
  }

  let match;
  try {
    match = matchRoute(req.method, url.pathname);
  } catch {
    return send(res, 400, { error: 'bad path' });
  }
  if (!match) return send(res, 404, { error: 'not found' });

  const handler = getHandler(match.name);
  if (!handler) return send(res, 500, { error: 'handler missing' });

  const token = (req.headers.authorization ?? '').replace(/^Bearer /, '');
  const ctx = {
    user: userForToken(token),
    params: match.params,
    query: url.searchParams,
    body: req.method === 'POST' ? await readBody(req) : {},
  };
  return handler(req, res, ctx);
}

export function createServer() {
  return http.createServer((req, res) => {
    handle(req, res).catch((error) => {
      if (res.headersSent) return res.destroy();
      return send(res, error.status ?? 500, { error: error.status ? error.message : 'internal error' });
    });
  });
}

export async function start(port = Number(process.env.PORT) || 3000) {
  await loadPlugins();
  const server = createServer();
  await new Promise((resolve) => server.listen(port, '127.0.0.1', resolve));
  return server;
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  start().then((server) => console.log(`notewell listening on port ${server.address().port}`));
}
