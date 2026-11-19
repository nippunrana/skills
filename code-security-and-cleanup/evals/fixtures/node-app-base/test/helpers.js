import { randomUUID } from 'node:crypto';
import { clearSessions } from '../src/lib/auth.js';
import { createUser, resetStore } from '../src/lib/store.js';
import { start } from '../src/server.js';

export async function startApp() {
  resetStore();
  clearSessions();

  const passwords = { alice: randomUUID(), bob: randomUUID() };
  const users = {
    alice: createUser('alice', passwords.alice),
    bob: createUser('bob', passwords.bob),
  };

  const server = await start(0);
  const base = `http://127.0.0.1:${server.address().port}`;

  const tokens = {};
  for (const name of Object.keys(users)) {
    const response = await fetch(`${base}/api/session`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username: name, password: passwords[name] }),
    });
    tokens[name] = (await response.json()).token;
  }

  const close = () =>
    new Promise((resolve) => {
      server.closeAllConnections();
      server.close(resolve);
    });

  return { base, tokens, users, close };
}

export function authed(token, init = {}) {
  return { ...init, headers: { ...(init.headers ?? {}), Authorization: `Bearer ${token}` } };
}

export function json(payload) {
  return {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  };
}
