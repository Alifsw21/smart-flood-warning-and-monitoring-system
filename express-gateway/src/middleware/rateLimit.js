const rateLimit = require('express-rate-limit');
const { gatewayError } = require('./errors');

const rateLimitHandler = (_req, res) => {
  gatewayError(res, 429, 'Too many requests');
};

const globalLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 100,
  standardHeaders: true,
  legacyHeaders: false,
  skip: (req) => req.path === '/health',
  handler: rateLimitHandler,
});

const authLimiter = rateLimit({
  windowMs: 60 * 60 * 1000,
  max: 500,
  keyGenerator: (req) => req.headers.authorization || req.ip,
  handler: rateLimitHandler,
});

module.exports = { globalLimiter, authLimiter };
