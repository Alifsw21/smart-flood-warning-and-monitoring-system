const db = require('../database');

async function findByUsername(username) {

    const [rows] = await db.execute(
        `
        SELECT *
        FROM auth_user
        WHERE username = ?
        `,
        [username]
    );

    return rows[0];
}

async function findByEmail(email) {

    const [rows] = await db.execute(
        `
        SELECT *
        FROM auth_user
        WHERE email = ?
        `,
        [email]
    );

    return rows[0];
}

module.exports = {
    findByUsername,
    findByEmail
};