<?php
namespace Craft;

/**
 * Class Market_CartController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Market_CartController extends Market_BaseController
{
    protected $allowAnonymous = true;

    /**
     * Add a purchasable into the cart
     *
     * @throws Exception
     * @throws HttpException
     * @throws \Exception
     */
    public function actionAdd()
    {
        $this->requirePostRequest();

        $purchasableId   = craft()->request->getPost('purchasableId');
        $qty             = craft()->request->getPost('qty', 1);
        /** @var Market_OrderModel $cart */
        $cart            = craft()->market_cart->getCart();
        $cart->setContentFromPost('fields');

        if (craft()->market_cart->addToCart($cart, $purchasableId, $qty, $error)) {
            if(craft()->request->isAjaxRequest){
                $this->returnJson(['success'=>true,'cart'=>$cart->toArray()]);
            }
            craft()->userSession->setFlash('notice', Craft::t('Product has been added'));
            $this->redirectToPostedUrl();
        } else {
            if(craft()->request->isAjaxRequest){
                $this->returnJson(['error'=>$error]);
            }
            craft()->userSession->setFlash('error', $error);
        }
    }

    /**
     * Update quantity
     *
     * @throws Exception
     * @throws HttpException
     */
    public function actionUpdateLineItem()
    {
        $this->requirePostRequest();

        $cart = craft()->market_cart->getCart();
        $lineItemId = craft()->request->getPost('lineItemId');
        $qty        = craft()->request->getPost('qty', 0);
        $note        = craft()->request->getPost('note');

        $lineItem = craft()->market_lineItem->getById($lineItemId);

        // Only let them update their own cart's line item.
        if (!$lineItem->id || $cart->id != $lineItem->order->id){
            throw new Exception(Craft::t('Line item not found for current cart'));
        }

        $lineItem->qty = $qty;
        $lineItem->note = $note;
        $lineItem->order->setContentFromPost('fields');

        if (craft()->market_lineItem->update($lineItem, $error)) {
            craft()->userSession->setFlash('notice',Craft::t('Order item has been updated'));
            if(craft()->request->isAjaxRequest){
                $this->returnJson(['success'=>true,'cart'=>$cart->toArray()]);
            }
            $this->redirectToPostedUrl();
        } else {
            if(craft()->request->isAjaxRequest){
                $this->returnJson(['error'=>$error]);
            }
            craft()->userSession->setFlash('error',$error);
        }
    }

    /**
     * @throws HttpException
     */
    public function actionApplyCoupon()
    {
        $this->requirePostRequest();

        $code            = craft()->request->getPost('couponCode');
        $cart            = craft()->market_cart->getCart();
        $cart->setContentFromPost('fields');

        if (craft()->market_cart->applyCoupon($cart, $code, $error)) {
            if(craft()->request->isAjaxRequest){
                $this->returnJson(['success'=>true]);
            }
            craft()->userSession->setFlash('info', Craft::t('Coupon has been applied'));
            $this->redirectToPostedUrl();
        } else {
            if(craft()->request->isAjaxRequest){
                $this->returnJson(['success'=>true,'cart'=>$cart->toArray()]);
            }
            craft()->userSession->setFlash('error', $error);
        }
    }


    /**
     * Sets the email on the cart. Also updates the current users email.
     *
     */
    public function actionSetEmail()
    {
        $this->requirePostRequest();

        $email = craft()->request->getPost('email');

        $validator = new \CEmailValidator;
        $validator->allowEmpty = false;

        if($validator->validateValue($email)){
            if(craft()->userSession->isGuest){
                $cart            = craft()->market_cart->getCart();
                $cart->customerId = craft()->market_customer->getCustomerId();
                $customer = craft()->market_customer->getCustomer();
                $customer->email = $email;
                craft()->market_customer->save($customer);

                if (craft()->market_order->save($cart)){
                    if(craft()->request->isAjaxRequest){
                        $this->returnJson(['success'=>true,'cart'=>$cart->toArray()]);
                    }
                    $this->redirectToPostedUrl();
                }
            }
        }else{
            $error = Craft::t('Email Not Valid');
            if(craft()->request->isAjaxRequest){
                $this->returnJson(['error'=>$error]);
            }
            craft()->userSession->setFlash('error',$error);
        }

    }


    /**
     * @throws HttpException
     * @throws \Exception
     */
    public function actionSetPaymentMethod()
    {
        $this->requirePostRequest();

        $id              = craft()->request->getPost('paymentMethodId');
        $cart            = craft()->market_cart->getCart();

        if (craft()->market_cart->setPaymentMethod($cart, $id)) {
            if(craft()->request->isAjaxRequest){
                $this->returnJson(['success'=>true,'cart'=>$cart->toArray()]);
            }
            craft()->userSession->setFlash('notice', Craft::t('Payment method has been set'));
            $this->redirectToPostedUrl();
        } else {
            $msg = Craft::t('Wrong payment method');
            if(craft()->request->isAjaxRequest){
                $this->returnJson(['error'=>$msg]);
            }
            craft()->userSession->setFlash('notice',$msg);
        }
    }

    /**
     * Remove Line item from the cart
     */
    public function actionRemove()
    {
        $this->requirePostRequest();

        $lineItemId      = craft()->request->getPost('lineItemId');
        $cart            = craft()->market_cart->getCart();

        $lineItem = craft()->market_lineItem->getById($lineItemId);

        // Only let them update their own cart's line item.
        if (!$lineItem->id || $cart->id != $lineItem->order->id){
            throw new Exception(Craft::t('Line item not found for current cart'));
        }

        craft()->market_cart->removeFromCart($cart, $lineItemId);
        if(craft()->request->isAjaxRequest){
            $this->returnJson(['success'=>true,'cart'=>$cart->toArray()]);
        }
        craft()->userSession->setFlash('notice', Craft::t('Product has been removed'));
        $this->redirectToPostedUrl();
    }

    /**
     * Remove all line items from the cart
     */
    public function actionRemoveAll()
    {
        $this->requirePostRequest();

        $cart = craft()->market_cart->getCart();

        craft()->market_cart->clearCart($cart);
        if(craft()->request->isAjaxRequest){
            $this->returnJson(['success'=>true,'cart'=>$cart->toArray()]);
        }
        craft()->userSession->setFlash('notice',Craft::t('All products have been removed'));
        $this->redirectToPostedUrl();
    }
}