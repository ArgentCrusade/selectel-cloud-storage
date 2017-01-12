<?php

namespace ArgentCrusade\Selectel\CloudStorage;

use ArgentCrusade\Selectel\CloudStorage\Collections\Collection;
use ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract;
use ArgentCrusade\Selectel\CloudStorage\Contracts\FilesTransformerContract;
use ArgentCrusade\Selectel\CloudStorage\Contracts\FluentFilesLoaderContract;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\FileNotFoundException;
use ArgentCrusade\Selectel\CloudStorage\Traits\FilesTransformer;

class FluentFilesLoader implements FluentFilesLoaderContract, FilesTransformerContract
{
    use FilesTransformer;

    /**
     * API Client.
     *
     * @var \ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract
     */
    protected $api;

    /**
     * Container name.
     *
     * @var string
     */
    protected $containerName = '';

    /**
     * Container URL.
     *
     * @var string
     */
    protected $containerUrl = '';

    /**
     * Default parameters.
     *
     * @var array
     */
    protected $params = [
        'limit' => 10000,
        'marker' => '',
        'path' => '',
        'prefix' => '',
        'delimiter' => '',
    ];

    /**
     * Determines if resulting Collection should container File objects
     * instead of file arrays.
     *
     * @var bool
     */
    protected $asFileObjects = false;

    /**
     * @param \ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract $api
     * @param string                                                               $container
     * @param string                                                               $containerUrl
     */
    public function __construct(ApiClientContract $api, $container, $containerUrl)
    {
        $this->api = $api;
        $this->containerName = $container;
        $this->containerUrl = $containerUrl;
    }

    /**
     * Sets loader parameter.
     *
     * @param string     $key
     * @param string|int $value
     * @param bool       $trimLeadingSlashes = true
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\FluentFilesLoaderContract
     */
    protected function setParam($key, $value, $trimLeadingSlashes = true)
    {
        $this->params[$key] = $trimLeadingSlashes ? ltrim($value, '/') : $value;

        return $this;
    }

    /**
     * Container name.
     *
     * @return string
     */
    public function containerName()
    {
        return $this->containerName;
    }

    /**
     * Sets directory from where load files. This value may be overwritten
     * to empty string if you're loading prefixed files from directory.
     *
     * @param string $directory
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\FluentFilesLoaderContract
     */
    public function fromDirectory($directory)
    {
        return $this->setParam('path', $directory);
    }

    /**
     * Sets files prefix. If you're planning to find prefixed files from a directory
     * (using along with fromDirectory method), do not provide path to a directory
     * here, since it will be appended to final prefix (before sending request).
     *
     * @param string $prefix
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\FluentFilesLoaderContract
     */
    public function withPrefix($prefix)
    {
        return $this->setParam('prefix', $prefix);
    }

    /**
     * Sets files delimiter.
     *
     * @param string $delimiter
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\FluentFilesLoaderContract
     */
    public function withDelimiter($delimiter)
    {
        return $this->setParam('delimiter', $delimiter, false);
    }

    /**
     * Sets files limit. If you need to paginate through results, pass markerFile
     * argument with latest filename from previous request as value. If you're
     * working within a directory, its path will be appended to markerFile.
     *
     * @param int    $limit
     * @param string $markerFile = ''
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\FluentFilesLoaderContract
     */
    public function limit($limit, $markerFile = '')
    {
        return $this->setParam('limit', intval($limit), false)
            ->setParam('marker', $markerFile, false);
    }

    /**
     * Tells builder to return Collection of File objects instead of arrays.
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\FluentFilesLoaderContract
     */
    public function asFileObjects()
    {
        $this->asFileObjects = true;

        return $this;
    }

    /**
     * Loads all available files from container.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\Collections\CollectionContract
     */
    public function all()
    {
        return $this->fromDirectory('')
            ->withPrefix('')
            ->withDelimiter('')
            ->limit(10000)
            ->get();
    }

    /**
     * Determines whether file exists or not.
     *
     * @param string $path File path.
     *
     * @return bool
     */
    public function exists($path)
    {
        return !is_null($this->findFileAt($path));
    }

    /**
     * Finds single file at given path.
     *
     * @param string $path
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\FileNotFoundException
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\FileContract
     */
    public function find($path)
    {
        $file = $this->findFileAt($path);

        if (is_null($file)) {
            throw new FileNotFoundException('File "'.$path.'" was not found.');
        }

        return new File($this->api, $this->containerName(), $file);
    }

    /**
     * Loads file from path.
     *
     * @param string $path
     *
     * @return array|null
     */
    protected function findFileAt($path)
    {
        try {
            $files = $this->fromDirectory('')
                ->withPrefix($path)
                ->withDelimiter('')
                ->limit(1)
                ->get();
        } catch (ApiRequestFailedException $e) {
            return;
        }

        return $files->get(0);
    }

    /**
     * Loads files.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\Collections\CollectionContract
     */
    public function get()
    {
        $response = $this->api->request('GET', $this->containerUrl, [
            'query' => $this->buildParams(),
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new ApiRequestFailedException('Unable to list container files.', $response->getStatusCode());
        }

        $files = json_decode($response->getBody(), true);

        if ($this->asFileObjects === true) {
            $this->asFileObjects = false;

            return $this->getFilesCollectionFromArrays($files);
        }

        // Add 'filename' attribute to each file, so users
        // can pass it to new loader instance as marker,
        // if they want to iterate inside a directory.

        $files = array_map(function ($file) {
            $path = explode('/', $file['name']);
            $file['filename'] = array_pop($path);

            return $file;
        }, $files);

        return new Collection($files);
    }

    /**
     * Builds query parameters.
     *
     * @return array
     */
    protected function buildParams()
    {
        // If user wants to paginate files let's check if they're working
        // in a specific directory, so they can provide only filename,
        // instead of sending full directory path with file marker.

        if ($this->params['marker'] && $this->params['path']) {
            $this->params['marker'] = $this->params['path'].'/'.ltrim($this->params['marker'], '/');
        }

        // Also, if user is loading prefixed files from a directory
        // there's no need to send directory path with prefix. We
        // can append path to prefix and then reset path value.

        if ($this->params['prefix'] && $this->params['path']) {
            $this->params['prefix'] = $this->params['path'].'/'.ltrim($this->params['prefix']);
            $this->params['path'] = '';
        }

        return $this->params;
    }
}
