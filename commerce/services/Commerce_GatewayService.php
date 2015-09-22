<?php
namespace Craft;

use Omnipay\Common\AbstractGateway;
use Omnipay\Common\GatewayFactory;

/**
 * Gateway service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_GatewayService extends BaseApplicationComponent
{
	/** @var AbstractGateway[] */
	private $gateways;
	/** @var GatewayFactory */
	private $factory;

	public function __construct ()
	{
		$this->_loadGateways();
	}

	/**
	 * @return GatewayFactory
	 */
	private function getFactory ()
	{
		if (!$this->factory)
		{
			$this->factory = new GatewayFactory();
		}

		return $this->factory;
	}

	/**
	 * @param string $shortName
	 *
	 * @return AbstractGateway
	 */
	public function getGateway ($shortName)
	{
		return $this->getFactory()->create($shortName);
	}

	/**
	 * @return AbstractGateway[]
	 */
	public function getGateways ()
	{
		return $this->gateways;
	}

	/**
	 * Pre-load all gateways
	 */
	public function _loadGateways ()
	{
		$gateways = [];

		$supportedGateways = $this->getFactory()->find();

		foreach ($supportedGateways as $shortName)
		{
			$gateways[] = $this->getGateway($shortName);
		}

		$this->gateways = $gateways;
	}
}