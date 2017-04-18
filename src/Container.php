<?php

namespace ArgentCrusade\Selectel\CloudStorage;

use Countable;
use JsonSerializable;
use ArgentCrusade\Selectel\CloudStorage\Traits\MetaData;
use ArgentCrusade\Selectel\CloudStorage\Contracts\HasMetaData;
use ArgentCrusade\Selectel\CloudStorage\Traits\FilesTransformer;
use ArgentCrusade\Selectel\CloudStorage\Contracts\ContainerContract;
use ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract;
use ArgentCrusade\Selectel\CloudStorage\Contracts\FilesTransformerContract;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException;

class Container implements ContainerContract, FilesTransformerContract, Countable, JsonSerializable, HasMetaData
{
    use FilesTransformer, MetaData;

    /**
     * @var \ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract $api
     */
    protected $api;

    /**
     * File uploader.
     *
     * @var \ArgentCrusade\Selectel\CloudStorage\Contracts\FileUploaderContract
     */
    protected $uploader;

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
     * Container URL. Uses "X-Storage-Url/container-name" by default.
     *
     * @var string
     */
    protected $url;

    /**
     * @param \ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract $api
     * @param \ArgentCrusade\Selectel\CloudStorage\FileUploader                    $uploader
     * @param string                                                               $name
     * @param array                                                                $data
     */
    public function __construct(ApiClientContract $api, FileUploader $uploader, $name, array $data = [])
    {
        $this->api = $api;
        $this->uploader = $uploader;
        $this->containerName = $name;
        $this->data = $data;
        $this->dataLoaded = !empty($data);
        $this->url = rtrim($api->storageUrl(), '/').'/'.$name;
    }

    /**
     * Returns specific container data.
     *
     * @param string $key
     * @param mixed  $default = null
     *
     * @return mixed
     */
    protected function objectData($key, $default = null)
    {
        if (!$this->dataLoaded) {
            $this->loadContainerData();
        }

        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }

    /**
     * Container data lazy loader.
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

        // We will extract some headers from response
        // and assign them as container data. Also
        // we'll try to find any container Meta.

        $this->data = [
            'type' => $response->getHeaderLine('X-Container-Meta-Type'),
            'count' => intval($response->getHeaderLine('X-Container-Object-Count')),
            'bytes' => intval($response->getHeaderLine('X-Container-Bytes-Used')),
            'rx_bytes' => intval($response->getHeaderLine('X-Received-Bytes')),
            'tx_bytes' => intval($response->getHeaderLine('X-Transfered-Bytes')),
            'meta' => $this->extractMetaData($response),
        ];
    }

    /**
     * Returns object meta type.
     *
     * @return string
     */
    protected function objectMetaType()
    {
        return 'Container';
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
     * API Client.
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract
     */
    public function apiClient()
    {
        return $this->api;
    }

    /**
     * JSON representation of container.
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
     * Get container's root URL.
     *
     * @param string $path = ''
     *
     * @return string
     */
    public function url($path = '')
    {
        return $this->url.($path ? '/'.ltrim($path, '/') : '');
    }

    /**
     * Container type.
     *
     * @return string
     */
    public function type()
    {
        return $this->objectData('type', 'public');
    }

    /**
     * Container files count.
     *
     * @return int
     */
    public function filesCount()
    {
        return intval($this->objectData('count', 0));
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
        return intval($this->objectData('bytes', 0));
    }

    /**
     * Total uploaded (received) bytes.
     *
     * @return int
     */
    public function uploadedBytes()
    {
        return intval($this->objectData('rx_bytes', 0));
    }

    /**
     * Total downloaded (transmitted) bytes.
     *
     * @return int
     */
    public function downloadedBytes()
    {
        return intval($this->objectData('tx_bytes', 0));
    }

    /**
     * Updates container type.
     *
     * @param string $type Container type: 'public', 'private' or 'gallery'.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     *
     * @return string
     */
    public function setType($type)
    {
        if ($this->type() === $type) {
            return $type;
        }

        // Catch any API Request Exceptions here
        // so we can replace exception message
        // with more informative one.

        try {
            $this->setMeta(['Type' => $type]);
        } catch (ApiRequestFailedException $e) {
            throw new ApiRequestFailedException('Unable to set container type to "'.$type.'".', $e->getCode());
        }

        return $this->data['type'] = $type;
    }

    /**
     * Set container's root URL.
     *
     * @param string $url
     *
     * @return static
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
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
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     *
     * @return bool
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
        return $this->uploader->upload($this->api, $this->absolutePath($path), $contents, $params, $verifyChecksum);
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
        return $this->uploader->upload($this->api, $this->absolutePath($path), $resource, $params, false);
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
