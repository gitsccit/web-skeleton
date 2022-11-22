<?php

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Http\Exception\HttpException;

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

    public function getFiles(array $ids): ?array
    {
        $result = [];
        $fetch = [];

        foreach ($ids as $id) {
            if (empty($id)) {
                continue;
            }

            $cacheKey = "file_$id";
            $file = Cache::read($cacheKey, $this->_cacheConfig);

            if (empty($file)) {
                $fetch[] = $id;
            }

            $result[$id] = $file;
        }

        if (empty($fetch)) {
            return $result;
        }

        $idsString = implode('/', $fetch);
        $url = "/files/$idsString";
        $queryParams = ['refresh_callback_url' => "/pages/clear-cache/files/$idsString"];
        $data = $this->get($url, $queryParams)->getJson()['data'];

        if ($files = $data['files'] ?? [$data['file']] ?? null) {
            foreach ($files as $file) {
                if ($file) {
                    $cacheKey = "file_$file[id]";
                    Cache::write($cacheKey, $file, $this->_cacheConfig);
                    $result[$file['id']] = $file;
                }
            }
        }

        return $result;
    }

    protected function getFileNames($ids, $width = null, $height = null): array
    {
        $files = $this->getFiles($ids);
        $allowedSizes = [50, 100, 200, 300, 400, 800, 1200, 1600, 2400];
        if ($width) {
            foreach ($allowedSizes as $size) {
                if ($width <= $size) {
                    $width = $size;
                    break;
                }
            }
        }
        if ($height) {
            foreach ($allowedSizes as $size) {
                if ($height <= $size) {
                    $height = $size;
                    break;
                }
            }
        }

        $fileNames = [];
        foreach ($files as $id => $file) {
            if (empty($file)) {
                $fileNames[$id] = null;
                continue;
            }

            $fileName = "$id/" . preg_replace('/[^a-zA-Z0-9.]/', '-', $file['name']);

            if ($width) {
                $parts = explode('.', $fileName);
                $extension = array_pop($parts);
                $fileName = implode('.', $parts);

                $fileName = "{$fileName}_$width";

                if ($height) {
                    $fileName = "{$fileName}x$height";
                }

                $fileName = "$fileName.$extension";
            }

            $fileNames[$id] = $fileName;
        }

        return $fileNames;
    }

    public function getFileUrl($id, ?int $width = null, ?int $height = null, bool $download = false): ?string
    {
//        $defaultImage = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
        $defaultImage = null;

        if (empty($id)) {
            return $defaultImage;
        }

        try {
            $fileName = $this->getFileNames([$id], $width, $height)[$id] ?? $defaultImage;
        } catch (HttpException $exception) {
            return $defaultImage;
        }

        $baseUrl = str_replace('/api', '/files', $this->_baseUrl);

        return $download ? "$baseUrl/download/$fileName" : "$baseUrl/$fileName";
    }

    public function getFileUrls(array $ids, ?int $width = null, ?int $height = null): array
    {
//        $defaultImage = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
        $defaultImage = null;

        if (empty($ids)) {
            return [];
        }

        try {
            $fileNames = $this->getFileNames($ids, $width, $height);
        } catch (HttpException $exception) {
            return [];
        }

        $baseUrl = str_replace('/api', '/files', $this->_baseUrl);

        foreach ($fileNames as $id => $fileName) {
            $fileNames[$id] = empty($fileName) ? $defaultImage : "$baseUrl/$fileName";
        }

        return $fileNames;
    }
}