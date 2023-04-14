<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\conditions\purchasables\CatalogPricingRulePurchasableCondition;
use craft\commerce\elements\conditions\purchasables\PurchasableConditionRule;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\CatalogPricingRule;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\records\CatalogPricingRule as CatalogPricingRuleRecord;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Localization;
use craft\i18n\Locale;
use Exception;
use Throwable;
use yii\base\InvalidArgumentException;
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
class CatalogPricingRulesController extends BaseStoreSettingsController
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
        return $this->renderTemplate('commerce/store-settings/pricing-rules/index', compact('catalogPricingRules'));
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
                $variables['catalogPricingRule'] = Plugin::getInstance()->getcatalogPricingRules()->getcatalogPricingRuleById($variables['id']);

                if (!$variables['catalogPricingRule'] || $variables['catalogPricingRule']->storeId !== $store->id) {
                    throw new HttpException(404);
                }
            } else {
                $variables['catalogPricingRule'] = Craft::createObject([
                    'class' => CatalogPricingRule::class,
                    'storeId' => $store->id,
                ]);
            }
        }

        DebugPanel::prependOrAppendModelTab(model: $variables['catalogPricingRule'], prepend: true);

        $variables = $this->_populateVariables($variables);

        return $this->renderTemplate('commerce/store-settings/pricing-rules/_edit', $variables);
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

        if ($id) {
            $catalogPricingRule = Plugin::getInstance()->getcatalogPricingRules()->getcatalogPricingRuleById($id);
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

        $catalogPricingRule->storeId = $this->request->getBodyParam('storeId');
        $catalogPricingRule->name = $this->request->getBodyParam('name');
        $catalogPricingRule->description = $this->request->getBodyParam('description');
        $catalogPricingRule->apply = $this->request->getBodyParam('apply');
        $catalogPricingRule->enabled = (bool)$this->request->getBodyParam('enabled');
        $catalogPricingRule->isPromotionalPrice = (bool)$this->request->getBodyParam('isPromotionalPrice');
        $catalogPricingRule->applyPriceType = $this->request->getBodyParam('applyPriceType');
        $catalogPricingRule->purchasableId = $this->request->getBodyParam('purchasableId');

        $dateFields = [
            'dateFrom',
            'dateTo',
        ];
        foreach ($dateFields as $field) {
            if (($date = $this->request->getBodyParam($field)) !== false) {
                $catalogPricingRule->$field = DateTimeHelper::toDateTime($date) ?: null;
            } else {
                $catalogPricingRule->$field = $catalogPricingRule->$date;
            }
        }

        $applyAmount = $this->request->getBodyParam('applyAmount');

        $applyAmount = Localization::normalizeNumber($applyAmount);
        if ($catalogPricingRule->apply == CatalogPricingRuleRecord::APPLY_BY_PERCENT || $catalogPricingRule->apply == CatalogPricingRuleRecord::APPLY_TO_PERCENT) {
            $catalogPricingRule->applyAmount = (float)$applyAmount / -100;
        } else {
            $catalogPricingRule->applyAmount = (float)$applyAmount * -1;
        }

        // Set purchasable conditions
        $purchasableCondition = $this->request->getBodyParam('purchasableCondition');
        if ($purchasableCondition === null) {
            $purchasableCondition = Craft::$app->getConditions()->createCondition([
                'class' => CatalogPricingRulePurchasableCondition::class,
            ]);

            if ($catalogPricingRule->purchasableId) {
                $purchasableType = Craft::$app->getElements()->getElementTypeById($catalogPricingRule->purchasableId);
                if ($purchasableType === null) {
                    throw new Exception('Invalid purchasable ID: ' . $catalogPricingRule->purchasableId);
                }

                $rule = Craft::$app->getConditions()->createConditionRule([
                    'class' => PurchasableConditionRule::class,
                    'elementIds' => [$purchasableType => [$catalogPricingRule->purchasableId]]
                ]);
                $purchasableCondition->addConditionRule($rule);
            }
        }

        $catalogPricingRule->setPurchasableCondition($purchasableCondition);

        // Set user conditions
        $catalogPricingRule->setCustomerCondition($this->request->getBodyParam('customerCondition'));

        // Save it
        if (Plugin::getInstance()->getcatalogPricingRules()->saveCatalogPricingRule($catalogPricingRule)) {
            $successMessage = $catalogPricingRule->isStoreRule()
                ? Craft::t('commerce', 'Catalog pricing rule saved.')
                : Craft::t('commerce', 'Catalog price saved.');
            return $this->asSuccess($successMessage);
        }

        $variables = compact('catalogPricingRule');
        $this->_populateVariables($variables);

        $failureMessage = $catalogPricingRule->isStoreRule()
            ? Craft::t('commerce', 'Couldn’t save catalog pricing rule.')
            : Craft::t('commerce', 'Couldn’t save catalog price.');

        return $this->asFailure($failureMessage, [], $variables);
    }

    /**
     * @throws Exception
     * @throws Throwable
     * @throws StaleObjectException
     * @throws BadRequestHttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePermission('commerce-deletecatalogPricingRules');
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
     * @throws InvalidConfigException
     */
    public function actionGetcatalogPricingRulesByPurchasableId(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $id = $this->request->getParam('id');

        if (!$id) {
            return $this->asFailure(Craft::t('commerce', 'Purchasable ID is required.'));
        }

        $purchasable = Plugin::getInstance()->getPurchasables()->getPurchasableById($id);

        if (!$purchasable) {
            return $this->asFailure(Craft::t('commerce', 'No purchasable available.'));
        }

        $rules = [];

        return $this->asSuccess(data: [
            'catalogPricingRules' => $rules,
        ]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws \yii\db\Exception
     * @throws ForbiddenHttpException
     * @since 3.0
     */
    public function actionUpdateStatus(): void
    {
        $this->requirePostRequest();
        $this->requirePermission('commerce-editCatalogPricingRules');

        $ids = $this->request->getRequiredBodyParam('ids');
        $status = $this->request->getRequiredBodyParam('status');


        if (empty($ids)) {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t updated catalog pricing rules status.'));
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        $rules = CatalogPricingRuleRecord::find()
            ->where(['id' => $ids])
            ->all();

        /** @var CatalogPricingRuleRecord $rule */
        foreach ($rules as $rule) {
            $rule->enabled = ($status == 'enabled');
            $rule->save();
        }
        $transaction->commit();

        $this->setSuccessFlash(Craft::t('commerce', 'Catalog pricing rules updated.'));
    }

    /**
     * @return Response
     */
    public function actionSlideout(): Response
    {
        $id = Craft::$app->getRequest()->getQueryParam('id');
        $storeId = Craft::$app->getRequest()->getQueryParam('storeId');
        $purchasableId = Craft::$app->getRequest()->getQueryParam('purchasableId');
        $catalogPricingRule = $id ? Plugin::getInstance()->getCatalogPricingRules()->getCatalogPricingRuleById($id, $storeId) : null;

        if ($id && !$catalogPricingRule) {
            throw new NotFoundHttpException('Catalog pricing rule not found');
        }

        if (!$id) {
            if (!$storeId) {
                throw new InvalidArgumentException('Store ID is required');
            }

            $catalogPricingRule = Craft::createObject([
                'class' => CatalogPricingRule::class,
                'storeId' => $storeId,
                'purchasableId' => $purchasableId,
                'name' => Craft::t('commerce', '{name} pricing rule', ['name' => Craft::$app->getRequest()->getQueryParam('title', Craft::t('commerce', 'Purchasable'))]),
            ]);
        }

        $template = 'commerce/store-settings/pricing-rules/_slideout';
        $variables = compact('id', 'catalogPricingRule');

        return $this->asCpScreen()
            ->title('Create a new catalog pricing rule')
            // ->addCrumb(Craft::t('app', 'Settings'), 'settings')
            // ->addCrumb(Craft::t('app', 'Filesystems'), 'settings/filesystems')
            ->tabs([
                ['label' => Craft::t('commerce', 'Conditions'), 'url' => '#conditions', 'visible' => true],
                ['label' => Craft::t('commerce', 'Actions'), 'url' => '#actions', 'visible' => false],
            ])
            ->action('commerce/catalog-pricing-rules/save')
            ->redirectUrl('commerce/catalog-pricing-rules/')
            ->contentTemplate($template, $this->_populateVariables($variables));
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
