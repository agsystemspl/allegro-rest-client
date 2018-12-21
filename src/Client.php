<?php

namespace AGSystems\Allegro\REST;

use AGSystems\OAuth2\Client\Provider\Allegro;
use AGSystems\Allegro\REST\Account\Token\AccessTokenInterface;
use GuzzleHttp\Psr7\Response;

class Client extends \AGSystems\REST\AbstractClient
{
    const RETRIES = 3;

    /**
     * @var AccessTokenInterface
     */
    protected $accessToken;

    /**
     * @var Allegro
     */
    protected $provider;

    public function __construct(
        AccessTokenInterface $accessToken,
        Allegro $provider
    )
    {
        $this->accessToken = $accessToken;
        $this->provider = $provider;
    }

    protected function pathHandler($path)
    {
        return str_replace('_', '-', $path);
    }

    protected function withOptions(): array
    {
        if ($this->accessToken->hasExpired()){
            $accessToken = $this->provider->getAccessToken('refresh_token', [
                'refresh_token' => $this->accessToken->getRefreshToken()
            ]);

            $this->accessToken->saveAuth($accessToken);
        }

        return [
            'http_errors' => false,
            'base_uri' => 'https://api.allegro.pl/',
            'headers' => [
                'accept' => 'application/vnd.allegro.public.v1+json',
                'content-type' => 'application/vnd.allegro.public.v1+json',
                'authorization' => 'Bearer ' . $this->accessToken->getToken(),
            ]
        ];
    }

    public function post($argument)
    {
        if (is_array($argument))
            return parent::post($argument);

        if (is_file($argument)) {
            $uri = implode('/', array_filter($this->query));
            $this->query = [];

            $this->customOptions([
                'headers' => [
                    'content-type' => getimagesize($argument)['mime'],
                ],
                'body' => fopen($argument, 'r'),
            ]);

            return $this->request($name, $uri, array_shift($arguments));
        }
    }

    protected function responseHandler(callable $callback)
    {
        $retries = 0;

        do {
            /**
             * @var $response Response
             */
            $response = call_user_func($callback);

            if (strpos($response->getHeaderLine('content-type'), 'text/plain') !== false) {
                return (object)[
                    'errors' => [(object)['code' => 'ERROR', 'message' => $response->getBody()->getContents()]]
                ];
            } else {
                $result = json_decode($response->getBody()->getContents());
                if (isset($result->error))
                    return (object)[
                        'errors' => [(object)['code' => 'ERROR', 'message' => $result->error_description]]
                    ];
            }

            if ($response->getStatusCode() != 500)
                return $result;

            sleep(1);
            $retries++;

        } while ($retries < static::RETRIES);

        return $result;
    }
}
