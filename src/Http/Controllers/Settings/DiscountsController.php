<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\helpers\Localization;
use craft\commerce\web\assets\coupons\CouponsAsset;
use craft\db\Query;
use craft\elements\Category;
use craft\helpers\AdminTable;
use CraftCms\Cms\Edition;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Facades\UserGroups;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Money;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Translation\Locale;
use CraftCms\Cms\View\Enums\Position;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen;
use CraftCms\Commerce\Payment\Currencies;
use CraftCms\Commerce\Promotion\Coupons;
use CraftCms\Commerce\Promotion\Discounts;
use CraftCms\Commerce\Promotion\Models\Coupon;
use CraftCms\Commerce\Promotion\Models\Discount;
use CraftCms\Commerce\Promotion\Records\Discount as DiscountRecord;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use CraftCms\Commerce\Purchasable\Purchasables;
use CraftCms\Commerce\Store\Stores;

use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

readonly class DiscountsController
{
    use HasStoreManagementScreen;
    use RespondsWithFlash;

    public const string DISCOUNT_COUNTER_TYPE_TOTAL = 'total';
    public const string DISCOUNT_COUNTER_TYPE_EMAIL = 'email';
    public const string DISCOUNT_COUNTER_TYPE_CUSTOMER = 'customer';

    public function index(?string $storeHandle = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        $actionButtonHtml = currentUserElement()?->can('commerce-createDiscounts')
            ? Html::a(t('New discount', category: 'commerce'), $store->getStoreSettingsUrl('discounts/new'), ['class' => 'btn submit add icon'])
            : '';

        $actions = [];
        if (currentUserElement()?->can('commerce-editDiscounts')) {
            $actions[] = [
                'label' => t('Set status', category: 'commerce'),
                'actions' => [
                    [
                        'label' => t('Enabled', category: 'commerce'),
                        'action' => 'commerce/discounts/update-status',
                        'param' => 'status',
                        'value' => 'enabled',
                        'status' => 'enabled',
                    ],
                    [
                        'label' => t('Disabled', category: 'commerce'),
                        'action' => 'commerce/discounts/update-status',
                        'param' => 'status',
                        'value' => 'disabled',
                        'status' => 'disabled',
                    ],
                ],
            ];
        }

        $deleteAction = null;
        if (currentUserElement()?->can('commerce-deleteDiscounts')) {
            $actions[] = [
                'label' => t('Delete', category: 'commerce'),
                'action' => 'commerce/discounts/delete',
                'error' => true,
            ];
            $deleteAction = '"commerce/discounts/delete"';
        }

        $actions = Json::encode($actions);

        $tableDataEndpoint = Url::actionUrl('commerce/discounts/table-data', ['storeId' => $store->id]);

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
        reorderFailMessage:    Craft.t('commerce', 'Couldn\'t reorder discounts.'),
        tableDataEndpoint: '{$tableDataEndpoint}',
        search: true,
        perPage: 100,
  });
JS;

        HtmlStack::js($js, Position::BodyEnd);

        return $this->storeManagementCpScreen($storeHandle)
            ->additionalButtonsHtml($actionButtonHtml)
            ->contentTemplate('commerce/store-management/discounts/index');
    }

    public function tableData(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $storeId = $request->input('storeId');
        $store = app(Stores::class)->getStoreById($storeId);
        abort_if($store === null, 400, 'Invalid store.');

        $page = (int)$request->input('page', 1);
        $limit = (int)$request->input('per_page', 100);
        $search = $request->input('search');
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
            $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            $sqlQuery
                ->andWhere([
                    'or',
                    [$likeOperator, 'discounts.name', '%' . str_replace(' ', '%', $search) . '%', false],
                    [$likeOperator, 'discounts.description', '%' . str_replace(' ', '%', $search) . '%', false],
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
        $dateFormat = I18N::getFormattingLocale()->getDateTimeFormat('short', Locale::FORMAT_PHP);
        foreach ($result as $item) {
            $dateFrom = $item['dateFrom'] ? DateTimeHelper::toDateTime($item['dateFrom']) : null;
            $dateTo = $item['dateTo'] ? DateTimeHelper::toDateTime($item['dateTo']) : null;
            $dateRange = ($dateFrom ? $dateFrom->format($dateFormat) : '∞') . ' - ' . ($dateTo ? $dateTo->format($dateFormat) : '∞');

            $dateRange = !$dateFrom && !$dateTo ? '∞' : $dateRange;

            $tableData[] = [
                'id' => $item['id'],
                'title' => t($item['name'], category: 'site'),
                'url' => Url::cpUrl('commerce/store-management/' . $store->handle . '/discounts/' . $item['id']),
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

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        abort_unless(currentUserElement()?->can($id === null ? 'commerce-createDiscounts' : 'commerce-editDiscounts'), 403);

        $variables = ['id' => $id, 'isNewDiscount' => false];

        $store = $this->resolveStore($storeHandle);

        $variables['siteIds'] = $store->getSites()->pluck('id')->all();
        $variables['storeHandle'] = $store->handle;
        $variables['currency'] = $store->getCurrency();
        $variables['decimals'] = app(Currencies::class)->getSubunitFor($store->getCurrency());

        if ($id) {
            $discount = app(Discounts::class)->getDiscountById($id, $store->id);
            abort_if($discount === null, 404);
        } else {
            $discount = \Craft::createObject([
                'class' => Discount::class,
                'attributes' => [
                    'allCategories' => true,
                    'allPurchasables' => true,
                    'storeId' => $store->id,
                ],
            ]);
            $variables['isNewDiscount'] = true;
        }
        $variables['discount'] = $discount;

        $this->populateVariables($variables);
        $variables['percentSymbol'] = I18N::getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);
        \Craft::$app->getView()->registerAssetBundle(CouponsAsset::class);

        $variables['coupons'] = collect($discount->getCoupons())
            ->map(fn(Coupon $coupon) => $coupon->toArray())
            ->all();

        $tabs = [
            'discount' => [
                'label' => t('Discount', category: 'commerce'),
                'url' => '#discount',
                'class' => $discount->getErrors('name') ? 'error' : '',
            ],
            'coupons' => [
                'label' => t('Coupons', category: 'commerce'),
                'url' => '#coupons',
                'class' => $discount->getErrors('code') ? 'error' : '',
            ],
            'matchingItems' => [
                'label' => t('Matching Items', category: 'commerce'),
                'url' => '#matching-items',
            ],
            'conditions' => [
                'label' => t('Conditions', category: 'commerce'),
                'url' => '#conditions',
                'class' => $discount->getErrors('startDate') || $discount->getErrors('endDate') ? 'error' : '',
            ],
            'actions' => [
                'label' => t('Actions', category: 'commerce'),
                'url' => '#actions',
                'class' => $discount->getErrors('startDate') || $discount->getErrors('endDate') ? 'error' : '',
            ],
        ];

        return $this->storeManagementCpScreen($storeHandle, false)
            ->title($variables['title'])
            ->tabs($tabs)
            ->addCrumb(t('Discounts', category: 'commerce'), $store->getStoreSettingsUrl('discounts'))
            ->metaSidebarTemplate('commerce/store-management/discounts/_sidebar', $variables)
            ->action('commerce/discounts/save')
            ->redirectUrl($store->getStoreSettingsUrl('discounts'))
            ->contentTemplate('commerce/store-management/discounts/_edit', $variables);
    }

    public function save(Request $request): Response
    {
        $discount = new Discount();

        $discount->id = $request->input('id');

        abort_unless(currentUserElement()?->can($discount->id === null ? 'commerce-createDiscounts' : 'commerce-editDiscounts'), 403);

        $discount->storeId = $request->input('storeId');
        $discount->name = $request->input('name');
        $discount->description = $request->input('description');
        $discount->enabled = (bool)$request->input('enabled');
        $discount->setOrderCondition($request->input('orderCondition'));
        $discount->setCustomerCondition($request->input('customerCondition'));
        $discount->setShippingAddressCondition($request->input('shippingAddressCondition'));
        $discount->setBillingAddressCondition($request->input('billingAddressCondition'));
        $discount->requireCouponCode = (bool)$request->input('requireCouponCode');
        $discount->stopProcessing = (bool)$request->input('stopProcessing');
        $discount->purchaseQty = $request->input('purchaseQty');
        $discount->maxPurchaseQty = $request->input('maxPurchaseQty');
        $discount->percentageOffSubject = $request->input('percentageOffSubject');
        $discount->hasFreeShippingForMatchingItems = (bool)$request->input('hasFreeShippingForMatchingItems');
        $discount->hasFreeShippingForOrder = (bool)$request->input('hasFreeShippingForOrder');
        $discount->excludeOnPromotion = (bool)$request->input('excludeOnPromotion');
        $discount->couponFormat = $request->input('couponFormat', Coupons::DEFAULT_COUPON_FORMAT);
        $discount->perUserLimit = (int)$request->input('perUserLimit');
        $discount->perEmailLimit = (int)$request->input('perEmailLimit');
        $discount->totalDiscountUseLimit = (int)$request->input('totalDiscountUseLimit');
        $discount->ignorePromotions = (bool)$request->input('ignorePromotions');
        $discount->categoryRelationshipType = $request->input('categoryRelationshipType', $discount->categoryRelationshipType);
        $discount->appliedTo = $request->input('appliedTo') ?: DiscountRecord::APPLIED_TO_MATCHING_LINE_ITEMS;
        $discount->orderConditionFormula = $request->input('orderConditionFormula');

        $moneyInputAttributes = ['baseDiscount', 'perItemDiscount', 'purchaseTotal'];
        foreach ($moneyInputAttributes as $attr) {
            $attrValue = $request->input($attr) ?: ['value' => '0'];
            $attrValue['value'] = preg_replace('/[^0-9\.\-\,]/', '', (string)$attrValue['value']);
            $attrValue += ['currency' => $discount->getStore()->getCurrency()];
            $attrValue = Money::toDecimal(Money::toMoney($attrValue));

            if ($attr !== 'purchaseTotal') {
                $attrValue = (float)$attrValue;
                if ($attrValue > 0) {
                    $attrValue *= -1;
                }
            }

            $discount->{$attr} = (float)$attrValue;
        }

        $date = $request->input('dateFrom');
        if ($date && $dateFrom = DateTimeHelper::toDateTime($date)) {
            $discount->dateFrom = $dateFrom instanceof DateTime ? $dateFrom : DateTime::createFromInterface($dateFrom);
        }

        $date = $request->input('dateTo');
        if ($date && $dateTo = DateTimeHelper::toDateTime($date)) {
            $discount->dateTo = $dateTo instanceof DateTime ? $dateTo : DateTime::createFromInterface($dateTo);
        }

        $percentDiscount = $request->input('percentDiscount', 0);
        $percentDiscount = preg_replace('/[^0-9\.\-\,]/', '', (string)$percentDiscount);
        $discount->percentDiscount = -Localization::normalizePercentage($percentDiscount);

        $allPurchasables = !$request->input('allPurchasables', false);
        if ($discount->allPurchasables = $allPurchasables) {
            $discount->setPurchasableIds([]);
        } else {
            $purchasables = [];
            $purchasableGroups = $request->input('purchasables') ?: [];
            foreach ($purchasableGroups as $group) {
                if (is_array($group)) {
                    array_push($purchasables, ...$group);
                }
            }
            $discount->setPurchasableIds(array_unique($purchasables));
        }

        $allCategories = !$request->input('allCategories', false);
        if ($discount->allCategories = $allCategories) {
            $discount->setCategoryIds([]);
        } else {
            $relatedElements = [];
            $relatedElementByType = $request->input('relatedElements') ?: [];
            foreach ($relatedElementByType as $type) {
                if (is_array($type)) {
                    array_push($relatedElements, ...$type);
                }
            }
            $discount->setCategoryIds(array_unique($relatedElements));
        }

        $coupons = $request->input('coupons') ?: [];
        $this->setCouponsOnDiscount(coupons: $coupons, discount: $discount);

        if (app(Discounts::class)->saveDiscount($discount)) {
            return $this->asModelSuccess($discount, t('Discount saved.', category: 'commerce'), 'discount');
        }

        return $this->asModelFailure($discount, t('Couldn\'t save discount.', category: 'commerce'), 'discount');
    }

    private function setCouponsOnDiscount(array $coupons, Discount $discount): void
    {
        if (empty($coupons)) {
            $discount->setCoupons([]);
            return;
        }

        $discountCoupons = [];

        foreach ($coupons as $c) {
            $discountCoupons[] = \Craft::createObject(Coupon::class, [
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

    public function reorder(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        abort_unless($request->input('ids'), 400, 'Missing ids');

        $ids = Json::decode($request->input('ids'));
        $key = (int)$request->input('startPosition');

        $idsOrdered = [];
        foreach ($ids as $id) {
            // Temporary -1 because the `reorderDiscounts()` method will increment the key before saving.
            $idsOrdered[$key - 1] = $id;
            $key++;
        }

        if (!app(Discounts::class)->reorderDiscounts($idsOrdered)) {
            return $this->asFailure(t('Couldn\'t reorder discounts.', category: 'commerce'));
        }

        return $this->asSuccess();
    }

    public function moveToPage(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        $page = $request->input('page');
        $perPage = $request->input('perPage');

        if (AdminTable::moveToPage(Table::DISCOUNTS, $id, $page, $perPage)) {
            return $this->asSuccess(t('Discounts reordered.', category: 'commerce'));
        }

        return $this->asFailure(t('Couldn\'t reorder discounts.', category: 'commerce'));
    }

    public function delete(Request $request): Response
    {
        abort_unless(currentUserElement()?->can('commerce-deleteDiscounts'), 403);

        $id = $request->input('id');
        $ids = $request->input('ids');

        abort_if((!$id && empty($ids)) || ($id && !empty($ids)), 400, 'id or ids must be specified.');

        if ($id) {
            abort_unless($request->expectsJson(), 400);
            $ids = [$id];
        }

        foreach ($ids as $deleteId) {
            app(Discounts::class)->deleteDiscountById($deleteId);
        }

        if ($request->expectsJson()) {
            return $this->asSuccess();
        }

        return $this->asSuccess(t('Discounts deleted.', category: 'commerce'), redirect: url()->previous());
    }

    public function clearDiscountUses(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        $type = $request->input('type', 'total');
        $types = [self::DISCOUNT_COUNTER_TYPE_TOTAL, self::DISCOUNT_COUNTER_TYPE_CUSTOMER, self::DISCOUNT_COUNTER_TYPE_EMAIL];

        if (!in_array($type, $types, true)) {
            return $this->asFailure(t('Type not in allowed options.', category: 'commerce'));
        }

        match ($type) {
            self::DISCOUNT_COUNTER_TYPE_EMAIL => app(Discounts::class)->clearEmailUsageHistoryById($id),
            self::DISCOUNT_COUNTER_TYPE_CUSTOMER => app(Discounts::class)->clearCustomerUsageHistoryById($id),
            self::DISCOUNT_COUNTER_TYPE_TOTAL => app(Discounts::class)->clearDiscountUsesById($id),
        };

        return $this->asSuccess();
    }

    public function updateStatus(Request $request): Response
    {
        abort_unless(currentUserElement()?->can('commerce-editDiscounts'), 403);

        $ids = $request->input('ids');
        $status = $request->input('status');

        abort_if(empty($ids), 400, 'Missing ids');

        DB::transaction(function() use ($ids, $status) {
            $discounts = DiscountRecord::whereIn('id', $ids)->get();

            foreach ($discounts as $discount) {
                $discount->enabled = ($status == 'enabled');
                $discount->save();
            }
        });

        return $this->asSuccess(t('Discounts updated.', category: 'commerce'));
    }

    public function getDiscountsByPurchasableId(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        $id = $request->input('id');

        if (!$id) {
            return $this->asFailure(t('Purchasable ID is required.', category: 'commerce'));
        }

        $purchasable = app(Purchasables::class)->getPurchasableById($id);

        if (!$purchasable) {
            return $this->asFailure(t('No purchasable available.', category: 'commerce'));
        }

        $discounts = [];
        $purchasableDiscounts = app(Discounts::class)->getDiscountsRelatedToPurchasable($purchasable);
        foreach ($purchasableDiscounts as $discount) {
            if (!Arr::contains($discounts, 'id', $discount->id)) {
                $discountArray = $discount->toArray();
                $discountArray['cpEditUrl'] = $discount->getCpEditUrl();
                $discounts[] = $discountArray;
            }
        }

        return $this->asSuccess(data: ['discounts' => $discounts]);
    }

    private function populateVariables(array &$variables): void
    {
        $discount = $variables['discount'];

        $variables['title'] = $discount->id ? $discount->name : t('Create a Discount', category: 'commerce');

        if (Edition::get() === Edition::Pro) {
            $groups = UserGroups::getAllGroups();
            $variables['groups'] = $groups->mapWithKeys(fn($group) => [$group->id => $group->name])->all();
        } else {
            $variables['groups'] = [];
        }

        $flipNegativeNumberAttributes = ['baseDiscount', 'perItemDiscount'];
        foreach ($flipNegativeNumberAttributes as $attr) {
            if (!isset($discount->{$attr})) {
                continue;
            }

            if ($discount->{$attr} < 0) {
                $discount->{$attr} *= -1;
            } elseif ($discount->{$attr} == 0) {
                $discount->{$attr} = 0;
            }
        }

        $variables['counterTypeTotal'] = self::DISCOUNT_COUNTER_TYPE_TOTAL;
        $variables['counterTypeEmail'] = self::DISCOUNT_COUNTER_TYPE_EMAIL;
        $variables['counterTypeUser'] = self::DISCOUNT_COUNTER_TYPE_CUSTOMER;

        if ($discount->id) {
            $variables['emailUsage'] = app(Discounts::class)->getEmailUsageStatsById($discount->id);
            $variables['customerUsage'] = app(Discounts::class)->getCustomerUsageStatsById($discount->id);
        } else {
            $variables['emailUsage'] = 0;
            $variables['customerUsage'] = 0;
        }

        $variables['categoryElementType'] = Category::class;
        $variables['entryElementType'] = Entry::class;

        $categories = [];
        $entries = [];

        $request = request();
        if (empty($variables['id']) && $request->input('categoryIds')) {
            $categoryIds = explode('|', (string)$request->input('categoryIds'));
        } else {
            $categoryIds = $discount->getCategoryIds();
        }

        foreach ($categoryIds as $categoryId) {
            $elementId = (int)$categoryId;
            $element = Elements::getElementById($elementId, siteId: '*');

            if ($element instanceof Category) {
                $categories[] = $element;
            } elseif ($element instanceof Entry) {
                $entries[] = $element;
            }
        }

        $variables['categories'] = $categories;
        $variables['entries'] = $entries;

        $variables['elementRelationshipTypeOptions'] = [
            DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_SOURCE => t('The purchasable defines the relationship', category: 'commerce'),
            DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_TARGET => t('The purchasable is related by another element', category: 'commerce'),
            DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH => t('Either way', category: 'commerce'),
        ];

        $variables['appliedTo'] = [
            DiscountRecord::APPLIED_TO_MATCHING_LINE_ITEMS => t('Discount the matching items only', category: 'commerce'),
            DiscountRecord::APPLIED_TO_ALL_LINE_ITEMS => t('Discount all line items', category: 'commerce'),
        ];

        $purchasableIds = [];
        if (empty($variables['id']) && $request->input('purchasableIds')) {
            $purchasableIdsFromUrl = explode('|', (string)$request->input('purchasableIds'));
            foreach ($purchasableIdsFromUrl as $purchasableId) {
                $purchasable = Elements::getElementById((int)$purchasableId, siteId: $variables['siteIds']);
                if ($purchasable instanceof Product) {
                    $purchasableIds[] = $purchasable->defaultVariantId;
                } else {
                    $purchasableIds[] = $purchasableId;
                }
            }
            $discount->allPurchasables = false;
        } else {
            $purchasableIds = $discount->getPurchasableIds();
        }

        $purchasableIds = array_filter($purchasableIds);

        $purchasables = [];
        foreach ($purchasableIds as $purchasableId) {
            $purchasable = Elements::getElementById((int)$purchasableId, siteId: $variables['siteIds']);
            if ($purchasable instanceof PurchasableInterface) {
                $class = $purchasable::class;
                $purchasables[$class] ??= [];
                $purchasables[$class][] = $purchasable;
            }
        }
        $variables['purchasables'] = $purchasables;

        $variables['purchasableTypes'] = [];
        $purchasableTypes = app(Purchasables::class)->getAllPurchasableElementTypes();

        /** @var Purchasable $purchasableType */
        foreach ($purchasableTypes as $purchasableType) {
            $variables['purchasableTypes'][] = [
                'name' => $purchasableType::displayName(),
                'elementType' => $purchasableType,
            ];
        }
    }

    public function generateCoupons(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $count = (int)$request->input('count', 0);
        $format = $request->input('format', Coupons::DEFAULT_COUPON_FORMAT);
        $existingCodes = $request->input('existingCodes', []);

        try {
            $coupons = app(Coupons::class)->generateCouponCodes(count: $count, format: $format, existingCodes: $existingCodes);
        } catch (\Exception $e) {
            return $this->asFailure(message: t('Unable to generate coupon codes: {message}', ['message' => $e->getMessage()], category: 'commerce'));
        }

        return $this->asSuccess(data: ['coupons' => $coupons]);
    }
}
