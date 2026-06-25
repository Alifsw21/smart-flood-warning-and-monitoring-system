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
            status: 'error',
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
                    status: 'error',
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

        if (!username || !password) {

            return res.status(400).json({
                status: 'error',
                message: 'Username dan password wajib diisi'
            });

        }

        const user =
            await User.findByUsername(
                username
            );

        if (!user) {

            return res.status(401).json({
                status: 'error',
                message: 'Username atau password salah'
            });

        }

        const validPassword =
            await bcrypt.compare(
                password,
                user.password
            );

        if (!validPassword) {

            return res.status(401).json({
                status: 'error',
                message: 'Username atau password salah'
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

        return res.json({
            status: 'success',
            token,
            id: user.id,
            username: user.username,
            role: user.role
        });

    } catch (error) {

        console.error(error);

        return res.status(500).json({
            status: 'error',
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
    async (req, res) => {

        try {

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
                `${config.PHP_CALLBACK_URL}` +
                `?token=${token}` +
                `&role=${req.user.role}` +
                `&username=${encodeURIComponent(req.user.username)}`;

            return res.redirect(
                redirectUrl
            );

        } catch (error) {

            return res.status(500).json({
                status: 'error',
                message: error.message
            });

        }

    }
);

/*
==================================
PROFILE
==================================
*/

router.get(
    '/profile',
    authenticateJWT,
    (req, res) => {

        return res.json({
            status: 'success',
            user: req.user
        });

    }
);

module.exports = {
    router,
    authenticateJWT
};