<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\StoreManagement;

use CraftCms\Cms\Cp\Html\ContentHtml;
use CraftCms\Cms\Edition;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Facades\UserGroups;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Translation\Formatter;
use CraftCms\Cms\Translation\Locale;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\DateTime as DateTimeControl;
use CraftCms\Cms\Ui\Controls\ElementSelect;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Number;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Enums\ChoicePresentation;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Action;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Group;
use CraftCms\Cms\Ui\Nodes\HiddenField;
use CraftCms\Cms\Ui\Nodes\MarkdownContent;
use CraftCms\Cms\Ui\Nodes\Table;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Payment\PaymentCurrencies;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Products;
use CraftCms\Commerce\Promotion\Data\Sale;
use CraftCms\Commerce\Promotion\Models\Sale as SaleRecord;
use CraftCms\Commerce\Promotion\Sales;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use CraftCms\Commerce\Purchasable\Purchasables;
use CraftCms\Commerce\Store\Data\Store;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

readonly class SalesController extends BaseStoreManagementController
{
    /** Sale effects that set the price outright, so earlier matching sales can't apply. */
    private const array SET_PRICE_EFFECTS = [SaleRecord::APPLY_TO_PERCENT, SaleRecord::APPLY_TO_FLAT];

    private function guard(): void
    {
        abort_unless(app(Sales::class)->canUseSales(), 403, 'Unable to use sales while using multi store or pricing rules.');
    }

    protected function getSectionCrumb(Store $store): array
    {
        return ['label' => t('Sales', category: 'commerce'), 'href' => $store->getStoreSettingsUrl('sales')];
    }

    public function index(?string $storeHandle = null): Response|CpScreenResponse
    {
        $this->guard();

        $store = $this->resolveStore($storeHandle);
        $sales = app(Sales::class)->getAllSales();

        if (empty($sales)) {
            return redirect($store->getStoreSettingsUrl('pricing-rules'));
        }

        $dateFormat = I18N::getFormattingLocale()->getDateTimeFormat('short', Locale::FORMAT_PHP);

        $table = Table::make('sales')
            ->columns([
                ['key' => 'name', 'label' => t('Name', category: 'commerce')],
                ['key' => 'duration', 'label' => t('Duration', category: 'commerce')],
                ['key' => 'effect', 'label' => t('Effect', category: 'commerce')],
                ['key' => 'ignore', 'label' => t('Overrides previous?', category: 'commerce')],
                ['key' => 'stop', 'label' => t('Stops subsequent?', category: 'commerce')],
            ])
            ->rows(array_map(fn(Sale $sale) => $this->buildSaleRow($sale, $store, $dateFormat), array_values($sales)))
            ->emptyMessage(t('No sales exist yet.', category: 'commerce'))
            ->statusFilter()
            ->searchable()
            ->reorderable(
                action([self::class, 'reorder']),
                t('Sale reordered.', category: 'commerce'),
                t('Couldn’t reorder sale.', category: 'commerce'),
            )
            ->when(
                currentUserElement()?->can('commerce-createSales'),
                fn(Table $table) => $table->createAction(t('New sale', category: 'commerce'), $store->getStoreSettingsUrl('sales/new')),
            )
            ->when(
                currentUserElement()?->can('commerce-deleteSales'),
                fn(Table $table) => $table
                    ->deletable(action([self::class, 'destroy']))
                    ->bulkDeletable(action([self::class, 'destroy'])),
            )
            ->when(
                currentUserElement()?->can('commerce-editSales'),
                fn(Table $table) => $table->statusActions($this->statusActions(action([self::class, 'updateStatus']))),
            );

        return $this->cpScreenResponse($store)
            ->title(t('Sales', category: 'commerce'))
            ->crumbs($this->crumbs($store))
            ->ui(Ui::make([$table]), props: [
                'contentMaxWidth' => false,
            ]);
    }

    /** @return array<string, mixed> */
    private function buildSaleRow(Sale $sale, Store $store, string $dateFormat): array
    {
        $duration = (!$sale->dateFrom && !$sale->dateTo)
            ? '∞'
            : ($sale->dateFrom?->format($dateFormat) ?? '∞') . ' - ' . ($sale->dateTo?->format($dateFormat) ?? '∞');

        $effect = match ($sale->apply) {
            SaleRecord::APPLY_BY_PERCENT => $sale->getApplyAmountAsPercent() . ' ' . t('(off original price)', category: 'commerce'),
            SaleRecord::APPLY_TO_PERCENT => $sale->getApplyAmountAsPercent() . ' ' . t('(of original price)', category: 'commerce'),
            SaleRecord::APPLY_BY_FLAT => $this->formatFlatAmount($sale) . ' ' . t('(off original price)', category: 'commerce'),
            default => $this->formatFlatAmount($sale) . ' ' . t('(new price)', category: 'commerce'),
        };

        return [
            'id' => $sale->id,
            '_status' => $sale->enabled,
            'name' => ['label' => t((string)$sale->name, category: 'site'), 'url' => $store->getStoreSettingsUrl('sales/' . $sale->id)],
            'duration' => $duration,
            'effect' => $effect,
            'ignore' => $sale->ignorePrevious ? ['icon' => 'check', 'label' => t('Yes')] : '',
            'stop' => $sale->stopProcessing ? ['icon' => 'check', 'label' => t('Yes')] : '',
        ];
    }

    private function formatFlatAmount(Sale $sale): string
    {
        return Currency::formatAsCurrency($sale->getApplyAmountAsFlat(), app(PaymentCurrencies::class)->getPrimaryPaymentCurrency(), true);
    }

    public function create(?string $storeHandle = null): CpScreenResponse
    {
        return $this->editScreen($storeHandle);
    }

    public function edit(?string $storeHandle, int $id): CpScreenResponse
    {
        return $this->editScreen($storeHandle, $id);
    }

    private function editScreen(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        $this->guard();

        abort_unless(currentUserElement()?->can($id === null ? 'commerce-createSales' : 'commerce-editSales'), 403);

        $store = $this->resolveStore($storeHandle);
        $sale = $id ? app(Sales::class)->getSaleById($id) : null;
        abort_if($id !== null && $sale === null, 404);
        $sale ??= $this->newSale();

        $title = $sale->id ? (string)$sale->name : t('Create a new sale', category: 'commerce');

        $formatter = app(Formatter::class);
        $metadataHtml = $sale->id ? app(ContentHtml::class)->metadataHtml([
            t('Created at') => $formatter->asDateTime($sale->dateCreated, 'short'),
            t('Updated at') => $formatter->asDateTime($sale->dateUpdated, 'short'),
        ]) : null;

        $values = $this->initialValues($sale, request());

        $ui = $this->uiResolver->resolve(
            $this->buildUi($values),
            new UiContext(values: $values, refreshable: true),
        );

        $sidebarUi = $this->uiResolver->resolve(
            Ui::make([
                Field::make(t('Enable this sale', category: 'commerce'), Lightswitch::make('enabled'))
                    ->instructions(t('Whether this sale should be available for use, regardless of other conditions.', category: 'commerce')),
            ]),
            new UiContext(values: $values),
        );

        return $this->cpScreenResponse($store, subnav: false)
            ->title($title)
            ->crumbs($this->crumbs($store, ...($sale->id ? [['label' => $title]] : [])))
            ->action('commerce/sales/save')
            ->redirectUrl($store->getStoreSettingsUrl('sales'))
            ->ui($ui, props: [
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'store']),
                ],
                'refreshUrl' => action([self::class, 'renderUi']),
                'metadataHtml' => $metadataHtml,
                'sidebarUi' => $sidebarUi,
            ]);
    }

    /**
     * Re-resolves the {@see editScreen()} Ui tree for the values currently in progress on the
     * client, so the matching, customer and effect fields can show or hide what depends on them.
     */
    public function renderUi(Request $request): JsonResponse
    {
        $this->guard();

        $request->validate([
            'values' => ['required', 'array'],
            'values.id' => ['nullable', 'integer'],
            'scope' => ['present', 'array', 'size:0'],
        ]);

        $values = $request->input('values');
        $saleId = $values['id'] ?? null;
        $sale = $saleId ? app(Sales::class)->getSaleById((int)$saleId) : null;
        abort_if($saleId !== null && $sale === null, 404);

        $values = array_replace($this->initialValues($sale ?? $this->newSale()), $values);

        $ui = $this->uiResolver->resolve(
            $this->buildUi($values),
            new UiContext(values: $values, refreshable: true),
        );

        return new JsonResponse(['ui' => $ui]);
    }

    private function newSale(): Sale
    {
        return new Sale([
            'allEntries' => true,
            'allPurchasables' => true,
            'allGroups' => true,
        ]);
    }

    /**
     * A new sale can be prefilled with `purchasableIds` and `entryIds` query params (pipe-separated);
     * a product ID in `purchasableIds` stands for all of its variants.
     *
     * @return array<string, mixed>
     */
    private function initialValues(Sale $sale, ?Request $request = null): array
    {
        $purchasableIds = $sale->getPurchasableIds();
        $entryIds = $sale->getEntryIds();
        $restrictPurchasables = !$sale->allPurchasables;
        $restrictRelatedElements = !$sale->allEntries;

        if (!$sale->id && $request?->query('purchasableIds')) {
            $purchasableIds = [];
            foreach (explode('|', (string)$request->query('purchasableIds')) as $purchasableId) {
                $element = Elements::getElementById((int)$purchasableId);
                if ($element instanceof Product) {
                    array_push($purchasableIds, ...$element->getVariants(true)->map(fn($variant) => (int)$variant->id)->all());
                } else {
                    $purchasableIds[] = (int)$purchasableId;
                }
            }
            $restrictPurchasables = true;
        }

        if (!$sale->id && $request?->query('entryIds')) {
            $entryIds = array_map('intval', explode('|', (string)$request->query('entryIds')));
            $restrictRelatedElements = true;
        }

        $purchasablesByType = [];
        foreach ($purchasableIds as $purchasableId) {
            $purchasable = Elements::getElementById((int)$purchasableId);
            if ($purchasable instanceof PurchasableInterface) {
                $purchasablesByType[$purchasable::class][] = (int)$purchasableId;
            }
        }

        $isPercent = in_array($sale->apply, [SaleRecord::APPLY_BY_PERCENT, SaleRecord::APPLY_TO_PERCENT], true);

        return [
            'id' => $sale->id,
            'sortOrder' => $sale->sortOrder,
            'name' => $sale->name,
            'description' => $sale->description,
            'enabled' => $sale->enabled,

            // Worded as "only match certain purchasables/related entries…", the opposite of the
            // `allPurchasables`/`allEntries` properties they set — inverted here and in store().
            'restrictPurchasables' => $restrictPurchasables,
            'purchasables' => $purchasablesByType,
            'restrictRelatedElements' => $restrictRelatedElements,
            'relatedElements' => ['entries' => array_values(array_filter(
                $entryIds,
                fn($entryId) => Elements::getElementById((int)$entryId, siteId: '*') instanceof Entry,
            ))],
            'entryRelationshipType' => $sale->entryRelationshipType,

            'dateFrom' => $this->dateTimeControlValue($sale->dateFrom),
            'dateTo' => $this->dateTimeControlValue($sale->dateTo),
            'allGroups' => $sale->allGroups,
            'groups' => array_map('intval', $sale->getUserGroupIds()),

            // Stored negative (an amount taken off the price) and, for percentages, as a fraction;
            // shown as a plain positive amount or percentage, and flipped back in store().
            'apply' => $sale->apply,
            'applyAmount' => $sale->applyAmount === null ? null : round(-$sale->applyAmount * ($isPercent ? 100 : 1), 6),
            'ignorePrevious' => $sale->ignorePrevious || in_array($sale->apply, self::SET_PRICE_EFFECTS, true),
            'stopProcessing' => $sale->stopProcessing,
        ];
    }

    /** @param array<string, mixed> $values */
    private function buildUi(array $values): Ui
    {
        $restrictPurchasables = (bool)($values['restrictPurchasables'] ?? false);
        $restrictRelatedElements = (bool)($values['restrictRelatedElements'] ?? false);
        $setsPrice = in_array($values['apply'] ?? null, self::SET_PRICE_EFFECTS, true);
        $isPercent = in_array($values['apply'] ?? null, [SaleRecord::APPLY_BY_PERCENT, SaleRecord::APPLY_TO_PERCENT], true);
        $currency = app(PaymentCurrencies::class)->getPrimaryPaymentCurrencyIso();

        $matchingItemsFields = [
            MarkdownContent::make('matching-items-intro', t('Limit which purchasables this sale applies to.', category: 'commerce'))
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

        if (Entry::find()->exists()) {
            $matchingItemsFields[] = Field::make(t('Only match purchasables related to…', category: 'commerce'), Lightswitch::make('restrictRelatedElements')->reactive());

            if ($restrictRelatedElements) {
                $matchingItemsFields[] = Field::make(t('Entries', category: 'app'), ElementSelect::make(['relatedElements', 'entries'])
                    ->elementType(Entry::class)
                    ->showSiteMenu());

                $matchingItemsFields[] = Group::make('entry-relationship-type-advanced', [
                    Field::make(t('Relationship Type', category: 'commerce'), Choice::make('entryRelationshipType')
                        ->presentation(ChoicePresentation::Radios)
                        ->options([
                            ['label' => t('The purchasable defines the relationship', category: 'commerce'), 'value' => SaleRecord::ENTRY_RELATIONSHIP_TYPE_SOURCE],
                            ['label' => t('The purchasable is related by another element', category: 'commerce'), 'value' => SaleRecord::ENTRY_RELATIONSHIP_TYPE_TARGET],
                            ['label' => t('Either way', category: 'commerce'), 'value' => SaleRecord::ENTRY_RELATIONSHIP_TYPE_BOTH],
                        ])),
                ])
                    ->label(t('Advanced', category: 'commerce'))
                    ->collapsible()
                    ->expanded(($values['entryRelationshipType'] ?? null) !== SaleRecord::ENTRY_RELATIONSHIP_TYPE_BOTH);
            }
        } else {
            // With no entries to relate to, keep it off rather than showing a toggle with nothing behind it.
            $matchingItemsFields[] = HiddenField::make('restrictRelatedElements');
        }

        $conditionsFields = [
            Field::make(t('Start Date', category: 'commerce'), DateTimeControl::make('dateFrom')->showTime())
                ->instructions(t('Date from which the sale will be active. Leave blank for unlimited start date', category: 'commerce')),
            Field::make(t('End Date', category: 'commerce'), DateTimeControl::make('dateTo')->showTime())
                ->instructions(t('Date when the sale will be finished. Leave blank for unlimited end date', category: 'commerce')),
        ];

        $groups = Edition::get() === Edition::Pro ? UserGroups::getAllGroups() : collect();

        if ($groups->isNotEmpty()) {
            $conditionsFields[] = Field::make(t('All customers', category: 'commerce'), Lightswitch::make('allGroups')->reactive());

            if (!($values['allGroups'] ?? true)) {
                $conditionsFields[] = Field::make(t('User Groups', category: 'commerce'), Choice::make('groups')
                    ->multiple()
                    ->options($groups->map(fn($group) => ['label' => t($group->name, category: 'site'), 'value' => $group->id])->values()->all()))
                    ->instructions(t('Groups for which this sale will be applicable to.', category: 'commerce'));
            }
        }

        $amountSymbol = $isPercent
            ? I18N::getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT)
            : I18N::getLocale()->getCurrencySymbol($currency);

        $actionsFields = [
            Field::make(t('Effect', category: 'commerce'), Number::make('applyAmount')->size(8))
                ->instructions(t('Select how the sale will be applied to the purchasable(s).', category: 'commerce') . ' (' . $amountSymbol . ')')
                ->actions(Action::make(Choice::make('apply')
                    ->withoutPlaceholder()
                    ->reactive()
                    ->options([
                        ['label' => t('Reduce the price by a percentage of the original price', category: 'commerce'), 'value' => SaleRecord::APPLY_BY_PERCENT],
                        ['label' => t('Reduce the price by a fixed amount', ['currency' => $currency], category: 'commerce'), 'value' => SaleRecord::APPLY_BY_FLAT],
                        ['label' => t('Set the sale price to a percentage of the original price', category: 'commerce'), 'value' => SaleRecord::APPLY_TO_PERCENT],
                        ['label' => t('Set the sale price to a flat amount', ['currency' => $currency], category: 'commerce'), 'value' => SaleRecord::APPLY_TO_FLAT],
                    ]))),
            Field::make(t('Ignore previous matching sales if this sale matches.', category: 'commerce'), Lightswitch::make('ignorePrevious')
                ->mode($setsPrice ? ControlMode::Disabled : ControlMode::Editable)),
            Field::make(t('Do not apply subsequent matching sales beyond applying this sale.', category: 'commerce'), Lightswitch::make('stopProcessing')),
        ];

        return Ui::make([
            HiddenField::make('id'),
            HiddenField::make('sortOrder'),
        ])
            ->addTab(t('Sale', category: 'commerce'), [
                Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())
                    ->instructions(t('What this sale will be called in the control panel.', category: 'commerce'))
                    ->required(),
                Field::make(t('Description', category: 'commerce'), Text::make('description'))
                    ->instructions(t('Sale description.', category: 'commerce')),
            ])
            ->addTab(t('Matching Items', category: 'commerce'), $matchingItemsFields)
            ->addTab(t('Conditions', category: 'commerce'), $conditionsFields)
            ->addTab(t('Actions', category: 'commerce'), $actionsFields);
    }

    public function store(Request $request): Response
    {
        $this->guard();

        $sale = new Sale();
        $sale->id = $request->input('id') ? (int)$request->input('id') : null;

        abort_unless(currentUserElement()?->can($sale->id === null ? 'commerce-createSales' : 'commerce-editSales'), 403);

        $sale->name = $request->input('name');
        $sale->description = $request->input('description');
        $sale->apply = $request->input('apply') ?: SaleRecord::APPLY_BY_PERCENT;
        $sale->enabled = (bool)$request->input('enabled');
        $sale->sortOrder = $request->input('sortOrder') !== null ? (int)$request->input('sortOrder') : null;
        $sale->stopProcessing = (bool)$request->input('stopProcessing');
        $sale->ignorePrevious = (bool)$request->input('ignorePrevious') || in_array($sale->apply, self::SET_PRICE_EFFECTS, true);
        $sale->entryRelationshipType = $request->input('entryRelationshipType') ?: $sale->entryRelationshipType;

        foreach (['dateFrom', 'dateTo'] as $field) {
            if (($date = $request->input($field)) && $dateValue = DateTimeHelper::toDateTime($date)) {
                $sale->$field = $dateValue instanceof DateTime ? $dateValue : DateTime::createFromInterface($dateValue);
            }
        }

        $applyAmount = (float)$request->input('applyAmount');
        $sale->applyAmount = in_array($sale->apply, [SaleRecord::APPLY_BY_PERCENT, SaleRecord::APPLY_TO_PERCENT], true)
            ? -$applyAmount / 100
            : -$applyAmount;

        $sale->allPurchasables = !$request->boolean('restrictPurchasables');
        $sale->setPurchasableIds($sale->allPurchasables ? [] : array_values(array_unique(Arr::flatten((array)$request->input('purchasables', [])))));

        $sale->allEntries = !$request->boolean('restrictRelatedElements');
        $sale->setEntryIds($sale->allEntries ? [] : array_values(array_unique((array)($request->input('relatedElements')['entries'] ?? []))));

        $sale->allGroups = $request->boolean('allGroups', true);
        $sale->setUserGroupIds($sale->allGroups ? [] : array_values((array)$request->input('groups', [])));

        if (app(Sales::class)->saveSale($sale)) {
            return $this->asModelSuccess($sale, t('Sale saved.', category: 'commerce'), 'sale');
        }

        return $this->asModelFailure($sale, t('Couldn’t save sale.', category: 'commerce'), 'sale');
    }

    public function reorder(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        abort_unless($request->input('ids'), 400, 'Missing ids');

        $ids = $request->input('ids');
        $ids = is_string($ids) ? Json::decode($ids) : $ids;

        if (!app(Sales::class)->reorderSales(array_values($ids))) {
            return $this->asFailure(t('Couldn’t reorder sales.', category: 'commerce'));
        }

        return $this->asSuccess();
    }

    public function destroy(Request $request): Response
    {
        abort_unless(currentUserElement()?->can('commerce-deleteSales'), 403);

        $id = $request->input('id');
        $ids = $request->input('ids');

        abort_if((!$id && empty($ids)) || ($id && !empty($ids)), 400, 'id or ids must be specified.');

        foreach ($id ? [$id] : $ids as $deleteId) {
            app(Sales::class)->deleteSaleById((int)$deleteId);
        }

        if ($request->expectsJson()) {
            return $this->asSuccess();
        }

        return $this->asSuccess(t('Sales deleted.', category: 'commerce'), redirect: url()->previous());
    }

    public function list(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        $sales = app(Sales::class)->getAllSales();

        return response()->json(array_values($sales));
    }

    public function byProduct(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        $id = $request->input('id');

        if (!$id) {
            return $this->asFailure(t('Product ID is required.', category: 'commerce'));
        }

        $product = app(Products::class)->getProductById($id);

        if (!$product) {
            return $this->asFailure(t('No product available.', category: 'commerce'));
        }

        $sales = [];
        foreach ($product->getVariants(true) as $variant) {
            $variantSales = app(Sales::class)->getSalesRelatedToPurchasable($variant);
            foreach ($variantSales as $sale) {
                if (!Arr::contains($sales, 'id', $sale->id)) {
                    $saleArray = $sale->toArray();
                    $saleArray['cpEditUrl'] = $sale->getCpEditUrl();
                    $sales[] = $saleArray;
                }
            }
        }

        return $this->asSuccess(data: ['sales' => $sales]);
    }

    public function byPurchasable(Request $request): Response
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

        $sales = [];
        $purchasableSales = app(Sales::class)->getSalesRelatedToPurchasable($purchasable);
        foreach ($purchasableSales as $sale) {
            if (!Arr::contains($sales, 'id', $sale->id)) {
                $saleArray = $sale->toArray();
                $saleArray['cpEditUrl'] = $sale->getCpEditUrl();
                $sales[] = $saleArray;
            }
        }

        return $this->asSuccess(data: ['sales' => $sales]);
    }

    public function addPurchasable(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        $ids = $request->input('ids', []);
        $saleId = $request->input('saleId');

        if (empty($ids) || !$saleId) {
            return $this->asFailure(t('Purchasable ID and Sale ID are required.', category: 'commerce'));
        }

        $purchasables = [];
        foreach ($ids as $id) {
            $purchasables[] = app(Purchasables::class)->getPurchasableById($id);
        }

        $sale = app(Sales::class)->getSaleById($saleId);

        if (empty($purchasables) || count($purchasables) != count($ids) || !$sale) {
            return $this->asFailure(t('Unable to retrieve Sale and Purchasable.', category: 'commerce'));
        }

        $salePurchasableIds = $sale->getPurchasableIds();

        array_push($salePurchasableIds, ...$ids);
        if (!empty($salePurchasableIds)) {
            $sale->allPurchasables = false;
        }
        $sale->setPurchasableIds(array_unique($salePurchasableIds));

        if (!app(Sales::class)->saveSale($sale)) {
            return $this->asFailure(t('Couldn’t save sale.', category: 'commerce'));
        }

        return $this->asSuccess();
    }

    public function updateStatus(Request $request): Response
    {
        abort_unless(currentUserElement()?->can('commerce-editSales'), 403);

        $ids = $request->input('ids');
        $status = $request->input('status');

        abort_if(empty($ids), 400, 'Missing ids');

        DB::transaction(function() use ($ids, $status) {
            $sales = SaleRecord::whereIn('id', $ids)->get();

            foreach ($sales as $sale) {
                $sale->enabled = ($status == 'enabled');
                $sale->save();
            }
        });

        return $this->asSuccess(t('Sales updated.', category: 'commerce'));
    }
}
