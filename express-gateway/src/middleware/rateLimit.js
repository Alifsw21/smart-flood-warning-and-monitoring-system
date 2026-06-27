const rateLimit = require('express-rate-limit');
const { RedisStore } = require('rate-limit-redis');
const { createClient } = require('redis');
const { gatewayError } = require('./errors');

const rateLimitHandler = (_req, res) => {
  gatewayError(res, 429, 'Too many requests');
};

const buildLimiter = (options, redisClient) => rateLimit({
  ...options,
  ...(redisClient
    ? { store: new RedisStore({ sendCommand: (...args) => redisClient.sendCommand(args) }) }
    : {}),
  handler: rateLimitHandler,
});

const createRateLimiters = async () => {
  let redisClient = null;

  const redisUrl = process.env.REDIS_URL || `redis://${process.env.REDIS_HOST || 'redis'}:${process.env.REDIS_PORT || 6379}`;

  try {
    redisClient = createClient({ url: redisUrl });
    redisClient.on('error', (error) => {
      console.error('Redis rate-limit client error:', error.message);
    });
    await redisClient.connect();
    console.log('Rate limiting backed by Redis');
  } catch (error) {
    console.warn('Redis unavailable for rate limiting, using in-memory store:', error.message);
    redisClient = null;
  }

  const globalLimiter = buildLimiter({
    windowMs: 15 * 60 * 1000,
    max: 100,
    standardHeaders: true,
    legacyHeaders: false,
    skip: (req) => req.path === '/health' || req.path === '/metrics',
  }, redisClient);

  const authLimiter = buildLimiter({
    windowMs: 60 * 60 * 1000,
    max: 500,
    keyGenerator: (req) => req.headers.authorization || req.ip,
  }, redisClient);

  return { globalLimiter, authLimiter, redisClient };
};

module.exports = { createRateLimiters };
