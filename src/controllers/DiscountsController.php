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
use craft\commerce\db\Table;
use craft\commerce\elements\Product;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\helpers\Localization;
use craft\commerce\models\Coupon;
use craft\commerce\models\Discount;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\records\Discount as DiscountRecord;
use craft\commerce\services\Coupons;
use craft\commerce\web\assets\coupons\CouponsAsset;
use craft\db\Query;
use craft\elements\Category;
use craft\elements\Entry;
use craft\errors\MissingComponentException;
use craft\helpers\AdminTable;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\i18n\Locale;
use yii\base\InvalidConfigException;
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
    public const DISCOUNT_COUNTER_TYPE_TOTAL = 'total';
    public const DISCOUNT_COUNTER_TYPE_EMAIL = 'email';
    public const DISCOUNT_COUNTER_TYPE_CUSTOMER = 'customer';

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        $this->requirePermission('commerce-managePromotions');
    }

    /**
     * @throws HttpException
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('commerce/promotions/discounts/index', [
            'tableDataEndpoint' => UrlHelper::actionUrl('commerce/discounts/table-data'),
        ]);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @since 4.3.3
     */
    public function actionTableData(): Response
    {
        $this->requireAcceptsJson();

        $page = $this->request->getParam('page', 1);
        $limit = $this->request->getParam('per_page', 100);
        $search = $this->request->getParam('search');
        $offset = ($page - 1) * $limit;

        $sqlQuery = (new Query())
            ->from(['discounts' => Table::DISCOUNTS])
            ->select([
                'discounts.id',
                'discounts.name',
                'discounts.enabled',
                'discounts.dateFrom',
                'discounts.dateTo',
                'discounts.totalDiscountUses',
                'discounts.ignoreSales',
                'discounts.stopProcessing',
                'discounts.sortOrder',
                'coupons.discountId',
            ])
            ->distinct()
            ->leftJoin(Table::COUPONS . ' coupons', '[[coupons.discountId]] = [[discounts.id]]')
            ->orderBy(['sortOrder' => SORT_ASC]);


        if ($search) {
            $likeOperator = Craft::$app->getDb()->getIsPgsql() ? 'ILIKE' : 'LIKE';
            $sqlQuery
                ->andWhere([
                    'or',
                    // Search discount name
                    [$likeOperator, 'discounts.name', '%' . str_replace(' ', '%', $search) . '%', false],
                    // Search discount description
                    [$likeOperator, 'discounts.description', '%' . str_replace(' ', '%', $search) . '%', false],
                    // Search coupon code
                    ['discounts.id' => (new Query())
                        ->from(Table::COUPONS)
                        ->select('discountId')
                        ->where([$likeOperator, 'code', '%' . str_replace(' ', '%', $search) . '%', false]),
                    ],
                ]);
        }

        $total = $sqlQuery->count();

        $sqlQuery->limit($limit);
        $sqlQuery->offset($offset);

        $result = $sqlQuery->all();

        $tableData = [];
        $dateFormat = Craft::$app->getFormattingLocale()->getDateTimeFormat('short');
        foreach ($result as $item) {
            $dateFrom = $item['dateFrom'] ? DateTimeHelper::toDateTime($item['dateFrom']) : null;
            $dateTo = $item['dateTo'] ? DateTimeHelper::toDateTime($item['dateTo']) : null;
            $dateRange = ($dateFrom ? $dateFrom->format($dateFormat) : '∞') . ' - ' > ($dateTo ? $dateTo->format($dateFormat) : '∞');
            $dateRange = !$dateFrom && !$dateTo ? '∞' : $dateRange;

            $tableData[] = [
                'id' => $item['id'],
                'title' => Craft::t('site', $item['name']),
                'url' => UrlHelper::cpUrl('commerce/promotions/discounts/' . $item['id']),
                'status' => (bool)$item['enabled'],
                'duration' => $dateRange,
                'timesUsed' => $item['totalDiscountUses'],
                // If there is joined data then there are coupons
                'hasCoupons' => (bool)$item['discountId'],
                'ignore' => (bool)$item['ignoreSales'],
                'stop' => (bool)$item['stopProcessing'],
            ];
        }

        return $this->asSuccess(data: [
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $tableData,
        ]);
    }

    /**
     * @param int|null $id
     * @param Discount|null $discount
     * @throws HttpException
     */
    public function actionEdit(int $id = null, Discount $discount = null): Response
    {
        if ($id === null) {
            $this->requirePermission('commerce-createDiscounts');
        } else {
            $this->requirePermission('commerce-editDiscounts');
        }

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

        DebugPanel::prependOrAppendModelTab(model: $variables['discount'], prepend: true);

        $this->_populateVariables($variables);
        $variables['percentSymbol'] = Craft::$app->getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);
        $this->getView()->registerAssetBundle(CouponsAsset::class);

        return $this->renderTemplate('commerce/promotions/discounts/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $discount = new Discount();

        $discount->id = $this->request->getBodyParam('id');

        if ($discount->id === null) {
            $this->requirePermission('commerce-createDiscounts');
        } else {
            $this->requirePermission('commerce-editDiscounts');
        }

        $discount->name = $this->request->getBodyParam('name');
        $discount->description = $this->request->getBodyParam('description');
        $discount->enabled = (bool)$this->request->getBodyParam('enabled');
        $discount->setOrderCondition($this->request->getBodyParam('orderCondition'));
        $discount->setCustomerCondition($this->request->getBodyParam('customerCondition'));
        $discount->setShippingAddressCondition($this->request->getBodyParam('shippingAddressCondition'));
        $discount->setBillingAddressCondition($this->request->getBodyParam('billingAddressCondition'));
        $discount->stopProcessing = (bool)$this->request->getBodyParam('stopProcessing');
        $discount->purchaseQty = $this->request->getBodyParam('purchaseQty');
        $discount->maxPurchaseQty = $this->request->getBodyParam('maxPurchaseQty');
        $discount->percentDiscount = (float)$this->request->getBodyParam('percentDiscount');
        $discount->percentageOffSubject = $this->request->getBodyParam('percentageOffSubject');
        $discount->hasFreeShippingForMatchingItems = (bool)$this->request->getBodyParam('hasFreeShippingForMatchingItems');
        $discount->hasFreeShippingForOrder = (bool)$this->request->getBodyParam('hasFreeShippingForOrder');
        $discount->excludeOnSale = (bool)$this->request->getBodyParam('excludeOnSale');
        $discount->couponFormat = $this->request->getBodyParam('couponFormat', Coupons::DEFAULT_COUPON_FORMAT);
        $discount->perUserLimit = (int)$this->request->getBodyParam('perUserLimit');
        $discount->perEmailLimit = (int)$this->request->getBodyParam('perEmailLimit');
        $discount->totalDiscountUseLimit = (int)$this->request->getBodyParam('totalDiscountUseLimit');
        $discount->ignoreSales = (bool)$this->request->getBodyParam('ignoreSales');
        $discount->categoryRelationshipType = $this->request->getBodyParam('categoryRelationshipType', $discount->categoryRelationshipType);
        $discount->baseDiscountType = $this->request->getBodyParam('baseDiscountType') ?: DiscountRecord::BASE_DISCOUNT_TYPE_VALUE;
        $discount->appliedTo = $this->request->getBodyParam('appliedTo') ?: DiscountRecord::APPLIED_TO_MATCHING_LINE_ITEMS;
        $discount->orderConditionFormula = $this->request->getBodyParam('orderConditionFormula');

        $baseDiscount = $this->request->getBodyParam('baseDiscount') ?: 0;
        $baseDiscount = preg_replace('/[^0-9\.\-\,]/', '', $baseDiscount);
        $baseDiscount = Localization::normalizeNumber($baseDiscount);
        $discount->baseDiscount = $baseDiscount * -1;

        $perItemDiscount = $this->request->getBodyParam('perItemDiscount') ?: 0;
        $perItemDiscount = preg_replace('/[^0-9\.\-\,]/', '', $perItemDiscount);
        $perItemDiscount = Localization::normalizeNumber($perItemDiscount);
        $discount->perItemDiscount = $perItemDiscount * -1;

        $purchaseTotal = $this->request->getBodyParam('purchaseTotal', 0);
        $purchaseTotal = preg_replace('/[^0-9\.\-\,]/', '', $purchaseTotal);
        $discount->purchaseTotal = (float)Localization::normalizeNumber($purchaseTotal);

        $date = $this->request->getBodyParam('dateFrom');
        if ($date) {
            $dateTime = DateTimeHelper::toDateTime($date) ?: null;
            $discount->dateFrom = $dateTime;
        }

        $date = $this->request->getBodyParam('dateTo');
        if ($date) {
            $dateTime = DateTimeHelper::toDateTime($date) ?: null;
            $discount->dateTo = $dateTime;
        }

        $percentDiscount = $this->request->getBodyParam('percentDiscount', 0);
        $percentDiscount = preg_replace('/[^0-9\.\-\,]/', '', $percentDiscount);
        $discount->percentDiscount = -Localization::normalizePercentage($percentDiscount);

        // Set purchasable conditions
        $allPurchasables = !$this->request->getBodyParam('allPurchasables', false);
        if ($discount->allPurchasables = $allPurchasables) {
            $discount->setPurchasableIds([]);
        } else {
            $purchasables = [];
            $purchasableGroups = $this->request->getBodyParam('purchasables') ?: [];
            foreach ($purchasableGroups as $group) {
                if (is_array($group)) {
                    array_push($purchasables, ...$group);
                }
            }
            $purchasables = array_unique($purchasables);
            $discount->setPurchasableIds($purchasables);
        }

        // False in the allCategories param is true in the DB
        $allCategories = !$this->request->getBodyParam('allCategories', false);
        // Set category conditions
        if ($discount->allCategories = $allCategories) {
            $discount->setCategoryIds([]);
        } else {
            $relatedElements = [];
            $relatedElementByType = $this->request->getBodyParam('relatedElements') ?: [];
            foreach ($relatedElementByType as $type) {
                if (is_array($type)) {
                    array_push($relatedElements, ...$type);
                }
            }
            $relatedElements = array_unique($relatedElements);
            $discount->setCategoryIds($relatedElements);
        }

        $coupons = $this->request->getBodyParam('coupons') ?: [];
        $this->_setCouponsOnDiscount(coupons: $coupons, discount: $discount);

        // Save it
        if (Plugin::getInstance()->getDiscounts()->saveDiscount($discount)) {
            $this->setSuccessFlash(Craft::t('commerce', 'Discount saved.'));
            return $this->redirectToPostedUrl($discount);
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

        return null;
    }

    /**
     * @param array $coupons
     * @param Discount $discount
     * @return void
     * @throws InvalidConfigException
     * @since 4.0
     */
    private function _setCouponsOnDiscount(array $coupons, Discount $discount): void
    {
        if (empty($coupons)) {
            return;
        }

        $discountCoupons = [];

        foreach ($coupons as $c) {
            $discountCoupons[] = Craft::createObject(Coupon::class, [
                'config' => [
                    'attributes' => [
                        'id' => $c['id'] ?: null,
                        'discountId' => null,
                        'code' => $c['code'],
                        'uses' => $c['uses'] ?: 0,
                        'maxUses' => is_numeric($c['maxUses']) ? (int)$c['maxUses'] : null,
                    ],
                ],
            ]);
        }

        $discount->setCoupons($discountCoupons);
    }

    /**
     * @throws BadRequestHttpException
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode($this->request->getRequiredBodyParam('ids'));
        $key = $this->request->getBodyParam('startPosition');

        $idsOrdered = [];
        foreach ($ids as $id) {
            // Temporary -1 because the `reorderDiscounts()` method will increment the key before saving.
            // @TODO update this when we can change the behaviour of the `reorderDiscounts()` method.
            $idsOrdered[$key - 1] = $id;
            $key++;
        }

        if (!Plugin::getInstance()->getDiscounts()->reorderDiscounts($idsOrdered)) {
            return $this->asFailure(Craft::t('commerce', 'Couldn’t reorder discounts.'));
        }

        return $this->asSuccess();
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @since 4.4.0
     */
    public function actionMoveToPage(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = $this->request->getRequiredBodyParam('id');
        $page = $this->request->getRequiredBodyParam('page');
        $perPage = $this->request->getRequiredBodyParam('perPage');

        if (AdminTable::moveToPage(Table::DISCOUNTS, $id, $page, $perPage)) {
            return $this->asSuccess(Craft::t('commerce', 'Discounts reordered.'));
        }

        return $this->asFailure(Craft::t('commerce', 'Couldn’t reorder discounts.'));
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePermission('commerce-deleteDiscounts');
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
            Plugin::getInstance()->getDiscounts()->deleteDiscountById($id);
        }

        if ($this->request->getAcceptsJson()) {
            return $this->asSuccess();
        }

        $this->setSuccessFlash(Craft::t('commerce', 'Discounts deleted.'));

        return $this->redirect($this->request->getReferrer());
    }

    /**
     * @throws Exception
     * @throws BadRequestHttpException
     * @since 3.0
     */
    public function actionClearDiscountUses(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = $this->request->getRequiredBodyParam('id');
        $type = $this->request->getBodyParam('type', 'total');
        $types = [self::DISCOUNT_COUNTER_TYPE_TOTAL, self::DISCOUNT_COUNTER_TYPE_CUSTOMER, self::DISCOUNT_COUNTER_TYPE_EMAIL];

        if (!in_array($type, $types, true)) {
            return $this->asFailure(Craft::t('commerce', 'Type not in allowed options.'));
        }

        switch ($type) {
            case self::DISCOUNT_COUNTER_TYPE_EMAIL:
                Plugin::getInstance()->getDiscounts()->clearEmailUsageHistoryById($id);
                break;
            case self::DISCOUNT_COUNTER_TYPE_CUSTOMER:
                Plugin::getInstance()->getDiscounts()->clearCustomerUsageHistoryById($id);
                break;
            case self::DISCOUNT_COUNTER_TYPE_TOTAL:
                Plugin::getInstance()->getDiscounts()->clearDiscountUsesById($id);
                break;
        }

        return $this->asSuccess();
    }

    /**
     * @throws MissingComponentException
     * @throws Exception
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @since 3.0
     */
    public function actionUpdateStatus(): void
    {
        $this->requirePostRequest();
        $this->requirePermission('commerce-editDiscounts');

        $ids = $this->request->getRequiredBodyParam('ids');
        $status = $this->request->getRequiredBodyParam('status');

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
        $id = $this->request->getParam('id');

        if (!$id) {
            return $this->asFailure(Craft::t('commerce', 'Purchasable ID is required.'));
        }

        $purchasable = Plugin::getInstance()->getPurchasables()->getPurchasableById($id);

        if (!$purchasable) {
            return $this->asFailure(Craft::t('commerce', 'No purchasable available.'));
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

        return $this->asSuccess(data: [
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

        $flipNegativeNumberAttributes = ['baseDiscount', 'perItemDiscount'];
        foreach ($flipNegativeNumberAttributes as $attr) {
            if (!isset($variables['discount']->{$attr})) {
                continue;
            }

            if ($variables['discount']->{$attr} != 0) {
                $variables['discount']->{$attr} *= -1;
            } else {
                $variables['discount']->{$attr} = 0;
            }
        }

        $variables['counterTypeTotal'] = self::DISCOUNT_COUNTER_TYPE_TOTAL;
        $variables['counterTypeEmail'] = self::DISCOUNT_COUNTER_TYPE_EMAIL;
        $variables['counterTypeUser'] = self::DISCOUNT_COUNTER_TYPE_CUSTOMER;

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
            $variables['baseDiscountTypes'][DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_TOTAL] = Craft::t('commerce', '{pct} off total original price and shipping total (deprecated)', [
                'pct' => $percentSymbol,
            ]);
        }

        if ($variables['discount']->baseDiscountType == DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_TOTAL_DISCOUNTED) {
            $variables['baseDiscountTypes'][DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_TOTAL_DISCOUNTED] = Craft::t('commerce', '{pct} off total discounted price and shipping total (deprecated)', [
                'pct' => $percentSymbol,
            ]);
        }

        if ($variables['discount']->baseDiscountType == DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_ITEMS) {
            $variables['baseDiscountTypes'][DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_ITEMS] = Craft::t('commerce', '{pct} off total original price (deprecated)', [
                'pct' => $percentSymbol,
            ]);
        }

        if ($variables['discount']->baseDiscountType == DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_ITEMS_DISCOUNTED) {
            $variables['baseDiscountTypes'][DiscountRecord::BASE_DISCOUNT_TYPE_PERCENT_ITEMS_DISCOUNTED] = Craft::t('commerce', '{pct} off total discounted price (deprecated)', [
                'pct' => $percentSymbol,
            ]);
        }

        $variables['categoryElementType'] = Category::class;
        $variables['entryElementType'] = Entry::class;
        $variables['categories'] = null;
        $variables['entries'] = null;

        $categories = [];
        $entries = [];

        if (empty($variables['id']) && $this->request->getParam('categoryIds')) {
            $categoryIds = explode('|', $this->request->getParam('categoryIds'));
        } else {
            $categoryIds = $variables['discount']->getCategoryIds();
        }

        foreach ($categoryIds as $categoryId) {
            $id = (int)$categoryId;
            $element = Craft::$app->getElements()->getElementById($id);

            if ($element instanceof Category) {
                $categories[] = $element;
            } elseif ($element instanceof Entry) {
                $entries[] = $element;
            }
        }

        $variables['categories'] = $categories;
        $variables['entries'] = $entries;

        $variables['elementRelationshipTypeOptions'] = [
            DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_SOURCE => Craft::t('commerce', 'The purchasable defines the relationship'),
            DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_TARGET => Craft::t('commerce', 'The purchasable is related by another element'),
            DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH => Craft::t('commerce', 'Either way'),
        ];

        $variables['appliedTo'] = [
            DiscountRecord::APPLIED_TO_MATCHING_LINE_ITEMS => Craft::t('commerce', 'Discount the matching items only'),
            DiscountRecord::APPLIED_TO_ALL_LINE_ITEMS => Craft::t('commerce', 'Discount all line items'),
        ];

        $variables['purchasables'] = null;

        if (empty($variables['id']) && $this->request->getParam('purchasableIds')) {
            $purchasableIdsFromUrl = explode('|', $this->request->getParam('purchasableIds'));
            $purchasableIds = [];
            foreach ($purchasableIdsFromUrl as $purchasableId) {
                $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId);
                if ($purchasable instanceof Product) {
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
            if ($purchasable instanceof PurchasableInterface) {
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

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @since 4.0
     */
    public function actionGenerateCoupons(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $count = (int)$this->request->getBodyParam('count', 0);
        $format = $this->request->getBodyParam('format', Coupons::DEFAULT_COUPON_FORMAT);
        $existingCodes = $this->request->getBodyParam('existingCodes', []);

        try {
            $coupons = Plugin::getInstance()->getCoupons()->generateCouponCodes(count: $count, format: $format, existingCodes: $existingCodes);
        } catch (\Exception $e) {
            return $this->asFailure(message: Craft::t('commerce', 'Unable to generate coupon codes: {message}', ['message' => $e->getMessage()]));
        }

        return $this->asSuccess(data: ['coupons' => $coupons]);
    }
}
