<?php

use ArgentCrusade\Selectel\CloudStorage\Api\ApiClient;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\AuthenticationFailedException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class ApiClientTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    /** @test */
    function default_http_client_has_base_uri()
    {
        $apiClient = Mockery::mock(ApiClient::class)->makePartial();
        $apiClient->shouldReceive('storageUrl')->andReturn('https://api.selcdn.ru/v1/SEL_22302');
        $client = $apiClient->getHttpClient();

        $this->assertEquals('https://api.selcdn.ru/v1/SEL_22302', $client->getConfig('base_uri'));
    }

    /** @test */
    function success_authenticate_should_save_token()
    {
        $container = [];
        $history = Middleware::history($container);

        $response = new Response(204, [
            'X-Storage-Token' => 'ec01a5f65efa70234bba6d86187173d5',
            'X-Content-Type-Options' => 'nosniff',
            'X-Expire-Auth-Token' => 76134,
            'X-Auth-Token' => 'ec01a5f65efa70234bba6d86187173d5',
            'X-Storage-Url' => 'https://api.selcdn.ru/v1/SEL_22302',
        ]);

        $apiClient = new ApiClient('test', 'pass');
        $apiClient->setHttpClient($this->mockHttpClient([$response], $history));
        $apiClient->authenticate();

        /** @var Request $request */
        $request = $container[0]['request'];

        $this->assertEquals(ApiClient::AUTH_URL, $request->getUri());
        $this->assertEquals('test', $request->getHeaderLine('X-Auth-User'));
        $this->assertEquals('pass', $request->getHeaderLine('X-Auth-Key'));

        $this->assertEquals('ec01a5f65efa70234bba6d86187173d5', $apiClient->token());
        $this->assertEquals('https://api.selcdn.ru/v1/SEL_22302', $apiClient->storageUrl());
        $this->assertTrue($apiClient->authenticated());
    }

    /** @test */
    function authenticated_client_should_not_send_authentication_request()
    {
        $apiClient = Mockery::mock(ApiClient::class)->makePartial();
        $apiClient->shouldReceive('authenticated')->andReturn(true);
        $apiClient->shouldNotReceive('authenticationResponse');

        $apiClient->authenticate();
    }

    /** @test */
    function authentication_response_without_token_causes_exception()
    {
        $response = new Response(204, [
            'X-Storage-Token' => 'ec01a5f65efa70234bba6d86187173d5',
            'X-Content-Type-Options' => 'nosniff',
            'X-Expire-Auth-Token' => 76134,
            'X-Storage-Url' => 'https://api.selcdn.ru/v1/SEL_22302',
        ]);

        $apiClient = new ApiClient('test', 'test');
        $apiClient->setHttpClient($this->mockHttpClient([$response]));

        $this->expectException(AuthenticationFailedException::class);
        $apiClient->authenticate();
    }

    /** @test */
    function authentication_response_without_storage_url_causes_exception()
    {
        $response = new Response(204, [
            'X-Storage-Token' => 'ec01a5f65efa70234bba6d86187173d5',
            'X-Content-Type-Options' => 'nosniff',
            'X-Expire-Auth-Token' => 76134,
            'X-Auth-Token' => 'ec01a5f65efa70234bba6d86187173d5',
        ]);

        $apiClient = new ApiClient('test', 'test');
        $apiClient->setHttpClient($this->mockHttpClient([$response]));

        $this->expectException(RuntimeException::class);
        $apiClient->authenticate();
    }

    /** @test */
    function authentication_with_wrong_credentials_causes_exception()
    {
        $request = new Request('GET', ApiClient::AUTH_URL);
        $response = new Response(403);
        $exception = new ClientException('Forbidden', $request, $response);

        $apiClient = new ApiClient('test', 'test');
        $apiClient->setHttpClient($this->mockHttpClient([$response, $exception]));

        $this->expectException(AuthenticationFailedException::class);
        $apiClient->authenticate();
    }

    /** @test */
    function authentication_to_unavailable_service_causes_exception()
    {
        $request = new Request('GET', ApiClient::AUTH_URL);
        $response = new Response(503);
        $exception = new ServerException('Service Unavailable ', $request, $response);

        $apiClient = new ApiClient('test', 'test');
        $apiClient->setHttpClient($this->mockHttpClient([$response, $exception]));

        $this->expectException(ServerException::class);
        $apiClient->authenticate();
    }

    /** @test */
    function request_method_should_return_response()
    {
        $container = [];
        $history = Middleware::history($container);

        $response = new Response(201);

        $apiClient = Mockery::mock(ApiClient::class)->makePartial();
        $apiClient->shouldReceive('authenticated')->andReturn(false);
        $apiClient->shouldReceive('authenticate')->andReturn(null);
        $apiClient->shouldReceive('token')->andReturn('test_token');
        $apiClient->setHttpClient($this->mockHttpClient([$response], $history));
        $apiResponse = $apiClient->request('PUT', 'https://api.selcdn.ru/v1/SEL_22302/container', [
            'headers' => [
                'X-Container-Meta-Type' => 'public',
            ],
        ]);

        $this->assertEquals($response, $apiResponse);

        /** @var Request $request */
        $request = $container[0]['request'];
        $this->assertEquals('https://api.selcdn.ru/v1/SEL_22302/container?format=json', $request->getUri());
        $this->assertEquals('test_token', $request->getHeaderLine('X-Auth-Token'));
        $this->assertEquals('public', $request->getHeaderLine('X-Container-Meta-Type'));
    }

    /** @test */
    function request_method_should_return_response_on_client_error()
    {
        $response = new Response(404);

        $apiClient = Mockery::mock(ApiClient::class)->makePartial();
        $apiClient->shouldReceive('authenticated')->andReturn(true);
        $apiClient->setHttpClient($this->mockHttpClient([$response]));
        $apiResponse = $apiClient->request('PUT', 'https://api.selcdn.ru/v1/SEL_22302/container', [
            'headers' => [
                'X-Container-Meta-Type' => 'public',
            ],
        ]);

        $this->assertEquals($response, $apiResponse);
    }

    /** @test */
    function request_method_should_throw_exception_on_connect_error()
    {
        $exception = new ConnectException('Connection Error', new Request('GET', '/'));

        $apiClient = Mockery::mock(ApiClient::class)->makePartial();
        $apiClient->shouldReceive('authenticated')->andReturn(true);
        $apiClient->setHttpClient($this->mockHttpClient([$exception]));

        $this->expectException(ConnectException::class);
        $apiClient->request('PUT', 'https://api.selcdn.ru/v1/SEL_22302/container', [
            'headers' => [
                'X-Container-Meta-Type' => 'public',
            ],
        ]);
    }

    protected function mockHttpClient(array $queue, $history = null)
    {
        $mockHandler = new MockHandler($queue);
        $handler = HandlerStack::create($mockHandler);
        if ($history) {
            $handler->push($history);
        }

        return new HttpClient(['handler' => $handler]);
    }
}
