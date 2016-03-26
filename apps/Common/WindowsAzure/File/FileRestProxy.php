<?php
namespace Aass\Common\WindowsAzure\File;
use Aass\Common\WindowsAzure\Common\ServiceRestProxy;

class FileRestProxy extends ServiceRestProxy
{
    public function copyFromUrl($sourceUrl, $to)
    {
        $path = "/{$to}";
        $method = 'PUT';
        $headers = [
            'x-ms-copy-source' => $sourceUrl,
        ];
        $queryParams = [];

        list($body, $info) = $this->send($method, $headers, $queryParams, [], $path, $null);

        return (isset($info['http_code']) && $info['http_code'] == '202');
    }

    public function getFileProperties($file)
    {
        $path = "/{$file}";
        $method = 'HEAD';
        $headers = [];
        $queryParams = [];

        list($body, $info) = $this->send($method, $headers, $queryParams, [], $path, null);

        // レスポンスヘッダーを取り出す
        $responseHeaders = null;
        if (isset($info['http_code']) && $info['http_code'] == '200') {
            $responseHeaderString = substr($body, 0 , $info['header_size']);
            $responseHeadersWithColon = explode("\n", $responseHeaderString);

            $responseHeaders = [];
            foreach ($responseHeadersWithColon as $line) {
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $responseHeaders[$key] = trim($value);
                }
            }
        }

        return $responseHeaders;
    }
}
