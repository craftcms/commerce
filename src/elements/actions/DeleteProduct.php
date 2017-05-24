<?php

namespace craft\commerce\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\commerce\elements\Product;
use craft\commerce\Plugin;
use craft\elements\db\ElementQueryInterface;
use yii\base\Exception;

/**
 * Delete Element Action
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.elementactions
 * @since     1.0
 */
class DeleteProduct extends ElementAction
{
    // Public Properties
    // ========================================================================

    /**
     * @var string
     */
    public $confirmationMessage;

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public static function isDestructive(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return Craft::t('commerce', 'Delete…');
    }

    /**
     * @inheritDoc
     */
    public function getConfirmationMessage()
    {
        return $this->confirmationMessage;
    }

    /**
     * @inheritDoc
     */
    public function performAction(ElementQueryInterface $query = null): bool
    {
        try {
            /** @var Product $product */
            foreach ($query->all() as $product) {
                Plugin::getInstance()->getProducts()->deleteProduct($product);
            }
        } catch (Exception $exception) {
            $this->setMessage($exception->getMessage());

            return false;
        }

        $this->setMessage(Craft::t('commerce', 'Products deleted.'));

        return true;
    }
}
