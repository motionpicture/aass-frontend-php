<?php
namespace Aass\Common\WindowsAzure\File;
use Aass\Common\WindowsAzure\Common\ServiceRestProxy;

class FileRestProxy extends ServiceRestProxy
{
    /**
     * このストレージ アカウントに対象のファイルにコピー元 blob またはファイルをコピーします。
     * 
     * @param string $source ソース ファイルまたは blob の URL を指定の長さは最大で 2 KB です。
     * @param string $to     https://myaccount.file.core.windows.net/{$to}
     */
    public function copyFile($source, $to)
    {
        $path = "/{$to}";
        $method = 'PUT';
        $headers = [
            'x-ms-copy-source' => $source,
        ];
        $queryParams = [];
        $statusCode = 202;

        $this->send($method, $headers, $queryParams, [], $path, null, $statusCode);
    }

    /**
     * ファイルのすべてのシステム プロパティとユーザー定義メタデータを返します。
     * 
     * @param string $file https://myaccount.file.core.windows.net/{$file}
     */
    public function getFileProperties($file)
    {
        $path = "/{$file}";
        $method = 'HEAD';
        $headers = [];
        $queryParams = [];
        $statusCode = 200;

        $result = $this->send($method, $headers, $queryParams, [], $path, null, 200);

        return $result;
    }
}