<?php
namespace Aass\Common\WindowsAzure\Common;

class ServicesBuilder
{
    const BLOB_BASE_DNS_NAME = 'blob.core.windows.net';
    const FILE_BASE_DNS_NAME = 'file.core.windows.net';

    /**
     * @var ServicesBuilder
     */
    private static $_instance = null;

    public function createBlobService($acoountName, $accountKey)
    {
        return new \Aass\Common\WindowsAzure\Blob\BlobRestProxy(
            "https://{$acoountName}." . self::BLOB_BASE_DNS_NAME,
            $acoountName,
            $accountKey
        );
    }

    public function createFileService($acoountName, $accountKey)
    {
        return new \Aass\Common\WindowsAzure\File\FileRestProxy(
            "https://{$acoountName}." . self::FILE_BASE_DNS_NAME,
            $acoountName,
            $accountKey
        );
    }

    /**
     * Gets the static instance of this class.
     *
     * @return ServicesBuilder
     */
    public static function getInstance()
    {
        if (!isset(self::$_instance)) {
            self::$_instance = new ServicesBuilder();
        }

        return self::$_instance;
    }
}
