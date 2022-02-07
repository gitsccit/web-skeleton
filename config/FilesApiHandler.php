<?php

use Cake\Core\Configure;
use Cake\Http\Session;
use Cake\Routing\Router;

class FilesApiHandler extends ApiHandler
{
    protected $_tokenName = 'fileServerApiToken';

    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->_baseUrl = Configure::read('Urls.files');
    }

    /**
     * Gets the file info from the API using its id.
     *
     * @param int|string $id
     * @return array|null
     */
    public function getFile($id): ?array
    {
        $cacheKey = "file_$id";
        $url = "/files/$id";
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
    public function getFileContent($id, $width = null, $height = null, bool $refresh = false): string
    {
        $filePath = FILES . $this->getFileName($id, $width, $height);

        if (!$refresh && file_exists($filePath)) {
            return file_get_contents($filePath);
        }

        $url = $width || $height ? "/files/resize/$id/$width/$height" : "/files/open/$id";
        $result = $this->get($url)->getStringBody();

        if (!is_dir(FILES)) {
            mkdir(FILES, 0775);
        }

        file_put_contents($filePath, $result);

        return $result;
    }

    protected function getFileName($id, $width = null, $height = null): string
    {
        $session = new Session();
        $file = $this->getFile($id);
        $allowedSizes = $session->read('options.files.resize');
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

        return $fileName . $extension;
    }

    public function getFileUrl($id, $width = null, $height = null, $full = false): string
    {
        if (empty($id)) {
            return 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
        }

        return Router::url('/files/' . $this->getFileName($id, $width, $height), $full);
    }
}