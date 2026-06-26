const express =
    require('express');

const OAuthController =
    require('../controllers/OAuthController');

const router =
    express.Router();

/*
==================================
TOKEN
==================================
*/

router.post(

    '/token',

    OAuthController.token

);

/*
==================================
INTROSPECT
==================================
*/

router.post(

    '/introspect',

    OAuthController.introspect

);

/*
==================================
REVOKE
==================================
*/

router.post(

    '/revoke',

    OAuthController.revoke

);

module.exports =
    router;