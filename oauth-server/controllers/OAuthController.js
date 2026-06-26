const OAuthService =
    require('../services/OAuthService');

class OAuthController {

    /*
    ==================================
    TOKEN
    ==================================
    */

    static async token(

        req,

        res

    ) {

        try {

            const result =

                await OAuthService.passwordGrant(

                    req.body

                );

            return res.json(

                result

            );

        }

        catch (error) {

            return res.status(500).json({

                status:
                    'error',

                message:
                    error.message

            });

        }

    }

    /*
    ==================================
    INTROSPECT
    ==================================
    */

    static async introspect(

        req,

        res

    ) {

        const token =

            req.body.token;

        const result =

            await OAuthService.introspect(

                token

            );

        return res.json(

            result

        );

    }

    /*
    ==================================
    REVOKE
    ==================================
    */

    static async revoke(

        req,

        res

    ) {

        const token =

            req.body.token;

        const result =

            await OAuthService.revoke(

                token

            );

        return res.json(

            result

        );

    }

}

module.exports =
    OAuthController;