const jwt = require('jsonwebtoken');
const { gatewayError } = require('./errors');

const JWT_SECRET = process.env.JWT_SECRET;

if (!JWT_SECRET) {
  console.error('FATAL: JWT_SECRET is not set');
  process.exit(1);
}

const PUBLIC_PATH_PREFIXES = ['/api/auth'];

const isPublicPath = (path) => {
  if (path === '/health') {
    return true;
  }

  return PUBLIC_PATH_PREFIXES.some((prefix) => path === prefix || path.startsWith(`${prefix}/`));
};

const jwtMiddleware = (req, res, next) => {
  if (isPublicPath(req.path)) {
    return next();
  }

  const authHeader = req.headers.authorization;

  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return gatewayError(res, 401, 'Token tidak ditemukan');
  }

  const token = authHeader.slice(7);

  try {
    const decoded = jwt.verify(token, JWT_SECRET);
    req.user = {
      id: decoded.id,
      role: decoded.role === 'admin' ? 'admin' : 'user',
    };
    return next();
  } catch {
    return gatewayError(res, 403, 'Token tidak valid');
  }
};

module.exports = { jwtMiddleware, isPublicPath };
