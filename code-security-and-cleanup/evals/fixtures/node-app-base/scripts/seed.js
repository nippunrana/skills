import { randomBytes } from 'node:crypto';
import { addNote, createUser } from '../src/lib/store.js';
import { start } from '../src/server.js';

const password = process.env.DEMO_PASSWORD ?? randomBytes(9).toString('base64url');
const demo = createUser('demo', password);
addNote(demo.id, 'Welcome', 'This is a demo note. Edit it or add your own.');
addNote(demo.id, 'Shortcuts', 'Use the search page to find a note by any word in it.');

const server = await start();
console.log(`demo server on port ${server.address().port} (user "demo", password "${password}")`);
