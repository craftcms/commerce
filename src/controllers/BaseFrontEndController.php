<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use craft\commerce\elements\Order;
use craft\commerce\events\ModifyCartInfoEvent;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingMethod;

/**
 * Class BaseFrontEndController
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class BaseFrontEndController extends BaseController
{
    /**
     * @event Event The event that is triggered when an cart is returned as an array (for ajax cart update requests)
     *
     * ---
     * ```php
     * use craft\commerce\controllers\BaseFrontEndController;
     * use craft\commerce\events\ModifyCartInfoEvent;
     * use yii\base\Event;
     *
     * Event::on(BaseFrontEndController::class, BaseFrontEndController::EVENT_MODIFY_CART_INFO, function(ModifyCartInfoEvent $e) {
     *     $cartArray = $e->cartInfo;
     *     $cartArray['anotherOne'] = 'Howdy';
     *     $e->cartInfo = $cartArray;
     * });
     * ```
     */
    const EVENT_MODIFY_CART_INFO = 'modifyCartInfo';


    /**
     * @inheritdoc
     */
    protected $allowAnonymous = true;


    /**
     * @param Order $cart
     * @return array
     */
    protected function cartArray(Order $cart): array
    {
        // Typecast order attributes
        $cart->typeCastAttributes();

        $extraFields = [
            'lineItems.snapshot',
            'availableShippingMethodOptions',
        ];

        $cartInfo = $cart->toArray([], $extraFields);

        // This is to avoid deprecation error with calling `getAvailableShippingMethods` method on the order
        // TODO Remove this at 4.0
        $availableShippingMethods =  Plugin::getInstance()->getShippingMethods()->getAvailableShippingMethods($cart);
        $cartInfo['availableShippingMethods'] = $availableShippingMethods;

        // Fire a 'modifyCartContent' event
        $event = new ModifyCartInfoEvent([
            'cartInfo' => $cartInfo,
            'cart' => $cart
        ]);

        $this->trigger(self::EVENT_MODIFY_CART_INFO, $event);

        return $event->cartInfo;
    }
}
