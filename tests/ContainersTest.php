<?php

use ArgentCrusade\Selectel\CloudStorage\CloudStorage;

class ContainersTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    /** @test */
    function container_can_be_created()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $createContainerRequest = $this->getCreateContainerRequest('container1', 'public');
            $containerRequest = $this->getContainerRequest('container1');

            $api->shouldReceive('request')
                ->with($createContainerRequest['method'], $createContainerRequest['url'], $createContainerRequest['params'])
                ->andReturn($createContainerRequest['response']);

            $api->shouldReceive('request')
                ->with($containerRequest['method'], $containerRequest['url'])
                ->andReturn($containerRequest['response']);
        });

        $storage = new CloudStorage($api);

        $container = $storage->createContainer('container1');

        $this->assertEquals('container1', $container->name());
    }

    /** @test */
    function containers_can_be_listed()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $request = $this->listContainersRequest();

            $api->shouldReceive('request')
                ->with($request['method'], $request['url'], $request['params'])
                ->andReturn($request['response']);
        });

        $storage = new CloudStorage($api);
        $containers = $storage->containers();

        $this->assertEquals(3, count($containers));
    }

    /** @test */
    function container_should_have_name_and_sizes_info()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $request = $this->listContainersRequest();

            $api->shouldReceive('request')
                ->with($request['method'], $request['url'], $request['params'])
                ->andReturn($request['response']);
        });

        $storage = new CloudStorage($api);
        $containers = $storage->containers();

        $first = $containers->get('container1');

        $this->assertEquals('container1', $first->name());
        $this->assertTrue($first->isPublic());
        $this->assertEquals(2, $first->filesCount());

        $this->assertEquals(1024, $first->size());
        $this->assertEquals(2048, $first->uploadedBytes());
        $this->assertEquals(1024, $first->downloadedBytes());
    }

    /** @test */
    function container_files_can_be_listed()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $requests = [
                $this->listContainersRequest(),
                $this->listFilesRequest('container1'),
            ];

            foreach ($requests as $request) {
                $api->shouldReceive('request')
                    ->with($request['method'], $request['url'], $request['params'])
                    ->andReturn($request['response']);
            }
        });

        $storage = new CloudStorage($api);
        $containers = $storage->containers();
        $container = $containers->get('container1');
        $files = $container->files();

        $this->assertEquals(3, count($files));
    }

    /** @test */
    function container_can_be_retrieved_from_storage()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $request = $this->getContainerRequest('container1');

            $api->shouldReceive('request')
                ->with($request['method'], $request['url'])
                ->andReturn($request['response']);
        });

        $storage = new CloudStorage($api);
        $container = $storage->getContainer('container1');

        $this->assertEquals('container1', $container->name());
        $this->assertTrue($container->isPublic());
        $this->assertEquals(2, $container->filesCount());

        $this->assertEquals(1024, $container->size());
        $this->assertEquals(2048, $container->uploadedBytes());
        $this->assertEquals(1024, $container->downloadedBytes());
    }

    /** @test */
    function files_can_be_uploaded_to_container_from_string()
    {
        $contents = '<h1>Hello World!</h1>';
        $path = '/index.html';
        $etag = md5($contents);

        $api = TestHelpers::mockApi(function ($api) use ($path, $contents, $etag) {
            $requests = [
                $this->listContainersRequest(),
                $this->uploadFromStringRequest('container1', $path, $contents, $etag),
            ];

            foreach ($requests as $request) {
                $api->shouldReceive('request')
                    ->with($request['method'], $request['url'], $request['params'])
                    ->andReturn($request['response']);
            }
        });

        $storage = new CloudStorage($api);
        $containers = $storage->containers();
        $container = $containers->get('container1');

        $uploadedEtag = $container->uploadFromString($path, $contents);

        $this->assertEquals($etag, $uploadedEtag);
    }

    /** @test */
    function files_can_be_uploaded_to_container_from_stream()
    {
        $resource = fopen(__DIR__.'/fixtures/test.html', 'r');
        $path = '/index.html';

        $api = TestHelpers::mockApi(function ($api) use ($path, $resource) {
            $requests = [
                $this->listContainersRequest(),
                $this->uploadFromStreamRequest('container1', $path, $resource),
            ];

            foreach ($requests as $request) {
                $api->shouldReceive('request')
                    ->with($request['method'], $request['url'], $request['params'])
                    ->andReturn($request['response']);
            }
        });

        $storage = new CloudStorage($api);
        $containers = $storage->containers();
        $container = $containers->get('container1');

        $uploadedEtag = $container->uploadFromStream($path, $resource);

        $this->assertInternalType('string', $uploadedEtag);
    }

    /** @test */
    function container_can_be_deleted()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $requests = [
                $this->getContainerRequest('container1'),
                $this->getContainerDeleteRequest('container1')
            ];

            foreach ($requests as $request) {
                $api->shouldReceive('request')
                    ->with($request['method'], $request['url'])
                    ->andReturn($request['response']);
            }
        });

        $storage = new CloudStorage($api);
        $container = $storage->getContainer('container1');
        $container->delete();
    }

    public function uploadFromStreamRequest($container, $path, $resource)
    {
        return [
            'method' => 'PUT',
            'url' => '/'.$container.'/'.ltrim($path, '/'),
            'params' => [
                'headers' => [],
                'body' => $resource,
            ],
            'response' => TestHelpers::toResponse([], 201, [
                'etag' => md5('test'),
            ]),
        ];
    }

    public function uploadFromStringRequest($container, $path, $contents, $etag)
    {
        return [
            'method' => 'PUT',
            'url' => '/'.$container.'/'.ltrim($path, '/'),
            'params' => [
                'headers' => [
                    'ETag' => $etag,
                ],
                'body' => $contents,
            ],
            'response' => TestHelpers::toResponse([], 201, [
                'etag' => $etag,
            ]),
        ];
    }

    public function getContainerRequest($name)
    {
        return [
            'method' => 'HEAD',
            'url' => '/'.$name,
            'response' => TestHelpers::toResponse([], 204, [
                'X-Container-Meta-Type' => 'public',
                'X-Container-Object-Count' => 2,
                'X-Container-Bytes-Used' => 1024,
                'X-Received-Bytes' => 2048,
                'X-Transfered-Bytes' => 1024,
            ]),
        ];
    }

    public function getCreateContainerRequest($name, $type)
    {
        return [
            'method' => 'PUT',
            'url' => '/'.$name,
            'params' => [
                'headers' => [
                    'X-Container-Meta-Type' => $type,
                ],
            ],
            'response' => TestHelpers::toResponse([], 201, []),
        ];
    }

    public function getContainerDeleteRequest($name)
    {
        return [
            'method' => 'DELETE',
            'url' => '/'.$name,
            'response' => TestHelpers::toResponse([], 204),
        ];
    }

    public function listContainersRequest()
    {
        return [
            'method' => 'GET',
            'url' => '/',
            'params' => [
                'query' => [
                    'limit' => 10000,
                    'marker' => '',
                ],
            ],
            'response' => TestHelpers::toResponse([
                [
                    'bytes' => 1024,
                    'count' => 2,
                    'name' => 'container1',
                    'rx_bytes' => 2048,
                    'tx_bytes' => 1024,
                    'type' => 'public',
                ],
                [
                    'bytes' => 1024,
                    'count' => 2,
                    'name' => 'container2',
                    'rx_bytes' => 2048,
                    'tx_bytes' => 1024,
                    'type' => 'public',
                ],
                [
                    'bytes' => 1024,
                    'count' => 2,
                    'name' => 'container3',
                    'rx_bytes' => 2048,
                    'tx_bytes' => 1024,
                    'type' => 'public',
                ],
            ]),
        ];
    }

    public function listFilesRequest($container)
    {
        return [
            'method' => 'GET',
            'url' => '/'.$container,
            'params' => [
                'query' => [
                    'limit' => 10000,
                    'marker' => '',
                    'path' => '',
                    'prefix' => '',
                    'delimiter' => '',
                ],
            ],
            'response' => TestHelpers::toResponse([
                [
                    'bytes' => 59392,
                    'content_type' => 'image/jpeg',
                    'hash' => '37c05df3550d4565537e4cf14281d1a5',
                    'last_modified' => '2013-05-27T15:31:25.325041',
                    'name' => 'image.jpg',
                ],
                [
                    'bytes' => 31,
                    'content_type' => 'text/html',
                    'hash' => 'b302ffc3b75770453e96c1348e30eb93',
                    'last_modified' => '2013-05-27T14:42:04.669760',
                    'name' => 'my_index.html',
                ],
                [
                    'bytes' => 1024,
                    'content_type' => 'application/octet-stream',
                    'hash' => '0f343b0931126a20f133d67c2b018a3b',
                    'last_modified' => '2013-05-27T13:16:49.007590',
                    'name' => 'new_object',
                ],
            ]),
        ];
    }
}
