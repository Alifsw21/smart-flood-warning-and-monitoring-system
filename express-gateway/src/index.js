require('dotenv').config();

const express = require('express');
const { createProxyMiddleware } = require('http-proxy-middleware');
const { authMiddleware } = require('./middleware/auth');
const { createRateLimiters } = require('./middleware/rateLimit');
const { requestLogger } = require('./middleware/requestLogger');
const { metricsHandler } = require('./middleware/metrics');
const { gatewayError, proxyErrorHandler } = require('./middleware/errors');

const PORT = Number(process.env.PORT || 3000);

const upstreams = {
  oauth: process.env.OAUTH_URL || 'http://oauth-server:3002',
  user: process.env.PHP_USER_URL || 'http://php-user:80',
  river: process.env.PHP_RIVER_URL || 'http://php-river:80',
  analytics: process.env.PHP_ANALYTICS_URL || 'http://php-analytics:80',
  ml: process.env.ML_URL || 'http://python-ml-service:8000',
};

const nowIso = () => new Date().toISOString();

const sendJson = (res, code, body) => {
  res.status(code).json(body);
};

const attachUserHeaders = (proxyReq, req) => {
  if (!req.user) {
    return;
  }

  proxyReq.setHeader('x-user-id', String(req.user.id));
  proxyReq.setHeader('x-user-role', req.user.role);
};

const proxyWithPrefix = (target, prefix) => createProxyMiddleware({
  target,
  changeOrigin: true,
  pathRewrite: (path) => `${prefix}${path}`,
  on: {
    proxyReq: attachUserHeaders,
    error: proxyErrorHandler,
  },
});

const proxyToPath = (target, upstreamPath) => createProxyMiddleware({
  target,
  changeOrigin: true,
  pathRewrite: () => upstreamPath,
  on: {
    proxyReq: attachUserHeaders,
    error: proxyErrorHandler,
  },
});

const bootstrap = async () => {
  const { globalLimiter, authLimiter } = await createRateLimiters();
  const app = express();

  app.use(requestLogger);
  app.use(globalLimiter);

  app.get('/health', async (_req, res) => {
    const checks = [
      { name: 'oauth-server', url: `${upstreams.oauth}/` },
      { name: 'php-user', url: `${upstreams.user}/index.php` },
      { name: 'php-river', url: `${upstreams.river}/health` },
      { name: 'php-analytics', url: `${upstreams.analytics}/health` },
      { name: 'python-ml-service', url: `${upstreams.ml}/health` },
    ];

    const results = await Promise.all(
      checks.map(async (check) => {
        try {
          const response = await fetch(check.url, { signal: AbortSignal.timeout(3000) });
          return {
            service: check.name,
            status: response.ok ? 'up' : 'degraded',
            code: response.status,
          };
        } catch (error) {
          return {
            service: check.name,
            status: 'down',
            message: error.message,
          };
        }
      })
    );

    const allUp = results.every((item) => item.status === 'up');

    sendJson(res, allUp ? 200 : 503, {
      status: allUp ? 'success' : 'error',
      code: allUp ? 200 : 503,
      data: { upstreams: results },
      message: allUp ? 'Gateway and upstreams healthy' : 'One or more upstreams unavailable',
      timestamp: nowIso(),
      service: 'express-gateway',
    });
  });

  app.get('/metrics', metricsHandler);

  app.use('/api/auth', proxyWithPrefix(upstreams.oauth, '/api/auth'));
  app.use('/oauth/token', proxyWithPrefix(upstreams.oauth, '/oauth/token'));

  app.use(authMiddleware);
  app.use(authLimiter);

  app.use('/iot/traffic', proxyToPath(upstreams.river, '/api/traffic/readings'));
  app.use('/iot/air', proxyToPath(upstreams.river, '/api/environment/readings'));

  app.use('/api/river', proxyWithPrefix(upstreams.river, '/api/river'));
  app.use('/api/environment', proxyWithPrefix(upstreams.river, '/api/environment'));
  app.use('/api/traffic', proxyWithPrefix(upstreams.river, '/api/traffic'));
  app.use('/api/analytics', proxyWithPrefix(upstreams.analytics, '/api/analytics'));

  app.use('/predict', proxyWithPrefix(upstreams.ml, '/predict'));
  app.use('/api/sensor', proxyToPath(upstreams.ml, '/api/sensor'));
  app.use('/detect', proxyWithPrefix(upstreams.ml, '/detect'));

  app.use('/oauth', proxyWithPrefix(upstreams.oauth, '/oauth'));

  app.use('/', createProxyMiddleware({
    target: upstreams.user,
    changeOrigin: true,
    on: {
      proxyReq: attachUserHeaders,
      error: proxyErrorHandler,
    },
  }));

  app.use((_req, res) => {
    gatewayError(res, 404, 'Endpoint tidak ditemukan');
  });

  app.listen(PORT, () => {
    console.log(`API Gateway listening on port ${PORT}`);
  });
};

bootstrap().catch((error) => {
  console.error('Failed to start API Gateway:', error);
  process.exit(1);
});
