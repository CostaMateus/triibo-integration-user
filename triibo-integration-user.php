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

    include_once( plugin_dir_path( __FILE__ ) . 'includes/helpers.php' );

    /**
     * Registrando variavel para controle de status do plugin | atiivo - inativo
     */
    add_action( "init", "TIL_init_status_global" );
    function TIL_init_status_global()
    {
        $_TIL_STATUS_PLUGIN = get_transient( "_TIL_STATUS_PLUGIN" );

        if ( $_TIL_STATUS_PLUGIN === false ) set_transient( "_TIL_STATUS_PLUGIN", "inactive" ); // active
    }
    TIL_init_status_global();


    /**
     * Adiciona o campo Telefone no form do Cadastro, antes do Email e Senha
     */
    add_action( 'woocommerce_register_form_start', 'TIL_woocommerce_register_form_start' );
    function TIL_woocommerce_register_form_start()
    {
        ?>
        <!-- Campo telefone obrigatorio no cadastro -->
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <input type="text" required
                class="woocommerce-Input woocommerce-Input--text input-text"
                placeholder="Celular"
                name="TIL_phone" id="TIL_phone" autocomplete="TIL_phone"
                value="<?php echo ( ! empty( $_POST['TIL_phone'] ) ) ? esc_attr( $_POST['TIL_phone'] ) : ''; ?>"/>
        </p>
        <?php
            echo getMaskPhone( "TIL_phone" );
    }
    /**
     * Adiciona o campo Telefone no form dos Detalhes da Conta, antes do Nome e Sobrenome
     */
    add_action( 'woocommerce_edit_account_form_start', 'TIL_woocommerce_edit_account_form_start' );
    function TIL_woocommerce_edit_account_form_start()
    {
        $phone = get_user_meta( get_current_user_id(), 'triiboId_phone', true );
        ?>
        <!-- Campo telefone obrigatorio no cadastro -->
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="account_email">Celular&nbsp;<span class="required">*</span></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text"
                name="TIL_account_phone" id="TIL_account_phone" autocomplete="TIL_account_phone"
                value="<?php echo esc_attr( substr( $phone, 3 ) ); ?>" required
                <?= ( $phone ? "readonly disabled style='color:#303133;background:#DDD;border-color:#DDD;'" : "") ?> />
        </p>
        <?php
            echo getMaskPhone( "TIL_account_phone" );
    }

    /**
     * Caso usuário não tenha telefone salvo, terá que adicionar para editar a conta
     * Associa a conta Triibo
     */
    add_action( "woocommerce_save_account_details", "TIL_save_account_details" );
    function TIL_save_account_details( $customer_id )
    {
        $phone = isset($_POST["TIL_account_phone"] ) ? $_POST["TIL_account_phone"] : null;

        if ( $phone )
        {
            // campo padronizado com o plugin triibo-auth-login
            update_user_meta( $customer_id, "triiboId_phone", sanitize_text_field( $phone ) );

            TIL_createUserTriibo( [
                "phone" => $phone,
                "email" => $_POST["account_email"]
            ] );
        }
    }

    // Campo de telefone no registro de novos usuários
    /**
     * Ao se registrar pelo form de Cadastro, associa a conta Triibo
     */
    add_action( "woocommerce_created_customer", "TIL_created_customer" );
    function TIL_created_customer( $customer_id )
    {
        $phone = isset($_POST["TIL_phone"] ) ? $_POST["TIL_phone"] : null;

        if ( $phone )
        {
            // campo padronizado com o plugin triibo-auth-login
            add_user_meta( $customer_id, "triiboId_phone", sanitize_text_field( $phone ) );

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
