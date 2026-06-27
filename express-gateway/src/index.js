require('dotenv').config();

const express = require('express');
const { createProxyMiddleware } = require('http-proxy-middleware');

const app = express();
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

const proxyWithPrefix = (target, prefix) => createProxyMiddleware({
  target,
  changeOrigin: true,
  pathRewrite: (path) => `${prefix}${path}`,
});

app.use('/api/auth', proxyWithPrefix(upstreams.oauth, '/api/auth'));

app.use('/api/river', proxyWithPrefix(upstreams.river, '/api/river'));

app.use('/api/environment', proxyWithPrefix(upstreams.river, '/api/environment'));

app.use('/api/traffic', proxyWithPrefix(upstreams.river, '/api/traffic'));

app.use('/api/analytics', proxyWithPrefix(upstreams.analytics, '/api/analytics'));

app.use('/predict', proxyWithPrefix(upstreams.ml, '/predict'));
app.use('/api/sensor', proxyWithPrefix(upstreams.ml, '/api/sensor'));
app.use('/detect', proxyWithPrefix(upstreams.ml, '/detect'));

app.use('/', createProxyMiddleware({
  target: upstreams.user,
  changeOrigin: true,
}));

app.listen(PORT, () => {
  console.log(`API Gateway listening on port ${PORT}`);
});
