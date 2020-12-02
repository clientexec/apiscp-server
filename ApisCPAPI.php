<?php

class ApisCPAPI extends SoapClient
{
    const WSDL_PATH = '/apnscp.wsdl';

    public static function create_client($server, $port, $apiKey)
    {
        $server .= ':' . $port;

        $uri = 'https://' . $server  . '/soap';
        $wsdl = str_replace('/soap', '/' . self::WSDL_PATH, $uri);
        $connopts = [
            'connection_timeout' => 30,
            'location'           => $uri,
            'uri'                => 'urn:apnscp.api.soap',
            'trace'              => true
        ];
        $connopts['location'] = $uri . '?authkey=' . $apiKey;

        return new static($wsdl, $connopts);
    }
}
