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
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\MoneyHelper;
use craft\helpers\UrlHelper;
use craft\i18n\Locale;
use craft\web\View;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\Response;
use function explode;

/**
 * Class Discounts Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class DiscountsController extends BaseStoreManagementController
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
    public function actionIndex(?string $storeHandle = null): Response
    {
        if ($storeHandle) {
            $store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle);
            if ($store === null) {
                throw new InvalidConfigException('Invalid store.');
            }
        } else {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $this->getView()->registerTranslations('commerce', [
            'Couldn’t reorder discounts.',
            'Delete',
            'Disabled',
            'Discounts reordered.',
            'Duration',
            'Enabled',
            'Require Coupon Code',
            'Ignore Promotions?',
            'Name',
            'No discounts exist yet.',
            'No',
            'Set status',
            'Stops Processing?',
            'Times Used',
            'Yes',
        ]);

        $actionButtonHtml = Craft::$app->getUser()->getIdentity()->can('commerce-createDiscounts')
            ? Html::a(Craft::t('commerce', 'New discount'), $store->getStoreSettingsUrl('discounts/new'), ['class' => 'btn submit add icon'])
            : '';

        $actions = [];
        if (Craft::$app->getUser()->getIdentity()->can('commerce-editDiscounts')) {
            $actions[] = [
                'label' => Craft::t('commerce', 'Set status'),
                'actions' => [
                    [
                        'label' => Craft::t('commerce', 'Enabled'),
                        'action' => 'commerce/discounts/update-status',
                        'param' => 'status',
                        'value' => 'enabled',
                        'status' => 'enabled',
                    ],
                    [
                        'label' => Craft::t('commerce', 'Disabled'),
                        'action' => 'commerce/discounts/update-status',
                        'param' => 'status',
                        'value' => 'disabled',
                        'status' => 'disabled',
                    ],
                ],
            ];
        }

        $deleteAction = null;
        if (Craft::$app->getUser()->getIdentity()->can('commerce-deleteDiscounts')) {
            $actions[] = [
                'label' => Craft::t('commerce', 'Delete'),
                'action' => 'commerce/discounts/delete',
                'error' => true,
            ];
            $deleteAction = '"commerce/discounts/delete"';
        }

        $actions = Json::encode($actions);

        $tableDataEndpoint = UrlHelper::actionUrl('commerce/discounts/table-data', ['storeId' => $store->id]);

        $js = <<<JS
    var actions = {$actions};

    var columns = [
        { name: '__slot:title', title: Craft.t('commerce', 'Name') },
        { name: 'requireCouponCode', title: Craft.t('commerce', 'Require Coupon Code'),
            callback: function(value) {
                if (value) {
                    return '<span data-icon="check" title="'+Craft.escapeHtml(Craft.t('commerce', 'Yes'))+'"></span>';
                }

                return '';
            }
        },
        { name: 'duration', title: Craft.t('commerce', 'Duration') },
        { name: 'timesUsed', title: Craft.t('commerce', 'Times Used') },
        { name: 'stop', title: Craft.t('commerce', 'Stops Processing?'),
            callback: function(value) {
                if (value) {
                    return '<span data-icon="check" title="'+Craft.escapeHtml(Craft.t('commerce', 'Yes'))+'"></span>';
                }

                return '';
            }
        },
        { name: 'ignore', title: Craft.t('commerce', 'Ignore Promotions?'),
            callback: function(value) {
                if (value) {
                    return '<span data-icon="check" title="'+Craft.escapeHtml(Craft.t('commerce', 'Yes'))+'"></span>';
                }

                return '';
            }
        },
    ];

    new Craft.VueAdminTable({
        actions: actions,
        checkboxes: true,
        columns: columns,
        fullPane: false,
        container: '#discounts-vue-admin-table',
        allowMultipleDeletions: true,
        deleteAction: {$deleteAction},
        emptyMessage: Craft.t('commerce', 'No discounts exist yet.'),
        padded: true,
        paginatedReorderAction: 'commerce/discounts/reorder',
        moveToPageAction: 'commerce/discounts/move-to-page',
        reorderSuccessMessage: Craft.t('commerce', 'Discounts reordered.') ,
        reorderFailMessage:    Craft.t('commerce', 'Couldn’t reorder discounts.'),
        tableDataEndpoint: '{$tableDataEndpoint}',
        search: true,
        perPage: 100,
  });
JS;

        $this->getView()->registerJs($js, View::POS_END);

        return $this->asStoreManagementCpScreen($storeHandle)
            ->additionalButtonsHtml($actionButtonHtml)
            ->contentTemplate('commerce/store-management/discounts/index');
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @since 4.3.3
     */
    public function actionTableData(): Response
    {
        $this->requireAcceptsJson();

        $storeId = $this->request->getRequiredParam('storeId');

        if (!$store = Plugin::getInstance()->getStores()->getStoreById($storeId)) {
            throw new InvalidConfigException('Invalid store.');
        }


        $page = $this->request->getParam('page', 1);
        $limit = $this->request->getParam('per_page', 100);
        $search = $this->request->getParam('search');
        $offset = ($page - 1) * $limit;

        $sqlQuery = new Query()
            ->from(['discounts' => Table::DISCOUNTS])
            ->select([
                'discounts.id',
                'discounts.name',
                'discounts.enabled',
                'discounts.dateFrom',
                'discounts.dateTo',
                'discounts.totalDiscountUses',
                'discounts.ignorePromotions',
                'discounts.requireCouponCode',
                'discounts.stopProcessing',
                'discounts.sortOrder',
            ])
            ->where(['discounts.storeId' => $storeId])
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
                    ['discounts.id' => new Query()
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
        $dateFormat = Craft::$app->getFormattingLocale()->getDateTimeFormat('short', Locale::FORMAT_PHP);
        foreach ($result as $item) {
            $dateFrom = $item['dateFrom'] ? DateTimeHelper::toDateTime($item['dateFrom']) : null;
            $dateTo = $item['dateTo'] ? DateTimeHelper::toDateTime($item['dateTo']) : null;
            $dateRange = ($dateFrom ? $dateFrom->format($dateFormat) : '∞') . ' - ' . ($dateTo ? $dateTo->format($dateFormat) : '∞');

            $dateRange = !$dateFrom && !$dateTo ? '∞' : $dateRange;

            $tableData[] = [
                'id' => $item['id'],
                'title' => Craft::t('site', $item['name']),
                'url' => UrlHelper::cpUrl('commerce/store-management/' . $store->handle . '/discounts/' . $item['id']),
                'status' => (bool)$item['enabled'],
                'duration' => $dateRange,
                'timesUsed' => $item['totalDiscountUses'],
                'requireCouponCode' => (bool)$item['requireCouponCode'],
                'ignore' => (bool)$item['ignorePromotions'],
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
    public function actionEdit(?int $id = null, ?Discount $discount = null, ?string $storeHandle = null): Response
    {
        if ($id === null) {
            $this->requirePermission('commerce-createDiscounts');
        } else {
            $this->requirePermission('commerce-editDiscounts');
        }

        $variables = compact('id', 'discount');
        $variables['isNewDiscount'] = false;

        if ($storeHandle) {
            $store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle);
            if ($store === null) {
                throw new InvalidConfigException('Invalid store.');
            }
        } else {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $variables['siteIds'] = $store->getSites()->pluck('id')->all();
        $variables['storeHandle'] = $store->handle;
        $variables['currency'] = $store->getCurrency();
        $variables['decimals'] = Plugin::getInstance()->getCurrencies()->getSubunitFor($store->getCurrency());

        if (!$variables['discount']) {
            if ($variables['id']) {
                $variables['discount'] = Plugin::getInstance()->getDiscounts()->getDiscountById($variables['id'], $store->id);

                if (!$variables['discount']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['discount'] = Craft::createObject([
                    'class' => Discount::class,
                    'attributes' => [
                        'allCategories' => true,
                        'allPurchasables' => true,
                        'storeId' => $store->id,
                    ],
                ]);
                $variables['isNewDiscount'] = true;
            }
        }

        $this->_populateVariables($variables);
        $variables['percentSymbol'] = Craft::$app->getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);
        $this->getView()->registerAssetBundle(CouponsAsset::class);

        $variables['coupons'] = collect($variables['discount']->getCoupons())
            ->map(fn(Coupon $coupon) => $coupon->toArray())
            ->all();

        $tabs = [
            'discount' => [
                'label' => Craft::t('commerce', 'Discount'),
                'url' => '#discount',
                'class' => $variables['discount']->getErrors('name') ? 'error' : '',
            ],
            'coupons' => [
                'label' => Craft::t('commerce', 'Coupons'),
                'url' => '#coupons',
                'class' => $variables['discount']->getErrors('code') ? 'error' : '',
            ],
            'matchingItems' => [
                'label' => Craft::t('commerce', 'Matching Items'),
                'url' => '#matching-items',
            ],
            'conditions' => [
                'label' => Craft::t('commerce', 'Conditions'),
                'url' => '#conditions',
                'class' => $variables['discount']->getErrors('startDate') || $variables['discount']->getErrors('endDate') ? 'error' : '',
            ],
            'actions' => [
                'label' => Craft::t('commerce', 'Actions'),
                'url' => '#actions',
                'class' => $variables['discount']->getErrors('startDate') || $variables['discount']->getErrors('endDate') ? 'error' : '',
            ],
        ];

        return $this->asStoreManagementCpScreen($storeHandle, false)
            ->title($variables['title'])
            ->tabs($tabs)
            ->addCrumb(Craft::t('commerce', 'Discounts'), $store->getStoreSettingsUrl('discounts'))
            ->metaSidebarTemplate('commerce/store-management/discounts/_sidebar', $variables)
            ->action('commerce/discounts/save')
            ->redirectUrl($store->getStoreSettingsUrl('discounts'))
            ->contentTemplate('commerce/store-management/discounts/_edit', $variables);
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

        $discount->storeId = $this->request->getBodyParam('storeId');
        $discount->name = $this->request->getBodyParam('name');
        $discount->description = $this->request->getBodyParam('description');
        $discount->enabled = (bool)$this->request->getBodyParam('enabled');
        $discount->setOrderCondition($this->request->getBodyParam('orderCondition'));
        $discount->setCustomerCondition($this->request->getBodyParam('customerCondition'));
        $discount->setShippingAddressCondition($this->request->getBodyParam('shippingAddressCondition'));
        $discount->setBillingAddressCondition($this->request->getBodyParam('billingAddressCondition'));
        $discount->requireCouponCode = (bool)$this->request->getBodyParam('requireCouponCode');
        $discount->stopProcessing = (bool)$this->request->getBodyParam('stopProcessing');
        $discount->purchaseQty = $this->request->getBodyParam('purchaseQty');
        $discount->maxPurchaseQty = $this->request->getBodyParam('maxPurchaseQty');
        $discount->percentDiscount = (float)$this->request->getBodyParam('percentDiscount');
        $discount->percentageOffSubject = $this->request->getBodyParam('percentageOffSubject');
        $discount->hasFreeShippingForMatchingItems = (bool)$this->request->getBodyParam('hasFreeShippingForMatchingItems');
        $discount->hasFreeShippingForOrder = (bool)$this->request->getBodyParam('hasFreeShippingForOrder');
        $discount->excludeOnPromotion = (bool)$this->request->getBodyParam('excludeOnPromotion');
        $discount->couponFormat = $this->request->getBodyParam('couponFormat', Coupons::DEFAULT_COUPON_FORMAT);
        $discount->perUserLimit = (int)$this->request->getBodyParam('perUserLimit');
        $discount->perEmailLimit = (int)$this->request->getBodyParam('perEmailLimit');
        $discount->totalDiscountUseLimit = (int)$this->request->getBodyParam('totalDiscountUseLimit');
        $discount->ignorePromotions = (bool)$this->request->getBodyParam('ignorePromotions');
        $discount->categoryRelationshipType = $this->request->getBodyParam('categoryRelationshipType', $discount->categoryRelationshipType);
        $discount->appliedTo = $this->request->getBodyParam('appliedTo') ?: DiscountRecord::APPLIED_TO_MATCHING_LINE_ITEMS;
        $discount->orderConditionFormula = $this->request->getBodyParam('orderConditionFormula');

        $moneyInputAttributes = [
            'baseDiscount',
            'perItemDiscount',
            'purchaseTotal',
        ];
        foreach ($moneyInputAttributes as $attr) {
            $attrValue = $this->request->getBodyParam($attr) ?: ['value' => '0'];
            $attrValue['value'] = preg_replace('/[^0-9\.\-\,]/', '', (string) $attrValue['value']);
            $attrValue += [
                'currency' => $discount->getStore()->getCurrency(),
            ];
            $attrValue = MoneyHelper::toDecimal(MoneyHelper::toMoney($attrValue));

            // Invert non-purchaseTotal values
            if ($attr !== 'purchaseTotal') {
                // Sanitize the input from the user - we store negative values, expecting the user to enter positive values
                $attrValue = (float)$attrValue;
                if ($attrValue > 0) {
                    $attrValue = $attrValue * -1;
                }
            }

            $discount->{$attr} = (float)$attrValue;
        }

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
        $percentDiscount = preg_replace('/[^0-9\.\-\,]/', '', (string) $percentDiscount);
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
            $discount->setCoupons([]);
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
            // @TODO Remove the `$key - 1` offset once `reorderDiscounts()` can be changed to not pre-increment the key (Commerce 6.0)
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

        // @TODO Scope discount move-to-page operations by `storeId` so reordering only affects the active store

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

        match ($type) {
            self::DISCOUNT_COUNTER_TYPE_EMAIL => Plugin::getInstance()->getDiscounts()->clearEmailUsageHistoryById($id),
            self::DISCOUNT_COUNTER_TYPE_CUSTOMER => Plugin::getInstance()->getDiscounts()->clearCustomerUsageHistoryById($id),
            self::DISCOUNT_COUNTER_TYPE_TOTAL => Plugin::getInstance()->getDiscounts()->clearDiscountUsesById($id),
        };

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

            if ($variables['discount']->{$attr} < 0) {
                // Flip negative numbers for display to the user
                $variables['discount']->{$attr} *= -1;
            } elseif ($variables['discount']->{$attr} == 0) {
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

        $variables['categoryElementType'] = Category::class;
        $variables['entryElementType'] = Entry::class;
        $variables['categories'] = null;
        $variables['entries'] = null;

        $categories = [];
        $entries = [];

        if (empty($variables['id']) && $this->request->getParam('categoryIds')) {
            $categoryIds = explode('|', (string) $this->request->getParam('categoryIds'));
        } else {
            $categoryIds = $variables['discount']->getCategoryIds();
        }

        foreach ($categoryIds as $categoryId) {
            $id = (int)$categoryId;
            $element = Craft::$app->getElements()->getElementById($id, siteId: '*');

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
            $purchasableIdsFromUrl = explode('|', (string) $this->request->getParam('purchasableIds'));
            foreach ($purchasableIdsFromUrl as $purchasableId) {
                $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId, siteId: $variables['siteIds']);
                if ($purchasable instanceof Product) {
                    $purchasableIds[] = $purchasable->defaultVariantId; // this would only be null if we are duplicating a variant, otherwise should never be null
                } else {
                    $purchasableIds[] = $purchasableId;
                }
            }
            $variables['discount']->allPurchasables = false;
        } else {
            $purchasableIds = $variables['discount']->getPurchasableIds();
        }

        $purchasableIds = array_filter($purchasableIds);

        $purchasables = [];
        foreach ($purchasableIds as $purchasableId) {
            $purchasable = Craft::$app->getElements()->getElementById((int)$purchasableId, siteId: $variables['siteIds']);
            if ($purchasable instanceof PurchasableInterface) {
                $class = $purchasable::class;
                $purchasables[$class] ??= [];
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
