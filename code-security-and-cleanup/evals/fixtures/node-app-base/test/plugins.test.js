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

test('enabled plugins add their fields to a note', async () => {
  const response = await fetch(
    `${app.base}/api/notes`,
    authed(app.tokens.alice, json({ title: 'Plan', body: 'three short words' })),
  );
  const note = await response.json();

  assert.equal(note.wordCount, 3);
  assert.ok(!Number.isNaN(Date.parse(note.createdAt)));
});
