<?php
namespace Stripey\Exception;

use Craft\Exception;
use Craft\StripeyPlugin;

class BaseException Extends Exception
{

    // Public Methods
    // =========================================================================

    /**
     * @param     $message
     * @param int $code
     *
     * @return Exception
     */
    public function __construct($message, $code = 0)
    {
        StripeyPlugin::log($message, LogLevel::Error);
        parent::__construct($message, $code);
    }
} 