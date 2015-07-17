<?php

namespace Craft;

use Market\Helpers\MarketDbHelper;

/**
 * Cart is the same as Order. This class deals with order as with cart. All
 * saving logic and etc. are in OrderService
 *
 * @package Craft
 */
class Market_CartService extends BaseApplicationComponent
{
    const CART_COOKIE_LIFETIME = 604800; //week

    /** @var string Session key for storing current cart number */
    protected $cookieCartId = 'market_cookie';
    /** @var Market_OrderModel */
    private $cart;

    /**
     * @param Market_OrderModel $order
     * @param int               $purchasableId
     * @param int               $qty
     * @param string            $error
     *
     * @return bool
     * @throws \Exception
     */
    public function addToCart($order, $purchasableId, $qty = 1, &$error = '')
    {
        MarketDbHelper::beginStackedTransaction();

        //saving current cart if it's new and empty
        if (!$order->id) {
            if (!craft()->market_order->save($order)) {
                throw new Exception(Craft::t('Error on creating empty cart'));
            }
        }

        //filling item model
        $lineItem = craft()->market_lineItem->getByOrderPurchasable($order->id,
            $purchasableId);

        if ($lineItem->id) {
            $lineItem->qty += $qty;
        } else {
            $lineItem = craft()->market_lineItem->create($purchasableId,
                $order->id, $qty);
        }

        try {
            if (craft()->market_lineItem->save($lineItem)) {
                craft()->market_order->save($order);
                MarketDbHelper::commitStackedTransaction();

                //raising event
                $event = new Event($this, [
                    'lineItem' => $lineItem,
                    'order'    => $order,
                ]);
                $this->onAddToCart($event);

                return true;
            }
        } catch (\Exception $e) {
            MarketDbHelper::rollbackStackedTransaction();
            throw $e;
        }

        MarketDbHelper::rollbackStackedTransaction();

        $errors = $lineItem->getAllErrors();
        $error  = array_pop($errors);

        return false;
    }

    /**
     * Event method.
     * Event params: order(Market_OrderModel), lineItem (Market_LineItemModel)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onAddToCart(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['order']) || !($params['order'] instanceof Market_OrderModel)) {
            throw new Exception('onAddToCart event requires "order" param with OrderModel instance');
        }

        if (empty($params['lineItem']) || !($params['lineItem'] instanceof Market_LineItemModel)) {
            throw new Exception('onAddToCart event requires "lineItem" param with LineItemModel instance');
        }
        $this->raiseEvent('onAddToCart', $event);
    }

    /**
     * @param string $orderTypeHandle
     *
     * @return Market_OrderModel
     * @throws \Exception
     */
    public function getCart($orderTypeHandle)
    {
        //checking handle and getting orderType model
        if (empty($orderTypeHandle)) {
            MarketPlugin::log('Empty order type handle passed to getCart()');
            throw new Exception('Empty orderTypeHandle passed to getCart()');
        }

        $orderType = craft()->market_orderType->getByHandle($orderTypeHandle);
        if (!$orderType->id) {
            MarketPlugin::log("Can not find Order Type by handle '$orderTypeHandle'");
            throw new Exception("Can not find Order Type by handle '$orderTypeHandle'");
        }

        // Should only be dealing with legit order types now
        if (!isset($this->cart[$orderType->handle])) {
            $number = $this->_getSessionCartNumber($orderType->handle);

            if ($cart = $this->_getCartRecordByNumber($number)) {
                $this->cart[$orderType->handle] = Market_OrderModel::populateModel($cart);
            } else {
                $this->cart[$orderType->handle]         = new Market_OrderModel;
                $this->cart[$orderType->handle]->typeId = $orderType->id;
                $this->cart[$orderType->handle]->number = $number;
            }

            $this->cart[$orderType->handle]->lastIp = craft()->request->getIpAddress();

            // Update the cart if the customer has changed and recalculate the cart.
            $customer = craft()->market_customer->getCustomer();
            if($customer->id){
                if (!$this->cart[$orderType->handle]->isEmpty() && $this->cart[$orderType->handle]->customerId != $customer->id) {
                    $this->cart[$orderType->handle]->customerId = $customer->id;
                    $this->cart[$orderType->handle]->email = $customer->email;
                    $this->cart[$orderType->handle]->billingAddressId = null;
                    $this->cart[$orderType->handle]->shippingAddressId = null;
                    $this->cart[$orderType->handle]->billingAddressData = null;
                    $this->cart[$orderType->handle]->shippingAddressData = null;
                    craft()->market_order->save($this->cart[$orderType->handle]);
                }
            }


        }
        return $this->cart[$orderType->handle];
    }

    /**
     * @param string $cartHandle
     *
     * @return string
     */
    private function _getSessionCartNumber($cartHandle)
    {
        $cookieId   = $cartHandle . "_" . $this->cookieCartId;
        $cartNumber = craft()->userSession->getStateCookieValue($cookieId);

        if (!$cartNumber) {
            $cartNumber = md5(uniqid(mt_rand(), true));
            craft()->userSession->saveCookie($cookieId, $cartNumber,
                    self::CART_COOKIE_LIFETIME);
        }

        return $cartNumber;
    }

    /**
     * @param string $number
     *
     * @return Market_OrderRecord
     */
    private function _getCartRecordByNumber($number)
    {
        $cart = Market_OrderRecord::model()->findByAttributes([
            'number'      => $number,
            'completedAt' => null,
        ]);

        return $cart;
    }

    /**
     * Forgets a Cart by deleting its cookie.
     *
     * @param Market_OrderModel $cart
     */
    public function forgetCart(Market_OrderModel $cart)
    {
        $cookieId = $cart->type->handle . '_' . $this->cookieCartId;
        craft()->userSession->deleteStateCookie($cookieId);
    }

    /**
     * @param Market_OrderModel $cart
     * @param string            $code
     * @param string            $error
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function applyCoupon(Market_OrderModel $cart, $code, &$error = '')
    {
        if (empty($code) || craft()->market_discount->checkCode($code,
                $cart->customerId, $error)
        ) {
            $cart->couponCode = $code ?: null;
            craft()->market_order->save($cart);

            return true;
        } else {
            return false;
        }
    }

    /**
     * Set shipping method to the current order
     *
     * @param Market_OrderModel $cart
     * @param int               $shippingMethodId
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function setShippingMethod(
        Market_OrderModel $cart,
        $shippingMethodId
    ) {
        $method = craft()->market_shippingMethod->getById($shippingMethodId);
        if (!$method->id) {
            return false;
        }

        if (!craft()->market_shippingMethod->getMatchingRule($cart, $method)) {
            return false;
        }

        $cart->shippingMethodId = $shippingMethodId;
        craft()->market_order->save($cart);

        return true;
    }

    /**
     * Set shipping method to the current order
     *
     * @param Market_OrderModel $cart
     * @param int               $paymentMethodId
     *
     * @return bool
     * @throws \Exception
     */
    public function setPaymentMethod(Market_OrderModel $cart, $paymentMethodId)
    {
        $method = craft()->market_paymentMethod->getById($paymentMethodId);
        if (!$method->id || !$method->frontendEnabled) {
            return false;
        }

        $cart->paymentMethodId = $paymentMethodId;
        craft()->market_order->save($cart);

        return true;
    }

    /**
     * @TODO check that line item belongs to the current user
     *
     * @param Market_OrderModel $cart
     * @param int               $lineItemId
     *
     * @throws Exception
     * @throws \Exception
     */
    public function removeFromCart(Market_OrderModel $cart, $lineItemId)
    {
        $lineItem = craft()->market_lineItem->getById($lineItemId);

        if (!$lineItem->id) {
            throw new Exception('Line item not found');
        }

        MarketDbHelper::beginStackedTransaction();
        try {
            craft()->market_lineItem->delete($lineItem);

            craft()->market_order->save($cart);

            //raising event
            $event = new Event($this, [
                'lineItemId' => $lineItemId,
                'order'      => $cart
            ]);
            $this->onRemoveFromCart($event);

        } catch (\Exception $e) {
            MarketDbHelper::rollbackStackedTransaction();
            throw $e;
        }

        MarketDbHelper::commitStackedTransaction();
    }

    /**
     * Event method.
     * Event params: order(Market_OrderModel), lineItemId (int)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onRemoveFromCart(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['order']) || !($params['order'] instanceof Market_OrderModel)) {
            throw new Exception('onRemoveFromCart event requires "order" param with OrderModel instance');
        }

        if (empty($params['lineItemId']) || !is_numeric($params['lineItemId'])) {
            throw new Exception('onRemoveFromCart event requires "lineItemId" param');
        }
        $this->raiseEvent('onRemoveFromCart', $event);
    }

    /**
     * Remove all items from a cart
     *
     * @param Market_OrderModel $cart
     *
     * @throws \Exception
     */
    public function clearCart(Market_OrderModel $cart)
    {
        MarketDbHelper::beginStackedTransaction();
        try {
            craft()->market_lineItem->deleteAllByOrderId($cart->id);
            craft()->market_order->save($cart);
        } catch (\Exception $e) {
            MarketDbHelper::rollbackStackedTransaction();
            throw $e;
        }

        MarketDbHelper::commitStackedTransaction();
    }

}
