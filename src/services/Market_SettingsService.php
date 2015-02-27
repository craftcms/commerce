<?php
namespace Craft;

/**
 * Class Market_SettingsService
 *
 * @package Craft
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