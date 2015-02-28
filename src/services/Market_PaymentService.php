<?php

namespace Craft;

use Omnipay\Common\CreditCard;
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
	 * @param Market_PaymentFormModel $form
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function processPayment(Market_PaymentFormModel $form, &$customError = '')
	{
		if (!$form->validate()) {
			return false;
		}

        $defaultAction = craft()->market_settings->getOption('paymentMethod');
        $defaultAction = ($defaultAction === Market_TransactionRecord::PURCHASE) ? $defaultAction : Market_TransactionRecord::AUTHORIZE;

        $cart    = craft()->market_cart->getCart();
        $gateway = $cart->paymentMethod->getGateway();

        if($defaultAction == Market_TransactionRecord::AUTHORIZE) {
            if (!$gateway->supportsAuthorize()) {
                $customError = 'Gateway doesn\'t support authorize';
                return false;
            }
        } else {
            if (!$gateway->supportsPurchase()) {
                $customError = 'Gateway doesn\'t support purchase';
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
			$redirect = $this->sendPaymentRequest($request, $transaction);
		} catch (\Exception $e) {
			$customError = $e->getMessage();
			craft()->market_transaction->delete($transaction);

			return false;
		}

		craft()->request->redirect($redirect);

		return true;
	}

	/**
	 * Send a payment request to the gateway, and redirect appropriately
	 *
	 * @param RequestInterface        $request
	 * @param Market_TransactionModel $transaction
	 *
	 * @return string
	 */
	private function sendPaymentRequest(RequestInterface $request, Market_TransactionModel $transaction)
	{
		// try {
		/** @var ResponseInterface $response */
		$response = $request->send();
		$this->updateTransaction($transaction, $response);

		if ($response->isRedirect()) {
			// redirect to off-site gateway
			return $response->redirect();
		}

		// exception required for SagePay Server
//        if (method_exists($response, 'confirm')) {
//            $response->confirm($this->buildReturnUrl($transaction));
//        }
		// } catch (\Exception $e) {
		//     $transaction->status = Market_TransactionModel::FAILED;
		//     //$transaction->message = lang('store.payment.communication_error');
		//     $transaction->message = 'store.payment.communication_error';
		//     $transaction->save();
		// }

//        $gateways_which_call_us_directly = [
//            'AuthorizeNet_SIM',
//            'Realex_Redirect',
//            'SecurePay_DirectPost',
//            'WorldPay',
//        ];
//        if (in_array($transaction->paymentMethod, $gateways_which_call_us_directly)) {
//            // send the customer's browser to our return URL instead of letting the
//            // gateway display the page directly to the customer, otherwise they
//            // end up on our payment failed or order complete page without their
//            // session cookie which obviously won't work
//            $this->redirectForm($this->buildReturnUrl($transaction));
//        }

		if ($response->isSuccessful()) {
			return $this->getReturnUrl($transaction);
		} else {
			craft()->userSession->setError(Craft::t("Couldn't save payment : " . $transaction->message));

			return $this->getCancelUrl($transaction);
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
	 * @param Market_TransactionModel $transaction
	 * @param ResponseInterface       $response
	 *
	 * @throws Exception
	 */
	private function updateTransaction(Market_TransactionModel &$transaction, ResponseInterface $response)
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
	 *
	 * @return string
	 */
	private function getReturnUrl(Market_TransactionModel $transaction)
	{
		return UrlHelper::getActionUrl('market/cartPayment/success', ['id' => $transaction->id, 'hash' => $transaction->hash]);
	}

	/**
	 * @param Market_TransactionModel $transaction
	 *
	 * @return string
	 */
	private function getCancelUrl(Market_TransactionModel $transaction)
	{
		return UrlHelper::getActionUrl('market/cartPayment/cancel', ['id' => $transaction->id, 'hash' => $transaction->hash]);
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
            'currency'      => 'USD', //TODO refine
            'transactionId' => $transaction->id,
            'clientIp'      => craft()->request->getIpAddress(),
            'returnUrl'     => $this->getReturnUrl($transaction),
            'cancelUrl'     => $this->getCancelUrl($transaction),
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