export default function addTimestamps(note) {
  return { ...note, createdAt: note.createdAt ?? new Date().toISOString() };
}
