<?php

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Routing\Router;
use Cake\Http\Exception\HttpException;

abstract class ApiHandler
{
    /**
     * @var Client HTTP client to send request from.
     */
    protected $_client;

    protected $_baseUrl;

    protected $_cacheConfig = 'default';

    protected $_refresh_callback_url;

    protected $_tokenName;

    public function __construct(array $config = [])
    {
        $this->_client = new Client([
            'headers' => ['scctoken' => Configure::read("Security.$this->_tokenName"), 'Accept' => 'application/json'],
            'ssl_verify_peer' => false,
        ]);
        $this->_refresh_callback_url = Router::url(Configure::read('Urls.refreshCallback', '/pages/clear-cache'), true);
    }

    /**
     * @param \Cake\Http\Client\Response $response
     * @return \Cake\Http\Client\Response
     */
    protected function parseResponse($response): Client\Response
    {
        $data = $response->getJson();
        $code = $data['code'] ?? $response->getStatusCode();

        if ($code < 200 || $code >= 400) {
            throw new HttpException($data['message'], $code);
        }

        return $response;
    }

    /**
     * Gets data from the cache, if data doesn't exist, sends a get($url, $options) request and caches the data.
     *
     * @param string $cacheKey The cache key.
     * @param string $url The url or path you want to request.
     * @param array $data The query data you want to send.
     * @return array
     */
    public function cacheOrGet(string $cacheKey, string $url, array $data = []): array
    {
        $result = Cache::read($cacheKey, $this->_cacheConfig);

        if (!empty($result)) {
            return $result;
        }

        $response = $this->get($url, $data);
        $result = $response->getJson()['data'];
        $etag = $response->getHeaderLine('Etag') ?: (string)crc32($response->getStringBody());
        $etag = trim($etag, '"');

        Cache::write("${cacheKey}_etag", $etag, $this->_cacheConfig);
        Cache::write($cacheKey, $result, $this->_cacheConfig);

        return $result;
    }

    /**
     * Do a GET request.
     *
     * The $data argument supports a special `_content` key
     * for providing a request body in a GET request. This is
     * generally not used, but services like ElasticSearch use
     * this feature.
     *
     * @param string $url The url or path you want to request.
     * @param array|string $data The query data you want to send.
     * @param array $options Additional options for the request.
     * @return \Cake\Http\Client\Response
     */
    public function get(string $url, $data = [], array $options = []): Client\Response
    {
        $url = $this->_baseUrl . $url;
        $response = $this->_client->get($url, $data, $options);

        return $this->parseResponse($response);
    }

    /**
     * Do a POST request.
     *
     * @param string $url The url or path you want to request.
     * @param mixed $data The post data you want to send.
     * @param array $options Additional options for the request.
     * @return \Cake\Http\Client\Response
     */
    public function post(string $url, $data = [], array $options = []): Client\Response
    {
        $url = $this->_baseUrl . $url;
        $response = $this->_client->post($url, $data, $options);

        return $this->parseResponse($response);
    }

    /**
     * Do a PUT request.
     *
     * @param string $url The url or path you want to request.
     * @param mixed $data The request data you want to send.
     * @param array $options Additional options for the request.
     * @return \Cake\Http\Client\Response
     */
    public function put(string $url, $data = [], array $options = []): Client\Response
    {
        $url = $this->_baseUrl . $url;
        $response = $this->_client->put($url, $data, $options);

        return $this->parseResponse($response);
    }

    /**
     * Do a DELETE request.
     *
     * @param string $url The url or path you want to request.
     * @param mixed $data The request data you want to send.
     * @param array $options Additional options for the request.
     * @return \Cake\Http\Client\Response
     */
    public function delete(string $url, $data = [], array $options = []): Client\Response
    {
        $url = $this->_baseUrl . $url;
        $response = $this->_client->delete($url, $data, $options);

        return $this->parseResponse($response);
    }

    public function readCache(string $cacheKey)
    {
        return Cache::read("${cacheKey}", $this->_cacheConfig);
    }

    /**
     * Refreshes the cache and etag cache (if exists) for the given url.
     *
     * @param string $cacheKey
     * @return bool
     */
    public function clearCache(string $cacheKey): bool
    {
        $success = true;
        if (Cache::read($cacheKey, $this->_cacheConfig)) {
            $success = Cache::delete($cacheKey, $this->_cacheConfig);
        }

        $etagCacheKey = "${cacheKey}_etag";
        if (Cache::read($etagCacheKey, $this->_cacheConfig)) {
            $success = $success && Cache::delete($etagCacheKey, $this->_cacheConfig);
        }

        return $success;
    }
}
