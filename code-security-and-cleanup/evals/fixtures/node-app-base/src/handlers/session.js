import { issueToken } from '../lib/auth.js';
import { send } from '../lib/http.js';
import { verifyUser } from '../lib/store.js';

export function create(req, res, ctx) {
  const username = String(ctx.body.username ?? '');
  const password = String(ctx.body.password ?? '');
  const user = verifyUser(username, password);
  if (!user) return send(res, 401, { error: 'invalid credentials' });

  return send(res, 201, { token: issueToken(user.id) });
}
