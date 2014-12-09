<?php

namespace Craft;

use Stripey\Cart\CartItemCollection;

require 'vendor/autoload.php';
require 'Stripey.php';


class StripeyPlugin extends BasePlugin
{
    /**
     *
     */
    public function init()
    {

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
        return @require_once('config/routes.php');
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

