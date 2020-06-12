<?php
declare(strict_types=1);

namespace Skeleton\Auth\Exception;

use Cake\Core\Exception\Exception;

class MissingEventListenerException extends Exception
{
    protected $message = 'Missing listener to the `%s` event.';
}
