<?php

namespace craft\commerce\services;

use Craft;
use craft\events\ElementEvent;
use yii\base\Component;

/**
 * Product type service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Purchasables extends Component
{

    // Public Methods
    // =========================================================================

    /**
     * @param int $purchasableId
     *
     * @return bool
     */
    public function deletePurchasableById(int $purchasableId): bool
    {
        Craft::$app->getElements()->deleteElementById($purchasableId);
    }

    /**
     * @param int $purchasableId
     *
     * @return \craft\base\ElementInterface|null
     */
    public function getPurchasableById(int $purchasableId)
    {
        return Craft::$app->getElements()->getElementById($purchasableId);
    }

    /**
     * @param ElementEvent $event
     */
    public function saveElementHandler(ElementEvent $event)
    {

    }
}
