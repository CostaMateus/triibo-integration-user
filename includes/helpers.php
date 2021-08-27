<?php

    function getMaskPhone($id)
    {
        return "
            <script src='https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js' ></script>
            <script>
                jQuery( '#$id' ).mask( '+55 (00) 00000-0000' );
                jQuery( '#$id' ).blur( function( event ) {
                    // Celular com 9 dígitos + 2 dígitos DDD e 8 da máscara
                    if( jQuery(this).val().length == 19 )
                        jQuery( '#$id' ).mask( '+55 (00) 00000-0009' );
                    else
                        jQuery( '#$id' ).mask( '+55 (00) 0000-00009' );
                });
            </script>
        ";
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
