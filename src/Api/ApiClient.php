<?php

namespace ArgentCrusade\Selectel\CloudStorage\Api;

use RuntimeException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\AuthenticationFailedException;

class ApiClient implements ApiClientContract
{
    const AUTH_URL = 'https://auth.selcdn.ru';

    /**
     * API Username.
     *
     * @var string
     */
    protected $username;

    /**
     * API Password.
     *
     * @var string
     */
    protected $password;

    /**
     * Authorization token.
     *
     * @var string
     */
    protected $token;

    /**
     * Storage URL.
     *
     * @var string
     */
    protected $storageUrl;

    /**
     * HTTP Client.
     *
     * @var \GuzzleHttp\ClientInterface
     */
    protected $httpClient;

    /**
     * Creates new API Client instance.
     *
     * @param string $username
     * @param string $password
     */
    public function __construct($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Replaces HTTP Client instance.
     *
     * @param \GuzzleHttp\ClientInterface $httpClient
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract
     */
    public function setHttpClient(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    /**
     * HTTP Client.
     *
     * @return \GuzzleHttp\ClientInterface|null
     */
    public function getHttpClient()
    {
        if (!is_null($this->httpClient)) {
            return $this->httpClient;
        }

        return $this->httpClient = new Client([
            'base_uri' => $this->storageUrl(),
        ]);
    }

    /**
     * Authenticated user's token.
     *
     * @return string
     */
    public function token()
    {
        return $this->token;
    }

    /**
     * Storage URL.
     *
     * @return string
     */
    public function storageUrl()
    {
        return $this->storageUrl;
    }

    /**
     * Determines if user is authenticated.
     *
     * @return bool
     */
    public function authenticated()
    {
        return !is_null($this->token());
    }

    /**
     * Performs authentication request.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\AuthenticationFailedException
     * @throws \RuntimeException
     */
    public function authenticate()
    {
        if ($this->authenticated()) {
            return;
        }

        $response = $this->authenticationResponse();

        if (!$response->hasHeader('X-Auth-Token')) {
            throw new AuthenticationFailedException('Given credentials are wrong.', 403);
        }

        if (!$response->hasHeader('X-Storage-Url')) {
            throw new RuntimeException('Storage URL is missing.', 500);
        }

        $this->token = $response->getHeaderLine('X-Auth-Token');
        $this->storageUrl = $response->getHeaderLine('X-Storage-Url');
    }

    /**
     * Performs authentication request and returns its response.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\AuthenticationFailedException
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function authenticationResponse()
    {
        $client = !is_null($this->httpClient) ? $this->httpClient : new Client();

        try {
            $response = $client->request('GET', static::AUTH_URL, [
                'headers' => [
                    'X-Auth-User' => $this->username,
                    'X-Auth-Key' => $this->password,
                ],
            ]);
        } catch (BadResponseException $e) {
            if ($e->getResponse()->getStatusCode() === 403) {
                throw new AuthenticationFailedException('Given credentials are wrong.', 403);
            }

            throw $e;
        }

        return $response;
    }

    /**
     * Performs new API request. $params array will be passed to HTTP Client as is.
     *
     * @param string $method
     * @param string $url
     * @param array  $params = []
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function request($method, $url, array $params = [])
    {
        if (!$this->authenticated()) {
            $this->authenticate();
        }

        $defaults = [
            'headers' => ['X-Auth-Token' => $this->token()],
        ];

        $params = array_merge_recursive($defaults, $params);

        if (!isset($params['query'])) {
            $params['query'] = [];
        }

        $params['query']['format'] = 'json';

        try {
            $response = $this->getHttpClient()->request($method, $url, $params);
        } catch (BadResponseException $e) {
            return $e->getResponse();
        }

        return $response;
    }
}
