<?php
namespace Craft;

use Omnipay\Common\AbstractGateway;

/**
 * Class Commerce_PaymentMethodModel
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
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_PaymentMethodModel extends BaseModel
{
	/** @var array */
	private $_settings = [];
	/** @var array Gateway which doesn't require card */
	private $_withoutCard = ['PayPal_Express', 'Manual'];

	/**
	 * @param Commerce_PaymentMethodRecord|array $values
	 *
	 * @return BaseModel
	 */
	public static function populateModel ($values)
	{
		/** @var self $model */
		$model = parent::populateModel($values);
		if (is_object($values))
		{
			$model->settings = $values->settings;
		}
		else
		{
			$model->settings = $values['settings'];
		}

		return $model;
	}

	/**
	 * Get gateway initialized with the settings
	 *
	 * @return \Omnipay\Common\GatewayInterface
	 */
	public function createGateway ()
	{
		$gateway = $this->getGateway();
		$gateway->initialize($this->settings);

		return $gateway;
	}

	/**
	 * @return AbstractGateway
	 */
	public function getGateway ()
	{
		if (!empty($this->class))
		{
			$gw = craft()->commerce_gateway->getGateway($this->class);
			$gw->initialize($this->settings);

			return $gw;
		}

		return null;
	}

	/**
	 * @return array
	 */
	public function getSettings ()
	{
		return $this->_settings;
	}

	/**
	 * Magic property setter
	 *
	 * @param array $settings
	 */
	public function setSettings ($settings)
	{
		if (is_array($settings))
		{
			foreach ($settings as $key => $value)
			{
				if (is_array($value))
				{
					$this->_settings[$key] = reset($value);
				}
				else
				{
					$this->_settings[$key] = $value;
				}
			}
		}
	}

	/**
	 * Settings fields which should be displayed as select-boxes
	 *
	 * @return array [setting name => [choices list]]
	 */
	public function getSelects ()
	{
		$gateway = craft()->commerce_gateway->getGateway($this->class);
		if (!$gateway)
		{
			return [];
		}

		$defaults = $gateway->getDefaultParameters();

		$selects = array_filter($defaults, 'is_array');
		foreach ($selects as $param => &$values)
		{
			$values = array_combine($values, $values);
		}

		return $selects;
	}

	/**
	 * Settings fields which should be displayed as check-boxes
	 *
	 * @return array
	 */
	public function getBooleans ()
	{
		$gateway = craft()->commerce_gateway->getGateway($this->class);
		if (!$gateway)
		{
			return [];
		}

		$result = [];
		$defaults = $gateway->getDefaultParameters();
		foreach ($defaults as $key => $value)
		{
			if (is_bool($value))
			{
				$result[] = $key;
			}
		}

		return $result;
	}

	/**
	 * Whether this payment method requires credit card details
	 *
	 * @return bool
	 */
	public function requiresCard ()
	{
		return !in_array($this->class, $this->_withoutCard);
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'id'              => AttributeType::Number,
			'class'           => AttributeType::String,
			'name'            => AttributeType::String,
			'cpEnabled'       => AttributeType::Bool,
			'frontendEnabled' => AttributeType::Bool,
		];
	}
}
