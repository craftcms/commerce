<?php
namespace Craft;

require_once(__DIR__ . '/Commerce_BaseElementType.php');

/**
 * Class Commerce_ProductElementType
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.elementtypes
 * @since     1.0
 */
class Commerce_ProductElementType extends Commerce_BaseElementType
{

    /**
     * @return null|string
     */
    public function getName()
    {
        return Craft::t('Products');
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
     * @param null $source
     *
     * @return array
     */
    public function getAvailableActions($source = null)
    {
        $deleteAction = craft()->elements->getAction('Delete');
        $deleteAction->setParams([
            'confirmationMessage' => Craft::t('Are you sure you want to delete the selected product and their variants?'),
            'successMessage' => Craft::t('Products deleted.'),
        ]);
        $actions[] = $deleteAction;

        $createSaleAction = craft()->elements->getAction('Commerce_CreateSale');
        $actions[] = $createSaleAction;

        $createDiscountAction = craft()->elements->getAction('Commerce_CreateDiscount');
        $actions[] = $createDiscountAction;

        // Allow plugins to add additional actions
        $allPluginActions = craft()->plugins->call('commerce_addProductActions', array($source), true);

        foreach ($allPluginActions as $pluginActions) {
            $actions = array_merge($actions, $pluginActions);
        }

        return $actions;
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
                'label' => Craft::t('All products'),
            ]
        ];

        $sources[] = ['heading' => "Product Types"];

        foreach (craft()->commerce_productTypes->getAll() as $productType) {
            $key = 'productType:' . $productType->id;

            $sources[$key] = [
                'label' => $productType->name,
                'criteria' => ['typeId' => $productType->id]
            ];
        }

        // Allow plugins to modify the sources
        craft()->plugins->call('commerce_modifyProductSources', array(&$sources, $context));

        return $sources;
    }

    /**
     * @param null $source
     *
     * @return array
     */
    public function defineTableAttributes($source = null)
    {
        $attributes = [
            'title' => Craft::t('Name'),
            'availableOn' => Craft::t('Available On'),
            'expiresOn' => Craft::t('Expires On')
        ];

        // Allow plugins to modify the attributes
        craft()->plugins->call('commerce_modifyProductTableAttributes', array(&$attributes));

        return $attributes;
    }

    /**
     * @return array
     */
    public function defineSearchableAttributes()
    {
        return ['title'];
    }


    /**
     * @param BaseElementModel $element
     * @param string $attribute
     *
     * @return mixed|string
     */
    public function getTableAttributeHtml(BaseElementModel $element, $attribute)
    {
        // First give plugins a chance to set this
        $pluginAttributeHtml = craft()->plugins->callFirst('commerce_getProductTableAttributeHtml', array($element, $attribute), true);

        if ($pluginAttributeHtml !== null) {
            return $pluginAttributeHtml;
        }

        return parent::getTableAttributeHtml($element, $attribute);
    }

    /**
     * Sortable by
     *
     * @return array
     */
    public function defineSortableAttributes()
    {
        $attributes = [
            'title' => Craft::t('Name'),
            'availableOn' => Craft::t('Available On'),
            'expiresOn' => Craft::t('Expires On')
        ];

        // Allow plugins to modify the attributes
        craft()->plugins->call('commerce_modifyProductSortableAttributes', array(&$attributes));

        return $attributes;
    }


    /**
     * @inheritDoc IElementType::getStatuses()
     *
     * @return array|null
     */
    public function getStatuses()
    {
        return [
            Commerce_ProductModel::LIVE => Craft::t('Live'),
            Commerce_ProductModel::PENDING => Craft::t('Pending'),
            Commerce_ProductModel::EXPIRED => Craft::t('Expired'),
            BaseElementModel::DISABLED => Craft::t('Disabled')
        ];
    }


    /**
     * @return array
     */
    public function defineCriteriaAttributes()
    {
        return [
            'typeId' => AttributeType::Mixed,
            'type' => AttributeType::Mixed,
            'availableOn' => AttributeType::Mixed,
            'expiresOn' => AttributeType::Mixed,
            'after' => AttributeType::Mixed,
            'order' => [AttributeType::String, 'default' => 'availableOn desc'],
            'before' => AttributeType::Mixed,
            'status' => [AttributeType::String, 'default' => Commerce_ProductModel::LIVE],
            'withVariant' => AttributeType::Mixed,
        ];
    }

    /**
     * @inheritDoc IElementType::getElementQueryStatusCondition()
     *
     * @param DbCommand $query
     * @param string $status
     *
     * @return array|false|string|void
     */
    public function getElementQueryStatusCondition(DbCommand $query, $status)
    {
        $currentTimeDb = DateTimeHelper::currentTimeForDb();

        switch ($status) {
            case Commerce_ProductModel::LIVE: {
                return ['and',
                    'elements.enabled = 1',
                    'elements_i18n.enabled = 1',
                    "products.availableOn <= '{$currentTimeDb}'",
                    ['or', 'products.expiresOn is null', "products.expiresOn > '{$currentTimeDb}'"]
                ];
            }

            case Commerce_ProductModel::PENDING: {
                return ['and',
                    'elements.enabled = 1',
                    'elements_i18n.enabled = 1',
                    "products.availableOn > '{$currentTimeDb}'"
                ];
            }

            case Commerce_ProductModel::EXPIRED: {
                return ['and',
                    'elements.enabled = 1',
                    'elements_i18n.enabled = 1',
                    'products.expiresOn is not null',
                    "products.expiresOn <= '{$currentTimeDb}'"
                ];
            }
        }
    }


    /**
     * @param DbCommand $query
     * @param ElementCriteriaModel $criteria
     * @return bool
     * @throws Exception
     */
    public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
    {
        $query
            ->addSelect("products.id, products.typeId, products.promotable, products.freeShipping, products.availableOn, products.expiresOn, products.taxCategoryId, products.authorId")
            ->join('commerce_products products', 'products.id = elements.id')
            ->join('commerce_producttypes producttypes', 'producttypes.id = products.typeId');

        if ($criteria->availableOn) {
            $query->andWhere(DbHelper::parseDateParam('products.availableOn', $criteria->availableOn, $query->params));
        } else {
            if ($criteria->after) {
                $query->andWhere(DbHelper::parseDateParam('products.availableOn', '>=' . $criteria->after, $query->params));
            }

            if ($criteria->before) {
                $query->andWhere(DbHelper::parseDateParam('products.availableOn', '<' . $criteria->before, $query->params));
            }
        }

        if ($criteria->expiresOn) {
            $query->andWhere(DbHelper::parseDateParam('products.expiresOn', $criteria->expiresOn, $query->params));
        }

        if ($criteria->type) {
            if ($criteria->type instanceof Commerce_ProductTypeModel) {
                $criteria->typeId = $criteria->type->id;
                $criteria->type = null;
            } else {
                $query->andWhere(DbHelper::parseParam('producttypes.handle', $criteria->type, $query->params));
            }
        }

        if ($criteria->typeId) {
            $query->andWhere(DbHelper::parseParam('products.typeId', $criteria->typeId, $query->params));
        }

        if ($criteria->withVariant) {
            if ($criteria->withVariant instanceof ElementCriteriaModel) {
                $variantCriteria = $criteria->withVariant;
            } else {
                $variantCriteria = craft()->elements->getCriteria('Commerce_Variant', $criteria->withVariant);
            }

            $productIds = craft()->elements->buildElementsQuery($variantCriteria)
                ->selectDistinct('productId')
                ->queryColumn();

            if (!$productIds) {
                return false;
            }

            $query->andWhere(['in', 'products.id', $productIds]);
        }

        return true;
    }


    /**
     * @param array $row
     *
     * @return BaseModel
     */
    public function populateElementModel($row)
    {
        return Commerce_ProductModel::populateModel($row);
    }

    /**
     * Routes the request when the URI matches a product.
     *
     * @param BaseElementModel $element
     *
     * @return array|bool|mixed
     */
    public function routeRequestForMatchedElement(BaseElementModel $element)
    {
        /** @var Commerce_ProductModel $element */
        if ($element->getStatus() == Commerce_ProductModel::LIVE) {
            $productType = $element->type;

            if ($productType->hasUrls) {
                return [
                    'action' => 'templates/render',
                    'params' => [
                        'template' => $productType->template,
                        'variables' => [
                            'product' => $element
                        ]
                    ]
                ];
            }
        }

        return false;
    }

    /**
     * @param BaseElementModel $element
     * @param array $params
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function saveElement(BaseElementModel $element, $params)
    {
        return craft()->commerce_products->save($element);
    }

} 
