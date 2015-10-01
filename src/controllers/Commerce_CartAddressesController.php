<?php
namespace Craft;

/**
 * Class Commerce_CartAddressesController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_CartAddressesController extends Commerce_BaseFrontEndController
{
	protected $allowAnonymous = true;

	/**
	 * Posting two new addresses in case when a user has no saved addresses
	 *
	 * @throws HttpException
	 * @throws \Exception
	 */
	public function actionPostTwoAddresses ()
	{
		$this->requirePostRequest();

		$billing = new Commerce_AddressModel;
		$billing->attributes = craft()->request->getPost('BillingAddress');

		if (craft()->request->getPost('sameAddress') == 1)
		{
			$shipping = $billing;
		}
		else
		{
			$shipping = new Commerce_AddressModel;
			$shipping->attributes = craft()->request->getPost('ShippingAddress');
		}

		$order = craft()->commerce_cart->getCart();

		if (craft()->commerce_orders->setAddresses($order, $shipping, $billing))
		{

			craft()->commerce_customers->setLastUsedAddresses($billing->id, $shipping->id);

			if (craft()->request->isAjaxRequest)
			{
				$this->returnJson(['success' => true, 'cart' => $this->cartArray($order)]);
			}
			$this->redirectToPostedUrl();
		}
		else
		{
			if (craft()->request->isAjaxRequest)
			{
				$this->returnJson(['error' => $billing->getAllErrors()]);
			}
			craft()->urlManager->setRouteVariables([
				'billingAddress'  => $billing,
				'shippingAddress' => $shipping,
			]);
		}
	}

	/**
	 * @throws HttpException
	 * @throws \Exception
	 */
	public function actionSetShippingMethod ()
	{
		$this->requirePostRequest();

		$id = craft()->request->getPost('shippingMethodId');
		$cart = craft()->commerce_cart->getCart();

		if (craft()->commerce_cart->setShippingMethod($cart, $id))
		{
			if (craft()->request->isAjaxRequest)
			{
				$this->returnJson(['success' => true, 'cart' => $this->cartArray($cart)]);
			}
			craft()->userSession->setFlash('notice', Craft::t('Shipping method has been set'));
			$this->redirectToPostedUrl();
		}
		else
		{
			$error = Craft::t('Wrong shipping method');
			if (craft()->request->isAjaxRequest)
			{
				$this->returnJson(['error' => $error]);
			}
			craft()->userSession->setFlash('error', $error);
		}
	}

	/**
	 * Choose Addresses
	 *
	 * @throws HttpException
	 * @throws \CHttpException
	 * @throws \Exception
	 */
	public function actionChooseAddresses ()
	{
		$this->requirePostRequest();

		$billingId = craft()->request->getPost('billingAddressId');
		$billingAddress = craft()->commerce_addresses->getAddressById($billingId);

		if (craft()->request->getPost('sameAddress') == 1)
		{
			$shippingAddress = $billingAddress;
		}
		else
		{
			$shippingId = craft()->request->getPost('shippingAddressId');
			$shippingAddress = craft()->commerce_addresses->getAddressById($shippingId);
		}

		$order = craft()->commerce_cart->getCart();

		if (!$billingAddress->id || !$shippingAddress->id)
		{
			if (empty($billingAddress->id))
			{
				craft()->userSession->setFlash('error', Craft::t('Please choose a billing address'));
			}
			if (empty($shippingAddress->id))
			{
				craft()->userSession->setFlash('error', Craft::t('Please choose a shipping address'));
			}

			return;
		}

		$customerId = craft()->commerce_customers->getCustomerId();
		$addressIds = craft()->commerce_customers->getAddressIds($customerId);

		if (in_array($billingAddress->id, $addressIds) && in_array($shippingAddress->id, $addressIds))
		{
			if (craft()->commerce_orders->setAddresses($order, $shippingAddress, $billingAddress))
			{
				craft()->commerce_customers->setLastUsedAddresses($billingAddress->id, $shippingAddress->id);
				if (craft()->request->isAjaxRequest)
				{
					$this->returnJson(['success' => true, 'cart' => $this->cartArray($order)]);
				}
				$this->redirectToPostedUrl();
			}
		}
		else
		{
			if (craft()->request->isAjaxRequest)
			{
				$this->returnJson(['error' => Craft::t('Choose addresses that are yours.')]);
			}
			craft()->userSession->setFlash('error', Craft::t('Choose addresses that are yours.'));
		}
	}

	/**
	 * Add New Address
	 *
	 * @throws Exception
	 * @throws HttpException
	 */
	public function actionAddAddress ()
	{
		$this->requirePostRequest();

		$address = new Commerce_AddressModel;
		$address->attributes = craft()->request->getPost('Address');

		$customerId = craft()->commerce_customers->getCustomerId();
		$addressIds = craft()->commerce_customers->getAddressIds($customerId);

		// if this is an existing address
		if ($address->id)
		{
			if (!in_array($address->id, $addressIds))
			{
				$error = Craft::t('Not allowed to edit that address.');
				if (craft()->request->isAjaxRequest)
				{
					$this->returnJson(['error' => $error]);
				}
				craft()->userSession->setFlash('error', $error);

				return;
			}
		}

		if (craft()->commerce_customers->saveAddress($address))
		{
			if (craft()->request->isAjaxRequest)
			{
				$this->returnJson(['success' => true]);
			}
			$this->redirectToPostedUrl();
		}
		else
		{
			if (craft()->request->isAjaxRequest)
			{
				$this->returnJson(['error' => $address->getAllErrors()]);
			}
			craft()->urlManager->setRouteVariables([
				'newAddress' => $address,
			]);
		}
	}

	/**
	 * Remove Address
	 *
	 * @throws HttpException
	 */
	public function actionRemoveAddress ()
	{
		$this->requirePostRequest();

		$customerId = craft()->commerce_customers->getCustomerId();
		$addressIds = craft()->commerce_customers->getAddressIds($customerId);

		$id = craft()->request->getPost('id', 0);

		if (!$id)
		{
			throw new HttpException(400);
		}

		// current customer is the owner of the address
		if (in_array($id, $addressIds))
		{
			if (craft()->commerce_addresses->deleteAddressById($id))
			{
				if (craft()->request->isAjaxRequest)
				{
					$this->returnJson(['success' => true]);
				}
				$this->redirectToPostedUrl();
			}
			craft()->userSession->setFlash('notice', Craft::t('Address removed.'));
		}
		else
		{
			$error = Craft::t('Not allowed to remove that address.');
			if (craft()->request->isAjaxRequest)
			{
				$this->returnJson(['error' => $error]);
			}
			craft()->userSession->setFlash('error', $error);
		}
	}
}