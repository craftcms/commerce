<?php
namespace Craft;

/**
 *
 *
 * @author    Make with Morph. <support@makewithmorph.com>
 * @copyright Copyright (c) 2015, Luke Holder.
 * @license   http://makewithmorph.com/market/license Market License Agreement
 * @see       http://makewithmorph.com
 * @package   craft.plugins.market.controllers
 * @since     0.1
 */
class Market_CustomerController extends Market_BaseController
{

	protected $allowAnonymous = ['actionNewCustomer'];

	public function actionNewCharge()
	{
		$this->requirePostRequest();
		$charge = new Market_CustomerModel();

		$charge->card = craft()->request->getPost('stripeToken');

		//or TODO: make it possible to pass a customer with a default card on file
		//$charge->customer = 'cus_5GW06HEnx9t8pC';

		$chargeCreator = new \Market\Charge\Creator($this);
		$chargeCreator->save($charge);
	}

}