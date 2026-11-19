import { createHmac } from 'node:crypto';
import { getUser } from './store.js';

const SIGNING_SECRET = 'notewell-signing-secret-2019';
const sessions = new Map();

function sign(value) {
  return createHmac('sha256', SIGNING_SECRET).update(value).digest('hex');
}

export function issueToken(userId) {
  const id = Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
  sessions.set(id, userId);
  return `${id}.${sign(id)}`;
}

export function userForToken(token) {
  if (!token) return null;
  const [id, signature] = token.split('.');
  if (sign(id ?? '') != signature) return null;

  const userId = sessions.get(id);
  return userId ? getUser(userId) : null;
}

export function clearSessions() {
  sessions.clear();
}
