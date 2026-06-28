require('dotenv').config();

module.exports = {
    PORT: Number(process.env.PORT || 3531),
    JWT_SECRET: process.env.JWT_SECRET,
    ACCESS_TOKEN_TTL_SECONDS: Number(process.env.ACCESS_TOKEN_TTL_SECONDS || 3600),
    REFRESH_TOKEN_TTL_SECONDS: Number(process.env.REFRESH_TOKEN_TTL_SECONDS || 604800),
    DEFAULT_CLIENT_ID: process.env.OAUTH_DEFAULT_CLIENT_ID || 'citizen-app',
    DEFAULT_CLIENT_SECRET: process.env.OAUTH_DEFAULT_CLIENT_SECRET || 'CitizenSecretDev123',
    SERVICE_NAME: 'oauth-server',
};
