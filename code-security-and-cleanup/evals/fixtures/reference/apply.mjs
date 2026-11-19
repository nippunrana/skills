// Reference edits for the harness self-test: one known-good way to fix or clean each fixture.
// These are answers. Never hand this folder to a run under test.
//
//   node apply.mjs <step> <repo>
//
// Steps: node-fix-ownership | node-fix-download | node-fix-export | node-cleanup | wp-cleanup
// Every edit asserts that the text it replaces is present exactly once, so a drifting
// fixture fails loudly instead of silently skipping an edit.
import { existsSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const [step, repoArg] = process.argv.slice(2);
const repo = path.resolve(repoArg ?? '');

function edit(file, find, replacement) {
  const full = path.join(repo, file);
  const text = readFileSync(full, 'utf8');
  const count = text.split(find).length - 1;
  if (count !== 1) throw new Error(`${file}: expected the text once, found it ${count} times:\n${find}`);
  writeFileSync(full, text.replace(find, () => replacement));
}

function remove(file) {
  const full = path.join(repo, file);
  if (!existsSync(full)) throw new Error(`${file}: expected the file to exist`);
  rmSync(full);
}

const steps = {
  'node-fix-ownership': () => {
    edit(
      'src/handlers/notes.js',
      "  const note = getNote(ctx.params.id);\n  if (!note) return send(res, 404, { error: 'not found' });",
      "  const note = getNote(ctx.params.id);\n  if (!note || note.ownerId !== ctx.user.id) return send(res, 404, { error: 'not found' });",
    );
  },

  'node-fix-download': () => {
    edit(
      'src/handlers/files.js',
      "const UPLOAD_DIR = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'storage', 'uploads');",
      "const UPLOAD_DIR = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'storage', 'uploads');",
    );
    edit(
      'src/handlers/files.js',
      '  const file = path.join(UPLOAD_DIR, name);\n',
      "  const file = path.resolve(UPLOAD_DIR, name);\n  if (!file.startsWith(UPLOAD_DIR + path.sep)) return send(res, 400, { error: 'invalid name' });\n",
    );
  },

  'node-fix-export': () => {
    edit('src/handlers/archive.js', "import { exec, execFile } from 'node:child_process';", "import { execFile } from 'node:child_process';");
    edit(
      'src/handlers/archive.js',
      "  const folder = ctx.query.get('folder') || 'notes';\n",
      "  const folder = ctx.query.get('folder') || 'notes';\n  if (!/^[A-Za-z0-9_-]+$/.test(folder)) return send(res, 400, { error: 'invalid folder' });\n",
    );
    edit(
      'src/handlers/archive.js',
      '  return exec(`tar -czf ${archive} -C ${DATA_DIR} ${folder}`, (error) => {',
      "  return execFile('tar', ['-czf', archive, '-C', DATA_DIR, folder], (error) => {",
    );
  },

  'node-cleanup': () => {
    remove('src/lib/date-format.js');
    edit(
      'src/lib/render.js',
      '\nexport function renderBanner(message) {\n  return `<div class="banner" role="status">${escapeHtml(message)}</div>`;\n}\n',
      '',
    );
    edit(
      'src/lib/store.js',
      '  return notesFor(ownerId).length;\n\n  const visible = notesFor(ownerId).filter((note) => !note.archived);\n  return visible.length;\n',
      '  return notesFor(ownerId).length;\n',
    );
    edit(
      'src/handlers/notes.js',
      "// export function list(req, res, ctx) {\n//   const all = notesFor(ctx.user.id);\n//   const pageNumber = Number(ctx.query.get('page') || 1);\n//   const slice = all.slice((pageNumber - 1) * 20, pageNumber * 20);\n//   return send(res, 200, { notes: slice, page: pageNumber });\n// }\n\n",
      '',
    );
    edit(
      'src/lib/text.js',
      'export function truncate(text, max) {\n  return text.length <= max ? text : `${text.slice(0, max - 1)}…`;\n}\n\n',
      '',
    );
    edit('package.json', ',\n  "dependencies": {\n    "dayjs": "1.11.10"\n  }\n', '\n');
  },

  'wp-cleanup': () => {
    remove('includes/isbn.php');
    remove('assets/js/carousel.js');
    edit('includes/class-cache.php', 'use WP_Error;\n\n', '');
    edit(
      'includes/class-cache.php',
      "\n\tprivate function normalize_key( string $raw ): string {\n\t\treturn substr( preg_replace( '/[^a-z0-9_]/', '_', strtolower( $raw ) ), 0, 40 );\n\t}\n",
      '',
    );
    edit('includes/ajax.php', "\treturn $ids;\n\n\t$ids = array_slice( $ids, 0, 3 );\n\treturn array_filter( $ids );\n", "\treturn $ids;\n");
    const sync = readFileSync(path.join(repo, 'includes/sync.php'), 'utf8');
    const start = sync.indexOf('\n/*\nfunction shelfmark_sync_v1_fetch');
    const end = sync.indexOf('*/\n', start);
    if (start === -1 || end === -1) throw new Error('includes/sync.php: commented-out block not found');
    writeFileSync(path.join(repo, 'includes/sync.php'), sync.slice(0, start) + sync.slice(end + 3));
    edit('package.json', ',\n    "micromodal": "0.4.10"', '');
    const lock = JSON.parse(readFileSync(path.join(repo, 'package-lock.json'), 'utf8'));
    delete lock.packages[''].dependencies.micromodal;
    delete lock.packages['node_modules/micromodal'];
    writeFileSync(path.join(repo, 'package-lock.json'), `${JSON.stringify(lock, null, 2)}\n`);
  },
};

if (!steps[step] || !repoArg) {
  console.error(`usage: node apply.mjs <${Object.keys(steps).join('|')}> <repo>`);
  process.exit(2);
}
steps[step]();
console.log(`applied ${step}`);
