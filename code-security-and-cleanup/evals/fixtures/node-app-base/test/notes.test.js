import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { authed, json, startApp } from './helpers.js';

let app;

before(async () => {
  app = await startApp();
});

after(async () => {
  await app.close();
});

test('a user can create, list and read their own note', async () => {
  const created = await fetch(
    `${app.base}/api/notes`,
    authed(app.tokens.alice, json({ title: 'Groceries', body: 'milk eggs bread' })),
  );
  assert.equal(created.status, 201);
  const note = await created.json();
  assert.match(note.id, /^[0-9a-f-]{36}$/);

  const listed = await (await fetch(`${app.base}/api/notes`, authed(app.tokens.alice))).json();
  assert.equal(listed.total, 1);
  assert.equal(listed.notes[0].title, 'Groceries');

  const read = await fetch(`${app.base}/api/notes/${note.id}`, authed(app.tokens.alice));
  assert.equal(read.status, 200);
  assert.equal((await read.json()).title, 'Groceries');
});

test('each user sees only their own notes in the list', async () => {
  const listed = await (await fetch(`${app.base}/api/notes`, authed(app.tokens.bob))).json();
  assert.equal(listed.total, 0);
  assert.deepEqual(listed.notes, []);
});

test('notes need a login', async () => {
  const response = await fetch(`${app.base}/api/notes`);
  assert.equal(response.status, 401);
});

test('a note without a title is rejected', async () => {
  const response = await fetch(`${app.base}/api/notes`, authed(app.tokens.alice, json({ title: '  ', body: 'x' })));
  assert.equal(response.status, 400);
});

test('a wrong password does not log in', async () => {
  const response = await fetch(`${app.base}/api/session`, json({ username: 'alice', password: 'nope' }));
  assert.equal(response.status, 401);
});
