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
     * Determines if container is public.
     *
     * @return bool
     */
    public function isPublic();

    /**
     * Determines if container is private.
     *
     * @return bool
     */
    public function isPrivate();

    /**
     * Retrieves files from current container.
     *
     * @param string $directory        = null
     * @param string $prefixOrFullPath = null
     * @param string $delimiter        = null
     * @param int    $limit            = 10000
     * @param string $marker           = ''
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\Collections\CollectionContract
     */
    public function files($directory = null, $prefixOrFullPath = null, $delimiter = null, $limit = 10000, $marker = '');

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
     * Retrieves file object container. This method does not actually download file, see File::download.
     *
     * @param string $path
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\FileNotFoundException
     *
     * @return \FileContract
     */
    public function getFile($path);

    /**
     * Deletes container. Container must be empty in order to perform this operation.
     */
    public function delete();
}
