<?php

namespace ArgentCrusade\Selectel\CloudStorage\Contracts\Collections;

interface CollectionContract
{
    /**
     * Determines if given key exists in current collection.
     *
     * @param mixed $key
     *
     * @return bool
     */
    public function has($key);

    /**
     * Retrieves item by given key from current collection.
     *
     * @param mixed $key
     *
     * @return mixed|null
     */
    public function get($key);
}
