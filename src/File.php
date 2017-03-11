<?php

namespace ArgentCrusade\Selectel\CloudStorage;

use LogicException;
use JsonSerializable;
use InvalidArgumentException;
use GuzzleHttp\Psr7\StreamWrapper;
use ArgentCrusade\Selectel\CloudStorage\Contracts\FileContract;
use ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException;

class File implements FileContract, JsonSerializable
{
    /**
     * @var \ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract
     */
    protected $api;

    /**
     * Container name.
     *
     * @var string
     */
    protected $container;

    /**
     * File info.
     *
     * @var array
     */
    protected $data;

    /**
     * Determines if current file was recently deleted.
     *
     * @var bool
     */
    protected $deleted = false;

    /**
     * @param \ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract $api
     * @param string                                                               $container
     * @param array                                                                $data
     */
    public function __construct(ApiClientContract $api, $container, array $data)
    {
        $this->api = $api;
        $this->container = $container;
        $this->data = $data;
    }

    /**
     * Returns specific file data.
     *
     * @param string $key
     * @param mixed  $default = null
     *
     * @throws \LogicException
     *
     * @return mixed|null
     */
    protected function fileData($key, $default = null)
    {
        $this->guardDeletedFile();

        return isset($this->data[$key]) ? $this->data[$key] : $default;
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
        if (!$path) {
            $path = $this->path();
        }

        return '/'.$this->container().($path ? '/'.ltrim($path, '/') : '');
    }

    /**
     * Container name.
     *
     * @throws \LogicException
     *
     * @return string
     */
    public function container()
    {
        return $this->container;
    }

    /**
     * Full path to file.
     *
     * @throws \LogicException
     *
     * @return string
     */
    public function path()
    {
        return $this->fileData('name');
    }

    /**
     * File directory.
     *
     * @throws \LogicException
     *
     * @return string
     */
    public function directory()
    {
        $path = explode('/', $this->path());

        array_pop($path);

        return implode('/', $path);
    }

    /**
     * File name.
     *
     * @throws \LogicException
     *
     * @return string
     */
    public function name()
    {
        $path = explode('/', $this->path());

        return array_pop($path);
    }

    /**
     * File size in bytes.
     *
     * @throws \LogicException
     *
     * @return int
     */
    public function size()
    {
        return intval($this->fileData('bytes'));
    }

    /**
     * File content type.
     *
     * @throws \LogicException
     *
     * @return string
     */
    public function contentType()
    {
        return $this->fileData('content_type');
    }

    /**
     * Date of last modification.
     *
     * @throws \LogicException
     *
     * @return string
     */
    public function lastModifiedAt()
    {
        return $this->fileData('last_modified');
    }

    /**
     * File ETag.
     *
     * @throws \LogicException
     *
     * @return string
     */
    public function etag()
    {
        return $this->fileData('hash');
    }

    /**
     * Determines if current file was recently deleted.
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deleted === true;
    }

    /**
     * Reads file contents.
     *
     * @return string
     */
    public function read()
    {
        $response = $this->api->request('GET', $this->absolutePath());

        return (string) $response->getBody();
    }

    /**
     * Reads file contents as stream.
     *
     * @param bool $psr7Stream = false
     *
     * @return resource|\Psr\Http\Message\StreamInterface
     */
    public function readStream($psr7Stream = false)
    {
        $response = $this->api->request('GET', $this->absolutePath());

        if ($psr7Stream) {
            return $response->getBody();
        }

        return StreamWrapper::getResource($response->getBody());
    }

    /**
     * Rename file. New file name must be provided without path.
     *
     * @param string $name
     *
     * @throws \LogicException
     * @throws \InvalidArgumentException
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     *
     * @return string
     */
    public function rename($name)
    {
        $this->guardDeletedFile();

        // If there is any slash character in new name, Selectel
        // will create new virtual directory and copy file to
        // this new one. Such behaviour may be unexpected.

        if (count(explode('/', $name)) > 1) {
            throw new InvalidArgumentException('File name can not contain "/" character.');
        }

        $destination = $this->directory().'/'.$name;

        $response = $this->api->request('PUT', $this->absolutePath($destination), [
            'headers' => [
                'X-Copy-From' => $this->absolutePath(),
                'Content-Length' => 0,
            ],
        ]);

        if ($response->getStatusCode() !== 201) {
            throw new ApiRequestFailedException(
                'Unable to rename file from "'.$this->name().'" to "'.$name.'" (path: "'.$this->directory().'").',
                $response->getStatusCode()
            );
        }

        // Since Selectel Storage does not provide such method as "rename",
        // we need to delete original file after copying. Also, "deleted"
        // flag needs to be reverted because file was actually renamed.

        $this->delete();
        $this->deleted = false;

        return $this->data['name'] = $destination;
    }

    /**
     * Copy file to given destination.
     *
     * @param string $destination
     * @param string $destinationContainer = null
     *
     * @throws \LogicException
     *
     * @return string
     */
    public function copy($destination, $destinationContainer = null)
    {
        $this->guardDeletedFile();

        if (is_null($destinationContainer)) {
            $destinationContainer = $this->container();
        }

        $fullDestination = '/'.$destinationContainer.'/'.ltrim($destination, '/');

        $response = $this->api->request('COPY', $this->absolutePath(), [
            'headers' => [
                'Destination' => $fullDestination,
            ],
        ]);

        if ($response->getStatusCode() !== 201) {
            throw new ApiRequestFailedException(
                'Unable to copy file from "'.$this->path().'" to "'.$destination.'".',
                $response->getStatusCode()
            );
        }

        return $fullDestination;
    }

    /**
     * Deletes file.
     *
     * @throws \LogicException
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     */
    public function delete()
    {
        $this->guardDeletedFile();

        $response = $this->api->request('DELETE', $this->absolutePath());

        if ($response->getStatusCode() !== 204) {
            throw new ApiRequestFailedException('Unable to delete file "'.$this->path().'".', $response->getStatusCode());
        }

        // Set deleted flag to true, so any other calls to
        // this File will result in throwing exception.

        $this->deleted = true;

        return true;
    }

    /**
     * JSON representation of file.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'name' => $this->name(),
            'path' => $this->path(),
            'directory' => $this->directory(),
            'container' => $this->container(),
            'size' => $this->size(),
            'content_type' => $this->contentType(),
            'last_modified' => $this->lastModifiedAt(),
            'etag' => $this->etag(),
        ];
    }

    /**
     * Protects FileAPI from unwanted requests.
     *
     * @throws \LogicException
     */
    protected function guardDeletedFile()
    {
        if ($this->deleted === true) {
            throw new LogicException('File was deleted recently.');
        }
    }
}
