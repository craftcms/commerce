<?php
namespace Craft;

/**
 * Settings service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_SettingsService extends BaseApplicationComponent
{
    /** @var BasePlugin */
    private $_plugin;

    /**
     * Setup
     */
    public function init()
    {
        $this->_plugin = craft()->plugins->getPlugin('commerce');
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
     * @return Commerce_SettingsModel
     */
    public function getSettings()
    {
        $data = $this->_plugin->getSettings();

        return Commerce_SettingsModel::populateModel($data);
    }

    /**
     * Set all settings from plugin core class
     *
     * @param Commerce_SettingsModel $settings
     *
     * @return bool
     */
    public function saveSettings(Commerce_SettingsModel $settings)
    {

        if (!$settings->validate()) {
            $errors = $settings->getAllErrors();

            return false;
        }

        craft()->plugins->savePluginSettings($this->_plugin, $settings);

        return true;
    }
}
