import assert from 'node:assert/strict';
import { existsSync, rmSync } from 'node:fs';
import { after, before, test } from 'node:test';
import { authed, startApp } from './helpers.js';

let app;

before(async () => {
  app = await startApp();
});

after(async () => {
  await app.close();
});

test('an uploaded file can be downloaded by name', async () => {
  const response = await fetch(`${app.base}/files/download?name=welcome.txt`, authed(app.tokens.alice));
  assert.equal(response.status, 200);
  assert.match(await response.text(), /Welcome to Notewell/);
});

test('a missing file gives 404', async () => {
  const response = await fetch(`${app.base}/files/download?name=nope.txt`, authed(app.tokens.alice));
  assert.equal(response.status, 404);
});

test('downloads need a login', async () => {
  const response = await fetch(`${app.base}/files/download?name=welcome.txt`);
  assert.equal(response.status, 401);
});

test('the notes folder can be exported as an archive', async () => {
  const response = await fetch(`${app.base}/api/archive/export?folder=notes`, authed(app.tokens.alice, { method: 'POST' }));
  assert.equal(response.status, 200);

  const { archive } = await response.json();
  assert.ok(existsSync(archive));
  rmSync(archive);
});

test('storage usage is reported in kilobytes', async () => {
  const response = await fetch(`${app.base}/api/archive/usage`, authed(app.tokens.alice));
  assert.equal(response.status, 200);
  assert.ok((await response.json()).kilobytes >= 0);
});
