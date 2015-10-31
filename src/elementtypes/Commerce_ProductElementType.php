<?php
namespace Craft;

use Commerce\Helpers\CommerceVariantMatrixHelper as VariantMatrixHelper;

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
        $allPluginActions = craft()->plugins->call('commerce_addProductActions', [$source], true);

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
                'data' => array('handle' => $productType->handle),
                'criteria' => ['typeId' => $productType->id]
            ];
        }

        // Allow plugins to modify the sources
        craft()->plugins->call('commerce_modifyProductSources', [&$sources, $context]);

        return $sources;
    }

    /**
     * @return array
     */
    public function defineAvailableTableAttributes()
    {
        $attributes = [
            'title' => ['label' => Craft::t('Title')],
            'type' => ['label' => Craft::t('Type')],
            'slug' => ['label' => Craft::t('Slug')],
            'uri' => ['label' => Craft::t('URI')],
            'postDate' => ['label' => Craft::t('Post Date')],
            'expiryDate' => ['label' => Craft::t('Expiry Date')],
            'taxCategory' => ['label' => Craft::t('Tax Category')],
            'freeShipping' => ['label' => Craft::t('Free Shipping?')],
            'promotable' => ['label' => Craft::t('Promotable?')],
            'link' => ['label' => Craft::t('Link'), 'icon' => 'world'],
            'dateCreated' => ['label' => Craft::t('Date Created')],
            'dateUpdated' => ['label' => Craft::t('Date Updated')],
        ];

        // Allow plugins to modify the attributes
        craft()->plugins->call('commerce_modifyProductTableAttributes', [&$attributes]);

        return $attributes;
    }

    /**
     * @param string|null $source
     *
     * @return array
     */
    public function getDefaultTableAttributes($source = null)
    {
        $attributes = [];

        if ($source == '*') {
            $attributes[] = 'type';
        }

        $attributes[] = 'postDate';
        $attributes[] = 'expiryDate';
        $attributes[] = 'link';

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
        $pluginAttributeHtml = craft()->plugins->callFirst('commerce_getProductTableAttributeHtml', [$element, $attribute], true);

        if ($pluginAttributeHtml !== null) {
            return $pluginAttributeHtml;
        }

        switch ($attribute) {
            case 'type': {
                $productType = $element->getType();

                return ($productType ? Craft::t($productType->name) : '');
            }

            case 'taxCategory': {
                $taxCategory = $element->getTaxCategory();

                return ($taxCategory ? Craft::t($taxCategory->name) : '');
            }

            case 'promotable':
            case 'freeShipping': {
                return ($element->$attribute ? '<span data-icon="check" title="'.Craft::t('Yes').'"></span>' : '');
            }

            default: {
                return parent::getTableAttributeHtml($element, $attribute);
            }
        }
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
            'postDate' => Craft::t('Available On'),
            'expiryDate' => Craft::t('Expires On')
        ];

        // Allow plugins to modify the attributes
        craft()->plugins->call('commerce_modifyProductSortableAttributes', [&$attributes]);

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
            'postDate' => AttributeType::Mixed,
            'expiryDate' => AttributeType::Mixed,
            'after' => AttributeType::Mixed,
            'order' => [AttributeType::String, 'default' => 'postDate desc'],
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
                    "products.postDate <= '{$currentTimeDb}'",
                    ['or', 'products.expiryDate is null', "products.expiryDate > '{$currentTimeDb}'"]
                ];
            }

            case Commerce_ProductModel::PENDING: {
                return ['and',
                    'elements.enabled = 1',
                    'elements_i18n.enabled = 1',
                    "products.postDate > '{$currentTimeDb}'"
                ];
            }

            case Commerce_ProductModel::EXPIRED: {
                return ['and',
                    'elements.enabled = 1',
                    'elements_i18n.enabled = 1',
                    'products.expiryDate is not null',
                    "products.expiryDate <= '{$currentTimeDb}'"
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
            ->addSelect("products.id, products.typeId, products.promotable, products.freeShipping, products.postDate, products.expiryDate, products.taxCategoryId, products.authorId")
            ->join('commerce_products products', 'products.id = elements.id')
            ->join('commerce_producttypes producttypes', 'producttypes.id = products.typeId');

        if ($criteria->postDate) {
            $query->andWhere(DbHelper::parseDateParam('products.postDate', $criteria->postDate, $query->params));
        } else {
            if ($criteria->after) {
                $query->andWhere(DbHelper::parseDateParam('products.postDate', '>=' . $criteria->after, $query->params));
            }

            if ($criteria->before) {
                $query->andWhere(DbHelper::parseDateParam('products.postDate', '<' . $criteria->before, $query->params));
            }
        }

        if ($criteria->expiryDate) {
            $query->andWhere(DbHelper::parseDateParam('products.expiryDate', $criteria->expiryDate, $query->params));
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
     * Returns the HTML for an editor HUD for the given element.
     *
     * @param BaseElementModel $element The element being edited.
     *
     * @return string The HTML for the editor HUD.
     */
    public function getEditorHtml(BaseElementModel $element)
    {
        $templatesService = craft()->templates;
        $html = $templatesService->renderMacro('commerce/products/_fields', 'titleField', array($element));
        $html .= $templatesService->renderMacro('commerce/products/_fields', 'generalMetaFields', array($element));
        $html .= $templatesService->renderMacro('commerce/products/_fields', 'behavioralMetaFields', array($element));
        $html .= parent::getEditorHtml($element);

        if ($element->getType()->hasVariants) {
            $html .= $templatesService->renderMacro('_includes/forms', 'field', array(
                array(
                    'label' => Craft::t('Variants'),
                ),
                VariantMatrixHelper::getVariantMatrixHtml($element)
            ));
        } else {
            $primaryVariant = ArrayHelper::getFirstValue($element->getVariants());
            $html .= $templatesService->renderMacro('commerce/products/_fields', 'generalVariantFields', array($primaryVariant));
            $html .= $templatesService->renderMacro('commerce/products/_fields', 'dimensionVariantFields', array($primaryVariant));
        }

        return $html;
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
