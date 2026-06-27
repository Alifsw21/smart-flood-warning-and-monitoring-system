const db = require('../database');

async function findByClientId(clientId) {
    const [rows] = await db.execute(
        'SELECT id, client_id, client_secret, grant_types FROM auth_oauthClient WHERE client_id = ?',
        [clientId]
    );

    return rows[0] || null;
}

module.exports = { findByClientId };
