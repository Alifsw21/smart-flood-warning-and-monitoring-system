require('dotenv').config();

const express = require('express');
const passport = require('passport');
const session = require('express-session');

const GoogleStrategy =
    require('passport-google-oauth20').Strategy;

const config = require('./config');
const db = require('./database');

const {
    router: authRouter
} = require('./routes/auth');

const app = express();

/*
==================================
TEST MYSQL CONNECTION
==================================
*/

(async () => {

    try {

        const [rows] =
            await db.execute(
                'SELECT DATABASE() AS db'
            );

        console.log(
            'MYSQL CONNECTED:',
            rows[0].db
        );

    } catch (error) {

        console.error(
            'MYSQL ERROR:',
            error.message
        );

    }

})();

/*
==================================
MIDDLEWARE
==================================
*/

app.use(express.json());

app.use(
    session({
        secret: config.SESSION_SECRET,
        resave: false,
        saveUninitialized: false
    })
);

app.use(passport.initialize());

/*
==================================
GOOGLE STRATEGY
==================================
*/

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

                const email =
                    profile.emails[0].value;

                const username =
                    profile.displayName;

                /*
                ==========================
                CEK USER DI DATABASE
                ==========================
                */

                const [rows] =
                    await db.execute(
                        `
                        SELECT *
                        FROM auth_user
                        WHERE email = ?
                        `,
                        [email]
                    );

                let user;

                /*
                ==========================
                USER SUDAH ADA
                ==========================
                */

                if (rows.length > 0) {

                    user = rows[0];

                }

                /*
                ==========================
                USER BARU GOOGLE
                ==========================
                */

                else {

                    const [result] =
                        await db.execute(
                            `
                            INSERT INTO auth_user
                            (
                                username,
                                email,
                                password,
                                role,
                                provider
                            )
                            VALUES
                            (?, ?, ?, ?, ?)
                            `,
                            [
                                username,
                                email,
                                '',
                                'user',
                                'google'
                            ]
                        );

                    user = {
                        id: result.insertId,
                        username,
                        email,
                        role: 'user'
                    };

                }

                return done(
                    null,
                    user
                );

            } catch (error) {

                console.error(
                    'Google OAuth Error:',
                    error
                );

                return done(error);

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
            '?client_id=' +
            config.GOOGLE.CLIENT_ID +
            '&redirect_uri=' +
            encodeURIComponent(
                config.GOOGLE.CALLBACK_URL
            ) +
            '&response_type=code' +
            '&scope=profile%20email';

        res.redirect(url);

    }
);

/*
==================================
ROUTES
==================================
*/

app.use(
    '/api/auth',
    authRouter
);

app.use(
    '/auth',
    authRouter
);

/*
==================================
HOME
==================================
*/

app.get(
    '/',
    (req, res) => {

        res.json({
            status: 'success',
            service: 'OAuth Server Running',
            port: config.PORT
        });

    }
);

/*
==================================
START SERVER
==================================
*/

app.listen(
    config.PORT,
    () => {

        console.log(
            `OAuth Server Running on Port ${config.PORT}`
        );

        console.log(
            `Google Callback: ${config.GOOGLE.CALLBACK_URL}`
        );

    }
);