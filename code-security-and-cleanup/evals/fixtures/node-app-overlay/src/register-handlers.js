import * as archive from './handlers/archive.js';
import * as files from './handlers/files.js';
import * as health from './handlers/health.js';
import * as notes from './handlers/notes.js';
import * as search from './handlers/search.js';
import * as session from './handlers/session.js';
import { register } from './registry.js';

const modules = { archive, files, health, notes, search, session };

for (const [prefix, module] of Object.entries(modules)) {
  for (const [name, handler] of Object.entries(module)) {
    register(`${prefix}.${name}`, handler);
  }
}
