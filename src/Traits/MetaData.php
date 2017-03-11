<?php

namespace ArgentCrusade\Selectel\CloudStorage\Traits;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException;

trait MetaData
{
    /**
     * Returns specific object data.
     *
     * @param string $key
     * @param mixed  $default = null
     *
     * @return mixed
     */
    abstract protected function objectData($key, $default = null);

    /**
     * API Client.
     *
     * @return \ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract
     */
    abstract public function apiClient();

    /**
     * Returns object meta type.
     *
     * @return string
     */
    abstract public function objectMetaType();

    /**
     * Returns absolute path to current object.
     *
     * @return string
     */
    abstract protected function absolutePath($path = '');

    /**
     * Extracts meta data from Object's response headers.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return array
     */
    protected function extractMetaData(ResponseInterface $response)
    {
        $headers = $this->findMetaHeaders($response);

        if (!count($headers)) {
            return [];
        }

        $metaData = [];

        foreach ($headers as $header) {
            $metaData[$header] = $response->getHeaderLine($header);
        }

        return $metaData;
    }

    /**
     * Filters meta headers from response.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return array
     */
    protected function findMetaHeaders(ResponseInterface $response)
    {
        $headerNames = array_keys($response->getHeaders());
        $metaType = $this->objectMetaType();

        return array_filter($headerNames, function ($header) use ($metaType) {
            return strpos($header, 'X-'.$metaType.'-Meta') !== false;
        });
    }

    /**
     * Sanitizes meta data name.
     *
     * @param string $name Meta name
     *
     * @return string
     */
    protected function sanitizeMetaName($name)
    {
        $metaType = $this->objectMetaType();

        return 'X-'.$metaType.'-Meta-'.str_replace('X-'.$metaType.'-Meta-', '', $name);
    }

    /**
     * Checks if given meta data exists.
     *
     * @param string $name Meta name
     *
     * @return bool
     */
    public function hasMeta($name)
    {
        $meta = $this->objectData('meta', []);

        return isset($meta[$this->sanitizeMetaName($name)]);
    }

    /**
     * Returns meta data.
     *
     * @param string $name Meta name
     *
     * @throws \InvalidArgumentException
     *
     * @return mixed
     */
    public function getMeta($name)
    {
        if (!$this->hasMeta($name)) {
            throw new InvalidArgumentException('Meta data with name "'.$name.'" does not exists.');
        }

        $meta = $this->objectData('meta', []);

        return $meta[$this->sanitizeMetaName($name)];
    }

    /**
     * Updates object meta data.
     *
     * @param array $meta Array of meta data (without "X-{Object}-Meta" prefixes).
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\ApiRequestFailedException
     *
     * @return bool
     */
    public function setMeta(array $meta)
    {
        $headers = [];
        $metaType = $this->objectMetaType();

        // We will replace any 'X-{Object}-Meta-' prefixes in meta name
        // and prepend final header names with same prefix so API will
        // receive sanitized headers and won't produce any errors.

        foreach ($meta as $name => $value) {
            $key = str_replace('X-'.$metaType.'-Meta-', '', $name);
            $headers['X-'.$metaType.'-Meta-'.$key] = $value;
        }

        $response = $this->apiClient()->request('POST', $this->absolutePath(), ['headers' => $headers]);

        if ($response->getStatusCode() !== 202) {
            throw new ApiRequestFailedException('Unable to update container meta data.', $response->getStatusCode());
        }

        return true;
    }
}
