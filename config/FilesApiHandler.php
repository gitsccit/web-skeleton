<?php

use Cake\Core\Configure;
use Cake\Http\Exception\HttpException;
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

    public function getFileUrl($id, ?int $width = null, ?int $height = null, bool $full = true): string
    {
        $defaultImage = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';

        if (empty($id)) {
            return $defaultImage;
        }

        try {
            $fileName = $this->getFileName($id, $width, $height);
        } catch (HttpException $exception) {
            return $defaultImage;
        }

        return $this->_baseUrl/$fileName;
    }
}