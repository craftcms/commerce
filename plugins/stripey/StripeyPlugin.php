<?php

namespace Craft;

require 'vendor/autoload.php';

class StripeyPlugin extends BasePlugin
{
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
            'publishableKey' => AttributeType::String
        );
    }

}