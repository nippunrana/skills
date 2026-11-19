import { readFile } from 'node:fs/promises';

const active = [];

export async function loadPlugins(configUrl = new URL('../config/plugins.json', import.meta.url)) {
  active.length = 0;
  const { enabled } = JSON.parse(await readFile(configUrl, 'utf8'));

  for (const name of enabled) {
    if (!/^[a-z][a-z0-9-]*$/.test(name)) throw new Error(`invalid plugin name: ${name}`);
    const plugin = await import(`./plugins/${name}.js`);
    active.push(plugin.default);
  }
}

export function applyPlugins(note) {
  return active.reduce((current, plugin) => plugin(current), note);
}
