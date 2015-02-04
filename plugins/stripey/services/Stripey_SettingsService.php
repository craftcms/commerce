<?php
namespace Craft;

/**
 * Class Stripey_SettingsService
 *
 * @package Craft
 */
class Stripey_SettingsService extends BaseApplicationComponent
{
	/** @var BasePlugin */
	private $_plugin;

	/**
	 * Setup
	 */
	public function init()
	{
		$this->_plugin = craft()->plugins->getPlugin('stripey');
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
	public function setSettings($settings)
	{
		//For now just use the basic built in settings mass assignment feature
		//In the future we will use our own record active model
		//$settings = array('secretKey'=>'sk_test_8Lvmi5qDkbHRLCsyexhvOGuj','publishableKey'=>'pk_test_ysElKNu1n56ehhFioJqVK2DJ');
		craft()->plugins->savePluginSettings($this->_plugin, $settings);
	}
} 