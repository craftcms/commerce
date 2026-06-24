<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\Store;
use craft\commerce\models\TaxCategory;
use craft\commerce\Plugin;
use craft\errors\MissingComponentException;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\web\View;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Tax Categories Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxCategoriesController extends BaseTaxSettingsController
{
    /**
     * @param string|null $storeHandle
     * @return Response
     * @throws InvalidConfigException
     */
    public function actionIndex(?string $storeHandle = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $taxCategories = Plugin::getInstance()->getTaxCategories()->getAllTaxCategories();

        // Generate table data with chips
        $tableData = [];
        foreach ($taxCategories as $taxCategory) {
            $label = Html::encode(Craft::t('site', $taxCategory->name));
            $taxRates = $taxCategory->getTaxRates($store->id);
            $tableData[] = [
                'id' => $taxCategory->id,
                'title' => $label,
                'chip' => Cp::chipHtml($taxCategory, [
                    'labelHtml' => Html::a($label, $taxCategory->getCpEditUrl($store->id), [
                        'class' => ['chip-label', 'cell-bold'],
                    ]),
                ]),
                'url' => $taxCategory->getCpEditUrl($store->id),
                'handle' => $taxCategory->handle,
                'description' => Html::encode(Craft::t('site', $taxCategory->description)),
                'default' => $taxCategory->default,
                '_showDelete' => $taxRates->isEmpty() && (count($taxCategories) > 1 && !$taxCategory->default),
            ];
        }

        $this->getView()->registerTranslations('commerce', [
            'Default?',
            'Description',
            'Handle',
            'Name',
            'Set default category',
            'Used By Tax Rates',
            'Used by Tax Rates',
        ]);

        $buttons = Plugin::getInstance()->getTaxes()->taxCategoryActionHtml();
        if (Plugin::getInstance()->getTaxes()->createTaxCategories()) {
            $buttons .= Html::a(Craft::t('commerce', 'New tax category'), $store->getStoreSettingsUrl('taxcategories/new'), [
                'class' => ['btn', 'submit', 'add', 'icon'],
            ]);
        }

        $tableData = Json::encode($tableData);
        $deleteAction = Plugin::getInstance()->getTaxes()->deleteTaxCategories() ? "'commerce/tax-categories/delete'" : 'null';

        $js = <<<JS
    var columns = [
        { name: 'chip', title: Craft.t('commerce', 'Name') },
        { name: '__slot:handle', title: Craft.t('commerce', 'Handle') },
        { name: 'description', title: Craft.t('commerce', 'Description') },
        {
            name: 'default',
            title: Craft.t('commerce', 'Default?'),
            callback: function(value) {
                if (value) {
                    return '<div data-icon="check"></div>';
                }
            }
        },
    ];

    var actions = [
        {
            label: '',
            icon: 'settings',
            actions: [
                {
                    label: Craft.t('commerce', 'Set default category'),
                    action: 'commerce/tax-categories/set-default-category',
                    param: 'default',
                    value: 1,
                    allowMultiple: false
                }
            ]
        }
    ];

    new Craft.VueAdminTable({
        columns: columns,
        checkboxes: true,
        actions: actions,
        padded: true,
        container: '#tax-vue-admin-table',
        deleteAction: {$deleteAction},
        tableData: {$tableData},
    });
JS;

        $this->getView()->registerJs($js, View::POS_END);

        return $this->asStoreManagementCpScreen($storeHandle, hasStoreSwitcher: false)
            ->additionalButtonsHtml($buttons)
            ->contentHtml(Html::tag('div', '', ['id' => 'tax-vue-admin-table']));
    }

    /**
     * @param int|null $id
     * @param TaxCategory|null $taxCategory
     * @throws HttpException
     */
    public function actionEdit(?string $storeHandle = null, ?int $id = null, ?TaxCategory $taxCategory = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $storeHandle = $store->handle;

        $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();

        if (!$taxCategory) {
            if ($id) {
                $taxCategory = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($id);

                if (!$taxCategory) {
                    throw new HttpException(404);
                }
            } else {
                $taxCategory = new TaxCategory();
            }
        }

        $title = $taxCategory->id ? $taxCategory->name : Craft::t('commerce', 'Create a new tax category');

        $productTypesOptions = [];
        if (!empty($productTypes)) {
            $productTypesOptions = ArrayHelper::map($productTypes, 'id', fn($row) => ['label' => $row->name, 'value' => $row->id]);
        }

        $allTaxCategoryIds = array_keys(Plugin::getInstance()->getTaxCategories()->getAllTaxCategories());
        $isDefaultAndOnlyCategory = $id && count($allTaxCategoryIds) === 1 && in_array($id, $allTaxCategoryIds);

        // Get all tax rates for all stores
        $taxRates = collect();
        Plugin::getInstance()->getStores()->getAllStores()->each(fn(Store $s) => $taxRates->push(...Plugin::getInstance()->getTaxRates()->getAllTaxRates($s->id)->all()));

        $metaSidebar = '';
        if ($taxCategory->id) {
            $metaSidebar = Cp::metadataHtml([
                Craft::t('app', 'Created at') => Craft::$app->getFormatter()->asDatetime($taxCategory->dateCreated, 'short'),
                Craft::t('app', 'Updated at') => Craft::$app->getFormatter()->asDatetime($taxCategory->dateUpdated, 'short'),
            ]);
        }

        return $this->asStoreManagementCpScreen($storeHandle, false, false)
            ->title($title)
            ->addCrumb(Craft::t('commerce', 'Tax Categories'), $store->getStoreSettingsUrl('taxcategories'))
            ->action('commerce/tax-categories/save')
            ->redirectUrl($store->getStoreSettingsUrl('taxcategories'))
            ->metaSidebarHtml($metaSidebar)
            ->contentTemplate('commerce/store-management/tax/taxcategories/_edit', [
                'taxCategory' => $taxCategory,
                'productTypes' => $productTypes,
                'productTypesOptions' => $productTypesOptions,
                'isDefaultAndOnlyCategory' => $isDefaultAndOnlyCategory,
                'taxRates' => $taxRates,
                'store' => $store,
            ]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws Exception
     * @noinspection Duplicates
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $taxCategory = new TaxCategory();

        // Shared attributes
        $taxCategory->id = $this->request->getBodyParam('taxCategoryId');
        $taxCategory->name = $this->request->getBodyParam('name');
        $taxCategory->handle = $this->request->getBodyParam('handle');
        $taxCategory->icon = $this->request->getBodyParam('icon');
        $taxCategory->color = $this->request->getBodyParam('color');
        $taxCategory->description = $this->request->getBodyParam('description');
        $taxCategory->default = (bool)$this->request->getBodyParam('default');

        // Set the new product types
        $postedProductTypes = $this->request->getBodyParam('productTypes', []) ?: [];
        $productTypes = [];
        foreach ($postedProductTypes as $productTypeId) {
            if ($productTypeId && $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($productTypeId)) {
                $productTypes[] = $productType;
            }
        }
        $taxCategory->setProductTypes($productTypes);

        // Save it
        if (!Plugin::getInstance()->getTaxCategories()->saveTaxCategory($taxCategory)) {
            return $this->asModelFailure(
                $taxCategory,
                Craft::t('commerce', 'Couldn’t save tax category.'),
                'taxCategory'
            );
        }

        return $this->asModelSuccess(
            $taxCategory,
            Craft::t('commerce', 'Tax category saved.'),
            'taxCategory'
        );
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): ?Response
    {
        $this->requirePostRequest();

        $id = $this->request->getBodyParam('id');
        $ids = $this->request->getBodyParam('ids');

        if ((!$id && empty($ids)) || ($id && !empty($ids))) {
            throw new BadRequestHttpException('id or ids must be specified.');
        }

        if ($id) {
            // If it is just the one id we know it has come from an ajax request on the table
            $this->requireAcceptsJson();
            $ids = [$id];
        }

        $failedIds = [];
        foreach ($ids as $id) {
            if (!Plugin::getInstance()->getTaxCategories()->deleteTaxCategoryById($id)) {
                $failedIds[] = $id;
            }
        }

        if (!empty($failedIds)) {
            return $this->asFailure(Craft::t('commerce', 'Could not delete {count, number} tax {count, plural, one{category} other{categories}}.', [
                'count' => count($failedIds),
            ]));
        }

        return $this->asSuccess(Craft::t('commerce', 'Tax categories deleted.'));
    }

    /**
     * @throws MissingComponentException
     * @throws Exception
     * @throws BadRequestHttpException
     * @since 3.2.9
     */
    public function actionSetDefaultCategory(): ?Response
    {
        $this->requirePostRequest();

        $ids = $this->request->getRequiredBodyParam('ids');

        if (!empty($ids)) {
            $id = ArrayHelper::firstValue($ids);

            $taxCategory = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($id);
            if ($taxCategory) {
                $taxCategory->default = true;
                if (Plugin::getInstance()->getTaxCategories()->saveTaxCategory($taxCategory)) {
                    $this->setSuccessFlash(Craft::t('commerce', 'Tax category updated.'));
                    return null;
                }
            }
        }

        $this->setFailFlash(Craft::t('commerce', 'Unable to set default tax category.'));
        return null;
    }
}
