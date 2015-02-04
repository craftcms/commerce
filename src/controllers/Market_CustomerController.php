<?php
namespace Craft;

class Market_CustomerController extends Market_BaseController
{

	protected $allowAnonymous = array('actionNewCustomer');

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