const jwt =
    require('jsonwebtoken');

const config =
    require('../config/config');

function authenticateJWT(
    req,
    res,
    next
) {

    const authHeader =
        req.headers.authorization;

    if (

        !authHeader

    ) {

        return res.status(401).json({

            status: 'error',

            message:
                'Token tidak ditemukan'

        });

    }

    const token =
        authHeader.split(' ')[1];

    jwt.verify(

        token,

        config.JWT_SECRET,

        (

            err,

            decoded

        ) => {

            if (

                err

            ) {

                return res.status(403).json({

                    status: 'error',

                    message:
                        'Token tidak valid'

                });

            }

            req.user =
                decoded;

            next();

        }

    );

}

module.exports =
    authenticateJWT;