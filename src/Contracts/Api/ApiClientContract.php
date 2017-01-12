<?php

namespace ArgentCrusade\Selectel\CloudStorage\Contracts\Api;

use GuzzleHttp\ClientInterface;

interface ApiClientContract
{
    /**
     * Replaces HTTP Client instance.
     *
     * @param \GuzzleHttp\ClientInterface $httpClient
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract
     */
    public function setHttpClient(ClientInterface $httpClient);

    /**
     * HTTP Client.
     *
     * @return \GuzzleHttp\ClientInterface|null
     */
    public function getHttpClient();

    /**
     * Authenticated user's token.
     *
     * @return string
     */
    public function token();

    /**
     * Storage URL.
     *
     * @return string
     */
    public function storageUrl();

    /**
     * Determines if user is authenticated.
     *
     * @return bool
     */
    public function authenticated();

    /**
     * Performs new API request. $params array will be passed to Guzzle as is.
     *
     * @param string $method
     * @param string $url
     * @param array  $params = []
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function request($method, $url, array $params = []);
}
