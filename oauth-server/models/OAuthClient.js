const db = require('../database');

/*
==================================
FIND CLIENT
==================================
*/

async function findClient(

    clientId

) {

    const [rows] =
        await db.execute(

            `
            SELECT *
            FROM auth_oauthClient
            WHERE client_id = ?
            `,

            [clientId]

        );

    return rows[0];

}

module.exports = {

    findClient

};