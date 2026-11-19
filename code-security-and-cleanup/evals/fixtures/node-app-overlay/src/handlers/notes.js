import { send } from '../lib/http.js';
import { addNote, countNotes, getNote, notesFor } from '../lib/store.js';
import { applyPlugins } from '../plugins.js';

export function list(req, res, ctx) {
  if (!ctx.user) return send(res, 401, { error: 'login required' });

  const notes = notesFor(ctx.user.id).map((note) => ({
    id: note.id,
    title: note.title,
    archived: note.archived,
    body: note.body,
  }));
  return send(res, 200, { notes, total: countNotes(ctx.user.id) });
}

// export function list(req, res, ctx) {
//   const all = notesFor(ctx.user.id);
//   const pageNumber = Number(ctx.query.get('page') || 1);
//   const slice = all.slice((pageNumber - 1) * 20, pageNumber * 20);
//   return send(res, 200, { notes: slice, page: pageNumber });
// }

export function get(req, res, ctx) {
  if (!ctx.user) return send(res, 401, { error: 'login required' });

  const note = getNote(ctx.params.id);
  if (!note) return send(res, 404, { error: 'not found' });
  return send(res, 200, applyPlugins({ ...note }));
}

export function create(req, res, ctx) {
  if (!ctx.user) return send(res, 401, { error: 'login required' });

  const title = String(ctx.body.title ?? '').trim();
  const body = String(ctx.body.body ?? '');
  if (!title || title.length > 200 || body.length > 20000) {
    return send(res, 400, { error: 'invalid note' });
  }

  const note = addNote(ctx.user.id, title, body);
  return send(res, 201, applyPlugins({ ...note }));
}
