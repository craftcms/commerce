<?php
namespace Craft;

use Commerce\Gateways\PaymentFormModels\BasePaymentFormModel;

/**
 * Class Commerce_PaymentsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_PaymentsController extends Commerce_BaseFrontEndController
{
	/**
	 * @throws HttpException
	 */
	public function actionPay()
	{
		$this->requirePostRequest();

		$customError = '';

		if (($number = craft()->request->getParam('orderNumber')) !== null)
		{
			$order = craft()->commerce_orders->getOrderByNumber($number);
			if (!$order)
			{
				$error = Craft::t('Can not find an order to pay.');
				if (craft()->request->isAjaxRequest())
				{
					$this->returnErrorJson($error);
				}
				else
				{
					craft()->userSession->setFlash('error', $error);
				}

				return;
			}
		}

		// Get the cart if no order number was passed.
		if (!isset($order) && !$number)
		{
			$order = craft()->commerce_cart->getCart();
		}

		// Allow setting the payment method at time of submitting payment.
		$paymentMethodId = craft()->request->getParam('paymentMethodId');
		if ($paymentMethodId)
		{
			if (!craft()->commerce_cart->setPaymentMethod($order, $paymentMethodId, $error))
			{
				if (craft()->request->isAjaxRequest())
				{
					$this->returnErrorJson($error);
				}
				else
				{
					craft()->userSession->setFlash('error', $error);
				}

				return;
			}
		}


		// Get the payment method' gateway adapter's expected form model
		/** @var BaseModel $paymentForm */
		$paymentForm = $order->paymentMethod->getPaymentFormModel();
		$paymentForm->populateModelFromPost(craft()->request->getPost());

		// For now we will hard code this for backwards compatibility.
		// Will move it to a StripePaymentFormModel when completed.
		if (craft()->request->getPost('stripeToken') != "")
		{
			if ($paymentForm instanceof BasePaymentFormModel)
			{
				$paymentForm->token = craft()->request->getPost('stripeToken');
			}
		}

		$order->setContentFromPost('fields');

		// Check email address exists on order.
		if (!$order->email)
		{
			$customError = Craft::t("No customer email address exists on this cart.");
			if (craft()->request->isAjaxRequest())
			{
				$this->returnErrorJson($customError);
			}
			else
			{
				craft()->userSession->setFlash('error', $customError);
				craft()->urlManager->setRouteVariables(compact('paymentForm'));
			}

			return;
		}

		// Save the return and cancel URLs to the order
		$returnUrl = craft()->request->getPost('redirect');
		$cancelUrl = craft()->request->getPost('cancelUrl');

		if ($returnUrl !== null || $cancelUrl !== null)
		{
			$order->returnUrl = craft()->templates->renderObjectTemplate($returnUrl, $order);
			$order->cancelUrl = craft()->templates->renderObjectTemplate($cancelUrl, $order);
			craft()->commerce_orders->saveOrder($order);
		}

		$paymentForm->validate();
		if(!$paymentForm->hasErrors()){
			$success = craft()->commerce_payments->processPayment($order, $paymentForm, $redirect, $customError);
		}else{
			$customError = Craft::t('Payment information submitted is invalid.');
			$success = false;
		}


		if ($success)
		{
			if (craft()->request->isAjaxRequest())
			{
				$response = ['success' => true];
				if ($redirect !== null)
				{
					$response['redirect'] = $redirect;
				}
				$this->returnJson($response);
			}
			else
			{
				if ($redirect !== null)
				{
					$this->redirect($redirect);
				}
				else
				{
					$this->redirectToPostedUrl($order);
				}
			}
		}
		else
		{
			if (craft()->request->isAjaxRequest())
			{
				$this->returnErrorJson($customError);
			}
			else
			{
				craft()->userSession->setFlash('error', $customError);
				craft()->urlManager->setRouteVariables(compact('paymentForm'));
			}
		}
	}

	/**
	 * Process return from off-site payment
	 *
	 * @throws Exception
	 * @throws HttpException
	 */
	public function actionCompletePayment()
	{
		$id = craft()->request->getParam('hash');

		$transaction = craft()->commerce_transactions->getTransactionByHash($id);

		if (!$transaction)
		{
			throw new HttpException(400, Craft::t("Can not complete payment for missing transaction."));
		}

		$success = craft()->commerce_payments->completePayment($transaction, $customError);

		if ($success)
		{
			$this->redirect($transaction->order->returnUrl);
		}
		else
		{
			craft()->userSession->setError(Craft::t('Payment error: {message}', ['message' => $customError]));
			$this->redirect($transaction->order->cancelUrl);
		}
	}
}
