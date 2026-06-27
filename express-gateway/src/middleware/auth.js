const jwt = require('jsonwebtoken');
const { gatewayError } = require('./errors');

const JWT_SECRET = process.env.JWT_SECRET;
const OAUTH_URL = process.env.OAUTH_URL || 'http://oauth-server:3002';
const OAUTH_CLIENT_ID = process.env.OAUTH_CLIENT_ID || 'gateway';
const OAUTH_CLIENT_SECRET = process.env.OAUTH_CLIENT_SECRET || '';

if (!JWT_SECRET) {
  console.error('FATAL: JWT_SECRET is not set');
  process.exit(1);
}

const PUBLIC_EXACT_PATHS = new Set(['/health', '/metrics', '/oauth/token']);
const PUBLIC_PREFIXES = ['/api/auth'];

const isPublicPath = (path) => {
  if (PUBLIC_EXACT_PATHS.has(path)) {
    return true;
  }

  return PUBLIC_PREFIXES.some((prefix) => path === prefix || path.startsWith(`${prefix}/`));
};

const mapRole = (role) => (role === 'admin' ? 'admin' : 'user');

const introspectToken = async (token) => {
  const response = await fetch(`${OAUTH_URL}/oauth/introspect`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      token,
      client_id: OAUTH_CLIENT_ID,
      client_secret: OAUTH_CLIENT_SECRET,
    }),
    signal: AbortSignal.timeout(3000),
  });

  if (!response.ok) {
    return null;
  }

  const data = await response.json();
  if (!data.active) {
    return null;
  }

  return {
    id: data.sub ?? data.user_id ?? data.id,
    role: mapRole(data.role),
  };
};

const verifyLocalJwt = (token) => {
  const decoded = jwt.verify(token, JWT_SECRET);
  return {
    id: decoded.id,
    role: mapRole(decoded.role),
  };
};

const authMiddleware = async (req, res, next) => {
  if (isPublicPath(req.path)) {
    return next();
  }

  const authHeader = req.headers.authorization;

  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return gatewayError(res, 401, 'Token tidak ditemukan');
  }

  const token = authHeader.slice(7);

  try {
    let user = null;

    try {
      user = await introspectToken(token);
    } catch {
      user = null;
    }

    if (!user) {
      user = verifyLocalJwt(token);
    }

    req.user = user;
    return next();
  } catch {
    return gatewayError(res, 403, 'Token tidak valid');
  }
};

module.exports = { authMiddleware, isPublicPath };
