<?php

    function TIU_getMaskPhone( $id )
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


    function TIU_getChannelToken_v1( $uId )
    {
        $passPhrase = floor( rand( 1000000000, 9999999999 ) ) . $uId . floor( rand( 1000000000, 9999999999 ) );
        $challenge  = TIU_hashTriibo1( TIU_API_TOKEN, $passPhrase, TIU_CHANNEL_TOKEN );
        $token      = [
            "triiboHeader" => [
                "apiToken"      => TIU_API_TOKEN,
                "channelGroup"  => TIU_CHANNEL_GROUP,
                "channelName"   => TIU_CHANNEL_NAME,
                "channelId"     => TIU_CHANNEL_ID,
                "sessionId"     => "001",
                "transactionId" => "002",
                "uId"           => $uId,
                "passPhrase"    => $passPhrase,
                "challenge"     => $challenge
            ],
        ];

        return TIU_exec( "getChannelToken_v1", $token );
    }


    function TIU_hashTriibo1( $apiToken, $passPhrase, $channelToken )
    {
        $challenge1 = md5( $passPhrase . $apiToken     );
        $challenge2 = md5( $challenge1 . $channelToken );
        return $challenge2;
    }


    function TIU_exec( $api, $params )
    {
        $ch = curl_init();

		curl_setopt_array( $ch, [
			CURLOPT_URL            => TIU_API_URL . $api,
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

    function TIU_getAddresses( $user_id )
    {
        $meta = [];
        $bill = "billing_";
        $ship = "shipping_";

        $data = array_map( function( $a ) {
            return $a[0];
        }, get_user_meta( $user_id ) );

        foreach ( $data as $key => $value )
        {
            if ( ( substr( $key, 0, strlen( $bill ) ) === $bill ) )
            {
                $temp = explode( $bill, $key )[1];
                $meta["billing"][$temp]  = $value;
            }

            if ( ( substr( $key, 0, strlen( $ship ) ) === $ship ) )
            {
                $temp = explode( $ship, $key )[1];
                $meta["shipping"][$temp] = $value;
            }
        }

        return TIU_formatAddresses( $meta );
    }

    function TIU_formatAddresses( $addresses )
    {
        $data = [];

        if ( isset( $addresses["billing"] ) )
        {
            $data[] = TIU_formatAddress( "billing", $addresses["billing"] );
        }

        if ( isset( $addresses["shipping"] ) )
        {
            $data[] = TIU_formatAddress( "shipping", $addresses["shipping"] );
        }

        return $data;
    }

    function TIU_formatAddress( $type, $address )
    {
        return [
            "zipCode"      => $address["postcode"],
            "streetAve"    => $address["address_1"],
            "number"       => $address["number"],
            "extra"        => $address["address_2"],
            "neighborhood" => $address["neighborhood"],
            "city"         => $address["city"],
            "state"        => $address["state"],
            "country"      => $address["country"],
            "googleAddr"   => $address["address_1"]    . ", "  .
                              $address["number"]       . " - " .
                              $address["neighborhood"] . ", "  .
                              $address["city"]         . " - " .
                              $address["state"]        . ", "  .
                              $address["postcode"],
            "type"         => $type,         // comercial, residencial, billing, shipping
            "origin"       => "marketplace", // marketplace,
        ];
    }
