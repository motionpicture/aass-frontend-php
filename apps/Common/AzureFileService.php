<?php
namespace Aass\Common;

class AzureFileService
{
    const BASE_DNS_NAME = 'file.core.windows.net';
    const API_LATEST_VERSION = '2015-02-21';

    private $accountName;
    private $accountKey;
    private $logger;

    public function __construct($accountName, $accountKey)
    {
        $this->accountName = $accountName;
        $this->accountKey = $accountKey;
        $this->logger = \Phalcon\DI::getDefault()->get('logger');
    }

    public function copyFromUrl($sourceUrl, $to)
    {
        $url = "https://{$this->accountName}." . self::BASE_DNS_NAME . "/{$to}";
        $httpMethod = 'PUT';
        $dateTime = new \DateTime('now', new \DateTimeZone('GMT'));
        $dateTimeString = $dateTime->format('D, d M Y H:i:s T');
        $headers = [
            'x-ms-version' => self::API_LATEST_VERSION,
            'x-ms-date' => $dateTimeString,
            'x-ms-copy-source' => $sourceUrl,
        ];
        $queryParams = [];
        $authorizationHeader = $this->getAuthorizationHeader($headers, $url, $queryParams, $httpMethod);

        $headers = array_merge($headers, [
            'Authorization' => $authorizationHeader,
            'Content-Length' => 0,
            'Date' => $dateTimeString,
        ]);

        $httpHeaders = [];
        foreach ($headers as $header => $value) {
            $httpHeaders[] = "{$header}: {$value}";
        }
        $options = [
            CURLOPT_CUSTOMREQUEST => $httpMethod,
            CURLOPT_HTTPHEADER => $httpHeaders,
        ];

        list($body, $info) = $this->send($url, $options);

        return (isset($info['http_code']) && $info['http_code'] == '202');
    }

    public function getFileProperties($file)
    {
        $url = "https://{$this->accountName}." . self::BASE_DNS_NAME . "/{$file}";
        $httpMethod = 'HEAD';
        $dateTime = new \DateTime('now', new \DateTimeZone('GMT'));
        $dateTimeString = $dateTime->format('D, d M Y H:i:s T');
        $headers = [
            'x-ms-version' => self::API_LATEST_VERSION,
            'x-ms-date' => $dateTimeString,
        ];
        $queryParams = [];
        $authorizationHeader = $this->getAuthorizationHeader($headers, $url, $queryParams, $httpMethod);

        $headers = array_merge($headers, [
            'Authorization' => $authorizationHeader,
            'Content-Length' => 0,
            'Date' => $dateTimeString,
        ]);

        $httpHeaders = [];
        foreach ($headers as $header => $value) {
            $httpHeaders[] = "{$header}: {$value}";
        }
        $options = [
            CURLOPT_CUSTOMREQUEST => $httpMethod,
            CURLOPT_HTTPHEADER => $httpHeaders,
            CURLOPT_NOBODY => true
        ];

        list($body, $info) = $this->send($url, $options);

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

    private function send($url, $options)
    {
        $defaultOptions = [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HEADER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ];
        $options = $defaultOptions + $options;

        $curl = curl_init($url);
        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $info   = curl_getinfo($curl);
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if (CURLE_OK !== $errno) {
            throw new \RuntimeException($error, $errno);
        }

        $this->logger->addInfo('body:' . var_export($body, true));
        $this->logger->addInfo('info:' . var_export($info, true));
        return [$body, $info];
    }

    private function getAuthorizationHeader($headers, $url, $queryParams, $httpMethod)
    {
        $stringToSign = [
            strtoupper($httpMethod), // VERB
            '', // Content-Encoding
            '', // Content-Language
//             0, // Content-Length
            '', // Content-Length
            '', // Content-MD5
            '', // Content-Type
            '', // Date
            '', // If-Modified-Since
            '', // If-Match
            '', // If-None-Match
            '', // If-Unmodified-Since
            '', // Range
        ];

        $canonicalizedHeaders = $this->computeCanonicalizedHeaders($headers);
        $canonicalizedResource = $this->computeCanonicalizedResource($url, $queryParams);

        $stringToSign[] = implode("\n", $canonicalizedHeaders);
        $stringToSign[] = $canonicalizedResource;
        $stringToSign = implode("\n", $stringToSign);

        return "SharedKey {$this->accountName}:"
                . base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($this->accountKey), true));
    }

    private function computeCanonicalizedHeaders($headers)
    {
        $canonicalizedHeaders = [];
        $normalizedHeaders    = [];

        foreach ($headers as $header => $value) {
            // Convert header to lower case.
            $header = strtolower($header);

            // Unfold the string by replacing any breaking white space
            // (meaning what splits the headers, which is \r\n) with a single
            // space.
            $value = str_replace("\r\n", ' ', $value);

            // Trim any white space around the colon in the header.
            $value  = ltrim($value);
            $header = rtrim($header);

            $normalizedHeaders[$header] = $value;
        }

        // Sort the headers lexicographically by header name, in ascending order.
        // Note that each header may appear only once in the string.
        ksort($normalizedHeaders);

        foreach ($normalizedHeaders as $key => $value) {
            $canonicalizedHeaders[] = $key . ':' . $value;
        }

        return $canonicalizedHeaders;
    }

    private function computeCanonicalizedResource($url, $queryParams)
    {
        $queryParams = array_change_key_case($queryParams);

        $canonicalizedResource = "/{$this->accountName}";

        $canonicalizedResource .= parse_url($url, PHP_URL_PATH);

        if (count($queryParams) > 0) {
            ksort($queryParams);
        }

        foreach ($queryParams as $key => $value) {
            // Grouping query parameters
            $values = explode(',', $value);
            sort($values);
            $separated = implode(',', $values);
            $canonicalizedResource .= "\n{$key}:{$separated}";
        }

        return $canonicalizedResource;
    }
}