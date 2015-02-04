<?php
namespace Craft;

/**
 * Class Stripey_PaymentMethodModel
 *
 * @package Craft
 *
 * @property int                              $id
 * @property string                           $class
 * @property string                           $name
 * @property array                            $settings
 * @property bool                             $cpEnabled
 * @property bool                             $frontendEnabled
 *
 * @property \Omnipay\Common\GatewayInterface $gateway
 */
class Stripey_PaymentMethodModel extends BaseModel
{
	/** @var array */
	private $_settings = array();

	/**
	 * Get gateway initialized with the settings
	 *
	 * @return \Omnipay\Common\GatewayInterface
	 */
	public function createGateway()
	{
		$gateway = $this->getGateway();
		$gateway->initialize($this->settings);

		return $gateway;
	}

	/**
	 * @return null|\Omnipay\Common\GatewayInterface
	 */
	public function getGateway()
	{
		if (!empty($this->class)) {
			return craft()->stripey_gateway->getGateway($this->class);
		}

		return NULL;
	}

	/**
	 * @return array
	 */
	public function getSettings()
	{
		return $this->_settings;
	}

	/**
	 * Magic property setter
	 *
	 * @param array $settings
	 */
	public function setSettings(array $settings)
	{
		foreach ($settings as $key => $value) {
			if (is_array($value)) {
				$this->_settings[$key] = reset($value);
			} else {
				$this->_settings[$key] = $value;
			}
		}
	}

	/**
	 * Settings fields which should be displayed as select-boxes
	 *
	 * @return array [setting name => choices list]
	 */
	public function getSelects()
	{
		$gateway = craft()->stripey_gateway->getGateway($this->class);
		if (!$gateway) {
			return array();
		}

		$defaults = $gateway->getDefaultParameters();

		return array_filter($defaults, 'is_array');
	}

	/**
	 * Settings fields which should be displayed as check-boxes
	 *
	 * @return array
	 */
	public function getBooleans()
	{
		$gateway = craft()->stripey_gateway->getGateway($this->class);
		if (!$gateway) {
			return array();
		}

		$result   = array();
		$defaults = $gateway->getDefaultParameters();
		foreach ($defaults as $key => $value) {
			if (is_bool($value)) {
				$result[] = $key;
			}
		}

		return $result;
	}

	protected function defineAttributes()
	{
		return array(
			'id'              => AttributeType::Number,
			'class'           => AttributeType::String,
			'name'            => AttributeType::String,
			'cpEnabled'       => AttributeType::Bool,
			'frontendEnabled' => AttributeType::Bool,
		);
	}

	/**
	 * @param Stripey_PaymentMethodRecord|array $values
	 *
	 * @return BaseModel
	 */
	public static function populateModel($values)
	{
		/** @var self $model */
		$model = parent::populateModel($values);
		if (is_object($values)) {
			$model->settings = $values->settings;
		} else {
			$model->settings = $values['settings'];
		}

		return $model;
	}
}
