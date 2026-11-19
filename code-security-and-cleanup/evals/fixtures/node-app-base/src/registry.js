const handlers = new Map();

export function register(name, handler) {
  handlers.set(name, handler);
}

export function getHandler(name) {
  return handlers.get(name);
}
