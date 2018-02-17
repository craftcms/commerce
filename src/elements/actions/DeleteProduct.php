<?php

namespace craft\commerce\elements\actions;

use Craft;
use craft\commerce\elements\Product;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use yii\base\Exception;

/**
 * Delete Element Action
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class DeleteProduct extends Delete
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function performAction(ElementQueryInterface $query = null): bool
    {
        if (!$query) {
            return false;
        }

        try {
            /** @var Product $product */
            foreach ($query->all() as $product) {
                Craft::$app->getElements()->deleteElement($product);
            }
        } catch (Exception $exception) {
            $this->setMessage($exception->getMessage());

            return false;
        }

        $this->setMessage(Craft::t('commerce', 'Products deleted.'));

        return true;
    }
}
