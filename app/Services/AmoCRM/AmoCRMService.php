<?php

namespace App\Services\AmoCRM;

use League\OAuth2\Client\Token\AccessToken;

class AmoCRMService
{
    protected $client = null;
    private $token_path = "";

    public function __construct()
    {
        $this->token_path = storage_path("access_token.json");
    }

    public function getTokenPath(){
        return $this->token_path;
    }

    public function get_client(): ?\AmoCRM\Client\AmoCRMApiClient
    {
        $clientId = config("amo.client_id");
        $clientSecret = config("amo.client_secret");
        $clientCode = config("amo.client_code");
        $redirectUri = "http://localhost:8000";

        $this->client = new \AmoCRM\Client\AmoCRMApiClient($clientId, $clientSecret, $redirectUri);

        return $this->client;
    }

    public function create_client(): ?\AmoCRM\Client\AmoCRMApiClient
    {

        try{

            if(file_exists($this->token_path)){

                $this->client = $this->get_client();
                $this->client->setAccessToken($this->getToken());
                $this->client->setAccountBaseDomain($this->getDomain());
            }

        }catch (\Exception $e)
        {
            echo $e->getMessage();
        }

        return $this->client;
    }

    private function getToken()
    {
        if (!file_exists($this->token_path)) {
            exit('Access token file not found');
        }

        $accessToken = json_decode(file_get_contents($this->token_path), true);

        if (
            isset($accessToken)
            && isset($accessToken['accessToken'])
            && isset($accessToken['refreshToken'])
            && isset($accessToken['expires'])
            && isset($accessToken['baseDomain'])
        ) {
            return new AccessToken([
                'access_token' => $accessToken['accessToken'],
                'refresh_token' => $accessToken['refreshToken'],
                'expires' => $accessToken['expires'],
                'baseDomain' => $accessToken['baseDomain'],
            ]);

        } else {
            exit('Invalid access token ' . var_export($accessToken, true));
        }
    }

    public function getDomain()
    {
        $data = json_decode(file_get_contents($this->token_path), true);

        if (
            isset($data) && isset($data['baseDomain'])
        ) {
            return $data['baseDomain'];

        } else {
            exit('Invalid access domain ' . var_export($data, true));
        }
    }

    public function getAccessToken()
    {
        $data = json_decode(file_get_contents($this->token_path), true);

        if (
            isset($data) && isset($data['accessToken'])
        ) {
            return $data['accessToken'];

        } else {
            exit('Invalid access token ' . var_export($data, true));
        }
    }
}
