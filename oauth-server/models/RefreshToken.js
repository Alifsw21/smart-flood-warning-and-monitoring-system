const db = require('../database');

/*
==================================
SAVE REFRESH TOKEN
==================================
*/

async function create(

    userId,

    refreshToken,

    expiredAt

) {

    await db.execute(

        `
        INSERT INTO auth_refreshToken
        (
            user_id,
            refresh_token,
            expired_at
        )
        VALUES
        (?, ?, ?)
        `,

        [

            userId,

            refreshToken,

            expiredAt

        ]

    );

}

/*
==================================
FIND REFRESH TOKEN
==================================
*/

async function find(

    refreshToken

) {

    const [rows] =
        await db.execute(

            `
            SELECT *
            FROM auth_refreshToken
            WHERE refresh_token = ?
            `,

            [refreshToken]

        );

    return rows[0];

}

/*
==================================
REVOKE TOKEN
==================================
*/

async function revoke(

    refreshToken

) {

    await db.execute(

        `
        DELETE
        FROM auth_refreshToken
        WHERE refresh_token = ?
        `,

        [refreshToken]

    );

}

module.exports = {

    create,

    find,

    revoke

};