<?php
namespace Aass\Common\WindowsAzure\Common;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class ServiceRestProxy
{
    private $uri;
    private $accountName;
    private $accountKey;
    protected $logger;

    const API_LATEST_VERSION = '2015-04-05';
    const URL_ENCODED_CONTENT_TYPE = 'application/x-www-form-urlencoded';

    /**
     * Initializes new ServiceRestProxy object.
     *
     * @param string $uri
     * @param string $accountName
     * @param string $accountKey
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

    /**
     * Sends HTTP request with the specified parameters.
     *
     * @param string $method         HTTP method used in the request
     * @param array  $headers        HTTP headers.
     * @param array  $queryParams    URL query parameters.
     * @param array  $postParameters The HTTP POST parameters.
     * @param string $path           URL path
     * @param string $body           Request body
     * @param int    $statusCode     Expected status code received in the response
     *
     * @return string
     */
    protected function send($method, $headers, $queryParams, $postParameters, $path, $body = null, $statusCode = 200)
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
        $contentType = (!is_null($body)) ? self::URL_ENCODED_CONTENT_TYPE : '';
        $authorizationHeader = $this->getAuthorizationHeader($headers, $url, $queryParams, $method, $contentLength, $contentType);

        $headers = array_merge($headers, [
            'Authorization' => $authorizationHeader,
            'Content-Length' => $contentLength,
            'Content-Type' => $contentType,
            'Date' => $dateTimeString,
        ]);

        $options = [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ];
        if (!is_null($body)) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        $client = new Client();
        $request = new Request($method, $url, $headers);
        $response = $client->send($request, [
            'curl' => $options
        ]);

        $this->logger->addDebug("status code:{$response->getStatusCode()}");
        $this->logger->addDebug("reason phrase:{$response->getReasonPhrase()}");
        $this->logger->addDebug('headers:' . var_export($response->getHeaders(), true));
        $this->logger->addDebug('body:' . var_export($response->getBody(), true));

        self::throwIfError(
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $response->getBody(),
            [$statusCode]
        );

        return ($method == 'HEAD') ? $response->getHeaders() : $response->getBody();
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

    /**
     * Throws LogicException if the recieved status code is not expected.
     * 
     * @param string $actual   The received status code.
     * @param string $reason   The reason phrase.
     * @param string $message  The detailed message (if any).
     * @param array  $expected The expected status codes.
     * 
     * @return none
     * @throws LogicException
     */
    public static function throwIfError($actual, $reason, $message, $expected)
    {
        if (!in_array($actual, $expected)) {
            throw new \LogicException(sprintf("Fail:\nCode: %s\nValue: %s\ndetails (if any): %s.", $actual, $reason, $message));
        }
    }
}