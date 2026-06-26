const AuthService =
    require('../services/AuthService');

const config =
    require('../config/config');

class AuthController {

    /*
    ==================================
    LOGIN JWT
    ==================================
    */

    static async login(
        req,
        res
    ) {

        try {

            const {
                username,
                password
            } = req.body;

            if (
                !username ||
                !password
            ) {

                return res.status(400).json({

                    status: 'error',

                    message:
                        'Username dan password wajib diisi'

                });

            }

            const result =
                await AuthService.login(

                    username,

                    password

                );

            return res.json(
                result
            );

        }

        catch (error) {

            return res.status(401).json({

                status: 'error',

                message:
                    error.message

            });

        }

    }

    /*
    ==================================
    GOOGLE CALLBACK
    ==================================
    */

    static async callback(
        req,
        res
    ) {

        try {

            const result =
                await AuthService.loginGoogle(

                    req.user

                );

            const redirectUrl =

                `${config.PHP_CALLBACK_URL}` +

                `?token=${result.token}` +

                `&role=${result.role}` +

                `&username=${encodeURIComponent(result.username)}`;

            return res.redirect(
                redirectUrl
            );

        }

        catch (error) {

            return res.status(500).json({

                status: 'error',

                message:
                    error.message

            });

        }

    }

    /*
    ==================================
    PROFILE
    ==================================
    */

    static async profile(
        req,
        res
    ) {

        try {

            const user =
                await AuthService.profile(

                    req.user.id

                );

            return res.json({

                status: 'success',

                user

            });

        }

        catch (error) {

            return res.status(500).json({

                status: 'error',

                message:
                    error.message

            });

        }

    }

}

module.exports =
    AuthController;