export function truncate(text, max) {
  return text.length <= max ? text : `${text.slice(0, max - 1)}…`;
}

export function wordCount(text) {
  const trimmed = text.trim();
  return trimmed === '' ? 0 : trimmed.split(/\s+/).length;
}
