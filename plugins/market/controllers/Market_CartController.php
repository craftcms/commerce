<?php
namespace Craft;

/**
 * Class Market_CartController
 *
 * @package Craft
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
        $cart            = craft()->market_cart->getCart();
        $cart->setContentFromPost('fields');

        if (craft()->market_cart->addToCart($cart, $purchasableId, $qty,
            $error)
        ) {
            craft()->userSession->setFlash('notice', Craft::t('Product has been added'));
            $this->redirectToPostedUrl();
        } else {
            craft()->userSession->setFlash('error',$error);
        }
    }

    /**
     * Update quantity
     *
     * @throws Exception
     * @throws HttpException
     */
    public function actionUpdateQty()
    {
        $this->requirePostRequest();

        $lineItemId = craft()->request->getPost('lineItemId');
        $qty        = craft()->request->getPost('qty', 0);

        $lineItem = craft()->market_lineItem->getById($lineItemId);
        if (!$lineItem->id) {
            throw new Exception(Craft::t('Line item not found'));
        }

        $lineItem->qty = $qty;
        $lineItem->order->setContentFromPost('fields');

        if (craft()->market_lineItem->update($lineItem, $error)) {
            craft()->userSession->setFlash('notice',Craft::t('Product quantity has been updated'));
            $this->redirectToPostedUrl();
        } else {
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
            craft()->userSession->setFlash('info', Craft::t('Coupon has been applied'));
            $this->redirectToPostedUrl();
        } else {
            craft()->userSession->setFlash('error', $error);
        }
    }


    /**
     *
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
                    $this->redirectToPostedUrl();
                }
            }
        }else{
            craft()->userSession->setFlash('notice',Craft::t('Email Not Valid'));
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
            craft()->userSession->setFlash('notice', Craft::t('Payment method has been set'));
            $this->redirectToPostedUrl();
        } else {
            craft()->userSession->setFlash('notice',Craft::t('Wrong payment method'));
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

        craft()->market_cart->removeFromCart($cart, $lineItemId);
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
        craft()->userSession->setFlash('notice',Craft::t('All products have been removed'));
        $this->redirectToPostedUrl();
    }
}