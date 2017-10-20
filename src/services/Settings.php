<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\models\Settings as SettingsModel;
use craft\commerce\Plugin;
use yii\base\Component;

/**
 * Settings service.
 *
 * @property SettingsModel $settings
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Settings extends Component
{
    /** @var \craft\base\Plugin */
    private $_plugin;

    /**
     * Setup
     */
    public function init()
    {
        $this->_plugin = Plugin::getInstance();
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
     * @return SettingsModel
     */
    public function getSettings(): SettingsModel
    {
        $data = $this->_plugin->getSettings();

        return new SettingsModel($data);
    }

    /**
     * Set all settings from plugin core class
     *
     * @param SettingsModel $settings
     *
     * @return bool
     */
    public function saveSettings(SettingsModel $settings): bool
    {
        if (!$settings->validate()) {
            return false;
        }

        Craft::$app->getPlugins()->savePluginSettings($this->_plugin, $settings->toArray());

        return true;
    }
}
