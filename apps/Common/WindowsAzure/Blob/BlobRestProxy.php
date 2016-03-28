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

    /**
     * List Containers 操作には、指定されたアカウントのコンテナーの一覧が返されます。
     */
    public function listContainers()
    {
        $path = '/';
        $method = 'GET';
        $headers = [];
        $queryParams = [
            'comp' => 'list',
            'include' => 'metadata'
        ];
        $statusCode = 200;

        $body = $this->send($method, $headers, $queryParams, [], $path, null, $statusCode);

        return (!is_null($body)) ? new \SimpleXMLElement($body) : $containers;
    }

    /**
     * 新しい BLOB を作成するか、コンテナー内の既存の BLOB を置換します。
     * 
     * @param string $container
     * @param string $blob
     * @param string $body
     */
    public function putBlob($container, $blob, $body)
    {
        $path = "/{$container}/{$blob}";
        $method = 'PUT';
        $headers = [
            'x-ms-blob-type' => 'BlockBlob'
        ];
        $queryParams = [];
        $statusCode = 201;

        $this->send($method, $headers, $queryParams, [], $path, $body, $statusCode);
    }

    /**
     * コミットする新しいブロックをブロック BLOB の一部として作成します。
     * 
     * @param string $container
     * @param string $blob
     * @param string $blockId
     * @param string $body
     */
    public function putBlock($container, $blob, $blockId, $body)
    {
        $path = "/{$container}/{$blob}";
        $method = 'PUT';
        $headers = [];
        $queryParams = [
            'comp' => 'block',
            'blockid' => base64_encode($blockId),
        ];
        $statusCode = 201;

        $this->send($method, $headers, $queryParams, [], $path, $body, $statusCode);
    }

    /**
     * ブロック BLOB を構成するブロック ID のセットを指定することで、BLOB をコミットします。
     * 
     * @param string $container
     * @param string $blob
     * @param array $blockIds
     */
    public function putBlockList($container, $blob, $blockIds)
    {
        $path = "/{$container}/{$blob}";
        $method = 'PUT';
        $headers = [];
        $queryParams = [
            'comp' => 'blocklist',
        ];
        $statusCode = 201;

        $body = '<?xml version="1.0" encoding="utf-8"?><BlockList>';
        foreach ($blockIds as $blockId) {
            $body .= '<Uncommitted>' . base64_encode($blockId) . '</Uncommitted>';
        }
        $body .= '</BlockList>';
        $this->logger->addDebug("body:{$body}");

        $this->send($method, $headers, $queryParams, [], $path, $body, $statusCode);
    }
}