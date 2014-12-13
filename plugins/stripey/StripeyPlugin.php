<?php

namespace Craft;

use Stripey\Stripey;
use Stripey\Api\Stripe;


require 'vendor/autoload.php';


class StripeyPlugin extends BasePlugin
{
    function __construct()
    {
        Stripey::app()["stripe"] = function ($c) {
            $key = $this->getSettings()->secretKey;
            return new Stripe($key);
        };
    }

    /**
     * Returns the Name of the Plugin.
     *
     * @return string
     */
    public function getName()
    {
        return "Stripey";
    }

    /**
     * Returns the plugin’s version.
     *
     * @return string
     */
    public function getVersion()
    {
        return "0.0.1";
    }

    /**
     * Returns the plugin developer's name.
     *
     * @return string
     */
    public function getDeveloper()
    {
        return "Luke Holder (HOLPAC)";
    }

    /**
     * Returns the plugin developer's URL.
     *
     * @return string
     */
    public function getDeveloperUrl()
    {
        return "http://holpac.com";
    }

    /**
     * @return bool
     */
    public function hasCpSection()
    {
        return true;
    }


    /**
     * @return mixed
     */
    public function registerCpRoutes()
    {
        return array(
            'stripey' => array('action' => 'stripey/dashboard/index'),
            'stripey/plans' => array('action' => 'stripey/plans/index'),
            'stripey/charges' => 'stripey/charges/index',
            'stripey/charges/(?P<chargeId>\d+)' => array('action' => 'stripey/charge/editCharge'),
            'stripey/settings' => array('action' => 'stripey/settings/edit')
        );
    }

    /**
     * @return array
     */
    protected function defineSettings()
    {
        return array(
            'secretKey'      => AttributeType::String,
            'publishableKey' => AttributeType::String,
            //TODO: Fill currency enum values dynamically based on https://support.stripe.com/questions/which-currencies-does-stripe-support
            'defaultCurrency' => AttributeType::String
        );
    }

}

