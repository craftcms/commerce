<?php

namespace craft\commerce\elements\db;

use craft\elements\db\ElementQuery;

/**
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class VariantQuery extends ElementQuery
{


    /**
     * @inheritDoc IElementType::getEagerLoadingMap()
     *
     * @param BaseElementModel[] $sourceElements
     * @param string             $handle
     *
     * @return array|false
     */
    public function getEagerLoadingMap($sourceElements, $handle)
    {
        if ($handle == 'product') {
            // Get the source element IDs
            $sourceElementIds = [];

            foreach ($sourceElements as $sourceElement) {
                $sourceElementIds[] = $sourceElement->id;
            }

            $map = Craft::$app->getDb()->createCommand()
                ->select('id as source, productId as target')
                ->from('commerce_variants')
                ->where(['in', 'id', $sourceElementIds])
                ->queryAll();

            return [
                'elementType' => Product::class,
                'map' => $map
            ];
        }

        return parent::getEagerLoadingMap($sourceElements, $handle);
    }


}