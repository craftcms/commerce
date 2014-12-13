<?php
namespace Stripey;
/**
 * Class Stripey
 * @package Stripey
 */

use Pimple\Container;
use Craft\Craft;


class Stripey extends Container{

    public function __get($memberName)
    {
        $craftName = 'stripey_' . strtolower($memberName);
        return Craft::app()->$craftName;
    }

    /**
     * Return singleton instance of the Stripey service manager
     * @return Stripey
     */
    public static function app()
    {
        static $inst = null;
        if ( $inst === null) {
            $inst = new self();
        }
        return $inst;
    }

}


/**
 * Returns the Stripey Plugin equivalent to webApp class that serves up all the services.
 *
 * @return Stripey
 */
function stripey()
{
    return \Stripey\Stripey::app();
}