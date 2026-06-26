require('dotenv').config();

const passport =
    require('passport');

const GoogleStrategy =
    require('passport-google-oauth20').Strategy;

const db =
    require('../database');

const config =
    require('./config');

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
                    profile.emails?.[0]?.value;

                const username =
                    profile.displayName;

                if (!email) {

                    return done(
                        new Error(
                            'Google tidak mengirim email.'
                        )
                    );

                }

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

                if (

                    rows.length > 0

                ) {

                    user =
                        rows[0];

                }

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

                        id:
                            result.insertId,

                        username,

                        email,

                        role:
                            'user'

                    };

                }

                return done(

                    null,

                    user

                );

            }

            catch (error) {

                console.error(

                    'Google OAuth Error:',

                    error

                );

                return done(error);

            }

        }

    )

);

module.exports =
    passport;