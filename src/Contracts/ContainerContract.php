<?php

namespace ArgentCrusade\Selectel\CloudStorage\Contracts;

interface ContainerContract
{
    /**
     * Container name.
     *
     * @return string
     */
    public function name();

    /**
     * Container visibility type.
     *
     * @return string
     */
    public function type();

    /**
     * Container files count.
     *
     * @return int
     */
    public function filesCount();

    /**
     * Container size in bytes.
     *
     * @return int
     */
    public function size();

    /**
     * Total uploaded (received) bytes.
     *
     * @return int
     */
    public function uploadedBytes();

    /**
     * Total downloaded (transmitted) bytes.
     *
     * @return int
     */
    public function downloadedBytes();

    /**
     * Updates container type.
     *
     * @param string $type Container type, 'public', 'private' or 'gallery'.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     *
     * @return string
     */
    public function setType($type);

    /**
     * Creates new Fluent files loader instance.
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\FluentFilesLoaderContract
     */
    public function files();

    /**
     * Creates new directory.
     *
     * @param string $name Directory name.
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     *
     * @return string
     */
    public function createDir($name);

    /**
     * Deletes directory.
     *
     * @param string $name Directory name.
     */
    public function deleteDir($name);

    /**
     * Uploads file contents from string. Returns ETag header value if upload was successful.
     *
     * @param string $path           Remote path.
     * @param string $contents
     * @param array  $params         = []
     * @param bool   $verifyChecksum = true
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\UploadFailedException
     *
     * @return string
     */
    public function uploadFromString($path, $contents, array $params = [], $verifyChecksum = true);

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
    public function uploadFromStream($path, $resource, array $params = []);

    /**
     * Deletes container. Container must be empty in order to perform this operation.
     */
    public function delete();
}
