const express = require('express');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const passport = require('passport');

const router = express.Router();

const User = require('../models/user');
const config = require('../config');

/*
==================================
JWT MIDDLEWARE
==================================
*/

function authenticateJWT(req, res, next) {

    const authHeader = req.headers.authorization;

    if (!authHeader) {
        return res.status(401).json({
            message: 'Token tidak ditemukan'
        });
    }

    const token = authHeader.split(' ')[1];

    jwt.verify(
        token,
        config.JWT_SECRET,
        (err, decoded) => {

            if (err) {
                return res.status(403).json({
                    message: 'Token tidak valid'
                });
            }

            req.user = decoded;

            next();
        }
    );
}

/*
==================================
LOGIN JWT
==================================
*/

router.post('/login', async (req, res) => {

    try {

        const { username, password } = req.body;

        const user = User.findByUsername(username);

        if (!user) {
            return res.status(401).json({
                message: 'Username atau Password salah'
            });
        }

        const validPassword =
            await bcrypt.compare(
                password,
                user.password
            );

        if (!validPassword) {

            return res.status(401).json({
                message: 'Username atau Password salah'
            });

        }

        const token = jwt.sign(
            {
                id: user.id,
                role: user.role
            },
            config.JWT_SECRET,
            {
                expiresIn: '24h'
            }
        );

        res.json({
            status: 'success',
            token,
            role: user.role,
            username: user.username
        });

    } catch (error) {

        res.status(500).json({
            message: error.message
        });

    }

});

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
    (req, res) => {

        const token = jwt.sign(
            {
                id: req.user.id,
                role: req.user.role
            },
            config.JWT_SECRET,
            {
                expiresIn: '24h'
            }
        );

        const redirectUrl =
            `${config.PHP_CALLBACK_URL}?token=${token}&role=${req.user.role}`;

        res.redirect(redirectUrl);

    }
);

/*
==================================
PROFILE TEST
==================================
*/

router.get(
    '/profile',
    authenticateJWT,
    (req, res) => {

        res.json({
            status: 'success',
            user: req.user
        });

    }
);

module.exports = {
    router,
    authenticateJWT
};