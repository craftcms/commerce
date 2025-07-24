<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\errors\StoreNotFoundException;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\helpers\Localization;
use craft\commerce\models\ProductType;
use craft\commerce\models\TaxAddressZone;
use craft\commerce\models\TaxRate;
use craft\commerce\Plugin;
use craft\commerce\records\TaxRate as TaxRateRecord;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\i18n\Locale;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
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

        return $this->renderTemplate('commerce/store-management/tax/taxrates/index', [
            'taxRates' => $taxRates,
        ]);
    }

    /**
     * @param int|null $id
     * @param TaxRate|null $taxRate
     * @throws ForbiddenHttpException
     * @throws HttpException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
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

        $variables = compact('id', 'taxRate', 'store', 'storeHandle');
        $variables['percentSymbol'] = Craft::$app->getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);

        $plugin = Plugin::getInstance();

        if (!$variables['taxRate']) {
            if ($variables['id']) {
                $variables['taxRate'] = $plugin->getTaxRates()->getTaxRateById($variables['id'], $store->id);

                if (!$variables['taxRate']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['taxRate'] = Craft::createObject([
                    'class' => TaxRate::class,
                    'storeId' => $store->id,
                ]);
            }
        }

        if ($variables['taxRate']->id) {
            $variables['title'] = $variables['taxRate']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new tax rate');
        }

        DebugPanel::prependOrAppendModelTab(model: $variables['taxRate'], prepend: true);

        $variables['taxZones'] = [
            ['value' => '', 'label' => ''],
        ];
        foreach ($plugin->getTaxZones()->getAllTaxZones($store->id)->all() as $zone) {
            $variables['taxZones'][] = ['value' => $zone->id, 'label' => $zone->name];
        }

        $taxCategories = $plugin->getTaxCategories()->getAllTaxCategories();
        $variables['taxCategories'] = [];

        foreach ($taxCategories as $model) {
            $variables['taxCategories'][$model->id] = $model->name;
        }

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

        // Get the HTML and JS for the new tax zone/category modals
        $view = $this->getView();
        $view->setNamespace('new');

        $view->startJsBuffer();

        $newZone = new TaxAddressZone();
        $condition = $newZone->getCondition();
        $condition->mainTag = 'div';
        $condition->name = 'condition';
        $condition->id = 'condition';
        $conditionField = Cp::fieldHtml($condition->getBuilderHtml(), [
            'label' => Craft::t('app', 'Address Condition'),
        ]);

        $variables['newTaxZoneFields'] = $view->namespaceInputs(
            $view->renderTemplate(
                'commerce/store-management/tax/taxzones/_fields',
                [
                    'conditionField' => $conditionField,
                    'store' => $store,
                ]
            )
        );
        $variables['newTaxZoneJs'] = $view->clearJsBuffer(false);

        $view->startJsBuffer();

        $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();
        $productTypesOptions = [];
        if (!empty($productTypes)) {
            $productTypesOptions = ArrayHelper::map($productTypes, 'id', fn(ProductType $row) => ['label' => $row->name, 'value' => $row->id]);
        }
        $variables['newTaxCategoryFields'] = $view->namespaceInputs(
            $view->renderTemplate('commerce/store-management/tax/taxcategories/_fields', compact('productTypes', 'productTypesOptions'))
        );

        $variables['newTaxCategoryJs'] = $view->clearJsBuffer(false);

        $view->setNamespace(null);

        $taxIdValidators = Plugin::getInstance()->getTaxes()->getEnabledTaxIdValidators();
        foreach ($taxIdValidators as $validator) {
            $variables['taxIdValidators'][] = $validator;
        }

        return $this->renderTemplate('commerce/store-management/tax/taxrates/_edit', $variables);
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
