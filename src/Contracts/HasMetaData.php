<?php

namespace ArgentCrusade\Selectel\CloudStorage\Contracts;

interface HasMetaData
{
    /**
     * Checks if given meta data exists.
     *
     * @param string $name Meta name
     *
     * @return bool
     */
    public function hasMeta($name);

    /**
     * Returns meta data.
     *
     * @param string $name Meta name
     *
     * @throws \InvalidArgumentException
     *
     * @return mixed
     */
    public function getMeta($name);

    /**
     * Updates object meta data.
     *
     * @param array $meta Array of meta data (without "X-{Object}-Meta" prefixes).
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     *
     * @return bool
     */
    public function setMeta(array $meta);
}
