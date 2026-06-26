require('dotenv').config();

const express = require('express');
const session = require('express-session');

const config = require('./config/config');
const passport = require('./config/passport');
const db = require('./database');

const authRoutes = require('./routes/auth');
const oauthRoutes = require('./routes/oauth');

const app = express();

/*
==================================
MYSQL CONNECTION TEST
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

    }

    catch (error) {

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

app.use(

    express.json()

);

app.use(

    session({

        secret:
            config.SESSION_SECRET,

        resave: false,

        saveUninitialized: false

    })

);

app.use(

    passport.initialize()

);

/*
==================================
ROUTES
==================================
*/

app.use(

    '/api/auth',

    authRoutes

);

app.use(

    '/auth',

    authRoutes

);

app.use(

    '/oauth',

    oauthRoutes

);

/*
==================================
HOME
==================================
*/

app.get(

    '/',

    (

        req,

        res

    ) => {

        res.json({

            status: 'success',

            service:
                'OAuth Authorization Server',

            port:
                config.PORT

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

            `Google Callback : ${config.GOOGLE.CALLBACK_URL}`

        );

    }

);