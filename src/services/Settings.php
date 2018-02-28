<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\models\Settings as SettingsModel;
use craft\commerce\Plugin;
use yii\base\Component;

/**
 * Settings service.
 *
 * @property SettingsModel $settings all settings from plugin core class
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Settings extends Component
{
    // Properties
    // =========================================================================

    /**
     * @var \craft\base\Plugin
     */
    private $_plugin;

    // Public Methods
    // =========================================================================

    /**
     * Setup
     */
    public function init()
    {
        $this->_plugin = Plugin::getInstance();
    }

    /**
     * @param string $option
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
     * Sets all settings from plugin core class
     *
     * @param SettingsModel $settings
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
