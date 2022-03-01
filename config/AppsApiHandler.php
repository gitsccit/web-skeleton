<?php

use Cake\Core\Configure;
use Cake\Routing\Router;
use Cake\Utility\Hash;

class AppsApiHandler extends ApiHandler
{
    protected $_tokenName = 'appServerApiToken';

    protected $_storeId;

    protected $_environmentId;

    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->_baseUrl = Configure::read('Urls.apps');
        $environment = $this->getEnvironment();
        $store = $this->getStore();
        $this->_storeId = $store['id'];
        $this->_environmentId = $environment['id'];
    }

    /**
     * Gets the CSS for the current store and environment.
     *
     * @return string|null
     */
    public function getCss(): ?string
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
    public function getEnvironment(): ?array
    {
        $storeIpMap = $this->getStoreIpMap();

        return $storeIpMap['environment'] ?? null;
    }

    /**
     * Gets the JavaScript for the current store and environment.
     *
     * @return string|null
     */
    public function getJavascript(): ?string
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
    public function getNavMenus(): array
    {
        $cacheKey = 'nav_menus';
        $url = "/admin/environments/$this->_environmentId/stores/$this->_storeId/menus";
        $queryParams = ['refresh_callback_url' => "$this->_refresh_callback_url/menus"];
        $menus = $this->cacheOrGet($cacheKey, $url, $queryParams)['menus'] ?? [];

        return Hash::combine($menus, '{n}.type', '{n}.menu_links');
    }

    /**
     * Gets the options for the current store and environment.
     *
     * @return array
     */
    public function getOptions(): array
    {
        $cacheKey = 'options';
        $url = "/admin/environments/$this->_environmentId/stores/$this->_storeId/options";
        $queryParams = ['refresh_callback_url' => "$this->_refresh_callback_url/options"];
        $options = $this->cacheOrGet($cacheKey, $url, $queryParams)['options'] ?? [];
        $options = Hash::combine($options, '{n}.name', '{n}.value');

        return Hash::expand($options);
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
    public function getSiteMap(): ?array
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
    public function getStore(): ?array
    {
        $storeIpMap = $this->getStoreIpMap();

        return $storeIpMap['store'] ?? null;
    }

    /**
     * Returns the current store IP map based on current IP.
     *
     * @return array|null
     */
    public function getStoreIpMap(): ?array
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
    public function render(string $html, string $content): string
    {
        preg_match_all('/{{\w+:?[\w\/\-.]+:?[\w\/\-.]+}}/', $html, $matches);
        $fileApiClient = new FilesApiHandler();

        foreach ($matches[0] ?? [] as $match) {
            $matchContent = trim($match, '{}');
            $components = explode(':', $matchContent, 2);
            [$placeholderType, $id] = array_pad($components, 2, null);

            switch ($placeholderType) {
                case 'content':
                    $replacement = $content;
                    break;
                case 'file':
                    $replacement = $fileApiClient->getFileUrl($id, null, null, true);
                    break;
                case 'image':
                    $replacement = $fileApiClient->getFileUrl($id);
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
}