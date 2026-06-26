const db = require('../database');

/*
==================================
FIND USERNAME
==================================
*/

async function findByUsername(
    username
) {

    const [rows] =
        await db.execute(

            `
            SELECT *
            FROM auth_user
            WHERE username = ?
            `,

            [username]

        );

    return rows[0];

}

/*
==================================
FIND EMAIL
==================================
*/

async function findByEmail(
    email
) {

    const [rows] =
        await db.execute(

            `
            SELECT *
            FROM auth_user
            WHERE email = ?
            `,

            [email]

        );

    return rows[0];

}

/*
==================================
FIND ID
==================================
*/

async function findById(
    id
) {

    const [rows] =
        await db.execute(

            `
            SELECT *
            FROM auth_user
            WHERE id = ?
            `,

            [id]

        );

    return rows[0];

}

/*
==================================
CREATE GOOGLE USER
==================================
*/

async function createGoogleUser(

    username,

    email

) {

    const [result] =
        await db.execute(

            `
            INSERT INTO auth_user
            (
                username,
                email,
                password,
                role,
                provider
            )
            VALUES
            (?, ?, ?, ?, ?)
            `,

            [

                username,

                email,

                '',

                'user',

                'google'

            ]

        );

    return {

        id:
            result.insertId,

        username,

        email,

        role:
            'user'

    };

}

module.exports = {

    findByUsername,

    findByEmail,

    findById,

    createGoogleUser

};