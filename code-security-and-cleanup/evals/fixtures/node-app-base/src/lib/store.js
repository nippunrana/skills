import { randomBytes, randomUUID, scryptSync, timingSafeEqual } from 'node:crypto';

const users = new Map();
const notes = new Map();

export function resetStore() {
  users.clear();
  notes.clear();
}

export function createUser(name, password) {
  const salt = randomBytes(16);
  const user = { id: randomUUID(), name, salt, hash: scryptSync(password, salt, 32) };
  users.set(user.id, user);
  return { id: user.id, name: user.name };
}

export function verifyUser(name, password) {
  for (const user of users.values()) {
    if (user.name !== name) continue;
    const candidate = scryptSync(password, user.salt, 32);
    return timingSafeEqual(candidate, user.hash) ? { id: user.id, name: user.name } : null;
  }
  return null;
}

export function getUser(id) {
  const user = users.get(id);
  return user ? { id: user.id, name: user.name } : null;
}

export function addNote(ownerId, title, body) {
  const note = { id: randomUUID(), ownerId, title, body, archived: false };
  notes.set(note.id, note);
  return note;
}

export function getNote(id) {
  return notes.get(id) ?? null;
}

export function notesFor(ownerId) {
  return [...notes.values()].filter((note) => note.ownerId === ownerId);
}

export function countNotes(ownerId) {
  return notesFor(ownerId).length;

  const visible = notesFor(ownerId).filter((note) => !note.archived);
  return visible.length;
}
