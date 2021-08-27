<?php
    /**
    * Plugin Name: #Triibo Integration User
    * Description: Integração dos usários da Triibo com o Marketplace da Triibo
    * Version: 1.5
    * Author: Mateus Costa
    * Author URI: https://costamateus.com.br/
    **/

    // don't call the file directly
    if ( ! defined( "ABSPATH" ) ) exit;

    /**
     * Verifica o ambiente que está
     * $isDev = strpos(home_url(), "dev-");
     */
    if (strpos(home_url(), "dev-") !== false)
    {
        // $isDev = "TRUE";
        define( "TIL_API_URL",       "https://triibo-api-gateway-hml.triibo.com.br/"                ); // HOMOLOG
        define( "TIL_API_TOKEN",     "7472216240776562417070486d4c6170693139"                       ); // HOMOLOG
        define( "TIL_CHANNEL_ID",    "54522121626f2d416854682d4150312d4368614e4e656c2d776562417070" ); // HOMOLOG
        define( "TIL_CHANNEL_GROUP", "triibo-webapp"                                                ); // HOMOLOG
        define( "TIL_CHANNEL_NAME",  "triibo-webapp"                                                ); // HOMOLOG
        define( "TIL_CHANNEL_TOKEN", "305221624057656241707021403131"                               ); // HOMOLOG
        define( "TIL_UID",            null                                                          ); // HOMOLOG
    }
    else
    {
        // $isDev = "FALSE";
        define( "TIL_API_URL",       "https://triibo-api-gateway.triibo.com.br/"                    ); // PROD
        define( "TIL_API_TOKEN",     "57336266417050612750524C4170495029"                           ); // PROD
        define( "TIL_CHANNEL_ID",    "21726962305052442443686E336C6C573342417050"                   ); // PROD
        define( "TIL_CHANNEL_GROUP", "triibo-webapp"                                                ); // PROD
        define( "TIL_CHANNEL_NAME",  "triibo-webapp"                                                ); // PROD
        define( "TIL_CHANNEL_TOKEN", "38522162405733624170505052236368403138"                       ); // PROD
        define( "TIL_UID",            null                                                          ); // PROD
    }

    /**
     * Registrando variavel para controle de status do plugin | atiivo - inativo
     */
    add_action( "init", "TIL_init_status_global" );
    function TIL_init_status_global()
    {
        $_STATUS_PLUGIN = get_transient( "_STATUS_PLUGIN" );

        if ( $_STATUS_PLUGIN === false ) set_transient( "_STATUS_PLUGIN", "inactive" ); // active
    }
    TIL_init_status_global();


    /**
     * Sobrescrevendo template de formulario de cadastro e edição da conta
     */
    add_filter( "woocommerce_locate_template", "TIL_woocommerce_locate_template", 999, 3 );
    function TIL_woocommerce_locate_template( $template, $template_name, $template_path )
    {
        $_STATUS_PLUGIN = get_transient( "_STATUS_PLUGIN" );

        if ( $_STATUS_PLUGIN == "active" )
        {
            global $woocommerce;
            $_template = $template;

            if ( ! $template_path ) $template_path = $woocommerce->template_url;

            $plugin_path  = untrailingslashit( plugin_dir_path( __FILE__ ) ) . "/woocommerce/";

            $over =  [
                "myaccount/form-login.php",
                "myaccount/form-edit-account.php"
            ];

            if ( in_array( $template_name, $over ) )
            {
                $template = $plugin_path . $template_name;
            }
            else
            {
                // Look within passed path within the theme - this is priority
                $template = locate_template( array( $template_path . $template_name, $template_name ) );

                // Modification: Get the template from this plugin, if it exists
                if ( ! $template && file_exists( $plugin_path . $template_name ) )
                {
                    $template = $plugin_path . $template_name;
                }
                // Use default template
                if ( ! $template )
                {
                    $template = $_template;
                }
            }

            // Return what we found
            return $template;
        }
        else
        {
            return $template;
        }
    }


    // Campo de telefone no registro de novos usuários
    add_action( "woocommerce_created_customer", "TIL_created_customer" );
    function TIL_created_customer( $customer_id )
    {
        $phone = isset($_POST["TIL_phone"] ) ? $_POST["TIL_phone"] : null;

        if ( $phone )
        {
            update_user_meta( $customer_id, "TIL_phone", sanitize_text_field( $phone ) );

            TIL_createUserTriibo( [
                "phone" => $phone,
                "email" => $_POST["email"]
            ] );
        }
    }


    /**
     * Manda os endereços do usuário para a Triibo
     */
    function TIL_createUserTriibo( $info )
    {
        $name      = explode("@", $info["email"])[0];
        $cellPhone = "+" . preg_replace( "/\D/", "", $info["phone"] );
        $email     = [
            "type"     => "email",
            "value"    => $info["email"],
            "verified" => false,
        ];

        $channelTokenId_1 = TIL_getChannelToken_v1( null );
        $registerUser     = [
            "triiboHeader"    => [
                "apiToken"       => TIL_API_TOKEN,
                "channelGroup"   => TIL_CHANNEL_GROUP,
                "channelName"    => TIL_CHANNEL_NAME,
                "channelId"      => TIL_CHANNEL_ID,
                "sessionId"      => "001",
                "transactionId"  => "002",
                "channelTokenId" => $channelTokenId_1["success"]["newToken"]["channelTokenId"]
            ],
            "linkedAccount" => null,
            "cellphone"     => $cellPhone,
        ];

        // cria usuário se não existir e retorna as informações
        $resp1 = TIL_exec("login", $registerUser);

        if ($resp1["success"])
        {
            $uId         = $resp1["user"]["uId"];
            $contactList = $resp1["user"]["contactList"];

            if ( count( $contactList ) > 1 )
            {
                foreach ( $contactList as &$c )
                {
                    if ( $c["type"] == "email" ) $c["value"] = $email["value"];
                }
            }
            else
            {
                $contactList = array_merge( $contactList, [ $email ] );
            }
        }

        $channelTokenId_2 = TIL_getChannelToken_v1( $uId );
        $setUserInfo    = [
            "triiboHeader"    => [
                "apiToken"       => TIL_API_TOKEN,
                "channelGroup"   => TIL_CHANNEL_GROUP,
                "channelName"    => TIL_CHANNEL_NAME,
                "channelId"      => TIL_CHANNEL_ID,
                "sessionId"      => "001",
                "transactionId"  => "002",
                "channelTokenId" => $channelTokenId_2["success"]["newToken"]["channelTokenId"],
                'uId'            => $uId
            ],
            "queryPartnerAPI" => [
                "setUserInfo"
            ],
            "userInfo"        => [
                "syncType"    => "merge",
                'uId'         => $uId,
                "name"        => $name,
                "aliasName"   => $name,
                "contactList" => [
                    ... $contactList
                ],
            ],
        ];

        // atualiza informações do usuario
        $resp2 = TIL_exec("setUserInfo_v1", $setUserInfo);
    }

    function TIL_getChannelToken_v1( $uId )
    {
        $passPhrase = floor( rand( 1000000000, 9999999999 ) ) . $uId . floor( rand( 1000000000, 9999999999 ) );
        $challenge  = TIL_hashTriibo1( TIL_API_TOKEN, $passPhrase, TIL_CHANNEL_TOKEN );
        $token      = [
            "triiboHeader" => [
                "apiToken"      => TIL_API_TOKEN,
                "channelGroup"  => TIL_CHANNEL_GROUP,
                "channelName"   => TIL_CHANNEL_NAME,
                "channelId"     => TIL_CHANNEL_ID,
                "sessionId"     => "001",
                "transactionId" => "002",
                "uId"           => $uId,
                "passPhrase"    => $passPhrase,
                "challenge"     => $challenge
            ],
        ];

        return TIL_exec("getChannelToken_v1", $token);
    }

    function TIL_hashTriibo1( $apiToken, $passPhrase, $channelToken )
    {
        $challenge1 = md5( $passPhrase . $apiToken     );
        $challenge2 = md5( $challenge1 . $channelToken );
        return $challenge2;
    }

    function TIL_exec($api, $params)
    {
        $ch = curl_init();

		curl_setopt_array( $ch, [
			CURLOPT_URL            => TIL_API_URL . $api,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => "",
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 60,
			CURLOPT_SSL_VERIFYPEER => false ,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => "POST",
			CURLOPT_HTTPHEADER     => [ "Content-type: application/json" ],
            CURLOPT_POSTFIELDS     => json_encode( $params )
        ]);

        $result = curl_exec( $ch );
        curl_close( $ch );

        return json_decode( $result, true );
    }
