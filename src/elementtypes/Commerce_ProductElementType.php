<?php
namespace Craft;

use Commerce\Helpers\CommerceProductHelper as CommerceProductHelper;
use Commerce\Helpers\CommerceVariantMatrixHelper as VariantMatrixHelper;

require_once(__DIR__ . '/Commerce_BaseElementType.php');

/**
 * Class Commerce_ProductElementType
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
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
        // Get the section(s) we need to check permissions on
        switch ($source)
        {
            case '*':
            {
                $productTypes = craft()->commerce_productTypes->getEditableProductTypes();
                break;
            }
            default:
            {
                if (preg_match('/^productType:(\d+)$/', $source, $matches))
                {
                    $productType = craft()->commerce_productTypes->getProductTypeById($matches[1]);

                    if ($productType)
                    {
                        $productTypes = [$productType];
                    }
                }
            }
        }

        $actions = [];

        if (!empty($productTypes))
        {
            $userSessionService = craft()->userSession;
            $canManage = false;

            foreach ($productTypes as $productType) {
                $canManage = $userSessionService->checkPermission('commerce-manageProductType:'.$productType->id);
            }

            if ($canManage)
            {
                // Allow deletion
                $deleteAction = craft()->elements->getAction('Commerce_DeleteProduct');
                $deleteAction->setParams(['confirmationMessage' => Craft::t('Are you sure you want to delete the selected product and its variants?'),
                                          'successMessage'      => Craft::t('Products and Variants deleted.'),]);
                $actions[] = $deleteAction;

                // Allow setting status
                $setStatusAction = craft()->elements->getAction('SetStatus');
                $setStatusAction->onSetStatus = function (Event $event)
                {
                    if ($event->params['status'] == BaseElementModel::ENABLED)
                    {
                        // Set a Post Date as well
                        craft()->db->createCommand()->update('entries',
                            ['postDate' => DateTimeHelper::currentTimeForDb()],
                            ['and',['in','id', $event->params['elementIds']], 'postDate is null']);
                    }
                };
                $actions[] = $setStatusAction;
            }

            if($userSessionService->checkPermission('commerce-managePromotions')){
                $actions[] = craft()->elements->getAction('Commerce_CreateSale');
                $actions[] = craft()->elements->getAction('Commerce_CreateDiscount');
            }
        }

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
        if ($context == 'index')
        {
            $productTypes = craft()->commerce_productTypes->getEditableProductTypes();
            $editable = true;
        }
        else
        {
            $productTypes = craft()->commerce_productTypes->getAllProductTypes();
            $editable = false;
        }

        $productTypeIds = array();

        foreach ($productTypes as $productType)
        {
            $productTypeIds[] = $productType->id;
        }

        $sources = [
            '*' => [
                'label'       => Craft::t('All products'),
                'criteria'    => ['typeId' => $productTypeIds, 'editable' => $editable],
                'defaultSort' => ['postDate', 'desc']
            ]
        ];

        $sources[] = ['heading' => Craft::t('Product Types')];

        foreach ($productTypes as $productType) {
            $key = 'productType:' . $productType->id;
            $canEditProducts = craft()->userSession->checkPermission('commerce-manageProductType:'.$productType->id);

            $sources[$key] = [
                'label' => $productType->name,
                'data' => [
                    'handle' => $productType->handle,
                    'editable' => $canEditProducts
                ],
                'criteria' => ['typeId' => $productType->id, 'editable' => $editable]
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
            'shippingCategory' => ['label' => Craft::t('Shipping Category')],
            'freeShipping' => ['label' => Craft::t('Free Shipping?')],
            'promotable' => ['label' => Craft::t('Promotable?')],
            'link' => ['label' => Craft::t('Link'), 'icon' => 'world'],
            'dateCreated' => ['label' => Craft::t('Date Created')],
            'dateUpdated' => ['label' => Craft::t('Date Updated')],
            'defaultPrice' => ['label' => Craft::t('Price')],
            'defaultSku' => ['label' => Craft::t('SKU')],
            'defaultWeight' => ['label' => Craft::t('Weight')],
            'defaultLength' => ['label' => Craft::t('Length')],
            'defaultWidth' => ['label' => Craft::t('Width')],
            'defaultHeight' => ['label' => Craft::t('Height')],
        ];

        // Allow plugins to modify the attributes
        $pluginAttributes = craft()->plugins->call('commerce_defineAdditionalProductTableAttributes', array(), true);

        foreach ($pluginAttributes as $thisPluginAttributes)
        {
            $attributes = array_merge($attributes, $thisPluginAttributes);
        }

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
        $attributes[] = 'defaultPrice';
        $attributes[] = 'defaultSku';
        $attributes[] = 'link';

        return $attributes;
    }

    /**
     * @return array
     */
    public function defineSearchableAttributes()
    {
        return ['title','defaultSku'];
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

        /* @var $productType Commerce_ProductTypeModel */
        $productType = $element->getType();

        switch ($attribute) {
            case 'type': {
                return ($productType ? Craft::t($productType->name) : '');
            }

            case 'taxCategory': {
                $taxCategory = $element->getTaxCategory();

                return ($taxCategory ? Craft::t($taxCategory->name) : '');
            }
            case 'shippingCategory': {
                $shippingCategory = $element->getShippingCategory();

                return ($shippingCategory ? Craft::t($shippingCategory->name) : '');
            }
            case 'defaultPrice': {
                $code = craft()->commerce_paymentCurrencies->getPrimaryPaymentCurrencyIso();

                return craft()->numberFormatter->formatCurrency($element->$attribute, strtoupper($code));
            }
            case 'defaultWeight': {
                if($productType->hasDimensions){
                    return craft()->numberFormatter->formatDecimal($element->$attribute) . " " . craft()->commerce_settings->getOption('weightUnits');;
                }else{
                    return "";
                }
            }
            case 'defaultLength':
            case 'defaultWidth':
            case 'defaultHeight': {
                if($productType->hasDimensions){
                    return craft()->numberFormatter->formatDecimal($element->$attribute) . " " . craft()->commerce_settings->getOption('dimensionUnits');;
                }else{
                    return "";
                }
            }
            case 'promotable':
            case 'freeShipping': {
                return ($element->$attribute ? '<span data-icon="check" title="' . Craft::t('Yes') . '"></span>' : '');
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
            'title' => Craft::t('Title'),
            'postDate' => Craft::t('Post Date'),
            'expiryDate' => Craft::t('Expiry Date'),
            'defaultPrice' => Craft::t('Price')
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
            'after' => AttributeType::Mixed,
            'before' => AttributeType::Mixed,
            'defaultPrice' => AttributeType::Mixed,
            'editable' => AttributeType::Bool,
            'expiryDate' => AttributeType::Mixed,
            'order' => [AttributeType::String, 'default' => 'postDate desc'],
            'postDate' => AttributeType::Mixed,
            'status' => [AttributeType::String, 'default' => Commerce_ProductModel::LIVE],
            'type' => AttributeType::Mixed,
            'typeId' => AttributeType::Mixed,
            'withVariant' => AttributeType::Mixed,
            'hasVariant' => AttributeType::Mixed,
            'hasSales' => AttributeType::Mixed,
            'defaultHeight' => AttributeType::Mixed,
            'defaultLength' => AttributeType::Mixed,
            'defaultWidth' => AttributeType::Mixed,
            'defaultWeight' => AttributeType::Mixed
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
            ->addSelect("products.id, products.typeId, products.promotable, products.freeShipping, products.postDate, products.expiryDate, products.defaultPrice, products.defaultVariantId, products.defaultSku, products.defaultWeight, products.defaultLength, products.defaultWidth, products.defaultHeight, products.taxCategoryId, products.shippingCategoryId")
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

        if ($criteria->defaultPrice) {
            $query->andWhere(DbHelper::parseParam('products.defaultPrice', $criteria->defaultPrice, $query->params));
        }

        if ($criteria->defaultHeight) {
            $query->andWhere(DbHelper::parseParam('products.defaultHeight', $criteria->defaultHeight, $query->params));
        }

        if ($criteria->defaultLength) {
            $query->andWhere(DbHelper::parseParam('products.defaultLength', $criteria->defaultLength, $query->params));
        }

        if ($criteria->defaultWidth) {
            $query->andWhere(DbHelper::parseParam('products.defaultWidth', $criteria->defaultWidth, $query->params));
        }

        if ($criteria->defaultWeight) {
            $query->andWhere(DbHelper::parseParam('products.defaultWeight', $criteria->defaultWeight, $query->params));
        }

        if ($criteria->withVariant) {
            $criteria->hasVariant = $criteria->withVariant;
            craft()->deprecator->log('Commerce:withVariant_param', 'The withVariant product param has been deprecated. Use hasVariant instead.');
            $criteria->withVariant = null;
        }

        if ($criteria->hasVariant) {
            if ($criteria->hasVariant instanceof ElementCriteriaModel) {
                $variantCriteria = $criteria->hasVariant;
            } else {
                $variantCriteria = craft()->elements->getCriteria('Commerce_Variant', $criteria->hasVariant);
            }

            $variantCriteria->limit = null;
            $elementQuery = craft()->elements->buildElementsQuery($variantCriteria);
            $productIds = null;
            if ($elementQuery) {
                $productIds = craft()->elements->buildElementsQuery($variantCriteria)
                    ->selectDistinct('productId')
                    ->queryColumn();
            }

            if (!$productIds) {
                return false;
            }

            // Remove any blank product IDs (if any)
            $productIds = array_filter($productIds);

            $query->andWhere(['in', 'products.id', $productIds]);
        }

        if ($criteria->editable) {
            $user = craft()->userSession->getUser();

            if (!$user) {
                return false;
            }

            // Limit the query to only the sections the user has permission to edit
            $editableProductTypeIds = craft()->commerce_productTypes->getEditableProductTypeIds();

            if (!$editableProductTypeIds) {
                return false;
            }

            $query->andWhere(array('in', 'products.typeId', $editableProductTypeIds));
        }


	    if ($criteria->hasSales !== null)
	    {
		    $productsCriteria = craft()->elements->getCriteria('Commerce_Product', $criteria);
		    $productsCriteria->hasSales = null;
		    $productsCriteria->limit = null;
		    $products = $productsCriteria->find();

		    $productIds = [];
		    foreach ($products as $product)
		    {
			    $sales = craft()->commerce_sales->getSalesForProduct($product);

			    if ($criteria->hasSales === true && count($sales) > 0)
			    {
				    $productIds[] = $product->id;
			    }

			    if ($criteria->hasSales === false && count($sales) == 0)
			    {
				    $productIds[] = $product->id;
			    }
		    }

		    // Remove any blank product IDs (if any)
		    $productIds = array_filter($productIds);

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
     * @inheritDoc IElementType::getEagerLoadingMap()
     *
     * @param BaseElementModel[]  $sourceElements
     * @param string $handle
     *
     * @return array|false
     */
    public function getEagerLoadingMap($sourceElements, $handle)
    {
        if ($handle == 'variants') {
            // Get the source element IDs
            $sourceElementIds = array();

            foreach ($sourceElements as $sourceElement) {
                $sourceElementIds[] = $sourceElement->id;
            }

            $map = craft()->db->createCommand()
                ->select('productId as source, id as target')
                ->from('commerce_variants')
                ->where(array('in', 'productId', $sourceElementIds))
                ->order('sortOrder asc')
                ->queryAll();

            return array(
                'elementType' => 'Commerce_Variant',
                'map' => $map
            );
        }

        return parent::getEagerLoadingMap($sourceElements, $handle);
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
        /** @ var Commerce_ProductModel $element */
        $templatesService = craft()->templates;
        $html = $templatesService->renderMacro('commerce/products/_fields', 'titleField', array($element));
        $html .= $templatesService->renderMacro('commerce/products/_fields', 'generalMetaFields', array($element));
        $html .= $templatesService->renderMacro('commerce/products/_fields', 'behavioralMetaFields', array($element));
        $html .= parent::getEditorHtml($element);

        $productType = $element->getType();

        if ($productType->hasVariants) {
            $html .= $templatesService->renderMacro('_includes/forms', 'field', array(
                array(
                    'label' => Craft::t('Variants'),
                ),
                VariantMatrixHelper::getVariantMatrixHtml($element)
            ));
        } else {
            /** @var Commerce_VariantModel $variant */
            $variant = ArrayHelper::getFirstValue($element->getVariants());
            $namespace = $templatesService->getNamespace();
            $newNamespace = 'variants['.($variant->id ?: 'new1').']';
            $templatesService->setNamespace($newNamespace);
            $html .= $templatesService->namespaceInputs($templatesService->renderMacro('commerce/products/_fields', 'generalVariantFields', array($variant)));

            if ($productType->hasDimensions)
            {
                $html .= $templatesService->namespaceInputs($templatesService->renderMacro('commerce/products/_fields', 'dimensionVariantFields', array($variant)));
            }

            $templatesService->setNamespace($namespace);
            $templatesService->includeJs('Craft.Commerce.initUnlimitedStockCheckbox($(".elementeditor").find(".meta"));');
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
        CommerceProductHelper::populateProductModel($element, $params);
        CommerceProductHelper::populateProductVariantModels($element, $params['variants']);

        return craft()->commerce_products->saveProduct($element);
    }

}
