<?php
/**
 * @author Shakewell Agency
 * @copyright Shakewell (c) Shakewell (https://www.shakewell.agency/)
 */

use Firebase\JWT\JWT;

class GF_Momentous_API
{
    const ALGORITHM = 'HS256';

    protected $apiUrl;

    protected $clientId;

    protected $clientKey;

    protected $clientSecret;

    public function __construct($config)
    {
        if (isset($config['api_url'])) {
            $this->apiUrl = $config['api_url'];
        }

        if (isset($config['client_id'])) {
            $this->clientId = $config['client_id'];
        }

        if (isset($config['client_key'])) {
            $this->clientKey = $config['client_key'];
        }
        if (isset($config['client_secret'])) {
            $this->clientSecret = $config['client_secret'];
        }
    }
    public function request($action, $parameters, $method = 'GET')
    {
        $url = $this->apiUrl . $action;
        $jwt = $this->getToken();
        $response = wp_remote_post($url, [
            'headers' => [
                'accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $jwt
            ],
            'body' => json_encode($parameters),
            'timeout' => 60
        ]);
        return $response;
    }

    private function getToken()
    {
        $jwt = JWT::encode([
            'iss' => $this->clientId,
            'key' => $this->clientKey,
            'exp' => time() + 3600,
            'iat' => time(),
            'sub' => '',
        ], $this->clientSecret, self::ALGORITHM);

        return $jwt;
    }
}
