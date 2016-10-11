<?php
namespace Craft;

require_once(__DIR__ . '/Commerce_BaseElementType.php');

/**
 * Class Commerce_VariantElementType
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.elementtypes
 * @since     1.0
 */
class Commerce_VariantElementType extends Commerce_BaseElementType
{

    /**
     * @return null|string
     */
    public function getName()
    {
        return Craft::t('Variants');
    }

    /**
     * @return bool
     */
    public function hasContent()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function hasTitles()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function hasStatuses()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isSelectable()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isLocalized()
    {
        return true;
    }

    /**
     * @param null $context
     *
     * @return array
     */
    public function getSources($context = null)
    {
        $sources = [

            '*' => [
                'label' => Craft::t('All product\'s variants'),
            ]
        ];

        return $sources;
    }

    /**
     * @param null $source
     *
     * @return array
     */
    public function defineTableAttributes($source = null)
    {
        return [
            'title' => Craft::t('Title'),
            'sku' => Craft::t('SKU'),
            'price' => Craft::t('Price'),
            'width' => Craft::t('Width ({unit})', ['unit' => craft()->commerce_settings->getOption('dimensionUnits')]),
            'height' => Craft::t('Height ({unit})', ['unit' => craft()->commerce_settings->getOption('dimensionUnits')]),
            'length' => Craft::t('Length ({unit})', ['unit' => craft()->commerce_settings->getOption('dimensionUnits')]),
            'weight' => Craft::t('Weight ({unit})', ['unit' => craft()->commerce_settings->getOption('weightUnits')]),
            'stock' => Craft::t('Stock'),
            'minQty' => Craft::t('Quantities')
        ];
    }

    /**
     * @return array
     */
    public function defineSearchableAttributes()
    {
        return ['sku', 'price', 'width', 'height', 'length', 'weight', 'stock', 'unlimitedStock', 'minQty', 'maxQty'];
    }

    /**
     * @return array
     */
    public function defineCriteriaAttributes()
    {
        return [
            'sku' => AttributeType::Mixed,
            'product' => AttributeType::Mixed,
            'productId' => AttributeType::Mixed,
            'isDefault' => AttributeType::Mixed,
            'default' => AttributeType::Mixed,
            'stock' => AttributeType::Mixed,
            'hasStock' => AttributeType::Mixed,
            'order' => [AttributeType::String, 'default' => 'variants.sortOrder asc'],
        ];
    }

    /**
     * @param DbCommand $query
     * @param ElementCriteriaModel $criteria
     *
     * @return void
     */
    public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
    {
        // Clear out existing onPopulateElements handlers on the criteria
        $criteria->detachEventHandler('onPopulateElements', array($this, 'setProductOnVariant'));

        $query
            ->addSelect("variants.id,variants.productId,variants.isDefault,variants.sku,variants.price,variants.sortOrder,variants.width,variants.height,variants.length,variants.weight,variants.stock,variants.unlimitedStock,variants.minQty,variants.maxQty")
            ->join('commerce_variants variants', 'variants.id = elements.id');

        if ($criteria->sku) {
            $query->andWhere(DbHelper::parseParam('variants.sku', $criteria->sku, $query->params));
        }

        if ($criteria->product) {
            if ($criteria->product instanceof Commerce_ProductModel) {
                $query->andWhere(DbHelper::parseParam('variants.productId', $criteria->product->id, $query->params));
                $criteria->attachEventHandler('onPopulateElements', array($this, 'setProductOnVariant'));
            } else {
                $query->andWhere(DbHelper::parseParam('variants.productId', $criteria->product, $query->params));
            }
        }

        if ($criteria->productId) {
            $query->andWhere(DbHelper::parseParam('variants.productId', $criteria->productId, $query->params));
        }

        if ($criteria->isDefault) {
            $query->andWhere(DbHelper::parseParam('variants.isDefault', $criteria->isDefault, $query->params));
        }

        if ($criteria->default) {
            $query->andWhere(DbHelper::parseParam('variants.isDefault', $criteria->default, $query->params));
        }

	    if ($criteria->stock)
	    {
		    $query->andWhere(DbHelper::parseParam('variants.stock', $criteria->stock, $query->params));
	    }

	    if (isset($criteria->hasStock) && $criteria->hasStock === true)
	    {
		    $hasStockCondition = ['or', '(variants.stock > 0 AND variants.unlimitedStock != 1)', 'variants.unlimitedStock = 1'];
		    $query->andWhere($hasStockCondition);
	    }

	    if (isset($criteria->hasStock) && $criteria->hasStock === false)
	    {
		    $hasStockCondition = ['and', 'variants.stock < 1', 'variants.unlimitedStock != 1'];
		    $query->andWhere($hasStockCondition);
	    }
    }

    /**
     * Sets the product on the resulting variants.
     *
     * @param Event $event
     *
     * @return void
     */
    public function setProductOnVariant(Event $event)
    {
        /** @var ElementCriteriaModel $criteria */
        $criteria = $event->sender;

        /** @var Commerce_VariantModel[] $variants */
        $variants = $event->params['elements'];

        if ($criteria->product instanceof Commerce_ProductModel)
        {
            craft()->commerce_variants->setProductOnVariants($criteria->product, $variants);
        }
    }

    /**
     * @param array $row
     *
     * @return BaseModel
     */
    public function populateElementModel($row)
    {
        return Commerce_VariantModel::populateModel($row);
    }

    /**
     * @inheritDoc IElementType::getEagerLoadingMap()
     *
     * @param BaseElementModel[]  $sourceElements
     * @param string $handle
     *
     * @return array|false
     */
    public function getEagerLoadingMap($sourceElements, $handle)
    {
        if ($handle == 'product') {
            // Get the source element IDs
            $sourceElementIds = array();

            foreach ($sourceElements as $sourceElement) {
                $sourceElementIds[] = $sourceElement->id;
            }

            $map = craft()->db->createCommand()
                ->select('id as source, productId as target')
                ->from('commerce_variants')
                ->where(array('in', 'id', $sourceElementIds))
                ->queryAll();

            return array(
                'elementType' => 'Commerce_Product',
                'map' => $map
            );
        }

        return parent::getEagerLoadingMap($sourceElements, $handle);
    }

    /**
     * @param BaseElementModel $element
     * @param array $params
     *
     * @return bool
     * @throws HttpException
     * @throws \Exception
     */
    public function saveElement(BaseElementModel $element, $params)
    {
        return craft()->commerce_variants->saveVariant($element);
    }

}
