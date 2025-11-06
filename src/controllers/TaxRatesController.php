<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\errors\StoreNotFoundException;
use craft\commerce\helpers\Cp as CommerceCp;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\helpers\Localization;
use craft\commerce\models\TaxRate;
use craft\commerce\Plugin;
use craft\commerce\records\TaxRate as TaxRateRecord;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\i18n\Locale;
use craft\web\assets\admintable\AdminTableAsset;
use craft\web\View;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Tax Rates Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxRatesController extends BaseTaxSettingsController
{
    /**
     * @param string|null $storeHandle
     * @return Response
     * @throws StoreNotFoundException
     * @throws InvalidConfigException
     */
    public function actionIndex(?string $storeHandle = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $plugin = Plugin::getInstance();
        $taxRates = $plugin->getTaxRates()->getAllTaxRates($store->id);

        // Preload all zone and category data for listing.
        $plugin->getTaxZones()->getAllTaxZones($store->id);
        $plugin->getTaxCategories()->getAllTaxCategories();

        // Generate table data
        $tableData = [];
        foreach ($taxRates as $taxRate) {
            $label = Craft::t('site', $taxRate->name);
            $tableData[] = [
                'id' => $taxRate->id,
                'status' => $taxRate->enabled,
                'title' => Html::a($label, $taxRate->getCpEditUrl()),
                'url' => $taxRate->getCpEditUrl(),
                'rate' => $taxRate->getRateAsPercent(),
                'included' => $taxRate->include,
                'removeIncluded' => $taxRate->removeIncluded,
                'vat' => $taxRate->isVat,
                'zone' => $taxRate->isEverywhere ? Craft::t('commerce', 'Everywhere') : ($taxRate->taxZone ? $taxRate->taxZone->name : ''),
                'category' => $taxRate->taxCategory ? Cp::chipHtml($taxRate->taxCategory) : '',
            ];
        }

        $this->getView()->registerTranslations('commerce', [
            'Include in price?',
            'Remove from price?',
            'Name',
            'Rate',
            'Tax Category',
            'Tax Zone',
            'Yes',
        ]);

        $buttonsHtml = Plugin::getInstance()->getTaxes()->taxRateActionHtml();

        if (Plugin::getInstance()->getTaxes()->createTaxRates()) {
            $buttonsHtml .= Html::a(Craft::t('commerce', 'New tax rate'), "commerce/store-management/$storeHandle/taxrates/new", [
                'class' => 'btn submit add icon',
            ]);
        }

        $tableData = Json::encode($tableData, JSON_UNESCAPED_UNICODE);
        $deleteAction = Plugin::getInstance()->getTaxes()->deleteTaxRates() ? 'commerce/tax-rates/delete' : null;

        $js = <<<JS
var columns = [
    { name: 'title', title: Craft.t('commerce', 'Name') },
    { name: 'rate', title: Craft.t('commerce', 'Rate') },
    { name: 'included', title: Craft.t('commerce', 'Include in price?'), callback: function(value) {
      if (value) {
          return '<span data-icon="check" title="'+Craft.escapeHtml(Craft.t('commerce', 'Yes'))+'"></span>';
      }
    } },
    { name: 'removeIncluded', title: Craft.t('commerce', 'Remove from price?'), callback: function(value) {
            if (value) {
                return '<span data-icon="check" title="'+Craft.escapeHtml(Craft.t('commerce', 'Yes'))+'"></span>';
            }
        } },
    { name: 'zone', title: Craft.t('commerce', 'Tax Zone') },
    { name: 'category', title: Craft.t('commerce', 'Tax Category') }
];

var actions = [
  {
    label: Craft.t('commerce', 'Set status'),
    actions: [
      {
        label: Craft.t('commerce', 'Enabled'),
        action: 'commerce/tax-rates/update-status',
        param: 'status',
        value: 'enabled',
        status: 'enabled'
      },
      {
        label: Craft.t('commerce', 'Disabled'),
        action: 'commerce/tax-rates/update-status',
        param: 'status',
        value: 'disabled',
        status: 'disabled'
      }
    ]
  }
];

new Craft.VueAdminTable({
    columns: columns,
    actions: actions,
    checkboxes: true,
    container: '#taxrate-vue-admin-table',
    deleteAction: '{$deleteAction}',
    tableData: {$tableData},
});
JS;

        $this->getView()->registerJs($js, View::POS_END);

        return $this->asStoreManagementCpScreen($storeHandle)
            ->additionalButtonsHtml($buttonsHtml)
            ->contentHtml(Html::tag('div', '', ['id' => 'taxrate-vue-admin-table']));
    }

    /**
     * @param int|null $id
     * @param TaxRate|null $taxRate
     * @throws ForbiddenHttpException
     * @throws HttpException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws Exception
     */
    public function actionEdit(?string $storeHandle = null, int $id = null, TaxRate $taxRate = null): Response
    {
        if (!Plugin::getInstance()->getTaxes()->viewTaxRates()) {
            throw new ForbiddenHttpException('Tax engine does not permit you to perform this action');
        }

        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $storeHandle = $store->handle;
        $percentSymbol = Craft::$app->getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);

        $plugin = Plugin::getInstance();

        if (!$taxRate) {
            if ($id) {
                $taxRate = $plugin->getTaxRates()->getTaxRateById($id, $store->id);

                if (!$taxRate) {
                    throw new HttpException(404);
                }
            } else {
                $taxRate = Craft::createObject([
                    'class' => TaxRate::class,
                    'storeId' => $store->id,
                ]);
            }
        }

        $title = $taxRate->id ? $taxRate->name : Craft::t('commerce', 'Create a new tax rate');

        DebugPanel::prependOrAppendModelTab(model: $taxRate, prepend: true);

        $variables = compact('taxRate', 'store', 'storeHandle', 'percentSymbol');

        // Get the actual tax zone object if there's an ID
        $taxZone = null;
        if ($taxRate->taxZoneId) {
            $taxZone = $plugin->getTaxZones()->getTaxZoneById($taxRate->taxZoneId, $store->id);
        }

        // Get the actual tax category object if there's an ID
        $taxCategory = null;
        if ($taxRate->taxCategoryId) {
            $taxCategory = $plugin->getTaxCategories()->getTaxCategoryById($taxRate->taxCategoryId);
        }

        // Tax zone field with slideout
        $variables['taxZoneField'] = CommerceCp::taxZoneFieldHtml([
            'label' => Craft::t('commerce', 'Tax Zone'),
            'instructions' => Craft::t('commerce', 'Select a tax zone. If empty, this rate will match anywhere.'),
            'id' => 'taxZoneId',
            'name' => 'taxZoneId',
            'value' => $taxZone,
            'errors' => $taxRate->getErrors('taxZoneId'),
            'required' => false,
            'limit' => 1,
            'storeId' => $store->id,
            'storeHandle' => $storeHandle,
        ]);

        // Tax category field with slideout
        $variables['taxCategoryField'] = CommerceCp::taxCategoryFieldHtml([
            'label' => Craft::t('commerce', 'Tax Category'),
            'instructions' => Craft::t('commerce', 'Select a tax category.'),
            'id' => 'taxCategoryId',
            'name' => 'taxCategoryId',
            'value' => $taxCategory,
            'errors' => $taxRate->getErrors('taxCategoryId'),
            'required' => true,
            'limit' => 1,
            'storeHandle' => $storeHandle,
        ]);

        $taxable = [];
        $taxable[TaxRateRecord::TAXABLE_PURCHASABLE] = Craft::t('commerce', 'Unit price (minus discounts)');
        $taxable[TaxRateRecord::TAXABLE_PRICE] = Craft::t('commerce', 'Line item price (minus discounts)');
        $taxable[TaxRateRecord::TAXABLE_SHIPPING] = Craft::t('commerce', 'Line item shipping cost');
        $taxable[TaxRateRecord::TAXABLE_PRICE_SHIPPING] = Craft::t('commerce', 'Both (Line item price + Line item shipping costs)');
        $taxable[TaxRateRecord::TAXABLE_ORDER_TOTAL_SHIPPING] = Craft::t('commerce', 'Order total shipping cost');
        $taxable[TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE] = Craft::t('commerce', 'Order total taxable price (Line item subtotal + Total discounts + Total shipping)');
        $variables['taxables'] = $taxable;
        $variables['taxablesNoTaxCategory'] = TaxRateRecord::ORDER_TAXABALES;

        $variables['hideTaxCategory'] = false;
        if ($variables['taxRate']->id && in_array($variables['taxRate']->taxable, $variables['taxablesNoTaxCategory'], false)) {
            $variables['hideTaxCategory'] = true;
        }

        $taxIdValidators = Plugin::getInstance()->getTaxes()->getEnabledTaxIdValidators();
        foreach ($taxIdValidators as $validator) {
            $variables['taxIdValidators'][] = $validator;
        }

        return $this->asCpScreen()
            ->title($title)
            ->crumbs([
                ['label' => Craft::t('commerce', 'Commerce'), 'url' => 'commerce'],
                $this->getStoreSwitcher($storeHandle),
                ['label' => Craft::t('commerce', 'Tax Rates'), 'url' => "commerce/store-management/{$storeHandle}/taxrates"],
            ])
            ->selectedSubnavItem('tax')
            ->action('commerce/tax-rates/save')
            ->redirectUrl($store->getStoreSettingsUrl('taxrates'))
            ->metaSidebarTemplate('commerce/store-management/tax/taxrates/_sidebar', $variables)
            ->contentTemplate('commerce/store-management/tax/taxrates/_edit', $variables);
    }

    /**
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws BadRequestHttpException
     */
    public function actionSave(): void
    {
        if (!Plugin::getInstance()->getTaxes()->editTaxRates()) {
            throw new ForbiddenHttpException('Tax engine does not permit you to perform this action');
        }

        $this->requirePostRequest();

        $taxRate = new TaxRate();

        // Shared attributes
        $taxRate->id = $this->request->getBodyParam('taxRateId');
        $taxRate->storeId = $this->request->getBodyParam('storeId');
        $taxRate->name = $this->request->getBodyParam('name');
        $taxRate->code = $this->request->getBodyParam('code');
        $taxRate->include = (bool)$this->request->getBodyParam('include');
        $taxRate->removeIncluded = (bool)$this->request->getBodyParam('removeIncluded');
        $taxRate->removeVatIncluded = (bool)$this->request->getBodyParam('removeVatIncluded');
        $taxRate->taxable = $this->request->getBodyParam('taxable');
        $taxRate->taxCategoryId = (int)$this->request->getBodyParam('taxCategoryId') ?: null;
        $taxRate->taxZoneId = (int)$this->request->getBodyParam('taxZoneId') ?: null;
        $taxRate->rate = Localization::normalizePercentage($this->request->getBodyParam('rate'));
        $taxRate->enabled = (bool)($this->request->getBodyParam('enabled'));

        // data comes in as className => bool, we want just the class names that are true
        $validators = collect($this->request->getBodyParam('taxIdValidators'))->filter(fn($enabled) => (bool)$enabled)->keys();
        $taxRate->taxIdValidators = $validators->toArray();

        // Save it
        if (Plugin::getInstance()->getTaxRates()->saveTaxRate($taxRate)) {
            $this->setSuccessFlash(Craft::t('commerce', 'Tax rate saved.'));
            $this->redirectToPostedUrl($taxRate);
        } else {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save tax rate.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'taxRate' => $taxRate,
        ]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     */
    public function actionDelete(): Response
    {
        if (!Plugin::getInstance()->getTaxes()->deleteTaxRates()) {
            throw new ForbiddenHttpException('Tax engine does not permit you to perform this action');
        }

        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = $this->request->getRequiredBodyParam('id');

        Plugin::getInstance()->getTaxRates()->deleteTaxRateById($id);
        return $this->asSuccess();
    }

    /**
     * @throws BadRequestHttpException
     * @throws Exception
     * @since 5.x
     */
    public function actionUpdateStatus(): void
    {
        $this->requirePostRequest();
        $ids = $this->request->getRequiredBodyParam('ids');
        $status = $this->request->getRequiredBodyParam('status');

        if (empty($ids)) {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t update status.'));
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        $taxRates = TaxRateRecord::find()
            ->where(['id' => $ids])
            ->all();

        /** @var TaxRateRecord $taxRate */
        foreach ($taxRates as $taxRate) {
            $taxRate->enabled = ($status == 'enabled');
            $taxRate->save();
        }
        $transaction->commit();

        $this->setSuccessFlash(Craft::t('commerce', 'Tax rates updated.'));
    }
}
