<?php

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Routing\Router;
use Cake\Utility\Hash;

class ApiHandler
{
    /**
     * @var \Cake\Http\Client HTTP client to send request from.
     */
    protected $_client;

    protected $_baseUrl;

    protected $_storeId;

    protected $_environmentId;

    protected $_cacheConfig = 'default';

    protected $_refresh_callback_url;

    public function __construct(array $config = [])
    {
        $this->_client = new Client([
            'headers' => ['scctoken' => Configure::read('Security.appServerApiToken'), 'Accept' => 'application/json'],
            'ssl_verify_peer' => false,
        ]);
        $this->_baseUrl = Configure::read('Urls.apps');
        $this->_refresh_callback_url = Router::url(Configure::read('Urls.refreshCallback', '/pages/clear-cache'), true);
        $environment = $this->getEnvironment();
        $store = $this->getStore();
        $this->_storeId = $store['id'];
        $this->_environmentId = $environment['id'];
    }

    /**
     * Gets data from the cache, if data doesn't exist, sends a get($url, $options) request and caches the data.
     *
     * @param string $cacheKey The cache key.
     * @param string $url The url or path you want to request.
     * @param array $data The query data you want to send.
     * @return array
     */
    public function cacheOrGet(string $cacheKey, string $url, $data = [])
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
    public function get(string $url, $data = [], array $options = [])
    {
        $url = $this->_baseUrl . $url;
        $response = $this->_client->get($url, $data, $options);

        return $this->parseResponse($response);
    }

    /**
     * @param \Cake\Http\Client\Response $response
     * @return \Cake\Http\Client\Response
     */
    protected function parseResponse($response)
    {
        $data = $response->getJson();
        $code = $data['code'] ?? $response->getStatusCode();

        if ($code < 200 || $code >= 400) {
            throw new Exception($data['message'], $code);
        }

        return $response;
    }

    /**
     * Do a POST request.
     *
     * @param string $url The url or path you want to request.
     * @param mixed $data The post data you want to send.
     * @param array $options Additional options for the request.
     * @return \Cake\Http\Client\Response
     */
    public function post(string $url, $data = [], array $options = [])
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
    public function put(string $url, $data = [], array $options = [])
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
    public function delete(string $url, $data = [], array $options = [])
    {
        $url = $this->_baseUrl . $url;
        $response = $this->_client->delete($url, $data, $options);

        return $this->parseResponse($response);
    }

    /**
     * Gets the CSS for the current store and environment.
     *
     * @return string|null
     */
    public function getCss()
    {
        $cacheKey = 'css';
        $url = "/admin/environments/$this->_environmentId/stores/$this->_storeId/css";
        $queryParams = ['refresh_callback_url' => "$this->_refresh_callback_url/css"];

        return $this->cacheOrGet($cacheKey, $url, $queryParams)['css'] ?? null;
    }

    /**
     * Returns the current environment.
     *
     * @return array|null
     */
    public function getEnvironment()
    {
        $storeIpMap = $this->getStoreIpMap();

        return $storeIpMap['environment'] ?? null;
    }

    /**
     * Gets the file info from the API using its id.
     *
     * @param int|string $id
     * @return array|null
     */
    public function getFile($id)
    {
        $cacheKey = "file_$id";
        $url = "/admin/files/$id";
        $queryParams = ['refresh_callback_url' => "/pages/clear-cache/files/$id"];

        return $this->cacheOrGet($cacheKey, $url, $queryParams)['file'] ?? null;
    }

    /**
     * Gets the content of the file.
     *
     * @param int|string $id
     * @param int|string $width
     * @param int|string $height
     * @param bool $refresh
     * @return string
     */
    public function getFileContent($id, $width = null, $height = null, $refresh = false)
    {
        $filePath = FILES . $this->getFileName($id, $width, $height);

        if (!$refresh && file_exists($filePath)) {
            return file_get_contents($filePath);
        }

        $url = $width || $height ? "/admin/files/resize/$id/$width/$height" : "/admin/files/open/$id";
        $result = $this->get($url)->getStringBody();

        if (!is_dir(FILES)) {
            mkdir(FILES, 0775);
        }

        file_put_contents($filePath, $result);

        return $result;
    }

    protected function getFileName($id, $width = null, $height = null)
    {
        $file = $this->getFile($id);
        $allowedSizes = $this->getOptions()['files']['resize'];
        $allowedSizes = explode(',', $allowedSizes);
        if ($width) {
            foreach ($allowedSizes as $size) {
                if ($width <= $size) {
                    $width = (int)$size;
                    break;
                }
            }
        }
        if ($height) {
            foreach ($allowedSizes as $size) {
                if ($height <= $size) {
                    $height = (int)$size;
                    break;
                }
            }
        }

        $fileName = $id;
        if ($width) {
            $fileName = "{$fileName}_$width";

            if ($height) {
                $fileName = "{$fileName}x$height";
            }
        }

        $extension = get_file_extension_from_mime_type($file['mime_type']['name']);
        $fileName = $fileName . $extension;

        return $fileName;
    }

    public function getFileUrl($id, $width = null, $height = null)
    {
        return Router::url('/files/' . $this->getFileName($id, $width, $height));
    }

    /**
     * Gets the JavaScript for the current store and environment.
     *
     * @return string|null
     */
    public function getJavascript()
    {
        $cacheKey = 'js';
        $url = "/admin/environments/$this->_environmentId/stores/$this->_storeId/javascript";
        $queryParams = ['refresh_callback_url' => "$this->_refresh_callback_url/javascript"];

        return $this->cacheOrGet($cacheKey, $url, $queryParams)['javascript'] ?? null;
    }

    /**
     * Gets the navigation menus for the current store and environment.
     *
     * @return array
     */
    public function getNavMenus()
    {
        $cacheKey = 'nav_menus';
        $url = "/admin/environments/$this->_environmentId/stores/$this->_storeId/menus";
        $queryParams = ['refresh_callback_url' => "$this->_refresh_callback_url/menus"];
        $menus = $this->cacheOrGet($cacheKey, $url, $queryParams)['menus'] ?? [];
        $menus = Hash::combine($menus, '{n}.type', '{n}.menu_links');

        return $menus;
    }

    /**
     * Gets the options for the current store and environment.
     *
     * @return array
     */
    public function getOptions()
    {
        $cacheKey = 'options';
        $url = "/admin/environments/$this->_environmentId/stores/$this->_storeId/options";
        $queryParams = ['refresh_callback_url' => "$this->_refresh_callback_url/options"];
        $options = $this->cacheOrGet($cacheKey, $url, $queryParams)['options'] ?? [];
        $options = Hash::combine($options, '{n}.name', '{n}.value');
        $options = Hash::expand($options);

        return $options;
    }

    /**
     * Gets the page info for the current store, environment and the given url.
     *
     * @param string $url
     * @return mixed|null
     */
    public function getPage(string $url)
    {
        $response = $this->get('/cms/pages', [
            'store_id' => $this->_storeId,
            'environment_id' => $this->_environmentId,
            'url' => ltrim($url, '/'),
        ]);

        return $response->getJson()['data']['page'] ?? null;
    }

    /**
     * Gets the sitemap for the current store and environment.
     *
     * @return array|null
     */
    public function getSiteMap()
    {
        $cacheKey = 'sitemap';
        $url = "/admin/environments/$this->_environmentId/stores/$this->_storeId/sitemap";
        $queryParams = ['refresh_callback_url' => "$this->_refresh_callback_url/sitemap"];

        return $this->cacheOrGet($cacheKey, $url, $queryParams)['sitemap'] ?? null;
    }

    /**
     * Returns the current store.
     *
     * @return array|null
     */
    public function getStore()
    {
        $storeIpMap = $this->getStoreIpMap();

        return $storeIpMap['store'] ?? null;
    }

    /**
     * Returns the current store IP map based on current IP.
     *
     * @return array|null
     */
    public function getStoreIpMap()
    {
        $cacheKey = 'store_ip_map';
        $url = '/admin/store-ip-maps/current-ip';
        $queryParams = ['refresh_callback_url' => "$this->_refresh_callback_url/store-ip-map"];

        return $this->cacheOrGet($cacheKey, $url, $queryParams)['storeIpMap'] ?? null;
    }

    /**
     * Replaces the {{xxx}} placeholders in the html with the content.
     *
     * @param string $html
     * @param string $content
     * @return string
     */
    public function render(string $html, string $content)
    {
        preg_match_all('/{{\w+:?[\w\/\-.]+:?[\w\/\-.]+}}/', $html, $matches);

        foreach ($matches[0] ?? [] as $match) {
            $matchContent = trim($match, '{}');
            $components = explode(':', $matchContent, 2);
            [$placeholderType, $id] = array_pad($components, 2, null);

            switch ($placeholderType) {
                case 'content':
                    $replacement = $content;
                    break;
                case 'file':
                    $replacement = Router::url("/files/download/$id");
                    break;
                case 'image':
                    $replacement = $this->getFileUrl($id);
                    break;
                case 'url':
                    $replacement = Router::url($id);
                    break;
                default:
                    break;
            }

            if (isset($replacement)) {
                $html = str_replace($match, $replacement, $html);
            }
        }

        return $html;
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
    public function clearCache(string $cacheKey)
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
