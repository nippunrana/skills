import { send } from '../lib/http.js';

export function check(req, res) {
  return send(res, 200, { status: 'ok' });
}
