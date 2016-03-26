<?php
namespace Aass\Common\WindowsAzure\Common;

class ServiceRestProxy
{
    private $uri;
    private $accountName;
    private $accountKey;
    protected $logger;

    const API_LATEST_VERSION = '2015-04-05';

    /**
     * Initializes new ServiceRestProxy object.
     *
     * @param string      $uri            The storage account uri.
     * @param string      $accountName    The name of the account.
     */
    public function __construct($uri, $accountName, $accountKey)
    {
        $this->uri = $uri;
        $this->accountName = $accountName;
        $this->accountKey = $accountKey;
        $this->logger = \Phalcon\DI::getDefault()->get('logger');
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function getAccountName()
    {
        return $this->accountName;
    }

    public function getAccountKey()
    {
        return $this->accountKey;
    }

    protected function send($method, $headers, $queryParams, $postParameters, $path, $body = null)
    {
        $url = "{$this->uri}{$path}?" . http_build_query($queryParams);

        $dateTime = new \DateTime('now', new \DateTimeZone('GMT'));
        $dateTimeString = $dateTime->format('D, d M Y H:i:s T');
        $defaultHeaders = [
            'x-ms-version' => self::API_LATEST_VERSION,
            'x-ms-date' => $dateTimeString,
        ];

        $headers = array_merge($headers, $defaultHeaders);

        // Authorizationヘッダーを作成
        $contentLength = (!is_null($body)) ? strlen($body) : 0;
        $contentType = (!is_null($body)) ? 'application/x-www-form-urlencoded' : '';
        $authorizationHeader = $this->getAuthorizationHeader($headers, $url, $queryParams, $method, $contentLength, $contentType);

        $headers = array_merge($headers, [
            'Authorization' => $authorizationHeader,
            'Content-Length' => $contentLength,
            'Date' => $dateTimeString,
        ]);

        $httpHeaders = [];
        foreach ($headers as $header => $value) {
            $httpHeaders[] = "{$header}: {$value}";
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $httpHeaders,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HEADER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ];
        if (!is_null($body)) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        if ($method == 'HEAD') {
            $options[CURLOPT_NOBODY] = true;
        }

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

    private function getAuthorizationHeader($headers, $url, $queryParams, $httpMethod, $contentLength = 0, $contentType = '')
    {
        $stringToSign = [
            strtoupper($httpMethod), // VERB
            '', // Content-Encoding
            '', // Content-Language
//             0, // Content-Length
            ($contentLength > 0) ? $contentLength : '', // Content-Length
            '', // Content-MD5
            $contentType, // Content-Type
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
        $this->logger->addDebug('stringToSign:' . var_export($stringToSign, true));
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
