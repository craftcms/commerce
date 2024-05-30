<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\conditions\purchasables\CatalogPricingRulePurchasableCondition;
use craft\commerce\elements\conditions\purchasables\PurchasableConditionRule;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\CatalogPricingRule;
use craft\commerce\Plugin;
use craft\commerce\records\CatalogPricingRule as CatalogPricingRuleRecord;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Localization;
use craft\helpers\MoneyHelper;
use craft\helpers\UrlHelper;
use craft\i18n\Locale;
use Exception;
use Throwable;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class Catalog Pricing Rules Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricingRulesController extends BaseStoreManagementController
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('commerce-managePromotions');

        return true;
    }

    /**
     * @throws InvalidConfigException
     */
    public function actionIndex(?string $storeHandle = null): Response
    {
        if ($storeHandle !== null) {
            $store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle);
        } else {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $catalogPricingRules = Plugin::getInstance()->getcatalogPricingRules()->getAllcatalogPricingRules($store->id);
        return $this->renderTemplate('commerce/store-management/pricing-rules/index', compact('catalogPricingRules'));
    }

    /**
     * @param int|null $id
     * @param CatalogPricingRule|null $catalogPricingRule
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function actionEdit(?string $storeHandle = null, int $id = null, CatalogPricingRule $catalogPricingRule = null): Response
    {
        if ($id === null) {
            $this->requirePermission('commerce-createCatalogPricingRules');
        } else {
            $this->requirePermission('commerce-editCatalogPricingRules');
        }

        $store = null;
        if ($storeHandle !== null) {
            $store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle);
        }

        $store = $store ?? Plugin::getInstance()->getStores()->getPrimaryStore();

        $variables = compact('id', 'catalogPricingRule', 'storeHandle');

        if (!$variables['catalogPricingRule']) {
            if ($variables['id']) {
                $variables['catalogPricingRule'] = Plugin::getInstance()->getcatalogPricingRules()->getcatalogPricingRuleById($variables['id'], $store->id);

                if (!$variables['catalogPricingRule'] || $variables['catalogPricingRule']->storeId !== $store->id) {
                    throw new HttpException(404);
                }
            } else {
                /** @var CatalogPricingRule $catalogPricingRule */
                $catalogPricingRule = Craft::createObject([
                    'class' => CatalogPricingRule::class,
                    'storeId' => $store->id,
                ]);

                $purchasableId = Craft::$app->getRequest()->getParam('purchasableId');
                if ($purchasableId && $purchasableType = Craft::$app->getElements()->getElementTypeById($purchasableId)) {
                    $purchasable = Craft::$app->getElements()->getElementById($purchasableId, $purchasableType);

                    // Create a "first pass" name for the rule
                    if ($purchasable && $purchasable->title) {
                        $catalogPricingRule->name = Craft::t('commerce', '{name} catalog price', ['name' => $purchasable->title]);
                    }

                    $rule = Craft::$app->getConditions()->createConditionRule([
                        'class' => PurchasableConditionRule::class,
                        'elementIds' => [$purchasableType => [$purchasableId]],
                    ]);

                    /** @var CatalogPricingRulePurchasableCondition $purchasableCondition */
                    $purchasableCondition = Craft::$app->getConditions()->createCondition(CatalogPricingRulePurchasableCondition::class);
                    $purchasableCondition->addConditionRule($rule);
                    $catalogPricingRule->setPurchasableCondition($purchasableCondition);
                }

                $variables['catalogPricingRule'] = $catalogPricingRule;
            }
        }

        DebugPanel::prependOrAppendModelTab(model: $variables['catalogPricingRule'], prepend: true);

        $variables = $this->_populateVariables($variables);

        return $this->asCpScreen()
            ->title(Craft::t('commerce', 'Catalog Pricing Rule'))
            ->crumbs([
                ['label' => $store->getName(), 'url' => UrlHelper::cpUrl('commerce/store-management/' . $store->handle)],
                ['label' => Craft::t('commerce', 'Pricing Rules'), 'url' => UrlHelper::cpUrl('commerce/store-management/' . $store->handle . '/pricing-rules')],
            ])
            ->action('commerce/catalog-pricing-rules/save')
            ->redirectUrl('commerce/store-management/' . $store->handle . '/pricing-rules')
            ->metaSidebarTemplate('commerce/store-management/pricing-rules/_sidebar', $variables)
            ->tabs([
                [
                    'label' => Craft::t('commerce', 'Rule'),
                    'url' => '#rule',
                    'class' => array_filter([$variables['catalogPricingRule']->getErrors() ? 'error' : null]),
                ],
                [
                    'label' => Craft::t('commerce', 'Conditions'),
                    'url' => '#conditions',
                ],
                [
                    'label' => Craft::t('commerce', 'Actions'),
                    'url' => '#actions',
                    'class' => array_filter([($variables['catalogPricingRule']->getErrors('applyAmount') or $variables['catalogPricingRule']->getErrors('apply')) ? 'error' : null]),
                ],
            ])
            ->contentTemplate('commerce/store-management/pricing-rules/_edit', $variables);
    }

    /**
     * @throws Exception
     * @throws \yii\base\Exception
     * @throws BadRequestHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $id = $this->request->getBodyParam('id');
        $storeId = $this->request->getBodyParam('storeId');

        if ($id) {
            $catalogPricingRule = Plugin::getInstance()->getcatalogPricingRules()->getcatalogPricingRuleById($id, $storeId);
            if (!$catalogPricingRule) {
                throw new NotFoundHttpException('Catalog Pricing Rule not found');
            }
        } else {
            $catalogPricingRule = Craft::createObject(CatalogPricingRule::class);
        }

        if ($catalogPricingRule->id === null) {
            $this->requirePermission('commerce-createCatalogPricingRules');
        } else {
            $this->requirePermission('commerce-editCatalogPricingRules');
        }

        $catalogPricingRule->storeId = $storeId;
        $catalogPricingRule->name = $this->request->getBodyParam('name');
        $catalogPricingRule->description = $this->request->getBodyParam('description');
        $catalogPricingRule->apply = $this->request->getBodyParam('apply');
        $catalogPricingRule->enabled = (bool)$this->request->getBodyParam('enabled');
        $catalogPricingRule->isPromotionalPrice = (bool)$this->request->getBodyParam('isPromotionalPrice');
        $catalogPricingRule->applyPriceType = $this->request->getBodyParam('applyPriceType');

        $catalogPricingRule->dateFrom =
            ($date = $this->request->getBodyParam('dateFrom')) !== false
            ? (DateTimeHelper::toDateTime($date) ?: null)
            : $catalogPricingRule->dateFrom;

        $catalogPricingRule->dateTo =
            ($date = $this->request->getBodyParam('dateTo')) !== false
            ? (DateTimeHelper::toDateTime($date) ?: null)
            : $catalogPricingRule->dateTo;

        $applyAmount = $this->request->getBodyParam('applyAmount');

        if ($catalogPricingRule->apply == CatalogPricingRuleRecord::APPLY_BY_PERCENT || $catalogPricingRule->apply == CatalogPricingRuleRecord::APPLY_TO_PERCENT) {
            $applyAmount = Localization::normalizeNumber($applyAmount);
            $catalogPricingRule->applyAmount = (float)$applyAmount / -100;
        } else {
            if (is_array($applyAmount)) {
                $applyAmount += [
                    'currency' => $catalogPricingRule->getStore()->getCurrency(),
                ];
                $applyAmount = MoneyHelper::toDecimal(MoneyHelper::toMoney($applyAmount));
            }
            $catalogPricingRule->applyAmount = (float)$applyAmount * -1;
        }

        // Set purchasable conditions
        $purchasableCondition = $this->request->getBodyParam('purchasableCondition');
        if ($purchasableCondition === null) {
            $purchasableCondition = Craft::$app->getConditions()->createCondition([
                'class' => CatalogPricingRulePurchasableCondition::class,
            ]);
        }

        $catalogPricingRule->setPurchasableCondition($purchasableCondition);

        // Set user conditions
        $catalogPricingRule->setCustomerCondition($this->request->getBodyParam('customerCondition'));

        // Save it
        if (Plugin::getInstance()->getcatalogPricingRules()->saveCatalogPricingRule($catalogPricingRule)) {
            return $this->asSuccess(Craft::t('commerce', 'Catalog pricing rule saved.'));
        }

        $variables = compact('catalogPricingRule');
        $this->_populateVariables($variables);

        return $this->asFailure(Craft::t('commerce', 'Couldn’t save catalog pricing rule.'), [], $variables);
    }

    /**
     * @throws Exception
     * @throws Throwable
     * @throws StaleObjectException
     * @throws BadRequestHttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePermission('commerce-deleteCatalogPricingRules');
        $this->requirePostRequest();

        $id = $this->request->getBodyParam('id');
        $ids = $this->request->getBodyParam('ids');

        if ((!$id && empty($ids)) || ($id && !empty($ids))) {
            throw new BadRequestHttpException('id or ids must be specified.');
        }

        if ($id) {
            $this->requireAcceptsJson();
            $ids = [$id];
        }

        foreach ($ids as $id) {
            Plugin::getInstance()->getcatalogPricingRules()->deletecatalogPricingRuleById($id);
        }

        if ($this->request->getAcceptsJson()) {
            return $this->asSuccess();
        }

        $this->setSuccessFlash(Craft::t('commerce', 'Catalog pricing rules deleted.'));

        return $this->redirect($this->request->getReferrer());
    }

    /**
     * @throws BadRequestHttpException
     * @throws \yii\db\Exception
     * @throws ForbiddenHttpException
     */
    public function actionUpdateStatus(): void
    {
        $this->requirePostRequest();
        $this->requirePermission('commerce-editCatalogPricingRules');

        $ids = $this->request->getRequiredBodyParam('ids');
        $status = $this->request->getRequiredBodyParam('status');


        if (empty($ids)) {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t update catalog pricing rule statuses.'));
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        $rules = CatalogPricingRuleRecord::find()
            ->where(['id' => $ids])
            ->all();
        $storeId = null;

        /** @var CatalogPricingRuleRecord $rule */
        foreach ($rules as $rule) {
            $storeId = $storeId ?? $rule->storeId;
            $rule->enabled = ($status == 'enabled');
            $rule->save();
        }
        $transaction->commit();

        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
            'catalogPricingRuleIds' => $ids,
            'storeId' => $storeId,
        ]);

        $this->setSuccessFlash(Craft::t('commerce', 'Catalog pricing rules updated.'));
    }

    /**
     * @param $variables
     * @return array
     * @throws InvalidConfigException
     */
    private function _populateVariables($variables): array
    {
        /** @var CatalogPricingRule $catalogPricingRule */
        $catalogPricingRule = $variables['catalogPricingRule'];

        if ($catalogPricingRule->id) {
            $variables['title'] = $catalogPricingRule->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new catalog pricing rule');
        }

        //getting user groups map
        $groups = Craft::$app->getUserGroups()->getAllGroups();
        $variables['groups'] = ArrayHelper::map($groups, 'id', 'name');

        $variables['percentSymbol'] = Craft::$app->getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);
        $primaryCurrencyIso = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        $variables['currencySymbol'] = Craft::$app->getLocale()->getCurrencySymbol($primaryCurrencyIso);

        $variables['applyAmount'] = '';
        if (isset($variables['catalogPricingRule']->applyAmount) && $variables['catalogPricingRule']->applyAmount !== null) {
            if ($catalogPricingRule->apply == CatalogPricingRuleRecord::APPLY_BY_PERCENT || $catalogPricingRule->apply == CatalogPricingRuleRecord::APPLY_TO_PERCENT) {
                $amount = -(float)$variables['catalogPricingRule']->applyAmount * 100;
                $variables['applyAmount'] = Craft::$app->getFormatter()->asDecimal($amount);
            } else {
                $variables['applyAmount'] = Craft::$app->getFormatter()->asDecimal(-(float)$variables['catalogPricingRule']->applyAmount);
            }
        }

        $variables['applyOptions'] = [
            ['optgroup' => Craft::t('commerce', 'Reduce price')],
            ['label' => Craft::t('commerce', 'Reduce the price by a percentage of the original price'), 'value' => CatalogPricingRuleRecord::APPLY_BY_PERCENT],
            ['label' => Craft::t('commerce', 'Reduce the price by a fixed amount'), 'value' => CatalogPricingRuleRecord::APPLY_BY_FLAT],
            ['optgroup' => Craft::t('commerce', 'Set price')],
            ['label' => Craft::t('commerce', 'Set the price to a percentage of the original price'), 'value' => CatalogPricingRuleRecord::APPLY_TO_PERCENT],
            ['label' => Craft::t('commerce', 'Set the price to a flat amount'), 'value' => CatalogPricingRuleRecord::APPLY_TO_FLAT],
        ];

        $variables['applyPriceTypeOptions'] = [
            ['label' => Craft::t('commerce', 'Original price'), 'value' => 'price' ],
            ['label' => Craft::t('commerce', 'Original promotional price'), 'value' => 'promotionalPrice' ],
        ];

        return $variables;
    }
}
