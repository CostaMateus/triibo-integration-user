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
            $data["billing"]  = TIU_formatAddress( "billing", $addresses["billing"] );
        }

        if ( isset( $addresses["shipping"] ) )
        {
            $data["shipping"] = TIU_formatAddress( "shipping", $addresses["shipping"] );
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


    function TIU_treatContactList( $contactList, $email )
    {
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

        return $contactList;
    }


    function TIU_treatAddressList( $currAddressList, $newAddressList )
    {
        //
        $nl = PHP_EOL . "==========================" . PHP_EOL . "Return 00 addressList" . PHP_EOL . date("Y-m-d H:i:s") . PHP_EOL;
        file_put_contents( plugin_dir_path( __FILE__ ) . "log.txt", $nl . print_r( $newAddressList, true ), FILE_APPEND );

        if (     ! empty( $currAddressList ) && ! empty( $newAddressList ) )
        {
            $addressList = TIU_addressListUpdate( $currAddressList, $newAddressList );
            //
            $nl = PHP_EOL . "==========================" . PHP_EOL . "Return 01 addressList" . PHP_EOL . date("Y-m-d H:i:s") . PHP_EOL;
            file_put_contents( plugin_dir_path( __FILE__ ) . "log.txt", $nl . print_r( $addressList, true ), FILE_APPEND );
        }
        elseif ( ! empty( $currAddressList ) &&  empty( $newAddressList ) )
        {
            $addressList = $currAddressList;
            //
            $nl = PHP_EOL . "==========================" . PHP_EOL . "Return 02 addressList" . PHP_EOL . date("Y-m-d H:i:s") . PHP_EOL;
            file_put_contents( plugin_dir_path( __FILE__ ) . "log.txt", $nl . print_r( $addressList, true ), FILE_APPEND );
        }
        elseif (  empty( $currAddressList )  && ! empty( $newAddressList ) )
        {
            $addressList = TIU_addressListUpdate( [], $newAddressList );
            //
            $nl = PHP_EOL . "==========================" . PHP_EOL . "Return 03 addressList" . PHP_EOL . date("Y-m-d H:i:s") . PHP_EOL;
            file_put_contents( plugin_dir_path( __FILE__ ) . "log.txt", $nl . print_r( $addressList, true ), FILE_APPEND );
        }
        else
        {
            $addressList = [];
            //
            $nl = PHP_EOL . "==========================" . PHP_EOL . "Return 04 addressList" . PHP_EOL . date("Y-m-d H:i:s") . PHP_EOL;
            file_put_contents( plugin_dir_path( __FILE__ ) . "log.txt", $nl . print_r( $addressList, true ), FILE_APPEND );
        }

        return $addressList;
    }


    function TIU_addressListUpdate( $currAddressList, $newAddressList )
    {
        $billing     = isset( $newAddressList["billing"]  ) ? $newAddressList["billing"]  : null;
        $shipping    = isset( $newAddressList["shipping"] ) ? $newAddressList["shipping"] : null;

        //
        $nl = PHP_EOL . "==========================" . PHP_EOL . "Return 10 addressList" . PHP_EOL . date("Y-m-d H:i:s") . PHP_EOL;
        file_put_contents( plugin_dir_path( __FILE__ ) . "log.txt", $nl . print_r( $billing, true ), FILE_APPEND );
        //
        $nl = PHP_EOL . "==========================" . PHP_EOL . "Return 10 addressList" . PHP_EOL . date("Y-m-d H:i:s") . PHP_EOL;
        file_put_contents( plugin_dir_path( __FILE__ ) . "log.txt", $nl . print_r( $shipping, true ), FILE_APPEND );

        $addBilling  = false;
        $addShipping = false;

        if ( empty( $currAddressList ) )
        {
            $addBilling  = ( $billing  ) ? true : false;
            $addShipping = ( $shipping ) ? true : false;

            //
            $nl = PHP_EOL . "==========================" . PHP_EOL . "Return 11 addressList" . PHP_EOL . date("Y-m-d H:i:s") . PHP_EOL;
            file_put_contents( plugin_dir_path( __FILE__ ) . "log.txt", $nl . print_r( $addBilling, true ), FILE_APPEND );
            //
            $nl = PHP_EOL . "==========================" . PHP_EOL . "Return 11 addressList" . PHP_EOL . date("Y-m-d H:i:s") . PHP_EOL;
            file_put_contents( plugin_dir_path( __FILE__ ) . "log.txt", $nl . print_r( $addShipping, true ), FILE_APPEND );
        }
        else
        {
            //
            $nl = PHP_EOL . "==========================" . PHP_EOL . "Return 12 addressList" . PHP_EOL . date("Y-m-d H:i:s") . PHP_EOL;
            file_put_contents( plugin_dir_path( __FILE__ ) . "log.txt", $nl . print_r( $currAddressList, true ), FILE_APPEND );

            foreach ($currAddressList as $key => &$addrs)
            {
                if ( ! isset( $addrs["origin"] ) || $addrs["origin"] != "marketplace" ) continue;

                if ( $billing )
                {
                    if ( $addrs["type"] == "billing" && $addrs["zipCode"] == $billing["zipCode"] )
                    {
                        TIU_addressUpdate( $addrs, $newAddressList );
                    }
                    else
                    {
                        $addBilling = true;
                    }
                }
                elseif ( $shipping )
                {
                    if ( $addrs["type"] == "shipping" && $addrs["zipCode"] == $shipping["zipCode"] )
                    {
                        TIU_addressUpdate( $addrs, $newAddressList );
                    }
                    else
                    {
                        $addShipping = true;
                    }
                }
            }
        }


        if ( $addBilling )
        {
            $billing["preferential"] = true;
            array_push($currAddressList, $billing);
        }

        if ( $addShipping )
        {
            $shipping["preferential"] = true;
            array_push($currAddressList, $shipping);
        }

        //
        $nl = PHP_EOL . "==========================" . PHP_EOL . "Return 13 addressList" . PHP_EOL . date("Y-m-d H:i:s") . PHP_EOL;
        file_put_contents( plugin_dir_path( __FILE__ ) . "log.txt", $nl . print_r( $currAddressList, true ), FILE_APPEND );

        return $currAddressList;
    }

    function TIU_addressUpdate( &$currAddr, $newAddr )
    {
        $currAddr = [
            "preferential" => true,
            "type"         => $newAddr["type"],
            "origin"       => $newAddr["origin"],
            "zipCode"      => $newAddr["postcode"],
            "streetAve"    => $newAddr["address_1"],
            "number"       => $newAddr["number"],       // campo no firebase é int
            "extra"        => $newAddr["address_2"],
            "neighborhood" => $newAddr["neighborhood"],
            "city"         => $newAddr["city"],
            "state"        => $newAddr["state"],
            "country"      => $newAddr["country"],
            "googleAddr"   => $newAddr["address_1"]    . ", "  .
                              $newAddr["number"]       . " - " .
                              $newAddr["neighborhood"] . ", "  .
                              $newAddr["city"]         . " - " .
                              $newAddr["state"]        . ", "  .
                              $newAddr["postcode"],
        ];
    }
