export function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

export function layout(title, content) {
  return [
    '<!doctype html>',
    '<html lang="en">',
    `<head><meta charset="utf-8"><title>${escapeHtml(title)} · Notewell</title></head>`,
    `<body>${content}<footer>Notewell</footer></body>`,
    '</html>',
  ].join('\n');
}

export function renderBanner(message) {
  return `<div class="banner" role="status">${escapeHtml(message)}</div>`;
}
