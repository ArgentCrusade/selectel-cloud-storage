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
     * Returns HTTP Client instance.
     *
     * @return \GuzzleHttp\ClientInterface | null
     */
    public function getHttpClient();

    /**
     * Returns authenticated user's token.
     *
     * @return string | null
     */
    public function token();

    /**
     * Determines if user is authenticated.
     *
     * @return bool
     */
    public function authenticated();

    /**
     * Performs new API request. $params array will be passed to Guzzle as is.
     *
     * @param string $url
     * @param array  $params = []
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function request($method, $url, array $params = []);
}
