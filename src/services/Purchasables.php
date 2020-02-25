<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\elements\Variant;
use craft\events\RegisterComponentTypesEvent;
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
     * Delete a purhasable by its ID.
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
