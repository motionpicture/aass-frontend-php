<?php
namespace Aass\Common\WindowsAzure\Blob;
use Aass\Common\WindowsAzure\Common\ServiceRestProxy;

class BlobRestProxy extends ServiceRestProxy
{
    /**
     * @var int Defaults to 32MB
     */
    private $_SingleBlobUploadThresholdInBytes = 33554432 ;

    /**
     * Get the value for SingleBlobUploadThresholdInBytes
     *
     * @return int
     */
    public function getSingleBlobUploadThresholdInBytes()
    {
        return $this->_SingleBlobUploadThresholdInBytes;
    }

    /**
     * Set the value for SingleBlobUploadThresholdInBytes, Max 64MB
     *
     * @param int $val The max size to send as a single blob block
     *
     * @return none
     */
    public function setSingleBlobUploadThresholdInBytes($val)
    {
        if ($val > 67108864) {
            // What should the proper action here be?
            $val = 67108864;
        } elseif ($val < 1) {
            // another spot that could use looking at
            $val = 33554432;
        }
        $this->_SingleBlobUploadThresholdInBytes = $val;
    }

    public function listContainers($options = null)
    {
        $path = '/';
        $method = 'GET';
        $headers = [];
        $queryParams = [
            'comp' => 'list',
            'include' => 'metadata'
        ];

        list($body, $info) = $this->send($method, $headers, $queryParams, [], $path, null);

        // レスポンスヘッダーを取り出す
        $responseHeaders = null;
        $responseHeaderBody = null;
        $containers = null;
        if (isset($info['http_code']) && $info['http_code'] == '200') {
            $responseHeaderString = substr($body, 0 , $info['header_size']);
            $responseHeaderBody = substr($body, $info['header_size']);
            $responseHeadersWithColon = explode("\n", $responseHeaderString);

            $responseHeaders = [];
            foreach ($responseHeadersWithColon as $line) {
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $responseHeaders[$key] = trim($value);
                }
            }
        }

        if (!is_null($responseHeaderBody)) {
            $containers = new \SimpleXMLElement($responseHeaderBody);
        }
        return $containers;
    }

    public function putBlock($container, $blob, $blockId, $body)
    {
        $path = "/{$container}/{$blob}";
        $method = 'PUT';
        $headers = [];
        $queryParams = [
            'comp' => 'block',
            'blockid' => base64_encode($blockId),
        ];

        list($body, $info) = $this->send($method, $headers, $queryParams, [], $path, $body);

        return (isset($info['http_code']) && $info['http_code'] == '201');
    }

    public function putBlockList($container, $blob, $blockIds)
    {
        $path = "/{$container}/{$blob}";
        $method = 'PUT';
        $headers = [];
        $queryParams = [
            'comp' => 'blocklist',
        ];

        $body = '<?xml version="1.0" encoding="utf-8"?><BlockList>';
        foreach ($blockIds as $blockId) {
            $body .= '<Uncommitted>' . base64_encode($blockId) . '</Uncommitted>';
        }
        $body .= '</BlockList>';
        $this->logger->addDebug("body:{$body}");

        list($body, $info) = $this->send($method, $headers, $queryParams, [], $path, $body);

        return (isset($info['http_code']) && $info['http_code'] == '201');
    }
}
