const db = require('../database');

async function resolveExpiresAt(ttlSeconds) {
    const [rows] = await db.execute(
        'SELECT DATE_ADD(NOW(), INTERVAL ? SECOND) AS expires_at',
        [ttlSeconds]
    );

    return rows[0].expires_at;
}

async function create({ clientId, userId, accessToken, refreshToken, expiresAt }) {
    await db.execute(
        `INSERT INTO auth_oauthToken (client_id, user_id, access_token, refresh_token, expires_at)
         VALUES (?, ?, ?, ?, ?)`,
        [clientId, userId ?? null, accessToken, refreshToken ?? null, expiresAt]
    );
}

async function findByAccessToken(accessToken) {
    const [rows] = await db.execute(
        `SELECT t.id, t.client_id, t.user_id, t.access_token, t.refresh_token, t.expires_at,
                u.role AS user_role
         FROM auth_oauthToken t
         LEFT JOIN user_user u ON u.id = t.user_id
         WHERE t.access_token = ?
           AND t.expires_at > NOW()`,
        [accessToken]
    );

    return rows[0] || null;
}

async function findByRefreshToken(refreshToken) {
    const [rows] = await db.execute(
        `SELECT id, client_id, user_id, access_token, refresh_token, expires_at
         FROM auth_oauthToken
         WHERE refresh_token = ?`,
        [refreshToken]
    );

    return rows[0] || null;
}

async function deleteByAccessToken(accessToken) {
    const [result] = await db.execute(
        'DELETE FROM auth_oauthToken WHERE access_token = ?',
        [accessToken]
    );

    return result.affectedRows > 0;
}

async function deleteByRefreshToken(refreshToken) {
    const [result] = await db.execute(
        'DELETE FROM auth_oauthToken WHERE refresh_token = ?',
        [refreshToken]
    );

    return result.affectedRows > 0;
}

async function deleteById(id) {
    await db.execute('DELETE FROM auth_oauthToken WHERE id = ?', [id]);
}

module.exports = {
    resolveExpiresAt,
    create,
    findByAccessToken,
    findByRefreshToken,
    deleteByAccessToken,
    deleteByRefreshToken,
    deleteById,
};
