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

            craft()->market_customer->setLastUsedAddresses($billing->id,$shipping->id);

            if(craft()->request->isAjaxRequest){
                $this->returnJson(['success'=>true,'cart'=>$order->toArray()]);
            }
            $this->redirectToPostedUrl();
        } else {
            if(craft()->request->isAjaxRequest){
                $this->returnJson(['error'=>$billing->getAllErrors()]);
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
    public function actionSetShippingMethod()
    {
        $this->requirePostRequest();

        $id              = craft()->request->getPost('shippingMethodId');
        $cart            = craft()->market_cart->getCart();

        if (craft()->market_cart->setShippingMethod($cart, $id)) {
            if(craft()->request->isAjaxRequest){
                $this->returnJson(['success'=>true,'cart'=>$cart->toArray()]);
            }
            craft()->userSession->setFlash('notice',Craft::t('Shipping method has been set'));
            $this->redirectToPostedUrl();
        } else {
            $error = Craft::t('Wrong shipping method');
            if(craft()->request->isAjaxRequest){
                $this->returnJson(['error'=>$error]);
            }
            craft()->userSession->setFlash('error',$error);
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
        $billingAddress = craft()->market_address->getAddressById($billingId);

        if (craft()->request->getPost('sameAddress') == 1) {
            $shippingAddress = $billingAddress;
        } else {
            $shippingId      = craft()->request->getPost('shippingAddressId');
            $shippingAddress = craft()->market_address->getAddressById($shippingId);
        }

        $order = craft()->market_cart->getCart();

        if (!$billingAddress->id || !$shippingAddress->id) {
            if (empty($billingAddress->id)) {
                craft()->userSession->setFlash('error',Craft::t('Please choose a billing address'));
            }
            if (empty($shippingAddress->id)) {
                craft()->userSession->setFlash('error',Craft::t('Please choose a shipping address'));
            }
            return;
        }

        $customerId = craft()->market_customer->getCustomerId();
        $addressIds = craft()->market_customer->getAddressIds($customerId);

        if (in_array($billingAddress->id,$addressIds) && in_array($shippingAddress->id,$addressIds)) {
            if (craft()->market_order->setAddresses($order, $shippingAddress, $billingAddress)) {
                craft()->market_customer->setLastUsedAddresses($billingAddress->id,$shippingAddress->id);
                if(craft()->request->isAjaxRequest){
                    $this->returnJson(['success'=>true,'cart'=>$order->toArray()]);
                }
                $this->redirectToPostedUrl();
            }
        }else{
            if(craft()->request->isAjaxRequest){
                $this->returnJson(['error'=>Craft::t('Choose addresses that are yours.')]);
            }
            craft()->userSession->setFlash('error',Craft::t('Choose addresses that are yours.'));
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

        $customerId = craft()->market_customer->getCustomerId();
        $addressIds = craft()->market_customer->getAddressIds($customerId);

        // if this is an existing address
        if($address->id){
            if (!in_array($address->id,$addressIds)){
                $error = Craft::t('Not allowed to edit that address.');
                if(craft()->request->isAjaxRequest){
                    $this->returnJson(['error'=>$error]);
                }
                craft()->userSession->setFlash('error',$error);
                return;
            }
        }

        if (craft()->market_customer->saveAddress($address)) {
            if(craft()->request->isAjaxRequest){
                $this->returnJson(['success'=>true]);
            }
            $this->redirectToPostedUrl();
        }else{
            if(craft()->request->isAjaxRequest){
                $this->returnJson(['error'=>$address->getAllErrors()]);
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
    public function actionRemoveAddress()
    {
        $this->requirePostRequest();

        $customerId = craft()->market_customer->getCustomerId();
        $addressIds = craft()->market_customer->getAddressIds($customerId);

        $id = craft()->request->getPost('id', 0);

        if (!$id) {
            throw new HttpException(400);
        }

        // current customer is the owner of the address
        if (in_array($id,$addressIds)){
            if(craft()->market_address->deleteAddressById($id)){
                if(craft()->request->isAjaxRequest){
                    $this->returnJson(['success'=>true]);
                }
                $this->redirectToPostedUrl();
            }
            craft()->userSession->setFlash('notice',Craft::t('Address removed.'));
        }else{
            $error = Craft::t('Not allowed to remove that address.');
            if(craft()->request->isAjaxRequest){
                $this->returnJson(['error'=>$error]);
            }
            craft()->userSession->setFlash('error',$error);
        }
    }
}