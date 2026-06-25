require('dotenv').config();

const express = require('express');

const config = require('./config');

if (!process.env.JWT_SECRET) {
    console.error('FATAL: JWT_SECRET is not set');
    process.exit(1);
}

const db = require('./database');
const { router: authRouter } = require('./routes/auth');

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

app.use('/api/auth', authRouter);

app.get('/', (req, res) => {
    res.json({
        status: 'success',
        service: 'OAuth Server Running',
        port: config.PORT
    });
});

app.listen(config.PORT, () => {
    console.log(`OAuth Server Running on Port ${config.PORT}`);
});
