<?php

namespace AGSystems\Allegro\REST;

use AGSystems\OAuth2\Client\Provider\Allegro;
use AGSystems\Allegro\REST\Account\Token\AccessTokenInterface;
use GuzzleHttp\Psr7\Response;

/**
 * Class Client
 * @package AGSystems\Allegro\REST
 *
 * @method Client offers(int $offerId)
 * @method Client offer_publication_commands(string $commandId)
 * @method Client offer_modification_commands(string $commandId)
 * @method Client offer_price_change_commands(string $commandId)
 * @method Client offer_quantity_change_commands(string $commandId)
 * @method Client categories(string $categoryId)
 * @method Client shipping_rates(string $id)
 * @method Client offer_contacts(string $id)
 * @method Client promotions(string $promotionId)
 *
 * @property Client sale
 * @property Client offers
 * @property Client categories
 * @property Client parameters
 * @property Client tasks
 * @property Client shipping_rates
 * @property Client delivery_methods
 * @property Client offer_contacts
 * @property Client users
 * @property Client points_of_service
 * @property Client after_sales_service_conditions
 * @property Client return_policies
 * @property Client implied_warranties
 * @property Client warranties
 * @property Client pricing
 * @property Client fee_preview
 * @property Client offer_quotes
 * @property Client loyalty
 * @property Client promotions
 * @property Client listings
 * @property Client events
 *
 * @method mixed get(array $parameters = [], array $requestOptions = [])
 * @method mixed post(array $parameters = [], array $requestOptions = [])
 * @method mixed put(array $parameters = [], array $requestOptions = [])
 * @method mixed delete(array $parameters = [], array $requestOptions = [])
 */
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
        Allegro $provider,
        array $options = []
    )
    {
        $this->accessToken = $accessToken;
        $this->provider = $provider;

        parent::__construct($options);
    }

    protected function handlePath($path)
    {
        return str_replace('_', '-', $path);
    }

    protected function clientOptions()
    {
        if ($this->accessToken->hasExpired()) {
            $accessToken = $this->provider->getAccessToken('refresh_token', [
                'refresh_token' => $this->accessToken->getRefreshToken()
            ]);

            $this->accessToken->saveAuth($accessToken);
        }

        return [
            'http_errors' => false,
            'base_uri' => 'https://api.allegro.pl',
            'headers' => [
                'accept' => 'application/vnd.allegro.public.v1+json',
                'content-type' => 'application/vnd.allegro.public.v1+json',
                'authorization' => 'Bearer ' . $this->accessToken->getToken(),
            ]
        ];
    }

    protected function handlePost($data = null)
    {
        if (!is_array($data) && is_file($data)) {
            return [
                'base_uri' => 'https://upload.allegro.pl',
                'headers' => [
                    'content-type' => mime_content_type($data)
                ],
                'body' => fopen($data, 'r'),
            ];
        }

        if (is_null($data))
            $data = [];

        return parent::handlePost($data);
    }

    protected function handleResponse(callable $callback)
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
                $result = \GuzzleHttp\json_decode($response->getBody()->getContents());
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
