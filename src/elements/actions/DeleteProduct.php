<?php

namespace craft\commerce\elements\actions;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\Plugin;
use craft\elements\actions\Delete;
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
class DeleteProduct extends Delete
{

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function performAction(ElementQueryInterface $query = null): bool
    {

        if(!$query)
        {
          return false;
        }

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
