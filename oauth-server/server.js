require('dotenv').config();

const express = require('express');

const config = require('./config');
const db = require('./database');
const oauthRoutes = require('./routes/oauth');
const { router: authRouter } = require('./routes/auth');
const { sendStandard } = require('./utils/response');

if (!process.env.JWT_SECRET) {
    console.error('FATAL: JWT_SECRET is not set');
    process.exit(1);
}

const app = express();

(async () => {
    try {
        const [rows] = await db.execute('SELECT DATABASE() AS db');
        console.log('MYSQL CONNECTED:', rows[0].db);
    } catch (error) {
        console.error('MYSQL ERROR:', error.message);
    }
})();

app.use(express.json());
app.use(express.urlencoded({ extended: false }));

app.get('/health', async (_req, res) => {
    try {
        await db.execute('SELECT 1');
        return sendStandard(res, 200, {
            status: 'success',
            data: { database: 'connected' },
            message: 'OAuth server healthy',
        });
    } catch (error) {
        return sendStandard(res, 503, {
            status: 'error',
            data: { database: 'disconnected' },
            message: error.message,
        });
    }
});

app.use('/oauth', oauthRoutes);
app.use('/api/auth', authRouter);

app.get('/', (_req, res) => {
    sendStandard(res, 200, {
        status: 'success',
        data: { port: config.PORT },
        message: 'OAuth Authorization Server',
    });
});

app.listen(config.PORT, () => {
    console.log(`OAuth Server Running on Port ${config.PORT}`);
});
