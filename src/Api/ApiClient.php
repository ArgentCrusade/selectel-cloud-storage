<?php

namespace ArgentCrusade\Selectel\CloudStorage\Api;

use ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\AuthenticationFailedException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

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
     * Returns HTTP Client instance.
     *
     * @return \GuzzleHttp\ClientInterface | null
     */
    public function getHttpClient()
    {
        if (!is_null($this->httpClient)) {
            return $this->httpClient;
        }

        return $this->httpClient = new Client([
            'base_uri' => $this->storageUrl(),
            'headers' => [
                'X-Auth-Token' => $this->token(),
            ],
        ]);
    }

    /**
     * Returns authenticated user's token.
     *
     * @return string | null
     */
    public function token()
    {
        return $this->token;
    }

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
     * @throws \AuthenticationFailedException
     */
    public function authenticate()
    {
        if (!is_null($this->token)) {
            return;
        }

        $response = $this->authenticationResponse();

        if (!$response->hasHeader('X-Auth-Token')) {
            throw new AuthenticationFailedException('Given credentials are wrong.', 403);
        }

        if (!$response->hasHeader('X-Storage-Url')) {
            throw new RuntimeException('Storage URL is missing.', 500);
        }

        $authTokenHeader = $response->getHeader('X-Auth-Token');
        $storageUrlHeader = $response->getHeader('X-Storage-Url');

        $this->token = $authTokenHeader[0];
        $this->storageUrl = $storageUrlHeader[0];
    }

    /**
     * Performs authentication request and returns its response.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\AuthenticationFailedException
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function authenticationResponse()
    {
        $client = new Client();

        try {
            $response = $client->request('GET', static::AUTH_URL, [
                'headers' => [
                    'X-Auth-User' => $this->username,
                    'X-Auth-Key' => $this->password,
                ],
            ]);
        } catch (RequestException $e) {
            throw new AuthenticationFailedException('Given credentials are wrong.', 403);
        }

        return $response;
    }

    /**
     * Performs new API request. $params array will be passed to HTTP Client as is.
     *
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

        if (!isset($params['query'])) {
            $params['query'] = [];
        }

        $params['query']['format'] = 'json';

        try {
            $response = $this->getHttpClient()->request($method, $url, $params);
        } catch (RequestException $e) {
            return $e->getResponse();
        }

        return $response;
    }
}
