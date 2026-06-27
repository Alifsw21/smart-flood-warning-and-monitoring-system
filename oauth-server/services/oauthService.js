const crypto = require('crypto');
const bcrypt = require('bcryptjs');

const config = require('../config');
const OAuthClient = require('../models/oauthClient');
const OAuthToken = require('../models/oauthToken');
const User = require('../models/user');

const GRANT_PASSWORD = 'password';
const GRANT_CLIENT_CREDENTIALS = 'client_credentials';
const GRANT_REFRESH_TOKEN = 'refresh_token';

const createTokenValue = () => crypto.randomBytes(32).toString('hex');

const parseGrantTypes = (grantTypes) => (
    String(grantTypes || '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean)
);

const verifyClientSecret = async (plainSecret, storedSecret) => {
    if (!plainSecret || !storedSecret) {
        return false;
    }

    if (storedSecret.startsWith('$2')) {
        return bcrypt.compare(plainSecret, storedSecret);
    }

    return plainSecret === storedSecret;
};

const validateClient = async (clientId, clientSecret, requiredGrant) => {
    if (!clientId || !clientSecret) {
        throw oauthServiceError('invalid_client', 'client_id and client_secret are required');
    }

    const client = await OAuthClient.findByClientId(clientId);

    if (!client) {
        throw oauthServiceError('invalid_client', 'Unknown client');
    }

    const secretValid = await verifyClientSecret(clientSecret, client.client_secret);

    if (!secretValid) {
        throw oauthServiceError('invalid_client', 'Invalid client credentials');
    }

    const allowedGrants = parseGrantTypes(client.grant_types);

    if (!allowedGrants.includes(requiredGrant)) {
        throw oauthServiceError('unauthorized_client', `Grant type ${requiredGrant} is not allowed for this client`);
    }

    return client;
};

const oauthServiceError = (error, description) => {
    const err = new Error(description);
    err.oauthError = error;
    err.oauthDescription = description;
    return err;
};

const buildTokenResponse = ({ accessToken, refreshToken = undefined, expiresIn }) => {
    const payload = {
        access_token: accessToken,
        token_type: 'Bearer',
        expires_in: expiresIn,
    };

    if (refreshToken) {
        payload.refresh_token = refreshToken;
    }

    return payload;
};

const persistToken = async ({ clientId, userId, includeRefresh }) => {
    const accessToken = createTokenValue();
    const refreshToken = includeRefresh ? createTokenValue() : null;
    const expiresAt = await OAuthToken.resolveExpiresAt(config.ACCESS_TOKEN_TTL_SECONDS);

    await OAuthToken.create({
        clientId,
        userId,
        accessToken,
        refreshToken,
        expiresAt,
    });

    return {
        accessToken,
        refreshToken,
        expiresIn: config.ACCESS_TOKEN_TTL_SECONDS,
    };
};

const passwordGrant = async (params) => {
    await validateClient(params.client_id, params.client_secret, GRANT_PASSWORD);

    const { username, password } = params;

    if (!username || !password) {
        throw oauthServiceError('invalid_request', 'username and password are required');
    }

    const user = await User.findByUsername(username);

    if (!user) {
        throw oauthServiceError('invalid_grant', 'Invalid username or password');
    }

    const passwordHash = String(user.password || '').replace(/^\$2y\$/, '$2a$');
    const passwordValid = await bcrypt.compare(password, passwordHash);

    if (!passwordValid) {
        throw oauthServiceError('invalid_grant', 'Invalid username or password');
    }

    const issued = await persistToken({
        clientId: params.client_id,
        userId: user.id,
        includeRefresh: true,
    });

    return buildTokenResponse({
        accessToken: issued.accessToken,
        refreshToken: issued.refreshToken,
        expiresIn: issued.expiresIn,
    });
};

const clientCredentialsGrant = async (params) => {
    await validateClient(params.client_id, params.client_secret, GRANT_CLIENT_CREDENTIALS);

    const issued = await persistToken({
        clientId: params.client_id,
        userId: null,
        includeRefresh: false,
    });

    return buildTokenResponse({
        accessToken: issued.accessToken,
        expiresIn: issued.expiresIn,
    });
};

const refreshTokenGrant = async (params) => {
    await validateClient(params.client_id, params.client_secret, GRANT_REFRESH_TOKEN);

    const { refresh_token: refreshToken } = params;

    if (!refreshToken) {
        throw oauthServiceError('invalid_request', 'refresh_token is required');
    }

    const stored = await OAuthToken.findByRefreshToken(refreshToken);

    if (!stored || stored.client_id !== params.client_id) {
        throw oauthServiceError('invalid_grant', 'Invalid refresh token');
    }

    await OAuthToken.deleteById(stored.id);

    const issued = await persistToken({
        clientId: stored.client_id,
        userId: stored.user_id,
        includeRefresh: true,
    });

    return buildTokenResponse({
        accessToken: issued.accessToken,
        refreshToken: issued.refreshToken,
        expiresIn: issued.expiresIn,
    });
};

const issueToken = async (params) => {
    const grantType = params.grant_type;

    if (!grantType) {
        throw oauthServiceError('invalid_request', 'grant_type is required');
    }

    switch (grantType) {
        case GRANT_PASSWORD:
            return passwordGrant(params);
        case GRANT_CLIENT_CREDENTIALS:
            return clientCredentialsGrant(params);
        case GRANT_REFRESH_TOKEN:
            return refreshTokenGrant(params);
        default:
            throw oauthServiceError('unsupported_grant_type', `Grant type ${grantType} is not supported`);
    }
};

const introspect = async ({ token, client_id: clientId, client_secret: clientSecret }) => {
    await validateClient(clientId, clientSecret, GRANT_CLIENT_CREDENTIALS);

    if (!token) {
        return { active: false };
    }

    const stored = await OAuthToken.findByAccessToken(token);

    if (!stored) {
        return { active: false };
    }

    const response = {
        active: true,
        client_id: stored.client_id,
        sub: stored.user_id ? String(stored.user_id) : undefined,
        user_id: stored.user_id ?? undefined,
        exp: Math.floor(new Date(stored.expires_at).getTime() / 1000),
        token_type: 'Bearer',
    };

    if (stored.user_id) {
        response.role = stored.user_role || 'user';
    }

    return response;
};

const revoke = async ({ token, client_id: clientId, client_secret: clientSecret }) => {
    await validateClient(clientId, clientSecret, GRANT_CLIENT_CREDENTIALS);

    if (!token) {
        throw oauthServiceError('invalid_request', 'token is required');
    }

    const removedByAccess = await OAuthToken.deleteByAccessToken(token);
    const removedByRefresh = removedByAccess ? false : await OAuthToken.deleteByRefreshToken(token);

    return { revoked: removedByAccess || removedByRefresh };
};

const findActiveAccessToken = async (accessToken) => OAuthToken.findByAccessToken(accessToken);

module.exports = {
    issueToken,
    passwordGrant,
    clientCredentialsGrant,
    refreshTokenGrant,
    introspect,
    revoke,
    findActiveAccessToken,
    oauthServiceError,
};
