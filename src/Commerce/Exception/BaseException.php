<?php

namespace Commerce\Exception;

use Craft\CommercePlugin;
use Craft\Exception;

class BaseException Extends Exception
{
    /**
     * @param string $message
     * @param int $code
     */
    public function __construct($message, $code = 0)
    {
        CommercePlugin::log($message, LogLevel::Error);
        parent::__construct($message, $code);
    }
} 