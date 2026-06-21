require('dotenv').config();

const express = require('express');
const passport = require('passport');
const session = require('express-session');

const GoogleStrategy =
require('passport-google-oauth20').Strategy;

const config = require('./config');

const {
    router: authRouter
} = require('./routes/auth');

const app = express();

app.use(express.json());

app.use(
    session({
        secret: config.SESSION_SECRET,
        resave: false,
        saveUninitialized: false
    })
);

app.use(passport.initialize());

passport.use(
    new GoogleStrategy(
        {
            clientID:
                config.GOOGLE.CLIENT_ID,

            clientSecret:
                config.GOOGLE.CLIENT_SECRET,

            callbackURL:
                config.GOOGLE.CALLBACK_URL
        },

        async (
            accessToken,
            refreshToken,
            profile,
            done
        ) => {

            try {

                const user = {

                    id: profile.id,

                    username:
                        profile.emails[0].value,

                    role: 'user',

                    provider: 'google'
                };

                return done(
                    null,
                    user
                );

            } catch (err) {

                return done(err);

            }

        }
    )
);

/*
==================================
TEST GOOGLE MANUAL
==================================
*/

app.get(
    '/test-google',
    (req, res) => {

        const url =
            'https://accounts.google.com/o/oauth2/v2/auth' +
            '?client_id=' + config.GOOGLE.CLIENT_ID +
            '&redirect_uri=' + encodeURIComponent(config.GOOGLE.CALLBACK_URL) +
            '&response_type=code' +
            '&scope=profile%20email';

        res.redirect(url);

    }
);

app.use(
    '/api/auth',
    authRouter
);

app.use(
    '/auth',
    authRouter
);

app.get(
    '/',
    (req, res) => {

        res.json({
            service:
                'OAuth Server Running'
        });

    }
);

app.listen(
    config.PORT,
    () => {

        console.log(
            `OAuth Server Running : ${config.PORT}`
        );

    }
);