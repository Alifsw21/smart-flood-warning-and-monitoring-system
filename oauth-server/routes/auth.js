const express = require('express');

const passport =
    require('passport');

const AuthController =
    require('../controllers/AuthController');

const authenticateJWT =
    require('../middleware/AuthenticateJWT');

const router =
    express.Router();

/*
==================================
LOGIN JWT
==================================
*/

router.post(

    '/login',

    AuthController.login

);

/*
==================================
GOOGLE LOGIN
==================================
*/

router.get(

    '/google',

    passport.authenticate(

        'google',

        {

            scope: [

                'profile',

                'email'

            ]

        }

    )

);

/*
==================================
GOOGLE CALLBACK
==================================
*/

router.get(

    '/callback',

    passport.authenticate(

        'google',

        {

            session: false,

            failureRedirect: '/'

        }

    ),

    AuthController.callback

);

/*
==================================
PROFILE
==================================
*/

router.get(

    '/profile',

    authenticateJWT,

    AuthController.profile

);

module.exports =
    router;