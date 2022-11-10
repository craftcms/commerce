<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use craft\commerce\elements\Order;
use craft\commerce\events\ModifyCartInfoEvent;

/**
 * Class BaseFrontEndController
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class BaseFrontEndController extends BaseController
{
    /**
     * @event Event The event that’s triggered when a cart is returned as an array for Ajax cart update requests.
     *
     * ---
     * ```php
     * use craft\commerce\controllers\BaseFrontEndController;
     * use craft\commerce\events\ModifyCartInfoEvent;
     * use yii\base\Event;
     *
     * Event::on(
     *     BaseFrontEndController::class,
     *     BaseFrontEndController::EVENT_MODIFY_CART_INFO,
     *     function(ModifyCartInfoEvent $e) {
     *         $cartArray = $e->cartInfo;
     *         $cartArray['anotherOne'] = 'Howdy';
     *         $e->cartInfo = $cartArray;
     *     }
     * );
     * ```
     */
    public const EVENT_MODIFY_CART_INFO = 'modifyCartInfo';


    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = true;

    protected function cartArray(Order $cart): array
    {
        $extraFields = [
            'availableShippingMethodOptions',
            'billingAddress',
            'lineItems.snapshot',
            'notices',
            'shippingAddress',
        ];

        $cartInfo = $cart->toArray([], $extraFields);

        // Fire a 'modifyCartContent' event
        $event = new ModifyCartInfoEvent([
            'cartInfo' => $cartInfo,
            'cart' => $cart,
        ]);

        $this->trigger(self::EVENT_MODIFY_CART_INFO, $event);

        return $event->cartInfo;
    }
}
