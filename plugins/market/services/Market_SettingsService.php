<?php
namespace Craft;

/**
 * Class Market_SettingsService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Market_SettingsService extends BaseApplicationComponent
{
    /** @var BasePlugin */
    private $_plugin;

    /**
     * Setup
     */
    public function init()
    {
        $this->_plugin = craft()->plugins->getPlugin('market');
    }

    /**
     * @param string $option
     *
     * @return mixed
     */
    public function getOption($option)
    {
        return $this->getSettings()->$option;
    }

    /**
     * Get all settings from plugin core class
     *
     * @return Market_SettingsModel
     */
    public function getSettings()
    {
        $data = $this->_plugin->getSettings();

        return Market_SettingsModel::populateModel($data);
    }

    /**
     * Set all settings from plugin core class
     *
     * @param Market_SettingsModel $settings
     *
     * @return bool
     */
    public function save(Market_SettingsModel $settings)
    {
        if (!$settings->validate()) {
            return false;
        }

        craft()->plugins->savePluginSettings($this->_plugin, $settings);

        return true;
    }
} 