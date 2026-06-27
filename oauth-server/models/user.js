const db = require('../database');

async function findByUsername(username) {
    const [rows] = await db.execute(
        'SELECT id, username, email, password, role FROM user_user WHERE username = ?',
        [username]
    );

    return rows[0] || null;
}

async function findById(id) {
    const [rows] = await db.execute(
        'SELECT id, username, email, role FROM user_user WHERE id = ?',
        [id]
    );

    return rows[0] || null;
}

module.exports = { findByUsername, findById };
