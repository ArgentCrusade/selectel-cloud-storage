<?php

namespace ArgentCrusade\Selectel\CloudStorage\Contracts;

interface FileContract
{
    /**
     * Container name.
     *
     * @throws \LogicException
     *
     * @return string | null
     */
    public function container();

    /**
     * Full path to file.
     *
     * @throws \LogicException
     *
     * @return string | null
     */
    public function path();

    /**
     * File directory.
     *
     * @throws \LogicException
     *
     * @return string | null
     */
    public function directory();

    /**
     * File name.
     *
     * @throws \LogicException
     *
     * @return string | null
     */
    public function name();

    /**
     * File size in bytes.
     *
     * @throws \LogicException
     *
     * @return int
     */
    public function size();

    /**
     * File content type.
     *
     * @throws \LogicException
     *
     * @return string | null
     */
    public function contentType();

    /**
     * Date of last modification.
     *
     * @throws \LogicException
     *
     * @return string | null
     */
    public function lastModifiedAt();

    /**
     * File ETag.
     *
     * @throws \LogicException
     *
     * @return string | null
     */
    public function etag();

    /**
     * Determines if current file was recently deleted.
     *
     * @return bool
     */
    public function isDeleted();

    /**
     * Reads file contents.
     *
     * @return string
     */
    public function read();

    /**
     * Reads file contents as stream.
     *
     * @param bool $psr7Stream = false
     *
     * @return resource|\Psr\Http\Message\StreamInterface
     */
    public function readStream($psr7Stream = false);

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
    public function rename($name);

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
    public function copy($destination, $destinationContainer = null);

    /**
     * Deletes file.
     *
     * @throws \LogicException
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     */
    public function delete();
}
