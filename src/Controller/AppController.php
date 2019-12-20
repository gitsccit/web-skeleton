<?php
declare(strict_types=1);

namespace Skeleton\Controller;

use Cake\Controller\Controller;
use Cake\Controller\Exception\SecurityException;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Routing\Router;
use Skeleton\Listener\TableFilter;

/**
 * Class AppController
 * @package Skeleton\Controller
 *
 * @property \Skeleton\Controller\Component\CrudComponent $Crud
 */
class AppController extends Controller
{

    /**
     * Settings for crud.
     *
     * - `fallbackTemplatePath` - Path to the fallback template folder, relative to /templates, defaults to 'Common'.
     *
     * @var array
     */
    public $crud = [];

    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('Security');`
     *
     * @return void
     * @throws \Exception
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('RequestHandler', [
            'enableBeforeRedirect' => false,
        ]);
        $this->loadComponent('Flash');
        $this->loadComponent('Skeleton.Crud');
        if (!Configure::read('debug')) {
            $this->loadComponent('Security', ['blackHoleCallback' => 'forceSSL']);
        }

        // add listeners
        EventManager::instance()->on(new TableFilter($this));
    }

    //======================================================================
    // Lifecycle Functions
    //======================================================================

    public function beforeFilter(Event $event)
    {
        if (!Configure::read('debug')) {
            $this->Security->requireSecure();
        }
    }

    //======================================================================
    // Utility Functions
    //======================================================================

    /**
     * @param string $error
     * @param SecurityException|null $exception
     * @return \Cake\Http\Response|null
     */
    public function forceSSL($error = '', SecurityException $exception = null)
    {
        if ($exception instanceof SecurityException && $exception->getType() === 'secure') {
            return $this->redirect('https://' . env('SERVER_NAME') . Router::url($this->request->getRequestTarget()));
        }

        throw $exception;
    }
}
