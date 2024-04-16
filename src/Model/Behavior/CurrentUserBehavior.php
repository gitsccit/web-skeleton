<?php
declare(strict_types=1);

namespace Skeleton\Model\Behavior;

use Cake\Event\Event;
use Cake\ORM\Behavior;
use Cake\Routing\Router;

/**
 * CurrentUser behavior
 */
class CurrentUserBehavior extends Behavior
{
    /**
     * Default configuration.
     *
     * @var array
     */
    protected array $_defaultConfig = [];

    public function beforeMarshal(Event $event, \ArrayObject $data, \ArrayObject $options)
    {
        $options['accessibleFields'] = ['user_id' => true];
        $data['user_id'] = Router::getRequest()->getSession()->read('Auth.User.id');
    }
}
