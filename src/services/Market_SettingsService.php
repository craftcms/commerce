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
	 */
	public function getSettings()
	{
		//For now just use the basic built in settings mass assignment feature
		return $this->_plugin->getSettings();
	}

	/**
	 * Set all settings from plugin core class
	 */
	public function save($settings)
	{
		craft()->plugins->savePluginSettings($this->_plugin, $settings);
	}
} 