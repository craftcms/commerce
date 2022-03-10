<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
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
    public function actionIndex(): Response
    {
        $plugin = Plugin::getInstance();
        $taxRates = $plugin->getTaxRates()->getAllTaxRates();

        // Preload all zone and category data for listing.
        $plugin->getTaxZones()->getAllTaxZones();
        $plugin->getTaxCategories()->getAllTaxCategories();

        return $this->renderTemplate('commerce/tax/taxrates/index', [
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
    public function actionEdit(int $id = null, TaxRate $taxRate = null): Response
    {
        if (!Plugin::getInstance()->getTaxes()->viewTaxRates()) {
            throw new ForbiddenHttpException('Tax engine does not permit you to perform this action');
        }

        $variables = compact('id', 'taxRate');
        $variables['percentSymbol'] = Craft::$app->getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);

        $plugin = Plugin::getInstance();

        if (!$variables['taxRate']) {
            if ($variables['id']) {
                $variables['taxRate'] = $plugin->getTaxRates()->getTaxRateById($variables['id']);

                if (!$variables['taxRate']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['taxRate'] = new TaxRate();
            }
        }

        if ($variables['taxRate']->id) {
            $variables['title'] = $variables['taxRate']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new tax rate');
        }

        DebugPanel::prependOrAppendModelTab(model: $variables['taxRate'], prepend: true);

        $taxZones = $plugin->getTaxZones()->getAllTaxZones();
        $variables['taxZones'] = [
            ['value' => '', 'label' => ''],
        ];

        foreach ($taxZones as $model) {
            $variables['taxZones'][$model->id] = $model->name;
        }

        $taxCategories = $plugin->getTaxCategories()->getAllTaxCategories();
        $variables['taxCategories'] = [];

        foreach ($taxCategories as $model) {
            $variables['taxCategories'][$model->id] = $model->name;
        }

        $taxable = [];
        $taxable[TaxRateRecord::TAXABLE_PRICE] = Craft::t('commerce', 'Line item price');
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
        $oldNamespace = $view->getNamespace();
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
            $view->renderTemplate('commerce/tax/taxzones/_fields', ['conditionField' => $conditionField])
        );
        $variables['newTaxZoneJs'] = $view->clearJsBuffer(false);

        $view->startJsBuffer();

        $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();
        $productTypesOptions = [];
        if (!empty($productTypes)) {
            $productTypesOptions = ArrayHelper::map($productTypes, 'id', function(ProductType $row) {
                return ['label' => $row->name, 'value' => $row->id];
            });
        }
        $variables['newTaxCategoryFields'] = $view->namespaceInputs(
            $view->renderTemplate('commerce/tax/taxcategories/_fields', compact('productTypes', 'productTypesOptions'))
        );
        $variables['newTaxCategoryJs'] = $view->clearJsBuffer(false);

        return $this->renderTemplate('commerce/tax/taxrates/_edit', $variables);
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
        $taxRate->id = Craft::$app->getRequest()->getBodyParam('taxRateId');
        $taxRate->name = Craft::$app->getRequest()->getBodyParam('name');
        $taxRate->code = Craft::$app->getRequest()->getBodyParam('code');
        $taxRate->include = (bool)Craft::$app->getRequest()->getBodyParam('include');
        $taxRate->removeIncluded = (bool)Craft::$app->getRequest()->getBodyParam('removeIncluded');
        $taxRate->removeVatIncluded = (bool)Craft::$app->getRequest()->getBodyParam('removeVatIncluded');
        $taxRate->isVat = (bool)Craft::$app->getRequest()->getBodyParam('isVat');
        $taxRate->taxable = Craft::$app->getRequest()->getBodyParam('taxable');
        $taxRate->taxCategoryId = Craft::$app->getRequest()->getBodyParam('taxCategoryId', null);
        $taxRate->taxZoneId = Craft::$app->getRequest()->getBodyParam('taxZoneId');
        $taxRate->rate = Localization::normalizePercentage($this->request->getBodyParam('rate'));

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

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Plugin::getInstance()->getTaxRates()->deleteTaxRateById($id);
        return $this->asSuccess();
    }
}
