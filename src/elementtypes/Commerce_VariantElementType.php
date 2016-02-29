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
    public function getAvailableActions($source = null)
    {
        $deleteAction = craft()->elements->getAction('Delete');
        $deleteAction->setParams([
            'confirmationMessage' => Craft::t('Are you sure you want to delete the selected variants?'),
            'successMessage' => Craft::t('Variants deleted.'),
        ]);
        $actions[] = $deleteAction;

        $editAction = craft()->elements->getAction('Edit');
        $actions[] = $editAction;

        $setValuesAction = craft()->elements->getAction('Commerce_SetVariantValues');
        $actions[] = $setValuesAction;

        return $actions;
    }

    /**
     * @param null $source
     *
     * @return array
     */
    public function defineTableAttributes($source = null)
    {
        // TODO do not show dimensions if product type hasDimentions == false. Leaving until custom columns is implemented.
        return [
            'title' => Craft::t('Title'),
            'sku' => Craft::t('SKU'),
            'price' => Craft::t('Price'),
            'width' => Craft::t('Width ') . "(" . craft()->commerce_settings->getOption('dimensionUnits') . ")",
            'height' => Craft::t('Height ') . "(" . craft()->commerce_settings->getOption('dimensionUnits') . ")",
            'length' => Craft::t('Length ') . "(" . craft()->commerce_settings->getOption('dimensionUnits') . ")",
            'weight' => Craft::t('Weight ') . "(" . craft()->commerce_settings->getOption('weightUnits') . ")",
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
     * @param BaseElementModel $element
     * @param string $attribute
     *
     * @return mixed|string
     */
    public function getTableAttributeHtml(BaseElementModel $element, $attribute)
    {
        $infinity = "<span style=\"color:#E5E5E5\">&infin;</span>";
        $numbers = ['weight', 'height', 'length', 'width'];
        if (in_array($attribute, $numbers)) {
            $formatter = craft()->getNumberFormatter();
            if ($element->$attribute == 0) {
                return "<span style=\"color:#E5E5E5\">" . $formatter->formatDecimal($element->$attribute) . "</span>";
            } else {
                return $formatter->formatDecimal($element->$attribute);
            }
        }

        if ($attribute == 'stock' && $element->unlimitedStock) {
            return $infinity;
        }

        if ($attribute == 'price') {
            $formatter = craft()->getNumberFormatter();

            return $formatter->formatCurrency($element->$attribute, craft()->commerce_settings->getSettings()->defaultCurrency);
        }

        if ($attribute == 'minQty') {
            if (!$element->minQty && !$element->maxQty) {
                return $infinity;
            } else {
                $min = $element->minQty ? $element->minQty : '1';
                $max = $element->maxQty ? $element->maxQty : $infinity;

                return $min . " - " . $max;
            }
        }

        return parent::getTableAttributeHtml($element, $attribute);
    }

    /**
     * @return array
     */
    public function defineSortableAttributes()
    {
        return [
            'sku' => Craft::t('SKU'),
            'price' => Craft::t('Price'),
            'width' => Craft::t('Width'),
            'height' => Craft::t('Height'),
            'length' => Craft::t('Length'),
            'weight' => Craft::t('Weight'),
            'stock' => Craft::t('Stock'),
            'unlimitedStock' => Craft::t('Unlimited Stock'),
            'minQty' => Craft::t('Min Qty'),
            'maxQty' => Craft::t('Max Qty')
        ];
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
        $query
            ->addSelect("variants.id,variants.productId,variants.isDefault,variants.sku,variants.price,variants.sortOrder,variants.width,variants.height,variants.length,variants.weight,variants.stock,variants.unlimitedStock,variants.minQty,variants.maxQty")
            ->join('commerce_variants variants', 'variants.id = elements.id');

        if ($criteria->sku) {
            $query->andWhere(DbHelper::parseParam('variants.sku', $criteria->sku, $query->params));
        }

        if ($criteria->product) {
            if ($criteria->product instanceof Commerce_ProductModel) {
                $criteria->productId = $criteria->product->id;
                $criteria->product = null;
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

        if ($criteria->stock) {
            $query->andWhere(DbHelper::parseParam('variants.stock', $criteria->stock, $query->params));
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
