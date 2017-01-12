<?php

namespace ArgentCrusade\Selectel\CloudStorage;

use ArgentCrusade\Selectel\CloudStorage\Collections\Collection;
use ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract;
use ArgentCrusade\Selectel\CloudStorage\Contracts\ContainerContract;
use ArgentCrusade\Selectel\CloudStorage\Contracts\FilesTransformerContract;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\FileNotFoundException;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\UploadFailedException;
use ArgentCrusade\Selectel\CloudStorage\Traits\FilesTransformer;
use Countable;
use JsonSerializable;
use LogicException;

class Container implements ContainerContract, FilesTransformerContract, Countable, JsonSerializable
{
    use FilesTransformer;

    /**
     * @var \ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract $api
     */
    protected $api;

    /**
     * Container name.
     *
     * @var string
     */
    protected $containerName;

    /**
     * Container data.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Determines if container data was already loaded.
     *
     * @var bool
     */
    protected $dataLoaded = false;

    /**
     * @param \ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract $api
     * @param string                                                               $name
     * @param array                                                                $data
     */
    public function __construct(ApiClientContract $api, $name, array $data = [])
    {
        $this->api = $api;
        $this->containerName = $name;
        $this->data = $data;
        $this->dataLoaded = !empty($data);
    }

    /**
     * Returns specific container data.
     *
     * @param string $key
     * @param mixed  $default = null
     *
     * @return mixed
     */
    protected function containerData($key, $default = null)
    {
        if (!$this->dataLoaded) {
            $this->loadContainerData();
        }

        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }

    /**
     * Lazy loading for container data.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     */
    protected function loadContainerData()
    {
        // CloudStorage::containers and CloudStorage::getContainer methods did not
        // produce any requests to Selectel API, since it may be unnecessary if
        // user only wants to upload/manage files or delete container via API.

        // If user really wants some container info, we will load
        // it here on demand. This speeds up application a bit.

        $response = $this->api->request('HEAD', $this->absolutePath());

        if ($response->getStatusCode() !== 204) {
            throw new ApiRequestFailedException('Container "'.$this->name().'" was not found.');
        }

        $this->dataLoaded = true;
        $this->data = [
            'type' => $response->getHeaderLine('X-Container-Meta-Type'),
            'count' => intval($response->getHeaderLine('X-Container-Object-Count')),
            'bytes' => intval($response->getHeaderLine('X-Container-Bytes-Used')),
            'rx_bytes' => intval($response->getHeaderLine('X-Received-Bytes')),
            'tx_bytes' => intval($response->getHeaderLine('X-Transfered-Bytes')),
        ];
    }

    /**
     * Absolute path to file from storage root.
     *
     * @param string $path = '' Relative file path.
     *
     * @return string
     */
    protected function absolutePath($path = '')
    {
        return '/'.$this->name().($path ? '/'.ltrim($path, '/') : '');
    }

    /**
     * Returns JSON representation of container.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'name' => $this->name(),
            'type' => $this->type(),
            'files_count' => $this->filesCount(),
            'size' => $this->size(),
            'uploaded_bytes' => $this->uploadedBytes(),
            'downloaded_bytes' => $this->downloadedBytes(),
        ];
    }

    /**
     * Container name.
     *
     * @return string
     */
    public function name()
    {
        return $this->containerName();
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
     * Container visibility type.
     *
     * @return string
     */
    public function type()
    {
        return $this->containerData('type', 'public');
    }

    /**
     * Container files count.
     *
     * @return int
     */
    public function filesCount()
    {
        return intval($this->containerData('count', 0));
    }

    /**
     * Container files count.
     *
     * @return int
     */
    public function count()
    {
        return $this->filesCount();
    }

    /**
     * Container size in bytes.
     *
     * @return int
     */
    public function size()
    {
        return intval($this->containerData('bytes', 0));
    }

    /**
     * Total uploaded (received) bytes.
     *
     * @return int
     */
    public function uploadedBytes()
    {
        return intval($this->containerData('rx_bytes', 0));
    }

    /**
     * Total downloaded (transmitted) bytes.
     *
     * @return int
     */
    public function downloadedBytes()
    {
        return intval($this->containerData('tx_bytes', 0));
    }

    /**
     * Determines if container is public.
     *
     * @return bool
     */
    public function isPublic()
    {
        return $this->type() == 'public';
    }

    /**
     * Determines if container is private.
     *
     * @return bool
     */
    public function isPrivate()
    {
        return $this->type() == 'private';
    }

    /**
     * Determines if container is a gallery container.
     *
     * @return bool
     */
    public function isGallery()
    {
        return $this->type() == 'gallery';
    }

    /**
     * Sets container type to 'public'.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     *
     * @return string
     */
    public function setPublic()
    {
        return $this->setType('public');
    }

    /**
     * Sets container type to 'private'.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     *
     * @return string
     */
    public function setPrivate()
    {
        return $this->setType('private');
    }

    /**
     * Sets container type to 'gallery'.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     *
     * @return string
     */
    public function setGallery()
    {
        return $this->setType('gallery');
    }

    /**
     * Updates container type.
     *
     * @param string $type Container type, 'public', 'private' or 'gallery'.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     *
     * @return string
     */
    protected function setType($type)
    {
        if ($this->type() === $type) {
            return $type;
        }

        $response = $this->api->request('POST', $this->absolutePath(), [
            'headers' => [
                'X-Container-Meta-Type' => $type,
            ],
        ]);

        if ($response->getStatusCode() !== 202) {
            throw new ApiRequestFailedException('Unable to set container type to "'.$type.'".', $response->getStatusCode());
        }

        return $this->data['type'] = $type;
    }

    /**
     * Creates new Fluent files loader instance.
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\FluentFilesLoaderContract
     */
    public function files()
    {
        return new FluentFilesLoader($this->api, $this->name(), $this->absolutePath());
    }

    /**
     * Determines whether file exists or not.
     *
     * @param string $path File path.
     *
     * @return bool
     */
    public function fileExists($path)
    {
        return $this->files()->exists($path);
    }

    /**
     * Retrieves file object container. This method does not actually download file, see File::read or File::readStream.
     *
     * @param string $path
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\FileNotFoundException
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\FileContract
     */
    public function getFile($path)
    {
        return $this->files()->find($path);
    }

    /**
     * Creates new directory.
     *
     * @param string $name Directory name.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     *
     * @return string
     */
    public function createDir($name)
    {
        $response = $this->api->request('PUT', $this->absolutePath($name), [
            'headers' => [
                'Content-Type' => 'application/directory',
            ],
        ]);

        if ($response->getStatusCode() !== 201) {
            throw new ApiRequestFailedException('Unable to create directory "'.$name.'".', $response->getStatusCode());
        }

        return $response->getHeaderLine('ETag');
    }

    /**
     * Deletes directory.
     *
     * @param string $name Directory name.
     */
    public function deleteDir($name)
    {
        $response = $this->api->request('DELETE', $this->absolutePath($name));

        if ($response->getStatusCode() !== 204) {
            throw new ApiRequestFailedException('Unable to delete directory "'.$name.'".', $response->getStatusCode());
        }

        return true;
    }

    /**
     * Uploads file contents from string. Returns ETag header value if upload was successful.
     *
     * @param string $path           Remote path.
     * @param string $contents       File contents.
     * @param array  $params         = [] Upload params.
     * @param bool   $verifyChecksum = true
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\UploadFailedException
     *
     * @return string
     */
    public function uploadFromString($path, $contents, array $params = [], $verifyChecksum = true)
    {
        return $this->uploadFrom($path, $contents, $params, $verifyChecksum);
    }

    /**
     * Uploads file from stream. Returns ETag header value if upload was successful.
     *
     * @param string   $path     Remote path.
     * @param resource $resource Stream resource.
     * @param array    $params   = [] Upload params.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\UploadFailedException
     *
     * @return string
     */
    public function uploadFromStream($path, $resource, array $params = [])
    {
        return $this->uploadFrom($path, $resource, $params, false);
    }

    /**
     * Upload file from string or stream resource.
     *
     * @param string            $path           Remote path.
     * @param string | resource $contents       File contents.
     * @param array             $params         = [] Upload params.
     * @param bool              $verifyChecksum = true
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\UploadFailedException
     *
     * @return string
     */
    protected function uploadFrom($path, $contents, array $params = [], $verifyChecksum = true)
    {
        $response = $this->api->request('PUT', $this->absolutePath($path), [
            'headers' => $this->convertUploadParamsToHeaders($contents, $params, $verifyChecksum),
            'body' => $contents,
        ]);

        if ($response->getStatusCode() !== 201) {
            throw new UploadFailedException('Unable to upload file.', $response->getStatusCode());
        }

        return $response->getHeaderLine('ETag');
    }

    /**
     * Parses upload parameters and assigns them to appropriate HTTP headers.
     *
     * @param string $contents       = null
     * @param array  $params         = []
     * @param bool   $verifyChecksum = true
     *
     * @return array
     */
    protected function convertUploadParamsToHeaders($contents = null, array $params = [], $verifyChecksum = true)
    {
        $headers = [];

        if ($verifyChecksum) {
            $headers['ETag'] = md5($contents);
        }

        $availableParams = [
            'contentType' => 'Content-Type',
            'contentDisposition' => 'Content-Disposition',
            'deleteAfter' => 'X-Delete-After',
            'deleteAt' => 'X-Delete-At',
        ];

        foreach ($availableParams as $key => $header) {
            if (isset($params[$key])) {
                $headers[$header] = $params[$key];
            }
        }

        return $headers;
    }

    /**
     * Deletes container. Container must be empty in order to perform this operation.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     */
    public function delete()
    {
        $response = $this->api->request('DELETE', $this->absolutePath());

        switch ($response->getStatusCode()) {
            case 204:
                // Container removed.
                return;
            case 404:
                throw new ApiRequestFailedException('Container "'.$this->name().'" was not found.');
            case 409:
                throw new ApiRequestFailedException('Container must be empty.');
        }
    }
}
