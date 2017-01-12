<?php

namespace ArgentCrusade\Selectel\CloudStorage;

use ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract;
use ArgentCrusade\Selectel\CloudStorage\Contracts\FileUploaderContract;
use ArgentCrusade\Selectel\CloudStorage\Exceptions\UploadFailedException;

class FileUploader implements FileUploaderContract
{
    /**
     * Upload file from string or stream resource.
     *
     * @param \ArgentCrusade\Selectel\CloudStorage\Contracts\Api\ApiClientContract $api
     * @param string                                                               $path           Remote path.
     * @param string|resource                                                      $body           File contents.
     * @param array                                                                $params         = [] Upload params.
     * @param bool                                                                 $verifyChecksum = true
     *
     * @throws \ArgentCrusade\Selectel\CloudStorage\Exceptions\UploadFailedException
     *
     * @return string
     */
    public function upload(ApiClientContract $api, $path, $body, array $params = [], $verifyChecksum = true)
    {
        $response = $api->request('PUT', $path, [
            'headers' => $this->convertUploadParamsToHeaders($body, $params, $verifyChecksum),
            'body' => $body,
        ]);

        if ($response->getStatusCode() !== 201) {
            throw new UploadFailedException('Unable to upload file.', $response->getStatusCode());
        }

        return $response->getHeaderLine('ETag');
    }

    /**
     * Parses upload parameters and assigns them to appropriate HTTP headers.
     *
     * @param string|resource $body           = null
     * @param array           $params         = []
     * @param bool            $verifyChecksum = true
     *
     * @return array
     */
    protected function convertUploadParamsToHeaders($body = null, array $params = [], $verifyChecksum = true)
    {
        $headers = [];

        if ($verifyChecksum) {
            $headers['ETag'] = md5($body);
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
}
