<?php
namespace Craft;
/**
 * Class Stripey
 * @package Craft
 */

/**
 * @property Stripey_PlansService $plans Stripey Plans Service
 * @property Stripey_SettingsService $settings Stripey Settings Service
 * @property Stripey_ApiService $api Stripey Stripe Api Service
 * @property Stripey_ChargeService $charge Stripey Charge Service
 */
class Stripey {

    public function __get($memberName)
    {
        $craftName = 'stripey_' . strtolower($memberName);
        return craft()->$craftName;
    }

    /**
     * Return singleton instance of the Stripey service manager
     * @return Stripey
     */
    public static function app()
    {
        static $inst = null;
        if ( $inst === null) {
            $inst = new Stripey();
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
    return Stripey::app();
}