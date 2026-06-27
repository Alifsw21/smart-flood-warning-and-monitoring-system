const gatewayError = (res, code, message) => {
  res.status(code).json({
    status: 'error',
    code,
    data: null,
    message,
    timestamp: new Date().toISOString(),
    service: 'express-gateway',
  });
};

const proxyErrorHandler = (err, _req, res) => {
  if (res.headersSent) {
    return;
  }

  const isTimeout = err?.code === 'ECONNABORTED' || err?.code === 'ETIMEDOUT';
  const statusCode = isTimeout ? 503 : 502;
  const message = isTimeout
    ? 'Upstream service timed out'
    : 'Upstream service unavailable';

  gatewayError(res, statusCode, message);
};

module.exports = { gatewayError, proxyErrorHandler };
