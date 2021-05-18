<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\events\PurchasableShippableEvent;
use craft\elements\User;
use craft\events\RegisterComponentTypesEvent;
use craft\commerce\events\PurchasableAvailableEvent;
use yii\base\BaseObject;
use yii\base\Component;

/**
 * Product type service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 *
 * @property array|string[] $allPurchasableElementTypes
 */
class Purchasables extends Component
{
    /**
     * @event PurchasableAvailableEvent The event that is triggered when the availability of a purchasables is checked.
     *
     * This example stop users of a certain group from having the purchasable be available to them in their order.
     *
     * ```php
     * use craft\commerce\events\PurchasableAvailableEvent;
     * use craft\commerce\services\Purchasables;
     * use yii\base\Event;
     *
     * Event::on(
     *     Purchasables::class,
     *     Purchasables::EVENT_PURCHASABLE_AVAILABLE,
     *     function(PurchasableAvailableEvent $event) {
     *         if($order && $user = $order->getUser()){
     *             $event->isAvailable = $event->isAvailable && !$user->isInGroup(1); // Group ID 1 not allowed to have purchasable in the cart.
     *         }
     *     }
     * );
     * ```
     */
    const EVENT_PURCHASABLE_AVAILABLE = 'purchasableAvailable';

    /**
     * @event PurchasableShippableEvent The event that is triggered when determining whether a purchasable may be shipped.
     *
     * This example stop users of a certain group from having the purchasable be shippable to them in their order.
     *
     * ```php
     * use craft\commerce\events\PurchasableShippableEvent;
     * use craft\commerce\services\Purchasables;
     * use yii\base\Event;
     *
     * Event::on(
     *     Purchasables::class,
     *     Purchasables::EVENT_PURCHASABLE_SHIPPABLE,
     *     function(PurchasableShippableEvent $event) {
     *         if($order && $user = $order->getUser()){
     *             $event->isShippable = $event->is && !$user->isInGroup(1); // Group ID 1 not allowed to have purchasable in the cart.
     *         }
     *     }
     * );
     * ```
     */
    const EVENT_PURCHASABLE_SHIPPABLE = 'purchasableShippable';

    /**
     * @event RegisterComponentTypesEvent The event that is triggered for registration of additional purchasables.
     *
     * This example adds an instance of `MyPurchasable` to the event object’s `types` array:
     *
     * ```php
     * use craft\events\RegisterComponentTypesEvent;
     * use craft\commerce\services\Purchasables;
     * use yii\base\Event;
     *
     * Event::on(
     *     Purchasables::class,
     *     Purchasables::EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES,
     *     function(RegisterComponentTypesEvent $event) {
     *         $event->types[] = MyPurchasable::class;
     *     }
     * );
     * ```
     */
    const EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES = 'registerPurchasableElementTypes';

    /**
     * @param PurchasableInterface $purchasable
     * @param Order|null $order
     * @param User|null $currentUser
     * @return bool
     * @since 3.3.1
     */
    public function isPurchasableAvailable(PurchasableInterface $purchasable, Order $order = null, User $currentUser = null): bool
    {
        if ($currentUser === null) {
            $currentUser = Craft::$app->getUser()->getIdentity();
        }
        $isAvailable = $purchasable->getIsAvailable();

        $event = new PurchasableAvailableEvent(compact('order', 'purchasable', 'currentUser', 'isAvailable'));
        $this->trigger(self::EVENT_PURCHASABLE_AVAILABLE, $event);

        return $event->isAvailable;
    }

    /**
     * @param PurchasableInterface $purchasable
     * @param Order|null $order
     * @param User|null $currentUser
     * @return bool
     * @since 3.3.2
     */
    public function isPurchasableShippable(PurchasableInterface $purchasable, Order $order = null, User $currentUser = null): bool
    {
        if ($currentUser === null) {
            $currentUser = Craft::$app->getUser()->getIdentity();
        }
        $isShippable = $purchasable->getIsShippable();

        $event = new PurchasableShippableEvent(compact('order', 'purchasable', 'currentUser', 'isShippable'));
        $this->trigger(self::EVENT_PURCHASABLE_SHIPPABLE, $event);

        return $event->isShippable;
    }

    /**
     * Delete a purchasable by its ID.
     *
     * @param int $purchasableId
     * @return bool
     */
    public function deletePurchasableById(int $purchasableId): bool
    {
        return Craft::$app->getElements()->deleteElementById($purchasableId);
    }

    /**
     * Get a purchasable by its ID.
     *
     * @param int $purchasableId
     * @return ElementInterface|null
     */
    public function getPurchasableById(int $purchasableId)
    {
        return Craft::$app->getElements()->getElementById($purchasableId);
    }

    /**
     * Returns all available purchasable element classes.
     *
     * @return string[] The available purchasable element classes.
     */
    public function getAllPurchasableElementTypes(): array
    {
        $purchasableElementTypes = [
            Variant::class,
        ];

        $event = new RegisterComponentTypesEvent([
            'types' => $purchasableElementTypes
        ]);
        $this->trigger(self::EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES, $event);

        return $event->types;
    }
}
