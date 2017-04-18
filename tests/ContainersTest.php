<?php

use ArgentCrusade\Selectel\CloudStorage\CloudStorage;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException;

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
        $this->assertEquals('public', $first->type());
        $this->assertEquals(2, $first->filesCount());

        $this->assertEquals(1024, $first->size());
        $this->assertEquals(2048, $first->uploadedBytes());
        $this->assertEquals(1024, $first->downloadedBytes());
    }

    /** @test */
    function container_should_have_default_url()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $request = $this->listContainersRequest();

            $api->shouldReceive('request')
                ->with($request['method'], $request['url'], $request['params'])
                ->andReturn($request['response']);
        });

        $storage = new CloudStorage($api);
        $containers = $storage->containers();
        $container = $containers->get('container1');

        $this->assertEquals('http://xxx.selcdn.ru/container1', $container->url());
        $this->assertEquals('http://xxx.selcdn.ru/container1/file.txt', $container->url('file.txt'));
        $this->assertEquals('http://xxx.selcdn.ru/container1/file.txt', $container->url('/file.txt'));
    }

    /** @test */
    function container_may_have_overriden_url()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $request = $this->listContainersRequest();

            $api->shouldReceive('request')
                ->with($request['method'], $request['url'], $request['params'])
                ->andReturn($request['response']);
        });

        $storage = new CloudStorage($api);
        $containers = $storage->containers();
        $container = $containers->get('container1');

        $container->setUrl('https://static.example.org');
        $this->assertEquals('https://static.example.org', $container->url());
        $this->assertEquals('https://static.example.org/file.txt', $container->url('file.txt'));
        $this->assertEquals('https://static.example.org/file.txt', $container->url('/file.txt'));
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
        $files = $container->files()->get();

        $this->assertEquals(3, count($files));
    }

    /** @test */
    function container_can_transform_file_array_to_file_object()
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
        $files = $container->files()->get();

        $firstFile = $files[0];

        $file = $container->getFileFromArray($firstFile);

        $this->assertEquals($firstFile['filename'], $file->name());
        $this->assertEquals($firstFile['name'], $file->path());
        $this->assertEquals($firstFile['bytes'], $file->size());
        $this->assertEquals($firstFile['content_type'], $file->contentType());
    }

    /** @test */
    function container_can_transform_collection_of_file_arrays_to_collection_of_file_objects()
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
        $files = $container->files()->get();
        $filesCollection = $container->getFilesCollectionFromArrays($files);

        foreach ($files as $index => $file) {
            $this->assertEquals($file['filename'], $filesCollection[$index]->name());
            $this->assertEquals($file['name'], $filesCollection[$index]->path());
            $this->assertEquals($file['bytes'], $filesCollection[$index]->size());
            $this->assertEquals($file['content_type'], $filesCollection[$index]->contentType());
        }
    }

    /** @test */
    function container_can_transform_array_of_file_arrays_to_collection_of_file_objects()
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
        $files = $container->files()->get();

        $filesCollection = $container->getFilesCollectionFromArrays([
            $files[0], $files[1], $files[2],
        ]);

        foreach ($files as $index => $file) {
            $this->assertEquals($file['filename'], $filesCollection[$index]->name());
            $this->assertEquals($file['name'], $filesCollection[$index]->path());
            $this->assertEquals($file['bytes'], $filesCollection[$index]->size());
            $this->assertEquals($file['content_type'], $filesCollection[$index]->contentType());
        }
    }

    /** @test */
    function container_can_check_if_file_exists()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $requests = [
                $this->listContainersRequest(),
                $this->getFileExistsRequest('container1', 'test.txt'),
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

        $this->assertTrue($container->files()->exists('test.txt'));
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
        $this->assertEquals('public', $container->type());
        $this->assertEquals(2, $container->filesCount());

        $this->assertEquals(1024, $container->size());
        $this->assertEquals(2048, $container->uploadedBytes());
        $this->assertEquals(1024, $container->downloadedBytes());
    }

    /** @test */
    function container_can_have_meta_data()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $request = $this->getContainerRequest('container1');

            $api->shouldReceive('request')
                ->with($request['method'], $request['url'])
                ->andReturn($request['response']);
        });

        $storage = new CloudStorage($api);
        $container = $storage->getContainer('container1');

        $this->assertTrue($container->hasMeta('Foo'));
        $this->assertTrue($container->hasMeta('X-Container-Meta-Bar'));
        $this->assertFalse($container->hasMeta('Unknown'));

        $this->assertEquals('Bar', $container->getMeta('Foo'));
        $this->assertEquals('Baz', $container->getMeta('X-Container-Meta-Bar'));

        $this->expectException(InvalidArgumentException::class);
        $container->getMeta('Unknown');
    }

    /** @test */
    function container_can_update_its_type()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $containerRequest = $this->getContainerRequest('container1');

            $api->shouldReceive('request')
                ->with($containerRequest['method'], $containerRequest['url'])
                ->andReturn($containerRequest['response']);

            $requests = [
                $this->getSetContainerTypeRequest('container1', 'private'),
                $this->getSetContainerTypeRequest('container1', 'gallery'),
                $this->getSetContainerTypeRequest('container1', 'public'),
            ];

            foreach ($requests as $request) {
                $api->shouldReceive('request')
                    ->with($request['method'], $request['url'], $request['params'])
                    ->andReturn($request['response']);
            }
        });

        $storage = new CloudStorage($api);
        $container = $storage->getContainer('container1');

        $this->assertEquals('public', $container->type());

        $container->setType('private');
        $this->assertEquals('private', $container->type());

        $container->setType('gallery');
        $this->assertEquals('gallery', $container->type());

        $container->setType('public');
        $this->assertEquals('public', $container->type());
    }

    /** @test */
    function container_can_update_its_meta()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $containerRequest = $this->getContainerRequest('container1');

            $api->shouldReceive('request')
                ->with($containerRequest['method'], $containerRequest['url'])
                ->andReturn($containerRequest['response']);

            $request = $this->getSetContainerMetaRequest('container1', [
                'X-Container-Meta-Some' => 'Test',
                'X-Container-Meta-Foo' => 'Bar',
                'X-Container-Meta-Bar' => 'Baz',
            ]);

            $api->shouldReceive('request')
                ->with($request['method'], $request['url'], $request['params'])
                ->andReturn($request['response']);
        });

        $storage = new CloudStorage($api);
        $container = $storage->getContainer('container1');

        $this->assertTrue(
            $container->setMeta([
                'Some' => 'Test',
                'Foo' => 'Bar',
                'X-Container-Meta-Bar' => 'Baz',
            ])
        );
    }

    /** @test */
    function container_can_create_directories()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $containerRequest = $this->getContainerRequest('container1');
            $createDirRequest = $this->getDirectoryCreateRequest('/container1/test-directory');

            $api->shouldReceive('request')
                ->with($containerRequest['method'], $containerRequest['url'])
                ->andReturn($containerRequest['response']);

            $api->shouldReceive('request')
                ->with($createDirRequest['method'], $createDirRequest['url'], $createDirRequest['params'])
                ->andReturn($createDirRequest['response']);
        });

        $storage = new CloudStorage($api);
        $container = $storage->getContainer('container1');

        $this->assertEquals(md5('test'), $container->createDir('/test-directory'));
    }

    /** @test */
    function container_can_delete_directories()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $containerRequest = $this->getContainerRequest('container1');
            $deleteDirRequest = $this->getDirectoryDeleteRequest('/container1/test-directory');

            $api->shouldReceive('request')
                ->with($containerRequest['method'], $containerRequest['url'])
                ->andReturn($containerRequest['response']);

            $api->shouldReceive('request')
                ->with($deleteDirRequest['method'], $deleteDirRequest['url'])
                ->andReturn($deleteDirRequest['response']);
        });

        $storage = new CloudStorage($api);
        $container = $storage->getContainer('container1');

        $this->assertTrue($container->deleteDir('/test-directory'));
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

    public function getFileExistsRequest($container, $file)
    {
        return [
            'method' => 'GET',
            'url' => '/'.$container,
            'params' => [
                'query' => [
                    'limit' => 1,
                    'marker' => '',
                    'path' => '',
                    'prefix' => $file,
                    'delimiter' => '',
                ],
            ],
            'response' => TestHelpers::toResponse([
                [
                    'bytes' => 59392,
                    'content_type' => 'image/jpeg',
                    'hash' => '37c05df3550d4565537e4cf14281d1a5',
                    'last_modified' => '2013-05-27T15:31:25.325041',
                    'name' => $file,
                ],
            ]),
        ];
    }

    public function getSetContainerTypeRequest($name, $type)
    {
        return [
            'method' => 'POST',
            'url' => '/'.$name,
            'params' => [
                'headers' => [
                    'X-Container-Meta-Type' => $type,
                ],
            ],
            'response' => TestHelpers::toResponse('', 202, []),
        ];
    }

    public function getSetContainerMetaRequest($name, $metas)
    {
        return [
            'method' => 'POST',
            'url' => '/'.$name,
            'params' => [
                'headers' => $metas,
            ],
            'response' => TestHelpers::toResponse('', 202, []),
        ];
    }

    public function getDirectoryCreateRequest($path)
    {
        return [
            'method' => 'PUT',
            'url' => $path,
            'params' => [
                'headers' => [
                    'Content-Type' => 'application/directory',
                ],
            ],
            'response' => TestHelpers::toResponse('', 201, [
                'etag' => md5('test'),
            ]),
        ];
    }

    public function getDirectoryDeleteRequest($path)
    {
        return [
            'method' => 'DELETE',
            'url' => $path,
            'response' => TestHelpers::toResponse('', 204, []),
        ];
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
                'X-Container-Meta-Foo' => 'Bar',
                'X-Container-Meta-Bar' => 'Baz',
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
