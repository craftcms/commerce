<?php

namespace Craft;

use Omnipay\Common\CreditCard;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Class Market_PaymentService
 *
 * @package craft
 */
class Market_PaymentService extends BaseApplicationComponent
{
    /**
     * @param Market_OrderModel       $cart
     * @param Market_PaymentFormModel $form
     *
     * @param                         $returnUrl
     * @param string                  $customError
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
	public function processPayment(Market_OrderModel $cart, Market_PaymentFormModel $form, $returnUrl, $cancelUrl, &$customError = '')
	{
        //validating card
        if($cart->paymentMethod->requiresCard()) {
            if (!$form->validate()) {
                return false;
            }
        } else {
            $form->attributes = [];
        }

        //saving returnUrl to cart
        $cart->returnUrl = $returnUrl;
        $cart->cancelUrl = $cancelUrl;
        craft()->market_order->save($cart);

        //choosing default action
        $defaultAction = craft()->market_settings->getOption('paymentMethod');
        $defaultAction = ($defaultAction === Market_TransactionRecord::PURCHASE) ? $defaultAction : Market_TransactionRecord::AUTHORIZE;
        $gateway = $cart->paymentMethod->getGateway();

        if($defaultAction == Market_TransactionRecord::AUTHORIZE) {
            if (!$gateway->supportsAuthorize()) {
                $customError = "Gateway doesn't support authorize";
                return false;
            }
        } else {
            if (!$gateway->supportsPurchase()) {
                $customError = "Gateway doesn't support purchase";
                return false;
            }
        }

        //creating cart,transaction and request
        $transaction       = craft()->market_transaction->create($cart);
        $transaction->type = $defaultAction;
        $this->saveTransaction($transaction);

		$card = $this->createCard($cart, $form);

        $request = $gateway->$defaultAction($this->buildPaymentRequest($transaction, $card));

		try {
			$returnUrl = $this->sendPaymentRequest($request, $transaction);
		} catch (\Exception $e) {
			$customError = $e->getMessage();
			craft()->market_transaction->delete($transaction);

			return false;
		}

		craft()->request->redirect($returnUrl);

		return true;
	}

    /**
     * Send a payment request to the gateway, and redirect appropriately
     *
     * @param AbstractRequest         $request
     * @param Market_TransactionModel $transaction

     * @return string
     */
	private function sendPaymentRequest(AbstractRequest $request, Market_TransactionModel $transaction)
	{
		// try {
		/** @var ResponseInterface $response */
		$response = $request->send();
		$this->updateTransaction($transaction, $response);

		if ($response->isRedirect()) {
			// redirect to off-site gateway
			return $response->redirect();
		}

        $order = $transaction->order;

		if ($response->isSuccessful()) {
			return $order->returnUrl;
		} else {
			craft()->userSession->setError(Craft::t("Payment error: " . $transaction->message));
			return $order->cancelUrl;
		}
	}

    /**
     * @param Market_TransactionModel $transaction
     * @return Market_TransactionModel
     */
    public function captureTransaction(Market_TransactionModel $transaction)
    {
        return $this->processCaptureOrRefund($transaction, Market_TransactionRecord::CAPTURE);
    }

    /**
     * @param Market_TransactionModel $transaction
     * @return Market_TransactionModel
     */
    public function refundTransaction(Market_TransactionModel $transaction)
    {
        return $this->processCaptureOrRefund($transaction, Market_TransactionRecord::REFUND);
    }

    /**
     * @param Market_TransactionModel $parent
     * @param string                  $action
     * @return Market_TransactionModel
     * @throws Exception
     */
    private function processCaptureOrRefund(Market_TransactionModel $parent, $action)
    {
        if(!in_array($action, [Market_TransactionRecord::CAPTURE, Market_TransactionRecord::REFUND])) {
            throw new Exception('Wrong action: ' .$action);
        }

        $order = $parent->order;
        $child = craft()->market_transaction->create($order);
        $child->parentId = $parent->id;
        $child->paymentMethodId = $parent->paymentMethodId;
        $child->type = $action;
        $child->amount = $parent->amount;
        $this->saveTransaction($child);

        $gateway = $parent->paymentMethod->getGateway();
        $request = $gateway->$action($this->buildPaymentRequest($child));
        $request->setTransactionReference($parent->reference);

        $order->returnUrl = $order->getCpEditUrl();
        craft()->market_order->save($order);

        try {
            $response = $request->send();
            $this->updateTransaction($child, $response);
        } catch (\Exception $e) {
            $child->status = Market_TransactionRecord::FAILED;
            $child->message = $e->getMessage();

            $this->saveTransaction($child);
        }

        return $child;
    }

    /**
     * Process return from off-site payment
     *
     * @param Market_TransactionModel $transaction
     * @throws Exception
     */
    public function completePayment(Market_TransactionModel $transaction)
    {
        $order = $transaction->order;

        // ignore already processed transactions
        if ($transaction->status != Market_TransactionRecord::REDIRECT) {
            if ($transaction->status == Market_TransactionRecord::SUCCESS) {
                craft()->request->redirect($order->returnUrl);
            } else {
                craft()->userSession->setError('Payment error: ' . $transaction->message);
                craft()->request->redirect($order->cancelUrl);
            }
        }

        // load payment driver
        $gateway = $transaction->paymentMethod->getGateway();

        $action = 'complete' . ucfirst($transaction->type);
        $supportsAction = 'supports' . ucfirst($action);
        if ($gateway->$supportsAction()) {
            // don't send notifyUrl for completePurchase
            $params = $this->buildPaymentRequest($transaction);

            // If MOLLIE, the transactionReference will be theirs
            if ($transaction->paymentMethod->class == 'Mollie_Ideal' || $transaction->paymentMethod->class == 'Mollie') {
                $params['transactionReference'] = $transaction->reference;
            }

            // If SagePay, we need the actual reference
            if ($transaction->paymentMethod->class == 'SagePay_Server') {
                $params['transactionReference'] = $transaction->reference;
            }

            unset($params['notifyUrl']);

            $request = $gateway->$action($params);
            $redirect = $this->sendPaymentRequest($request, $transaction);

            if($transaction->status == Market_TransactionRecord::SUCCESS) {
                craft()->market_order->complete($order);
            }
            craft()->request->redirect($redirect);
        } else {
            throw new Exception('Payment return not supported');
        }
    }

	/**
	 * @param Market_TransactionModel $transaction
	 * @param ResponseInterface       $response
	 *
	 * @throws Exception
	 */
	private function updateTransaction(Market_TransactionModel $transaction, ResponseInterface $response)
	{
		if ($response->isSuccessful()) {
			$transaction->status = Market_TransactionRecord::SUCCESS;
		} elseif ($response->isRedirect()) {
			$transaction->status = Market_TransactionRecord::REDIRECT;
		} else {
			$transaction->status = Market_TransactionRecord::FAILED;
		}

		$transaction->reference = $response->getTransactionReference();
		$transaction->message   = $response->getMessage();

        $this->saveTransaction($transaction);
	}

	/**
	 * @param Market_OrderModel       $order
	 * @param Market_PaymentFormModel $paymentForm
	 *
	 * @return CreditCard
	 */
	private function createCard(Market_OrderModel $order, Market_PaymentFormModel $paymentForm)
	{
		$card = new CreditCard;

		$card->setFirstName($paymentForm->firstName);
		$card->setLastName($paymentForm->lastName);
		$card->setNumber($paymentForm->number);
		$card->setExpiryMonth($paymentForm->month);
		$card->setExpiryYear($paymentForm->year);
		$card->setCvv($paymentForm->cvv);

		$billingAddress = $order->billingAddress;
		$card->setBillingAddress1($billingAddress->address1);
		$card->setBillingAddress2($billingAddress->address2);
		$card->setBillingPostcode($billingAddress->zipCode);
		$card->setBillingState($billingAddress->getStateText());
		$card->setBillingCountry($billingAddress->country->name);
		$card->setBillingPhone($billingAddress->phone);

		$shippingAddress = $order->shippingAddress;
		$card->setShippingAddress1($shippingAddress->address1);
		$card->setShippingAddress2($shippingAddress->address2);
		$card->setShippingPostcode($shippingAddress->zipCode);
		$card->setShippingState($shippingAddress->getStateText());
		$card->setShippingCountry($shippingAddress->country->name);
		$card->setShippingPhone($shippingAddress->phone);
		$card->setCompany($shippingAddress->company);

		$user = craft()->userSession->getUser();
		$card->setEmail($user ? $user->email : '');

		return $card;
	}

    /**
     * @param Market_TransactionModel $transaction
     * @param CreditCard $card
     * @return array
     */
    private function buildPaymentRequest(Market_TransactionModel $transaction, CreditCard $card = null)
    {
        $request = [
            'amount'        => $transaction->amount,
            'currency'      => craft()->market_settings->getOption('defaultCurrency'),
            'transactionId' => $transaction->id,
            'clientIp'      => craft()->request->getIpAddress(),
            'returnUrl'     => UrlHelper::getActionUrl('market/cartPayment/complete', ['id' => $transaction->id, 'hash' => $transaction->hash]),
            'cancelUrl'     => UrlHelper::getSiteUrl($transaction->order->cancelUrl),
        ];
        if($card) {
            $request['card'] = $card;
        }
        return $request;
    }

    /**
     * @param Market_TransactionModel $child
     * @throws Exception
     */
    private function saveTransaction($child)
    {
        if (!craft()->market_transaction->save($child)) {
            throw new Exception('Error saving transaction: ' . implode(', ', $child->getAllErrors()));
        }
    }
}