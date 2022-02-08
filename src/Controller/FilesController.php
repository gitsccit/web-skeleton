<?php
declare(strict_types=1);

namespace Skeleton\Controller;

use Cake\Event\EventInterface;
use FilesApiHandler;

/**
 * Files Controller
 *
 * @property \FilesApiHandler $filesApiHandler
 */
class FilesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();

        if (!isset($this->filesApiHandler)) {
            $this->filesApiHandler = new FilesApiHandler();
        }
    }

    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated(['open', 'download']);
    }

    public function open($name)
    {
        $name = pathinfo($name, PATHINFO_FILENAME);
        $parts = explode('_', $name);
        $id = $parts[0];
        $width = null;
        $height = null;

        if (count($parts) > 1) {
            $dimension = $parts[1];
            $dimension = explode('x', $dimension);
            $width = $dimension[0];

            if (count($dimension) > 1) {
                $height = $dimension[1];
            }
        }

        return $this->fetch($id, false, $width, $height);
    }

    public function download($id)
    {
        return $this->fetch($id);
    }

    protected function fetch($id, $download = true, $width = null, $height = null)
    {
        $file = $this->filesApiHandler->getFile($id);
        $etag = $this->filesApiHandler->readCache("file_${id}_etag");
        $response = $this->response->withEtag($etag)
            ->withCache($file['created_at'], '+5 days')
            ->withType($file['mime_type']['name']);

        if ($response->checkNotModified($this->getRequest())) {
            return $response;
        }

        $etagWasSent = !empty($this->getRequest()->getHeaderLine('Etag'));
        $fileContent = $this->filesApiHandler->getFileContent($id, $width, $height, $etagWasSent);
        $response = $response->withStringBody($fileContent);

        if ($download) {
            $response = $response->withDownload($file['name']);
        }

        return $response;
    }
}
