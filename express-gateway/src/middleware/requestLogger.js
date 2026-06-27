const { recordRequest } = require('./metrics');

const requestLogger = (req, res, next) => {
  const start = Date.now();

  res.on('finish', () => {
    const durationMs = Date.now() - start;
    const durationSeconds = durationMs / 1000;
    const path = req.path || req.originalUrl;

    recordRequest(req.method, path, res.statusCode, durationSeconds);

    console.log(
      `[${new Date().toISOString()}] ${req.method} ${req.originalUrl} ${res.statusCode} ${durationMs}ms`
    );
  });

  next();
};

module.exports = { requestLogger };
