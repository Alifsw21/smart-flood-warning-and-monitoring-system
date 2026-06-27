const express = require('express');
const jwt = require('jsonwebtoken');

const config = require('../config');
const OAuthService = require('../services/oauthService');
const User = require('../models/user');

const router = express.Router();

const authenticateAccessToken = async (req, res, next) => {
    const authHeader = req.headers.authorization;

    if (!authHeader || !authHeader.startsWith('Bearer ')) {
        return res.status(401).json({
            status: 'error',
            message: 'Token tidak ditemukan',
        });
    }

    const token = authHeader.slice(7);

    try {
        const stored = await OAuthService.findActiveAccessToken(token);

        if (stored) {
            req.user = {
                id: stored.user_id,
                role: stored.user_role || 'user',
            };
            return next();
        }

        const decoded = jwt.verify(token, config.JWT_SECRET);
        req.user = {
            id: decoded.id,
            role: decoded.role,
        };
        return next();
    } catch {
        return res.status(403).json({
            status: 'error',
            message: 'Token tidak valid',
        });
    }
};

router.post('/login', async (req, res) => {
    const { username, password } = req.body;

    if (!username || !password) {
        return res.status(400).json({
            status: 'error',
            message: 'Username dan password wajib diisi',
        });
    }

    try {
        const tokenResponse = await OAuthService.passwordGrant({
            grant_type: 'password',
            client_id: config.DEFAULT_CLIENT_ID,
            client_secret: config.DEFAULT_CLIENT_SECRET,
            username,
            password,
        });

        const user = await User.findByUsername(username);

        return res.json({
            status: 'success',
            token: tokenResponse.access_token,
            access_token: tokenResponse.access_token,
            refresh_token: tokenResponse.refresh_token,
            expires_in: tokenResponse.expires_in,
            token_type: tokenResponse.token_type,
            id: user?.id,
            username: user?.username,
            role: user?.role,
        });
    } catch {
        return res.status(401).json({
            status: 'error',
            message: 'Username atau password salah',
        });
    }
});

router.get('/profile', authenticateAccessToken, async (req, res) => {
    try {
        const user = await User.findById(req.user.id);

        if (!user) {
            return res.status(404).json({
                status: 'error',
                message: 'User tidak ditemukan',
            });
        }

        return res.json({
            status: 'success',
            user: {
                id: user.id,
                username: user.username,
                email: user.email,
                role: user.role,
            },
        });
    } catch (error) {
        return res.status(500).json({
            status: 'error',
            message: error.message,
        });
    }
});

module.exports = { router, authenticateAccessToken };
