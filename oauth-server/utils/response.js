const config = require('../config');

const nowIso = () => new Date().toISOString();

const sendJson = (res, code, payload) => res.status(code).json(payload);

const sendStandard = (res, code, { status, data = null, message }) => sendJson(res, code, {
    status,
    code,
    data,
    message,
    timestamp: nowIso(),
    service: config.SERVICE_NAME,
});

const oauthError = (res, code, error, description) => sendJson(res, code, {
    error,
    error_description: description,
});

module.exports = { sendJson, sendStandard, oauthError };
