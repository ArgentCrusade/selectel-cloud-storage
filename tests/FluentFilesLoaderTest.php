<?php

use ArgentCrusade\Selectel\CloudStorage\FluentFilesLoader;

class FluentFilesLoaderTest extends PHPUnit_Framework_TestCase
{
    /** @test */
    function directory_loader()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $this->mockRequest($api, [
                'path' => 'test-directory',
            ]);
        });

        $loader = new FluentFilesLoader($api, 'test', '/test');

        $loader->fromDirectory('/test-directory')->get();
    }

    /** @test */
    function prefix_loader()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $this->mockRequest($api, [
                'prefix' => 'test-directory/image-',
            ]);
        });

        $loader = new FluentFilesLoader($api, 'test', '/test');

        $loader->withPrefix('test-directory/image-')->get();
    }

    /** @test */
    function delimiter_loader()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $this->mockRequest($api, [
                'delimiter' => 'test',
            ]);
        });

        $loader = new FluentFilesLoader($api, 'test', '/test');

        $loader->withDelimiter('test')->get();
    }

    /** @test */
    function limit_loader()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $this->mockRequest($api, [
                'limit' => 5,
            ]);
        });

        $loader = new FluentFilesLoader($api, 'test', '/test');

        $loader->limit(5)->get();
    }

    /** @test */
    function limit_marker_loader()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $this->mockRequest($api, [
                'limit' => 5,
                'marker' => 'last-previous-file.txt',
            ]);
        });

        $loader = new FluentFilesLoader($api, 'test', '/test');

        $loader->limit(5, 'last-previous-file.txt')->get();
    }

    /** @test */
    function directory_prefix_loader()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $this->mockRequest($api, [
                'path' => 'test',
                'prefix' => 'file-',
            ]);
        });

        $loader = new FluentFilesLoader($api, 'test', '/test');

        $loader->fromDirectory('/test')
            ->withPrefix('file-')
            ->get();
    }

    /** @test */
    function all_together()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $this->mockRequest($api, [
                'path' => 'test',
                'prefix' => 'file-',
                'limit' => 10,
                'delimiter' => 'test',
                'marker' => 'last-previous-file.txt',
            ]);
        });

        $loader = new FluentFilesLoader($api, 'test', '/test');

        $loader->fromDirectory('/test')
            ->withPrefix('/file-')
            ->withDelimiter('test')
            ->limit(10, 'last-previous-file.txt')
            ->get();
    }

    /** @test */
    function files_transformer()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $this->mockRequest($api, [
                'path' => 'test',
                'prefix' => 'file-',
                'limit' => 10,
                'delimiter' => 'test',
                'marker' => 'last-previous-file.txt',
            ]);
        });

        $loader = new FluentFilesLoader($api, 'test', '/test');

        $files = $loader->fromDirectory('/test')
            ->withPrefix('/file-')
            ->withDelimiter('test')
            ->limit(10, 'last-previous-file.txt')
            ->asFileObjects()
            ->get();

        foreach ($files as $file) {
            $this->assertInternalType('string', $file->name());
        }
    }

    public function mockRequest($api, $params)
    {
        $path = isset($params['path']) ? $params['path'] : '';
        $prefix = isset($params['prefix']) ? $params['prefix'] : '';
        $delimiter = isset($params['delimiter']) ? $params['delimiter'] : '';
        $limit = isset($params['limit']) ? intval($params['limit']) : 10000;
        $marker = isset($params['marker']) ? $params['marker'] : '';

        if ($marker && $path) {
            $marker = $path.'/'.ltrim($marker, '/');
        }

        if ($prefix && $path) {
            $prefix = $path.'/'.ltrim($prefix, '/');
            $path = '';
        }

        $api->shouldReceive('request')
            ->with('GET', '/test', [
                'query' => compact('limit', 'marker', 'path', 'prefix', 'delimiter'),
            ])
            ->andReturn(TestHelpers::toResponse([
                ['name' => 'test1'],
                ['name' => 'test2'],
            ]));

        return $this;
    }
}
