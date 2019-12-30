<?php
namespace Skeleton\Auth\Exception;

use Exception;

class MissingProviderConfigurationException extends Exception
{
    protected $message = 'No OAuth providers configured.';
    protected $code = 500;
}
