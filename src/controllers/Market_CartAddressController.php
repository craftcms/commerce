<?php
namespace Craft;

/**
 * Cart. Step "Address".
 *
 * Class Market_CartAddressController
 *
 * @package Craft
 */
class Market_CartAddressController extends Market_BaseController
{
    protected $allowAnonymous = true;

    /**
     * Posting two new addresses in case when a user has no saved address
     *
     * @throws HttpException
     * @throws \Exception
     */
    public function actionPostTwoAddresses()
    {
        $this->requirePostRequest();

        $billing             = new Market_AddressModel;
        $billing->attributes = craft()->request->getPost('BillingAddress');

        if (craft()->request->getPost('sameAddress') == 1) {
            $shipping = $billing;
        } else {
            $shipping             = new Market_AddressModel;
            $shipping->attributes = craft()->request->getPost('ShippingAddress');
        }

        $order = craft()->market_cart->getCart();

        if (craft()->market_order->setAddresses($order, $shipping, $billing)) {
            $this->redirectToPostedUrl();
        } else {
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
    public function actionSetShippingMethod()
    {
        $this->requirePostRequest();

        $id              = craft()->request->getPost('shippingMethodId');
        $cart            = craft()->market_cart->getCart();

        if (craft()->market_cart->setShippingMethod($cart, $id)) {
            craft()->userSession->setFlash('notice',Craft::t('Shipping method has been set'));
            $this->redirectToPostedUrl();
        } else {
            craft()->userSession->setFlash('error',Craft::t('Wrong shipping method'));
        }
    }

    /**
     * Choose Addresses
     *
     * @throws HttpException
     * @throws \CHttpException
     * @throws \Exception
     */
    public function actionChooseAddresses()
    {
        $this->requirePostRequest();

        $billingId      = craft()->request->getPost('billingAddressId');
        $billingAddress = craft()->market_address->getById($billingId);

        if (craft()->request->getPost('sameAddress') == 1) {
            $shippingAddress = $billingAddress;
        } else {
            $shippingId      = craft()->request->getPost('shippingAddressId');
            $shippingAddress = craft()->market_address->getById($shippingId);
        }

        $order           = craft()->market_cart->getCart();

        if (!$billingAddress->id || !$shippingAddress->id) {
            if (empty($billingAddress->id)) {
                $order->addError('billingAddressId',
                    'Choose please billing address');
            }
            if (empty($shippingAddress->id)) {
                $order->addError('shippingAddressId',
                    'Choose please shipping address');
            }

            return;
        }

        if (craft()->market_order->setAddresses($order, $shippingAddress,
            $billingAddress)
        ) {
            $this->redirectToPostedUrl();
        }
    }

    /**
     * Add New Address
     *
     * @throws Exception
     * @throws HttpException
     */
    public function actionAddAddress()
    {
        $this->requirePostRequest();

        $address             = new Market_AddressModel;
        $address->attributes = craft()->request->getPost('Address');

        if (!craft()->market_customer->saveAddress($address)) {
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
    public function actionRemoveAddress()
    {
        $this->requirePostRequest();

        $id = craft()->request->getPost('id', 0);

        if (!$id) {
            throw new HttpException(400);
        }

        craft()->market_address->deleteById($id);
    }
}