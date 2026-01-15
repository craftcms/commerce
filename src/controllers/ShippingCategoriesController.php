<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\ShippingCategory;
use craft\commerce\Plugin;
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
 * Class Shipping Categories Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingCategoriesController extends BaseShippingSettingsController
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

        $shippingCategories = Plugin::getInstance()->getShippingCategories()->getAllShippingCategories($store->id);

        // Generate table data with chips
        $tableData = [];
        foreach ($shippingCategories as $shippingCategory) {
            $label = Html::encode(Craft::t('site', $shippingCategory->name));
            $tableData[] = [
                'id' => $shippingCategory->id,
                'title' => $label,
                'chip' => Cp::chipHtml($shippingCategory, [
                    'labelHtml' => Html::a($label, $shippingCategory->getCpEditUrl(), [
                        'class' => ['chip-label', 'cell-bold'],
                    ]),
                ]),
                'url' => $shippingCategory->getCpEditUrl(),
                'handle' => $shippingCategory->handle,
                'description' => Html::encode(Craft::t('site', $shippingCategory->description)),
                'default' => $shippingCategory->default,
                '_showDelete' => (count($shippingCategories) > 1 && !$shippingCategory->default),
            ];
        }

        $this->getView()->registerTranslations('commerce', [
            'Default',
            'Description',
            'Handle',
            'Name',
            'Yes',
        ]);

        $tableData = Json::encode($tableData);

        $js = <<<JS
var columns = [
        { name: 'chip', title: Craft.t('commerce', 'Name') },
        { name: '__slot:handle', title: Craft.t('commerce', 'Handle') },
        { name: 'description', title: Craft.t('commerce', 'Description') },
        {
            name: 'default',
            title: Craft.t('commerce', 'Default'),
            callback: function(value) {
                if (value) {
                    return '<div data-icon="check" title="'+Craft.escapeHtml(Craft.t('commerce','Yes'))+'"></div>';
                }
            }
        },
    ];

    new Craft.VueAdminTable({
        actions: [
        {
            label: '',
            icon: 'settings',
            actions: [
                {
                    label: Craft.t('commerce', 'Set Default Category'),
                    action: 'commerce/shipping-categories/set-default-category',
                    param: 'storeHandle',
                    value: '{$storeHandle}',
                    allowMultiple: false
                }
            ]
        }
    ],
        checkboxes: true,
        columns: columns,
        container: '#shipping-vue-admin-table',
        deleteAction: 'commerce/shipping-categories/delete',
        padded: true,
        tableData: {$tableData},
    });
    
JS;


        $this->getView()->registerJs($js, View::POS_END);

        return $this->asStoreManagementCpScreen($storeHandle)
            ->additionalButtonsHtml(Html::a(
                Craft::t('commerce', 'New shipping category'),
                $store->getStoreSettingsUrl('shippingcategories/new'),
                ['class' => 'btn submit add icon']
            ))
            ->contentHtml(Html::tag('div', '', ['id' => 'shipping-vue-admin-table']));
    }

    /**
     * @param int|null $id
     * @param ShippingCategory|null $shippingCategory
     * @throws HttpException
     */
    public function actionEdit(?string $storeHandle = null, int $id = null, ShippingCategory $shippingCategory = null): Response
    {
        $variables = [
            'id' => $id,
            'shippingCategory' => $shippingCategory,
            'productTypes' => Plugin::getInstance()->getProductTypes()->getAllProductTypes(),
            'storeHandle' => $storeHandle,
        ];

        $store = null;
        if ($storeHandle !== null) {
            $store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle);
        }

        $store ??= Plugin::getInstance()->getStores()->getPrimaryStore();

        if (!$variables['shippingCategory']) {
            if ($variables['id']) {
                $variables['shippingCategory'] = Plugin::getInstance()
                    ->getShippingCategories()
                    ->getShippingCategoryById($variables['id'], $store->id);

                if (!$variables['shippingCategory']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['shippingCategory'] = Craft::createObject([
                    'class' => ShippingCategory::class,
                    'attributes' => ['storeId' => $store->id],
                ]);
            }
        }

        if ($variables['shippingCategory']->id) {
            $variables['title'] = $variables['shippingCategory']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new shipping category');
        }

        DebugPanel::prependOrAppendModelTab(model: $variables['shippingCategory'], prepend: true);

        $variables['productTypesOptions'] = [];
        if (!empty($variables['productTypes'])) {
            $variables['productTypesOptions'] = ArrayHelper::map($variables['productTypes'], 'id', fn($row) => ['label' => $row->name, 'value' => $row->id]);
        }

        $allShippingCategories = Plugin::getInstance()->getShippingCategories()->getAllShippingCategories($store->id);
        $variables['isDefaultAndOnlyCategory'] = $variables['id'] && $allShippingCategories->count() === 1 && $allShippingCategories->firstWhere('id', $variables['id']);

        $metaSidebar = '';
        if ($variables['shippingCategory']->id) {
            $metaSidebar = Cp::metadataHtml([
                Craft::t('app', 'Created at') => Craft::$app->getFormatter()->asDatetime($variables['shippingCategory']->dateCreated, 'short'),
                Craft::t('app', 'Updated at') => Craft::$app->getFormatter()->asDatetime($variables['shippingCategory']->dateUpdated, 'short'),
            ]);
        }

        return $this->asStoreManagementCpScreen($storeHandle, false)
            ->title($variables['title'])
            ->addCrumb(Craft::t('commerce', 'Shipping Categories'),$store->getStoreSettingsUrl('shippingcategories'))
            ->action('commerce/shipping-categories/save')
            ->redirectUrl($store->getStoreSettingsUrl('shippingcategories'))
            ->metaSidebarHtml($metaSidebar)
            ->contentTemplate('commerce/store-management/shipping/shippingcategories/_edit', $variables);
    }

    /**
     * @throws BadRequestHttpException
     * @throws Exception
     * @noinspection Duplicates
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $shippingCategory = new ShippingCategory();

        // Shared attributes
        $shippingCategory->id = $this->request->getBodyParam('shippingCategoryId');
        $shippingCategory->storeId = $this->request->getBodyParam('storeId');
        $shippingCategory->name = $this->request->getBodyParam('name');
        $shippingCategory->handle = $this->request->getBodyParam('handle');
        $shippingCategory->icon = $this->request->getBodyParam('icon');
        $shippingCategory->color = $this->request->getBodyParam('color');
        $shippingCategory->description = $this->request->getBodyParam('description');
        $shippingCategory->default = (bool)$this->request->getBodyParam('default');

        // Set the new product types
        // If this is the default category, it should be available to all product types
        if ($shippingCategory->default) {
            $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();
        } else {
            $postedProductTypes = $this->request->getBodyParam('productTypes', []) ?: [];
            $productTypes = [];
            foreach ($postedProductTypes as $productTypeId) {
                if ($productTypeId && $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($productTypeId)) {
                    $productTypes[] = $productType;
                }
            }
        }
        $shippingCategory->setProductTypes($productTypes);


        // Save it
        if (!Plugin::getInstance()->getShippingCategories()->saveShippingCategory($shippingCategory)) {
            return $this->asModelFailure(
                $shippingCategory,
                Craft::t('commerce', 'Couldn’t save shipping category.'),
                'shippingCategory'
            );
        }

        return $this->asModelSuccess(
            $shippingCategory,
            Craft::t('commerce', 'Shipping category saved.'),
            'shippingCategory',
            data: [
                'id' => $shippingCategory->id,
                'name' => $shippingCategory->name,
            ]
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
            if (!Plugin::getInstance()->getShippingCategories()->deleteShippingCategoryById($id)) {
                $failedIds[] = $id;
            }
        }

        if (!empty($failedIds)) {
            return $this->asFailure(Craft::t('commerce', 'Could not delete {count, number} shipping {count, plural, one{category} other{categories}}.', [
                'count' => count($failedIds),
            ]));
        }

        return $this->asSuccess(Craft::t('commerce', 'Shipping categories deleted.'));
    }

    /**
     * @throws BadRequestHttpException
     * @throws Exception
     * @since 3.2.9
     */
    public function actionSetDefaultCategory(): ?Response
    {
        $this->requirePostRequest();

        $ids = $this->request->getRequiredBodyParam('ids');
        $storeHandle = $this->request->getRequiredBodyParam('storeHandle');
        if (!$storeHandle || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            throw new InvalidConfigException('Invalid store.');
        }

        if (!empty($ids)) {
            $id = ArrayHelper::firstValue($ids);

            $shippingCategory = Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($id, $store->id);
            if ($shippingCategory) {
                $shippingCategory->default = true;
                if (Plugin::getInstance()->getShippingCategories()->saveShippingCategory($shippingCategory)) {
                    $this->setSuccessFlash(Craft::t('commerce', 'Shipping category updated.'));
                    return null;
                }
            }
        }

        $this->setFailFlash(Craft::t('commerce', 'Unable to set default shipping category.'));
        return null;
    }
}
