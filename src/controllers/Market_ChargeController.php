<?php
namespace Craft;

/**
 * Deprecated Charge Controller
 *
 * @author    Make with Morph. <support@makewithmorph.com>
 * @copyright Copyright (c) 2015, Luke Holder.
 * @license   http://makewithmorph.com/market/license Market License Agreement
 * @see       http://makewithmorph.com
 * @package   craft.plugins.market.controllers
 * @since     0.1
 */
class Market_ChargeController extends Market_BaseController
{
	/*
	protected $allowAnonymous = array('actionNewCharge');

	/**
	 * The public Charge creation action.
	 */
	public function actionNewCharge()
	{
		$this->requirePostRequest();
		$charge = new Market_ChargeModel();

		$defaultCurrency     = craft()->market_settings->getSettings()->defaultCurrency;
		$amount              = craft()->request->getPost('amount', 100);
		$charge->amount      = $amount;
		$charge->currency    = craft()->request->getPost('currency', $defaultCurrency);
		$charge->card        = craft()->request->getPost('stripeToken');
		$charge->description = craft()->request->getPost('description');
		$charge->metadata    = craft()->request->getPost('metadata');
		//or TODO: make it possible to pass a customer with a default card on file
		//$charge->customer = 'cus_5GW06HEnx9t8pC';

		$chargeCreator = new \Market\Charge\Creator;
		$charge        = $chargeCreator->create($charge);

		if ($charge->hasErrors()) {
			craft()->urlManager->setRouteVariables(array(
				'charge' => $charge
			));
		} else {
			$this->redirectToPostedUrl($charge);
		}
	}

	/**
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEditCharge(array $variables = array())
	{
		$chargeId = $variables['chargeId'];
		$this->renderTemplate('market/charges/_edit', compact('chargeId'));
	}

	/**
	 * @param array $variables
	 */
	public function actionRefundCharge(array $variables = array())
	{

	}
} 