require('dotenv').config();

module.exports = {
    PORT: process.env.PORT || 3000,

    JWT_SECRET: process.env.JWT_SECRET,

    SESSION_SECRET: process.env.SESSION_SECRET,

    GOOGLE: {
        CLIENT_ID: process.env.GOOGLE_CLIENT_ID,
        CLIENT_SECRET: process.env.GOOGLE_CLIENT_SECRET,
        CALLBACK_URL: process.env.GOOGLE_CALLBACK_URL
    },

    PHP_CALLBACK_URL: process.env.PHP_CALLBACK_URL
};