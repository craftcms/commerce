<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\StoreManagement;

use CraftCms\Cms\Cp\Html\ContentHtml;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\ConditionBuilder;
use CraftCms\Cms\Form\Controls\DateTime as DateTimeControl;
use CraftCms\Cms\Form\Controls\ElementSelect;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Controls\Money as MoneyControl;
use CraftCms\Cms\Form\Controls\Number;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Controls\Textarea;
use CraftCms\Cms\Form\Enums\ChoicePresentation;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\FormResolver;
use CraftCms\Cms\Form\Nodes\Action;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\Group;
use CraftCms\Cms\Form\Nodes\Heading;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Form\Nodes\MarkdownContent;
use CraftCms\Cms\Form\Nodes\Table;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Money;
use CraftCms\Cms\Translation\Formatter;
use CraftCms\Cms\Translation\Locale;
use CraftCms\Commerce\Address\Conditions\DiscountAddressCondition;
use CraftCms\Commerce\Customer\Conditions\DiscountCustomerCondition;
use CraftCms\Commerce\Form\Nodes\UsageCounter;
use CraftCms\Commerce\Helpers\Localization;
use CraftCms\Commerce\Order\Conditions\DiscountOrderCondition;
use CraftCms\Commerce\Promotion\Coupons;
use CraftCms\Commerce\Promotion\Data\Coupon;
use CraftCms\Commerce\Promotion\Data\Discount;
use CraftCms\Commerce\Promotion\Discounts;
use CraftCms\Commerce\Promotion\Models\Discount as DiscountRecord;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use CraftCms\Commerce\Purchasable\Purchasables;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;

use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

readonly class DiscountsController extends BaseStoreManagementController
{
    public const string DISCOUNT_COUNTER_TYPE_TOTAL = 'total';
    public const string DISCOUNT_COUNTER_TYPE_EMAIL = 'email';
    public const string DISCOUNT_COUNTER_TYPE_CUSTOMER = 'customer';

    public function __construct(
        FormResolver $formResolver,
    ) {
        parent::__construct($formResolver);
    }

    protected function getSectionCrumb(Store $store): array
    {
        return ['label' => t('Discounts', category: 'commerce'), 'href' => $store->getStoreSettingsUrl('discounts')];
    }

    /** Page size for the {@see Table} Node's `dataUrl()` mode (smaller than legacy's 100, to exercise pagination at this screen's current scale). */
    private const int DISCOUNTS_PER_PAGE = 10;

    public function index(?string $storeHandle = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        $nodes = [
            Table::make('discounts')
                ->columns([
                    ['key' => 'name', 'label' => t('Name')],
                    ['key' => 'requireCouponCode', 'label' => t('Require Coupon Code', category: 'commerce')],
                    ['key' => 'duration', 'label' => t('Duration', category: 'commerce')],
                    ['key' => 'timesUsed', 'label' => t('Times Used', category: 'commerce')],
                    ['key' => 'stop', 'label' => t('Stops Processing?', category: 'commerce')],
                    ['key' => 'ignore', 'label' => t('Ignore Promotions?', category: 'commerce')],
                ])
                ->dataUrl(action([self::class, 'tableData'], ['storeHandle' => $store->handle]), self::DISCOUNTS_PER_PAGE)
                ->moveToPageUrl(action([self::class, 'moveToPage'], ['storeHandle' => $store->handle]))
                ->emptyMessage(t('No discounts exist yet.', category: 'commerce'))
                ->searchable()
                ->when(
                    currentUserElement()?->can('commerce-createDiscounts'),
                    fn(Table $table) => $table->createAction(t('New discount', category: 'commerce'), $store->getStoreSettingsUrl('discounts/new')),
                )
                ->reorderable(
                    action([self::class, 'reorder']),
                    t('Discounts reordered.', category: 'commerce'),
                    t('Couldn’t reorder discounts.', category: 'commerce'),
                )
                ->when(
                    currentUserElement()?->can('commerce-deleteDiscounts'),
                    fn(Table $table) => $table->deletable(action([self::class, 'delete']), bulk: true),
                )
                ->when(
                    currentUserElement()?->can('commerce-editDiscounts'),
                    fn(Table $table) => $table->statusActions($this->statusActions(action([self::class, 'updateStatus']))),
                ),
        ];

        return $this->cpScreenResponse($store)
            ->title(t('Discounts', category: 'commerce'))
            ->crumbs($this->crumbs($store))
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve(Form::make($nodes), new FormContext()),
                'contentMaxWidth' => false,
            ]);
    }

    /** {@see Table::dataUrl()}'s endpoint: one (optionally searched) page of discounts, paginated the same way {@see \CraftCms\Cms\Http\ViewModels\ContentIndexViewModel::pagination()} does. */
    public function tableData(Request $request): JsonResponse
    {
        abort_unless($request->expectsJson(), 400);

        $store = $this->resolveStore($request->input('storeHandle'));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = self::DISCOUNTS_PER_PAGE;
        $search = trim((string) $request->input('search', ''));

        $discounts = $this->searchedDiscounts($store, $search);
        $dateFormat = I18N::getFormattingLocale()->getDateTimeFormat('short', Locale::FORMAT_PHP);

        $rows = Table::prepareRows(
            $discounts
                ->slice(($page - 1) * $perPage, $perPage)
                ->map(fn(Discount $discount) => $this->buildDiscountRow($discount, $store, $dateFormat))
                ->values()
                ->all(),
        );

        $paginator = new LengthAwarePaginator($rows, $discounts->count(), $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);

        return new JsonResponse([
            'data' => $rows,
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /** {@see Table::moveToPageUrl()}'s endpoint — see {@see Discounts::moveDiscountToPosition()} for the actual move. */
    public function moveToPage(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $store = $this->resolveStore($request->input('storeHandle'));
        $id = (int) $request->input('id');
        $page = max(1, (int) $request->input('page', 1));

        $discount = app(Discounts::class)->getDiscountById($id, $store->id);
        abort_if($discount === null, 404);

        $toPosition = ($page - 1) * self::DISCOUNTS_PER_PAGE;

        if (!app(Discounts::class)->moveDiscountToPosition($id, $toPosition)) {
            return $this->asFailure(t('Couldn’t reorder discounts.', category: 'commerce'));
        }

        return $this->asSuccess();
    }

    /**
     * Matches the legacy screen's own search exactly: name, description, and any coupon code
     * (neither a column here).
     *
     * @return Collection<int, Discount>
     */
    private function searchedDiscounts(Store $store, string $search): Collection
    {
        $discounts = app(Discounts::class)->getAllDiscounts($store->id);

        if ($search === '') {
            return $discounts;
        }

        $needle = mb_strtolower($search);

        return $discounts->filter(function(Discount $discount) use ($needle) {
            $couponCodes = collect(app(Coupons::class)->getCouponsByDiscountId($discount->id))
                ->map(fn(Coupon $coupon) => $coupon->code)
                ->implode(' ');

            $haystack = mb_strtolower(trim("{$discount->name} {$discount->description} {$couponCodes}"));

            return str_contains($haystack, $needle);
        })->values();
    }

    /** `_status` gives a plain enabled/disabled dot (matching legacy), not a richer live/pending/expired scheme — `dateFrom`/`dateTo` already have their own "Duration" column. */
    private function buildDiscountRow(Discount $discount, Store $store, string $dateFormat): array
    {
        $dateRange = (!$discount->dateFrom && !$discount->dateTo)
            ? '∞'
            : ($discount->dateFrom?->format($dateFormat) ?? '∞') . ' - ' . ($discount->dateTo?->format($dateFormat) ?? '∞');

        return [
            'id' => $discount->id,
            '_status' => $discount->enabled,
            'name' => ['label' => t($discount->name, category: 'site'), 'url' => $store->getStoreSettingsUrl('discounts/' . $discount->id)],
            'requireCouponCode' => $discount->requireCouponCode ? ['icon' => 'check', 'label' => t('Yes')] : '',
            'duration' => $dateRange,
            'timesUsed' => $discount->totalDiscountUses,
            'stop' => $discount->stopProcessing ? ['icon' => 'check', 'label' => t('Yes')] : '',
            'ignore' => $discount->ignorePromotions ? ['icon' => 'check', 'label' => t('Yes')] : '',
        ];
    }

    public function edit(?string $storeHandle = null, ?int $id = null): Response|CpScreenResponse
    {
        abort_unless(currentUserElement()?->can($id === null ? 'commerce-createDiscounts' : 'commerce-editDiscounts'), 403);

        $store = $this->resolveStore($storeHandle);
        $discount = $this->resolveDiscount($id, $store);
        abort_if($id !== null && $discount === null, 404);
        $discount ??= new Discount(['allPurchasables' => true, 'allCategories' => true, 'storeId' => $store->id]);

        $title = $discount->id ? $discount->name : t('Create a Discount', category: 'commerce');

        $formatter = app(Formatter::class);
        $metadataHtml = $discount->id ? app(ContentHtml::class)->metadataHtml([
            t('Created at') => $formatter->asDateTime($discount->dateCreated, 'short'),
            t('Updated at') => $formatter->asDateTime($discount->dateUpdated, 'short'),
        ]) : null;

        $values = $this->initialValues($discount, $store);

        $form = $this->formResolver->resolve(
            $this->buildForm($discount, $values, $store),
            new FormContext(values: $values, refreshable: true),
        );

        return $this->cpScreenResponse($store, subnav: false)
            ->title($title)
            ->crumbs($this->crumbs($store, ...($discount->id ? [['label' => $title]] : [])))
            ->action('commerce/discounts/save')
            ->redirectUrl($store->getStoreSettingsUrl('discounts'))
            ->inertiaPage('Form', [
                'form' => $form,
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'save']),
                ],
                'refreshUrl' => action([self::class, 'renderForm']),
                'metadataHtml' => $metadataHtml,
            ]);
    }

    /**
     * Re-resolves the {@see edit()} Form tree for the values currently in progress on the
     * client, so toggling "only match certain purchasables"/"only match related entries" can
     * reveal or hide the fields that depend on them without a full page reload.
     */
    public function renderForm(Request $request): JsonResponse
    {
        $request->validate([
            'values' => ['required', 'array'],
            'values.storeId' => ['required', 'integer'],
            'values.id' => ['nullable', 'integer'],
            'scope' => ['present', 'array', 'size:0'],
        ]);

        $values = $request->input('values');
        $store = app(Stores::class)->getStoreById((int) $values['storeId']);
        abort_if($store === null, 404);

        $discountId = $values['id'] ?? null;
        $discount = $discountId ? $this->resolveDiscount((int) $discountId, $store) : null;
        abort_if($discountId !== null && $discount === null, 404);
        $discount ??= new Discount(['allPurchasables' => true, 'allCategories' => true, 'storeId' => $store->id]);

        $values = array_replace($this->initialValues($discount, $store), $values);

        $form = $this->formResolver->resolve(
            $this->buildForm($discount, $values, $store),
            new FormContext(values: $values, refreshable: true),
        );

        return new JsonResponse(['form' => $form]);
    }

    private function resolveDiscount(?int $id, Store $store): ?Discount
    {
        return $id ? app(Discounts::class)->getDiscountById($id, $store->id) : null;
    }

    /** @return array<string, mixed> */
    private function initialValues(Discount $discount, Store $store): array
    {
        $siteIds = $store->getSites()->pluck('id')->all();

        $purchasablesByType = [];
        foreach ($discount->getPurchasableIds() as $purchasableId) {
            $purchasable = Elements::getElementById((int) $purchasableId, siteId: $siteIds);
            if ($purchasable instanceof PurchasableInterface) {
                $purchasablesByType[$purchasable::class][] = $purchasableId;
            }
        }

        $entryIds = [];
        foreach ($discount->getCategoryIds() as $categoryId) {
            $element = Elements::getElementById((int) $categoryId, siteId: '*');
            if ($element instanceof Entry) {
                $entryIds[] = $categoryId;
            }
        }

        return [
            'id' => $discount->id,
            'storeId' => $store->id,
            'name' => $discount->name,
            'description' => $discount->description,
            'enabled' => $discount->enabled,
            'stopProcessing' => $discount->stopProcessing,

            // The lightswitches are worded as "only match certain purchasables/related
            // entries…", the opposite sense of the `allPurchasables`/`allCategories` properties
            // they end up setting — kept as its own path (rather than a Choice bound directly
            // to `allPurchasables`) so nothing here has to reason about a double negative; the
            // inversion happens once, in `save()`/here, not everywhere the value is read.
            'restrictPurchasables' => !$discount->allPurchasables,
            'purchasables' => $purchasablesByType,
            'restrictRelatedElements' => !$discount->allCategories,
            'relatedElements' => ['entries' => $entryIds],
            'categoryRelationshipType' => $discount->categoryRelationshipType,

            'requireCouponCode' => $discount->requireCouponCode,
            // No coupon-management UI yet (see buildForm()) — carried through as a hidden field
            // purely so an unrelated save doesn't reset it back to the default format.
            'couponFormat' => $discount->couponFormat,

            'orderCondition' => $discount->getOrderCondition()->getConfig(),
            'customerCondition' => $discount->getCustomerCondition()->getConfig(),
            'shippingAddressCondition' => $discount->getShippingAddressCondition()->getConfig(),
            'billingAddressCondition' => $discount->getBillingAddressCondition()->getConfig(),
            'dateFrom' => $this->dateTimeControlValue($discount->dateFrom),
            'dateTo' => $this->dateTimeControlValue($discount->dateTo),
            'orderConditionFormula' => $discount->orderConditionFormula,
            'purchaseTotal' => $discount->purchaseTotal,
            'purchaseQty' => $discount->purchaseQty,
            'maxPurchaseQty' => $discount->maxPurchaseQty,
            'perUserLimit' => $discount->perUserLimit,
            'perEmailLimit' => $discount->perEmailLimit,
            'totalDiscountUseLimit' => $discount->totalDiscountUseLimit,
            'excludeOnPromotion' => $discount->excludeOnPromotion,

            'appliedTo' => $discount->appliedTo,
            // Money/percentage inputs mirror the legacy screen's own sign convention: stored as
            // negative internally (a discount subtracted from the total), shown to the editor as
            // a plain positive amount/percentage — flipped back in save().
            'perItemDiscount' => $discount->perItemDiscount < 0 ? $discount->perItemDiscount * -1 : $discount->perItemDiscount,
            'percentDiscount' => round(-($discount->percentDiscount ?? 0) * 100, 6),
            'percentageOffSubject' => $discount->percentageOffSubject,
            'ignorePromotions' => $discount->ignorePromotions,
            'baseDiscount' => $discount->baseDiscount < 0 ? $discount->baseDiscount * -1 : $discount->baseDiscount,
            'hasFreeShippingForOrder' => $discount->hasFreeShippingForOrder,
            'hasFreeShippingForMatchingItems' => $discount->hasFreeShippingForMatchingItems,
        ];
    }

    /** @return array{date: string, time: string, timezone: string} */
    private function dateTimeControlValue(?DateTime $value): array
    {
        return [
            'date' => $value?->format('Y-m-d') ?? '',
            'time' => $value?->format('H:i') ?? '',
            'timezone' => $value?->getTimezone()->getName() ?? date_default_timezone_get(),
        ];
    }

    /** @param array<string, mixed> $values */
    private function buildForm(Discount $discount, array $values, Store $store): Form
    {
        $currency = $store->getCurrency()?->getCode() ?? 'USD';
        $percentSymbol = I18N::getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);
        $showEntries = Entry::find()->exists();
        $restrictPurchasables = (bool) ($values['restrictPurchasables'] ?? false);
        $restrictRelatedElements = (bool) ($values['restrictRelatedElements'] ?? false);

        $matchingItemsFields = [
            MarkdownContent::make('matching-items-intro', t('Limit which orders this discount applies to based on its line items.', category: 'commerce'))
                ->displayInPane(false),
            Field::make(t('Only match certain purchasables…', category: 'commerce'), Lightswitch::make('restrictPurchasables')->reactive()),
        ];

        if ($restrictPurchasables) {
            foreach (app(Purchasables::class)->getAllPurchasableElementTypes() as $purchasableType) {
                $matchingItemsFields[] = Field::make($purchasableType::displayName(), ElementSelect::make(['purchasables', $purchasableType])
                    ->elementType($purchasableType)
                    ->showSiteMenu());
            }
        }

        if ($showEntries) {
            $matchingItemsFields[] = Field::make(t('Only match purchasables related to…', category: 'commerce'), Lightswitch::make('restrictRelatedElements')->reactive());

            if ($restrictRelatedElements) {
                $matchingItemsFields[] = Field::make(t('Entries', category: 'app'), ElementSelect::make(['relatedElements', 'entries'])
                    ->elementType(Entry::class)
                    ->showSiteMenu());

                $matchingItemsFields[] = Group::make('category-relationship-type-advanced', [
                    Field::make(t('Relationship Type', category: 'commerce'), Choice::make('categoryRelationshipType')
                        ->presentation(ChoicePresentation::Radios)
                        ->options([
                            ['label' => t('The purchasable defines the relationship', category: 'commerce'), 'value' => DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_SOURCE],
                            ['label' => t('The purchasable is related by another element', category: 'commerce'), 'value' => DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_TARGET],
                            ['label' => t('Either way', category: 'commerce'), 'value' => DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH],
                        ])),
                ])
                    ->label(t('Advanced', category: 'commerce'))
                    ->collapsible()
                    ->expanded($discount->categoryRelationshipType !== DiscountRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH);
            }
        } else {
            // Categories were removed as a discount-matching option in Craft 6 (see the
            // `allCategories`/`categoryRelationshipType` TODOs on the Discount model itself) —
            // when there's nothing left for it to restrict, keep it permanently off rather than
            // showing a toggle with nothing behind it.
            $matchingItemsFields[] = HiddenField::make('restrictRelatedElements');
        }

        $couponsFields = [
            Field::make(t('Require Coupon Code', category: 'commerce'), Lightswitch::make('requireCouponCode')),
            HiddenField::make('couponFormat'),
            // TODO: coupon code management (the editable list of codes, uses, and max-uses,
            // plus the "Generate" batch-create action) isn't built yet — existing coupons on
            // this discount are left untouched by a save from this screen in the meantime (see
            // save()).
            MarkdownContent::make('coupons-todo', t('Coupon code management isn’t available on this screen yet — existing coupons are left as-is when you save.', category: 'commerce'))
                ->displayInPane(false),
        ];

        // Only a saved discount has usage history to report — a brand new, unsaved one has
        // nothing yet, and no valid id to reset usage against anyway. The raw SQL aggregates
        // these come back as (numeric) strings, not ints.
        $customerUsage = $discount->id ? array_map('intval', app(Discounts::class)->getCustomerUsageStatsById($discount->id)) : ['uses' => 0, 'users' => 0];
        $emailUsage = $discount->id ? array_map('intval', app(Discounts::class)->getEmailUsageStatsById($discount->id)) : ['uses' => 0, 'emails' => 0];

        $conditionsFields = [
            Field::make(t('Match Order', category: 'commerce'), ConditionBuilder::make('orderCondition')
                ->conditionClass(DiscountOrderCondition::class))
                ->instructions(t('Create rules that allow this discount to match the order.', category: 'commerce')),
            Field::make(t('Match Customer', category: 'commerce'), ConditionBuilder::make('customerCondition')
                ->conditionClass(DiscountCustomerCondition::class))
                ->instructions(t('Create rules that allow this discount to match the order’s customer.', category: 'commerce')),
            Field::make(t('Match Shipping Address', category: 'commerce'), ConditionBuilder::make('shippingAddressCondition')
                ->conditionClass(DiscountAddressCondition::class))
                ->instructions(t('Create rules that allow this discount to match the order’s shipping address.', category: 'commerce')),
            Field::make(t('Match Billing Address', category: 'commerce'), ConditionBuilder::make('billingAddressCondition')
                ->conditionClass(DiscountAddressCondition::class))
                ->instructions(t('Create rules that allow this discount to match the order’s billing address.', category: 'commerce')),

            Field::make(t('Start Date', category: 'commerce'), DateTimeControl::make('dateFrom')->showTime())
                ->instructions(t('Date from which the discount will be active. Leave blank for unlimited start date', category: 'commerce')),
            Field::make(t('End Date', category: 'commerce'), DateTimeControl::make('dateTo')->showTime())
                ->instructions(t('Date when the discount will be finished. Leave blank for unlimited end date', category: 'commerce')),

            Group::make('order-condition-formula-advanced', [
                Field::make(t('Order Condition Formula', category: 'commerce'), Textarea::make('orderConditionFormula')->rows(5)->monospace())
                    ->instructions(t('Specify a <a href="{url}">Twig condition</a> that determines whether the discount should apply to a given order. (The order can be referenced via an `order` variable.)', [
                        'url' => 'https://twig.symfony.com/doc/2.x/templates.html#expressions',
                    ], category: 'commerce')),
            ])
                ->label(t('Advanced', category: 'commerce'))
                ->collapsible()
                ->expanded((bool) ($values['orderConditionFormula'] ?? '')),

            Field::make(t('Purchase Total', category: 'commerce'), MoneyControl::make('purchaseTotal')->currency($currency))
                ->instructions(t('Restrict the discount to only those orders where the customer has purchased a minimum total value of matching items.', category: 'commerce')),
            Field::make(t('Minimum Purchase Quantity', category: 'commerce'), Number::make('purchaseQty'))
                ->instructions(t('Minimum number of matching items that need to be ordered for this discount to apply.', category: 'commerce')),
            Field::make(t('Maximum Purchase Quantity', category: 'commerce'), Number::make('maxPurchaseQty'))
                ->instructions(t('Maximum number of matching items that can be ordered for this discount to apply. A zero value here will skip this condition.', category: 'commerce')),

            Field::make(t('Per User Discount Limit', category: 'commerce'), Number::make('perUserLimit')->min(0)->step(1)->size(5))
                ->instructions(t('How many times one user is allowed to use this discount. If this is set to something besides zero, the discount will only be available to signed in users.', category: 'commerce'))
                ->when($discount->id, fn(Field $field) => $field->actions(
                    UsageCounter::make(
                        'per-user-usage',
                        t('{uses} uses across {users} users', ['uses' => $customerUsage['uses'], 'users' => $customerUsage['users']], category: 'commerce'),
                        action([self::class, 'clearDiscountUses']),
                        ['id' => $discount->id, 'type' => self::DISCOUNT_COUNTER_TYPE_CUSTOMER],
                        t('Reset usage', category: 'commerce'),
                    )->confirmMessage(t('Are you sure you want to clear this discount usage counter?', category: 'commerce')),
                )),
            Field::make(t('Per Email Address Discount Limit', category: 'commerce'), Number::make('perEmailLimit')->min(0)->step(1)->size(5))
                ->instructions(t('How many times one email address is allowed to use this discount. This applies to all previous orders, whether guest or user. Set to zero for unlimited use by guests or users.', category: 'commerce'))
                ->when($discount->id, fn(Field $field) => $field->actions(
                    UsageCounter::make(
                        'per-email-usage',
                        t('{uses} uses across {emails} email addresses', ['uses' => $emailUsage['uses'], 'emails' => $emailUsage['emails']], category: 'commerce'),
                        action([self::class, 'clearDiscountUses']),
                        ['id' => $discount->id, 'type' => self::DISCOUNT_COUNTER_TYPE_EMAIL],
                        t('Reset usage', category: 'commerce'),
                    )->confirmMessage(t('Are you sure you want to clear this discount usage counter?', category: 'commerce')),
                )),
            Field::make(t('Total Discount Use Limit', category: 'commerce'), Number::make('totalDiscountUseLimit')->min(0)->step(1)->size(5))
                ->instructions(t('How many times this discount can be used in total by guests or signed in users. Set zero for unlimited use.', category: 'commerce'))
                ->when($discount->id, fn(Field $field) => $field->actions(
                    UsageCounter::make(
                        'total-usage',
                        $discount->totalDiscountUses === 1
                            ? t('{count} time', ['count' => 1], category: 'commerce')
                            : t('{count} times', ['count' => $discount->totalDiscountUses], category: 'commerce'),
                        action([self::class, 'clearDiscountUses']),
                        ['id' => $discount->id, 'type' => self::DISCOUNT_COUNTER_TYPE_TOTAL],
                        t('Clear counter', category: 'commerce'),
                    )->confirmMessage(t('Are you sure you want to clear this discount usage counter?', category: 'commerce')),
                )),

            Field::make(t('Exclude this discount for products that are already on promotion', category: 'commerce'), Lightswitch::make('excludeOnPromotion')),
        ];

        $actionsFields = [
            Heading::make('per-item-discount-heading', t('Per Item Discount', category: 'commerce')),
            Field::make(t('Discounted Items', category: 'commerce'), Choice::make('appliedTo')
                ->options([
                    ['label' => t('Discount the matching items only', category: 'commerce'), 'value' => DiscountRecord::APPLIED_TO_MATCHING_LINE_ITEMS],
                    ['label' => t('Discount all line items', category: 'commerce'), 'value' => DiscountRecord::APPLIED_TO_ALL_LINE_ITEMS],
                ])
                ->withoutPlaceholder())
                ->instructions(t('When this discount is applied to an order, which line items should be discounted?', category: 'commerce')),
            Field::make(t('Per Item Amount Off', category: 'commerce'), MoneyControl::make('perItemDiscount')->currency($currency))
                ->instructions(t('The flat value which should discount each item. i.e “3” for $3 off each item.', category: 'commerce')),
            Field::make(t('Per Item Percentage Off', category: 'commerce'), Number::make('percentDiscount')->size(5))
                ->actions(Action::make(Choice::make('percentageOffSubject')
                    ->withoutPlaceholder()
                    ->options([
                        ['label' => t('{pct} off the discounted item price', ['pct' => $percentSymbol], category: 'commerce'), 'value' => DiscountRecord::TYPE_DISCOUNTED_SALEPRICE],
                        ['label' => t('{pct} off the original item price', ['pct' => $percentSymbol], category: 'commerce'), 'value' => DiscountRecord::TYPE_ORIGINAL_SALEPRICE],
                    ])))
                ->instructions(t('The percentile value which should discount each item. i.e. {ex1} for {ex2} off. Percentages are rounded to 2 decimal places.', [
                    'ex1' => '`10`',
                    'ex2' => $percentSymbol,
                ], category: 'commerce'))
                ->tip(t('If you select the percentage to be  “off the discounted item price”, this will include the “Per Item Amount” as well as any other discounts that applied before this one.', category: 'commerce')),
            Field::make(t('Ignore promotional prices when this discount is applied to matching line items', category: 'commerce'), Lightswitch::make('ignorePromotions')),

            Heading::make('flat-amount-off-order-heading', t('Flat Amount Off Order', category: 'commerce')),
            Field::make(t('Flat Order Discount Amount Off', category: 'commerce'), MoneyControl::make('baseDiscount')->currency($currency))
                ->instructions(t('The amount of discount that is applied to the whole order. This amount is spread across line items in order of highest price to lowest price, until the discount is used up.', category: 'commerce'))
                ->tip(t('The base discount can only discount items in the cart to down to zero until it is used up, it can not make the order negative.', category: 'commerce')),

            Heading::make('additional-actions-heading', t('Additional Actions', category: 'commerce')),
            Field::make(t('Remove all shipping costs from the order', category: 'commerce'), Lightswitch::make('hasFreeShippingForOrder')),
            Field::make(t('Remove shipping costs for matching items only', category: 'commerce'), Lightswitch::make('hasFreeShippingForMatchingItems')),
            Field::make(t('Don’t apply any subsequent discounts to an order if this discount is applied', category: 'commerce'), Lightswitch::make('stopProcessing')),
        ];

        return Form::make([
            HiddenField::make('id'),
            HiddenField::make('storeId'),
        ])
            ->addTab(t('Discount', category: 'commerce'), [
                Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())
                    ->instructions(t('What this discount will be called in the control panel.', category: 'commerce'))
                    ->required(),
                Field::make(t('Description', category: 'commerce'), Text::make('description'))
                    ->instructions(t('Discount description.', category: 'commerce')),
                Field::make(t('Enable this discount', category: 'commerce'), Lightswitch::make('enabled')),
            ])
            ->addTab(t('Matching Items', category: 'commerce'), $matchingItemsFields)
            ->addTab(t('Coupons', category: 'commerce'), $couponsFields)
            ->addTab(t('Conditions', category: 'commerce'), $conditionsFields)
            ->addTab(t('Actions', category: 'commerce'), $actionsFields);
    }

    public function save(Request $request): Response
    {
        $discount = new Discount();

        $discount->id = $request->input('id') ? (int) $request->input('id') : null;

        abort_unless(currentUserElement()?->can($discount->id === null ? 'commerce-createDiscounts' : 'commerce-editDiscounts'), 403);

        $discount->storeId = (int) $request->input('storeId');
        $this->requireStoreAccess($discount->storeId);
        $discount->name = $request->input('name');
        $discount->description = $request->input('description');
        $discount->enabled = (bool) $request->input('enabled');
        $discount->setOrderCondition($request->input('orderCondition'));
        $discount->setCustomerCondition($request->input('customerCondition'));
        $discount->setShippingAddressCondition($request->input('shippingAddressCondition'));
        $discount->setBillingAddressCondition($request->input('billingAddressCondition'));
        $discount->requireCouponCode = (bool) $request->input('requireCouponCode');
        $discount->stopProcessing = (bool) $request->input('stopProcessing');
        $discount->purchaseQty = (int) $request->input('purchaseQty');
        $discount->maxPurchaseQty = (int) $request->input('maxPurchaseQty');
        $discount->percentageOffSubject = $request->input('percentageOffSubject');
        $discount->hasFreeShippingForMatchingItems = (bool) $request->input('hasFreeShippingForMatchingItems');
        $discount->hasFreeShippingForOrder = (bool) $request->input('hasFreeShippingForOrder');
        $discount->excludeOnPromotion = (bool) $request->input('excludeOnPromotion');
        $discount->couponFormat = $request->input('couponFormat', Coupons::DEFAULT_COUPON_FORMAT);
        $discount->perUserLimit = (int) $request->input('perUserLimit');
        $discount->perEmailLimit = (int) $request->input('perEmailLimit');
        $discount->totalDiscountUseLimit = (int) $request->input('totalDiscountUseLimit');
        $discount->ignorePromotions = (bool) $request->input('ignorePromotions');
        $discount->categoryRelationshipType = $request->input('categoryRelationshipType', $discount->categoryRelationshipType);
        $discount->appliedTo = $request->input('appliedTo') ?: DiscountRecord::APPLIED_TO_MATCHING_LINE_ITEMS;
        $discount->orderConditionFormula = trim((string) $request->input('orderConditionFormula', ''));

        // No coupon-management UI yet (see buildForm()) — deliberately not touching
        // setCoupons()/setCouponsOnDiscount() here at all. `id` is already set above, so
        // Discount::getCoupons()'s own lazy-load-from-DB fallback keeps whatever coupons this
        // discount already has, unchanged, once the request reaches Discounts::saveDiscount().

        $moneyInputs = ['baseDiscount', 'perItemDiscount', 'purchaseTotal'];
        $signFlippedMoneyInputs = ['baseDiscount', 'perItemDiscount'];
        foreach ($moneyInputs as $moneyInput) {
            $input = $request->input($moneyInput);
            $input = is_array($input) ? $input : ['value' => $input];
            $input += ['currency' => $discount->getStore()->getCurrency()];
            $value = (float) Money::toDecimal(Money::toMoney($input));

            // baseDiscount/perItemDiscount are entered as a plain positive amount but stored
            // negative internally (a discount subtracted from the total) — purchaseTotal is a
            // real threshold, not a discount, so it's left alone.
            if (in_array($moneyInput, $signFlippedMoneyInputs, true) && $value > 0) {
                $value *= -1;
            }

            $discount->$moneyInput = $value;
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
        $percentDiscount = preg_replace('/[^0-9\.\-\,]/', '', (string) $percentDiscount);
        $discount->percentDiscount = -Localization::normalizePercentage($percentDiscount);

        $allPurchasables = !$request->input('restrictPurchasables', false);
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

        $allCategories = !$request->input('restrictRelatedElements', false);
        if ($discount->allCategories = $allCategories) {
            $discount->setCategoryIds([]);
        } else {
            $discount->setCategoryIds(array_unique((array) ($request->input('relatedElements')['entries'] ?? [])));
        }

        if (app(Discounts::class)->saveDiscount($discount)) {
            return $this->asModelSuccess($discount, t('Discount saved.', category: 'commerce'), 'discount');
        }

        return $this->asModelFailure($discount, t('Couldn’t save discount.', category: 'commerce'), 'discount');
    }

    /**
     * Used by {@see save()} once the Coupons tab's editable table is built — kept ready rather
     * than deleted so wiring it back up is a one-line change, not a rewrite.
     */
    private function setCouponsOnDiscount(array $coupons, Discount $discount): void
    {
        if (empty($coupons)) {
            $discount->setCoupons([]);
            return;
        }

        $discountCoupons = [];

        foreach ($coupons as $c) {
            $discountCoupons[] = new Coupon([
                'id' => $c['id'] ?: null,
                'discountId' => null,
                'code' => $c['code'],
                'uses' => $c['uses'] ?: 0,
                'maxUses' => is_numeric($c['maxUses']) ? (int) $c['maxUses'] : null,
            ]);
        }

        $discount->setCoupons($discountCoupons);
    }

    /** Two payload shapes, same `id`/`ids` dual-payload precedent {@see delete()} follows: upfront mode posts the full reordered `ids`; endpoint mode (only one page loaded) posts a single `id`/`toPosition` instead. */
    public function reorder(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        if ($request->has('toPosition')) {
            $id = (int) $request->input('id');
            $toPosition = (int) $request->input('toPosition');

            if (!app(Discounts::class)->moveDiscountToPosition($id, $toPosition)) {
                return $this->asFailure(t('Couldn’t reorder discounts.', category: 'commerce'));
            }

            return $this->asSuccess();
        }

        abort_unless($request->input('ids'), 400, 'Missing ids');

        $ids = $request->input('ids');
        $ids = is_string($ids) ? Json::decode($ids) : $ids;

        $idsOrdered = [];
        $key = 0;
        foreach ($ids as $id) {
            $idsOrdered[$key++] = $id;
        }

        if (!app(Discounts::class)->reorderDiscounts($idsOrdered)) {
            return $this->asFailure(t('Couldn’t reorder discounts.', category: 'commerce'));
        }

        return $this->asSuccess();
    }

    public function delete(Request $request): Response
    {
        abort_unless(currentUserElement()?->can('commerce-deleteDiscounts'), 403);

        $id = $request->input('id');
        $ids = $request->input('ids');

        abort_if((!$id && empty($ids)) || ($id && !empty($ids)), 400, 'id or ids must be specified.');

        if ($id) {
            $ids = [$id];
        }

        foreach ($ids as $deleteId) {
            $discount = app(Discounts::class)->getDiscountById((int) $deleteId);
            if ($discount) {
                $this->requireStoreAccess($discount->storeId);
            }

            app(Discounts::class)->deleteDiscountById($deleteId);
        }

        return $this->asSuccess();
    }

    public function clearDiscountUses(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        abort_unless(currentUserElement()?->can('commerce-editDiscounts'), 403);

        $id = $request->input('id');
        $type = $request->input('type', 'total');
        $types = [self::DISCOUNT_COUNTER_TYPE_TOTAL, self::DISCOUNT_COUNTER_TYPE_CUSTOMER, self::DISCOUNT_COUNTER_TYPE_EMAIL];

        if (!in_array($type, $types, true)) {
            return $this->asFailure(t('Type not in allowed options.', category: 'commerce'));
        }

        $discount = app(Discounts::class)->getDiscountById((int) $id);
        if ($discount) {
            $this->requireStoreAccess($discount->storeId);
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
                $this->requireStoreAccess($discount->storeId);
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

    public function generateCoupons(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $count = (int) $request->input('count', 0);
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
