import { exec, execFile } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { send } from '../lib/http.js';

const DATA_DIR = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'storage');

export function usage(req, res, ctx) {
  if (!ctx.user) return send(res, 401, { error: 'login required' });

  return execFile('du', ['-sk', DATA_DIR], (error, stdout) => {
    if (error) return send(res, 500, { error: 'usage unavailable' });
    return send(res, 200, { kilobytes: Number(stdout.split('\t')[0]) });
  });
}

export function exportArchive(req, res, ctx) {
  if (!ctx.user) return send(res, 401, { error: 'login required' });

  const folder = ctx.query.get('folder') || 'notes';
  const archive = path.join(os.tmpdir(), `notewell-${Date.now()}.tgz`);

  return exec(`tar -czf ${archive} -C ${DATA_DIR} ${folder}`, (error) => {
    if (error) return send(res, 400, { error: 'export failed' });
    return send(res, 200, { archive });
  });
}
