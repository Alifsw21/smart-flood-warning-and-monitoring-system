const express = require('express');
const OAuthService = require('../services/oauthService');
const { oauthError } = require('../utils/response');

const router = express.Router();

const getRequestParams = (req) => ({ ...req.body, ...req.query });

const handleOAuthError = (res, error) => {
    const code = error.oauthError || 'server_error';
    const description = error.oauthDescription || error.message || 'Internal server error';

    if (code === 'invalid_client' || code === 'unauthorized_client') {
        return oauthError(res, 401, code, description);
    }

    if (code === 'invalid_grant') {
        return oauthError(res, 400, code, description);
    }

    if (code === 'invalid_request' || code === 'unsupported_grant_type') {
        return oauthError(res, 400, code, description);
    }

    console.error(error);
    return oauthError(res, 500, 'server_error', 'Internal server error');
};

router.post('/token', async (req, res) => {
    try {
        const result = await OAuthService.issueToken(getRequestParams(req));
        return res.status(200).json(result);
    } catch (error) {
        return handleOAuthError(res, error);
    }
});

router.post('/introspect', async (req, res) => {
    try {
        const result = await OAuthService.introspect(getRequestParams(req));
        return res.status(200).json(result);
    } catch (error) {
        return handleOAuthError(res, error);
    }
});

router.post('/revoke', async (req, res) => {
    try {
        await OAuthService.revoke(getRequestParams(req));
        return res.status(200).end();
    } catch (error) {
        return handleOAuthError(res, error);
    }
});

module.exports = router;
