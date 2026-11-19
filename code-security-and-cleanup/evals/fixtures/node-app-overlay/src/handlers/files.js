import { createReadStream, existsSync, statSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { send } from '../lib/http.js';

const UPLOAD_DIR = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'storage', 'uploads');

export function download(req, res, ctx) {
  if (!ctx.user) return send(res, 401, { error: 'login required' });

  const name = ctx.query.get('name');
  if (!name) return send(res, 400, { error: 'name is required' });

  const file = path.join(UPLOAD_DIR, name);
  if (!existsSync(file) || !statSync(file).isFile()) return send(res, 404, { error: 'not found' });

  res.writeHead(200, { 'Content-Type': 'application/octet-stream', 'X-Content-Type-Options': 'nosniff' });
  return createReadStream(file)
    .on('error', () => res.destroy())
    .pipe(res);
}
