<?php
namespace Skeleton\Auth\Exception;

use Cake\Core\Exception\Exception;

class InvalidProviderException extends Exception
{
    protected $message = 'Invalid provider or missing class (%s)';
    protected $code = 500;
}
