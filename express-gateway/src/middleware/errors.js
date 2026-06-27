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

const proxyErrorHandler = (_err, _req, res) => {
  if (!res.headersSent) {
    gatewayError(res, 502, 'Upstream service unavailable');
  }
};

module.exports = { gatewayError, proxyErrorHandler };
