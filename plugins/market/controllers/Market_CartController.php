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
        $orderTypeHandle = craft()->request->getPost('orderTypeHandle');
        $cart            = craft()->market_cart->getCart($orderTypeHandle);
        $cart->setContentFromPost('fields');

        if (craft()->market_cart->addToCart($cart, $purchasableId, $qty,
            $error)
        ) {
            craft()->userSession->setFlash('market', 'Product has been added');
            $this->redirectToPostedUrl();
        } else {
            craft()->urlManager->setRouteVariables(['error' => $error]);
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
            throw new Exception('Line item not found');
        }

        $lineItem->qty = $qty;
        $lineItem->order->setContentFromPost('fields');

        if (craft()->market_lineItem->update($lineItem, $error)) {
            craft()->userSession->setFlash('market',
                'Product quantity has been updated');
            $this->redirectToPostedUrl();
        } else {
            craft()->urlManager->setRouteVariables(['error' => $error]);
        }
    }

    /**
     * @throws HttpException
     */
    public function actionApplyCoupon()
    {
        $this->requirePostRequest();

        $code            = craft()->request->getPost('couponCode');
        $orderTypeHandle = craft()->request->getPost('orderTypeHandle');
        $cart            = craft()->market_cart->getCart($orderTypeHandle);
        $cart->setContentFromPost('fields');

        if (craft()->market_cart->applyCoupon($cart, $code, $error)) {
            craft()->userSession->setFlash('market', 'Coupon has been applied');
            $this->redirectToPostedUrl();
        } else {
            craft()->userSession->setFlash('error', $error);
            craft()->urlManager->setRouteVariables(['couponError' => $error]);
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
        $orderTypeHandle = craft()->request->getPost('orderTypeHandle');
        $cart            = craft()->market_cart->getCart($orderTypeHandle);

        if (craft()->market_cart->setPaymentMethod($cart, $id)) {
            craft()->userSession->setFlash('market',
                'Payment method has been set');
            $this->redirectToPostedUrl();
        } else {
            craft()->urlManager->setRouteVariables(['paymentMethodError' => 'Wrong payment method']);
        }
    }

    /**
     * Remove Line item from the cart
     */
    public function actionRemove()
    {
        $this->requirePostRequest();

        $lineItemId      = craft()->request->getPost('lineItemId');
        $orderTypeHandle = craft()->request->getPost('orderTypeHandle');
        $cart            = craft()->market_cart->getCart($orderTypeHandle);

        craft()->market_cart->removeFromCart($cart, $lineItemId);
        craft()->userSession->setFlash('market', 'Product has been removed');
        $this->redirectToPostedUrl();
    }

    /**
     * Remove all line items from the cart
     */
    public function actionRemoveAll()
    {
        $this->requirePostRequest();

        $orderTypeHandle = craft()->request->getPost('orderTypeHandle');
        $cart            = craft()->market_cart->getCart($orderTypeHandle);

        craft()->market_cart->clearCart($cart);
        craft()->userSession->setFlash('market',
            'All products have been removed');
        $this->redirectToPostedUrl();
    }
}