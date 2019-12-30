<?php
namespace Skeleton\Auth\Exception;

use Cake\Core\Exception\Exception;

class MissingEventListenerException extends Exception
{
    protected $message = 'Missing listener to the `%s` event.';
}
