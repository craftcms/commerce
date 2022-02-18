<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Product;
use craft\commerce\helpers\Localization;
use craft\commerce\models\Discount;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\records\Discount as DiscountRecord;
use craft\elements\Category;
use craft\errors\MissingComponentException;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\i18n\Locale;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\Response;
use function explode;
use function get_class;

/**
 * Class Discounts Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class DiscountsController extends BaseCpController
{
    const DISCOUNT_COUNTER_TYPE_TOTAL = 'total';
    const DISCOUNT_COUNTER_TYPE_EMAIL = 'email';
    const DISCOUNT_COUNTER_TYPE_CUSTOMER = 'customer';

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        if (Plugin::getInstance()->is(Plugin::EDITION_PRO, '<')) {
            throw new ForbiddenHttpException('Managing discounts is not permitted on the Lite edition.');
        }

        $this->requirePermission('commerce-managePromotions');
    }

    /**
     * @throws HttpException
     */
    public function actionIndex(): Response
    {
        $discounts = Plugin::getInstance()->getDiscounts()->getAllDiscounts();
        return $this->renderTemplate('commerce/promotions/discounts/index', compact('discounts'));
    }

    /**
     * @param int|null $id
     * @param Discount|null $discount
     * @throws HttpException
     */
    public function actionEdit(int $id = null, Discount $discount = null): Response
    {
        $variables = compact('id', 'discount');
        $variables['isNewDiscount'] = false;

        if (!$variables['discount']) {
            if ($variables['id']) {
                $variables['discount'] = Plugin::getInstance()->getDiscounts()->getDiscountById($variables['id']);

                if (!$variables['discount']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['discount'] = new Discount();
                $variables['discount']->allCategories = true;
                $variables['discount']->allPurchasables = true;
                $variables['isNewDiscount'] = true;
            }
        }

        $this->_populateVariables($variables);
        $variables['percentSymbol'] = Craft::$app->getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);

        return $this->renderTemplate('commerce/promotions/discounts/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave(): void
    {
        $this->requirePostRequest();

        $discount = new Discount();
        $request = Craft::$app->getRequest();

        $discount->id = $request->getBodyParam('id');
        $discount->name = $request->getBodyParam('name');
        $discount->description = $request->getBodyParam('description');
        $discount->enabled = (bool)$request->getBodyParam('enabled');
        $discount->setOrderMatchCondition($request->getBodyParam('orderCondition'));
        $discount->stopProcessing = (bool)$request->getBodyParam('stopProcessing');
        $discount->purchaseQty = $request->getBodyParam('purchaseQty');
        $discount->maxPurchaseQty = $request->getBodyParam('maxPurchaseQty');
        $discount->percentDiscount = (float)$request->getBodyParam('percentDiscount');
        $discount->percentageOffSubject = $request->getBodyParam('percentageOffSubject');
        $discount->hasFreeShippingForMatchingItems = (bool)$request->getBodyParam('hasFreeShippingForMatchingItems');
        $discount->hasFreeShippingForOrder = (bool)$request->getBodyParam('hasFreeShippingForOrder');
        $discount->excludeOnSale = (bool)$request->getBodyParam('excludeOnSale');
        $discount->code = trim($request->getBodyParam('code')) ?: null;
        $discount->perUserLimit = $request->getBodyParam('perUserLimit');
        $discount->perEmailLimit = $request->getBodyParam('perEmailLimit');
        $discount->totalDiscountUseLimit = $request->getBodyParam('totalDiscountUseLimit');
        $discount->ignoreSales = (bool)$request->getBodyParam('ignoreSales');
        $discount->categoryRelationshipType = $request->getBodyParam('categoryRelationshipType');
        $discount->baseDiscountType = $request->getBodyParam('baseDiscountType') ?: DiscountRecord::BASE_DISCOUNT_TYPE_VALUE;
        $discount->appliedTo = $request->getBodyParam('appliedTo') ?: DiscountRecord::APPLIED_TO_MATCHING_LINE_ITEMS;
        $discount->orderConditionFormula = $request->getBodyParam('orderConditionFormula');
        $discount->userGroupsCondition = $request->getBodyParam('userGroupsCondition');

        $baseDiscount = $request->getBodyParam('baseDiscount') ?: 0;
        $baseDiscount = Localization::normalizeNumber($baseDiscount);
        $discount->baseDiscount = $baseDiscount * -1;

        $perItemDiscount = $request->getBodyParam('perItemDiscount') ?: 0;
        $perItemDiscount = Localization::normalizeNumber($perItemDiscount);
        $discount->perItemDiscount = $perItemDiscount * -1;

        $discount->purchaseTotal = Localization::normalizeNumber($request->getBodyParam('purchaseTotal'));

        $date = $request->getBodyParam('dateFrom');
        if ($date) {
            $dateTime = DateTimeHelper::toDateTime($date) ?: null;
            $discount->dateFrom = $dateTime;
        }

        $date = $request->getBodyParam('dateTo');
        if ($date) {
            $dateTime = DateTimeHelper::toDateTime($date) ?: null;
            $discount->dateTo = $dateTime;
        }

        $discount->percentDiscount = -Localization::normalizePercentage($request->getBodyParam('percentDiscount'));

        // Set purchasable conditions
        if ($discount->allPurchasables = (bool)$request->getBodyParam('allPurchasables')) {
            $discount->setPurchasableIds([]);
        } else {
            $purchasables = [];
            $purchasableGroups = $request->getBodyParam('purchasables') ?: [];
            foreach ($purchasableGroups as $group) {
                if (is_array($group)) {
                    array_push($purchasables, ...$group);
                }
            }
            $purchasables = array_unique($purchasables);
            $discount->setPurchasableIds($purchasables);
        }

        // Set category conditions
        if ($discount->allCategories = (bool)$request->getBodyParam('allCategories')) {
            $discount->setCategoryIds([]);
        } else {
            $categories = $request->getBodyParam('categories', []);
            if (!$categories) {
                $categories = [];
            }
            $discount->setCategoryIds($categories);
        }

        $groups = $request->getBodyParam('groups', []);

        if ($discount->userGroupsCondition == DiscountRecord::CONDITION_USER_GROUPS_ANY_OR_NONE) {
            $groups = [];
        }

        $discount->setUserGroupIds($groups);

        // Save it
        if (Plugin::getInstance()->getDiscounts()->saveDiscount($discount)
        ) {
            $this->setSuccessFlash(Craft::t('commerce', 'Discount saved.'));
            $this->redirectToPostedUrl($discount);
        } else {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save discount.'));

            // Set back to original input value of the text field to prevent negative value.
            $discount->baseDiscount = $baseDiscount;
            $discount->perItemDiscount = $perItemDiscount;
        }

        // Send the model back to the template
        $variables = [
            'discount' => $discount,
        ];
        $this->_populateVariables($variables);

        Craft::$app->getUrlManager()->setRouteParams($variables);
    }

    /**
     * @throws BadRequestHttpException
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));
        if ($success = Plugin::getInstance()->getDiscounts()->reorderDiscounts($ids)) {
            return $this->asJson(['success' => $success]);
        }

        return $this->asJson(['error' => Craft::t('commerce', 'Couldn’t reorder discounts.')]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $id = Craft::$app->getRequest()->getBodyParam('id');
        $ids = Craft::$app->getRequest()->getBodyParam('ids');

        if ((!$id && empty($ids)) || ($id && !empty($ids))) {
            throw new BadRequestHttpException('id or ids must be specified.');
        }

        if ($id) {
            $this->requireAcceptsJson();
            $ids = [$id];
        }

        foreach ($ids as $id) {
            Plugin::getInstance()->getDiscounts()->deleteDiscountById($id);
        }

        if ($this->request->getAcceptsJson()) {
            return $this->asJson(['success' => true]);
        }

        $this->setSuccessFlash(Craft::t('commerce', 'Discounts deleted.'));

        return $this->redirect($this->request->getReferrer());
    }

    /**
     * @throws \yii\db\Exception
     * @throws \yii\web\BadRequestHttpException
     * @since 3.0
     */
    public function actionClearDiscountUses(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');
        $type = Craft::$app->getRequest()->getBodyParam('type', 'total');
        $types = [self::DISCOUNT_COUNTER_TYPE_TOTAL, self::DISCOUNT_COUNTER_TYPE_CUSTOMER, self::DISCOUNT_COUNTER_TYPE_EMAIL];

        if (!in_array($type, $types, true)) {
            return $this->asErrorJson(Craft::t('commerce', 'Type not in allowed options.'));
        }

        switch ($type) {
            case self::DISCOUNT_COUNTER_TYPE_CUSTOMER:
                Plugin::getInstance()->getDiscounts()->clearCustomerUsageHistoryById($id);
                break;
            case self::DISCOUNT_COUNTER_TYPE_EMAIL:
                Plugin::getInstance()->getDiscounts()->clearEmailUsageHistoryById($id);
                break;
            case self::DISCOUNT_COUNTER_TYPE_TOTAL:
                Plugin::getInstance()->getDiscounts()->clearDiscountUsesById($id);
                break;
        }

        return $this->asJson(['success' => true]);
    }

    /**
     * @throws MissingComponentException
     * @throws Exception
     * @throws BadRequestHttpException
     * @since 3.0
     */
    public function actionUpdateStatus(): void
    {
        $this->requirePostRequest();
        $ids = Craft::$app->getRequest()->getRequiredBodyParam('ids');
        $status = Craft::$app->getRequest()->getRequiredBodyParam('status');

        if (empty($ids)) {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t update status.'));
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        $discounts = DiscountRecord::find()
            ->where(['id' => $ids])
            ->all();

        /** @var DiscountRecord $discount */
        foreach ($discounts as $discount) {
            $discount->enabled = ($status == 'enabled');
            $discount->save();
        }
        $transaction->commit();

        $this->setSuccessFlash(Craft::t('commerce', 'Discounts updated.'));
    }

    /**
     * @throws BadRequestHttpException
     */
    public function actionGetDiscountsByPurchasableId(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $request = Craft::$app->getRequest();
        $id = $request->getParam('id', null);

        if (!$id) {
            return $this->asErrorJson(Craft::t('commerce', 'Purchasable ID is required.'));
        }

        $purchasable = Plugin::getInstance()->getPurchasables()->getPurchasableById($id);

        if (!$purchasable) {
            return $this->asErrorJson(Craft::t('commerce', 'No purchasable available.'));
        }

        $discounts = [];
        $purchasableDiscounts = Plugin::getInstance()->getDiscounts()->getDiscountsRelatedToPurchasable($purchasable);
        foreach ($purchasableDiscounts as $discount) {
            if (!ArrayHelper::firstWhere($discounts, 'id', $discount->id)) {
                /** @var Sale $discount */
                $discountArray = $discount->toArray();
                $discountArray['cpEditUrl'] = $discount->getCpEditUrl();
                $discounts[] = $discountArray;
            }
        }

        return $this->asJson([
            'success' => true,
            'discounts' => $discounts,
        ]);
    }

    private function _populateVariables(array &$variables): void
    {
        if ($variables['discount']->id) {
            $variables['title'] = $variables['discount']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a Discount');
        }

        // getting user groups map
        if (Craft::$app->getEdition() == Craft::Pro) {
            $groups = Craft::$app->getUserGroups()->getAllGroups();
            $variables['groups'] = ArrayHelper::map($groups, 'id', 'name');
        } else {
            $variables['groups'] = [];
        }

        $localizedNumberAttributes = ['baseDiscount', 'perItemDiscount', 'purchaseTotal'];
        $flipNegativeNumberAttributes = ['baseDiscount', 'perItemDiscount'];
        foreach ($localizedNumberAttributes as $attr) {
            if (!isset($variables['discount']->{$attr})) {
                continue;
            }

            if ($variables['discount']->{$attr} != 0) {
                $number = (float)$variables['discount']->{$attr};
                if (in_array($attr, $flipNegativeNumberAttributes, false)) {
                    $number *= -1;
                }

                $variables['discount']->{$attr} = Craft::$app->formatter->asDecimal($number);
            } else {
                $variables['discount']->{$attr} = 0;
            }
        }

        $variables['counterTypeTotal'] = self::DISCOUNT_COUNTER_TYPE_TOTAL;
        $variables['counterTypeCustomer'] = self::DISCOUNT_COUNTER_TYPE_CUSTOMER;
        $variables['counterTypeEmail'] = self::DISCOUNT_COUNTER_TYPE_EMAIL;

        if ($variables['discount']->id) {
            $variables['emailUsage'] = Plugin::getInstance()->getDiscounts()->getEmailUsageStatsById($variables['discount']->id);
            $variables['customerUsage'] = Plugin::getInstance()->getDiscounts()->getCustomerUsageStatsById($variables['discount']->id);
        } else {
            $variables['emailUsage'] = 0;
            $variables['customerUsage'] = 0;
        }

        $currency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrency();
        $currencyName = $currency ? $currency->getCurrency() : '';
        $percentSymbol = Craft::$app->getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);

        $variables['baseDiscountTypes'] = [
            DiscountRecord::BASE_DISCOUNT_TYPE_VALUE => Craft::t('commerce', $currencyName . ' value'),
        ];

        if ($variables['discount']->baseDiscountType == DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_TOTAL) {
            $variables['baseDiscountTypes'][DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_TOTAL] = Craft::t('commerce', '{pct} off total original price and shipping total (Deprecated)', [
                'pct' => $percentSymbol,
            ]);
        }

        if ($variables['discount']->baseDiscountType == DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_TOTAL_DISCOUNTED) {
            $variables['baseDiscountTypes'][DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_TOTAL_DISCOUNTED] = Craft::t('commerce', '{pct} off total discounted price and shipping total (Deprecated)', [
                'pct' => $percentSymbol,
            ]);
        }

        if ($variables['discount']->baseDiscountType == DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_ITEMS) {
            $variables['baseDiscountTypes'][DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_ITEMS] = Craft::t('commerce', '{pct} off total original price (Deprecated)', [
                'pct' => $percentSymbol,
            ]);
        }

        if ($variables['discount']->baseDiscountType == DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_ITEMS_DISCOUNTED) {
            $variables['baseDiscountTypes'][DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_ITEMS_DISCOUNTED] = Craft::t('commerce', '{pct} off total discounted price (Deprecated)', [
                'pct' => $percentSymbol,
            ]);
        }

        $variables['categoryElementType'] = Category::class;
        $variables['categories'] = null;
        $categories = [];

        if (empty($variables['id']) && Craft::$app->getRequest()->getParam('categoryIds')) {
            $categoryIds = explode('|', Craft::$app->getRequest()->getParam('categoryIds'));
        } else {
            $categoryIds = $variables['discount']->getCategoryIds();
        }

        foreach ($categoryIds as $categoryId) {
            $id = (int)$categoryId;
            $categories[] = Craft::$app->getElements()->getElementById($id);
        }

        $variables['categories'] = $categories;

        $variables['categoryRelationshipTypeOptions'] = [
            DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_SOURCE => Craft::t('commerce', 'Source - The category relationship field is on the purchasable'),
            DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_TARGET => Craft::t('commerce', 'Target - The purchasable relationship field is on the category'),
            DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH => Craft::t('commerce', 'Either (Default) - The relationship field is on the purchasable or the category'),
        ];

        $variables['appliedTo'] = [
            DiscountRecord::APPLIED_TO_MATCHING_LINE_ITEMS => Craft::t('commerce', 'Discount the matching items only'),
            DiscountRecord::APPLIED_TO_ALL_LINE_ITEMS => Craft::t('commerce', 'Discount all line items'),
        ];

        $variables['purchasables'] = null;

        if (empty($variables['id']) && Craft::$app->getRequest()->getParam('purchasableIds')) {
            $purchasableIdsFromUrl = explode('|', Craft::$app->getRequest()->getParam('purchasableIds'));
            $purchasableIds = [];
            foreach ($purchasableIdsFromUrl as $purchasableId) {
                $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
                if ($purchasable && $purchasable instanceof Product) {
                    $purchasableIds[] = $purchasable->defaultVariantId; // this would only be null if we are duplicating a variant, otherwise should never be null
                } else {
                    $purchasableIds[] = $purchasableId;
                }
            }
        } else {
            $purchasableIds = $variables['discount']->getPurchasableIds();
        }

        $purchasableIds = array_filter($purchasableIds);

        $purchasables = [];
        foreach ($purchasableIds as $purchasableId) {
            $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
            if ($purchasable && $purchasable instanceof PurchasableInterface) {
                $class = get_class($purchasable);
                $purchasables[$class] = $purchasables[$class] ?? [];
                $purchasables[$class][] = $purchasable;
            }
        }
        $variables['purchasables'] = $purchasables;

        $variables['purchasableTypes'] = [];
        $purchasableTypes = Plugin::getInstance()->getPurchasables()->getAllPurchasableElementTypes();

        /** @var Purchasable $purchasableType */
        foreach ($purchasableTypes as $purchasableType) {
            $variables['purchasableTypes'][] = [
                'name' => $purchasableType::displayName(),
                'elementType' => $purchasableType,
            ];
        }
    }
}
