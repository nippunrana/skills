import { send } from '../lib/http.js';
import { escapeHtml, layout } from '../lib/render.js';
import { notesFor } from '../lib/store.js';

function escapeRegExp(text) {
  return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

export function page(req, res, ctx) {
  const q = ctx.query.get('q') ?? '';
  let items = '';

  if (ctx.user && q) {
    const pattern = new RegExp(escapeRegExp(q), 'i');
    items = notesFor(ctx.user.id)
      .filter((note) => pattern.test(note.title) || pattern.test(note.body))
      .map((note) => `<li>${escapeHtml(note.title)}</li>`)
      .join('');
  }

  const html = layout('Search', `<h1>Results for ${q}</h1><ul>${items}</ul>`);
  return send(res, 200, html, 'text/html; charset=utf-8');
}
