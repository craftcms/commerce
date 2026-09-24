<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\StoreManagement;

use CraftCms\Cms\Cp\Html\ContentHtml;
use CraftCms\Cms\Cp\Html\ElementHtml;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\Combobox;
use CraftCms\Cms\Form\Controls\Combobox\CreateOption as ComboboxCreateOption;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Controls\Number;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Form\Nodes\Table;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Translation\Formatter;
use CraftCms\Cms\Translation\Locale;
use CraftCms\Commerce\Helpers\Localization;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tax\Data\TaxAddressZone;
use CraftCms\Commerce\Tax\Data\TaxCategory;
use CraftCms\Commerce\Tax\Data\TaxRate;
use CraftCms\Commerce\Tax\Models\TaxRate as TaxRateRecord;
use CraftCms\Commerce\Tax\TaxCategories;
use CraftCms\Commerce\Tax\Taxes;
use CraftCms\Commerce\Tax\TaxRates;
use CraftCms\Commerce\Tax\TaxZones;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\t;

readonly class TaxRatesController extends BaseStoreManagementController
{
    protected function getSectionCrumb(Store $store): array
    {
        return ['label' => t('Tax Rates', category: 'commerce'), 'href' => $store->getStoreSettingsUrl('taxrates')];
    }

    public function index(?string $storeHandle = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        $taxRates = app(TaxRates::class)->getAllTaxRates($store->id);

        $rows = $taxRates
            ->map(fn(TaxRate $taxRate) => [
                'id' => $taxRate->id,
                // Restores the legacy VueAdminTable screen's own automatic status dot (driven
                // there by an implicit `status` row key, not a real column) — the explicit
                // "Enabled?" Yes/No column below was this same information, added as a
                // stand-in for the dot when this screen first converted; dropped now that the
                // dot itself is back, matching the legacy column set exactly again.
                '_status' => $taxRate->enabled,
                'name' => ['html' => Html::a(Html::encode(t($taxRate->name, category: 'site')), $taxRate->getCpEditUrl(), ['class' => 'cell-bold'])],
                'rate' => $taxRate->getRateAsPercent(),
                'included' => $taxRate->include ? ['icon' => 'check', 'label' => t('Yes')] : '',
                'removeIncluded' => $taxRate->removeIncluded ? ['icon' => 'check', 'label' => t('Yes')] : '',
                'zone' => $taxRate->getIsEverywhere() ? t('Everywhere', category: 'commerce') : t($taxRate->getTaxZone()->name, category: 'site'),
                'category' => $taxRate->getTaxCategory() ? ['html' => app(ElementHtml::class)->chipHtml($taxRate->getTaxCategory())] : '',
            ])
            ->values()
            ->all();

        $nodes = [
            Table::make('tax-rates')
                ->columns([
                    ['key' => 'name', 'label' => t('Name')],
                    ['key' => 'rate', 'label' => t('Rate', category: 'commerce')],
                    ['key' => 'included', 'label' => t('Include in price?', category: 'commerce')],
                    ['key' => 'removeIncluded', 'label' => t('Remove from price?', category: 'commerce')],
                    ['key' => 'zone', 'label' => t('Tax Zone', category: 'commerce')],
                    ['key' => 'category', 'label' => t('Tax Category', category: 'commerce')],
                ])
                ->rows($rows)
                ->emptyMessage(t('No tax rates exist yet.', category: 'commerce'))
                ->statusFilter()
                ->when(
                    app(Taxes::class)->createTaxRates(),
                    fn(Table $table) => $table->createAction(t('New tax rate', category: 'commerce'), $store->getStoreSettingsUrl('taxrates/new')),
                )
                ->when($this->canDeleteTaxRates(), fn(Table $table) => $table->deletable(action([self::class, 'delete']))),
        ];

        $title = t('Tax Rates', category: 'commerce');
        $engineButtonsHtml = app(Taxes::class)->taxRateActionHtml();

        return $this->cpScreenResponse($store)
            ->title($title)
            ->crumbs($this->crumbs($store))
            ->when($engineButtonsHtml !== '', fn(CpScreenResponse $screen) => $screen->additionalButtonsHtml($engineButtonsHtml))
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve(Form::make($nodes), new FormContext()),
                'contentMaxWidth' => false,
            ]);
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        abort_unless(app(Taxes::class)->viewTaxRates(), 403, 'Tax engine does not permit you to perform this action');

        $store = $this->resolveStore($storeHandle);

        if ($id) {
            $taxRate = app(TaxRates::class)->getTaxRateById($id, $store->id);
            abort_if($taxRate === null, 404);
        } else {
            $taxRate = new TaxRate(['storeId' => $store->id]);
        }

        $title = $taxRate->id ? $taxRate->name : t('Create a new tax rate', category: 'commerce');

        $formatter = app(Formatter::class);
        $metadataHtml = $taxRate->id ? app(ContentHtml::class)->metadataHtml([
            t('Created at') => $formatter->asDateTime($taxRate->dateCreated, 'short'),
            t('Updated at') => $formatter->asDateTime($taxRate->dateUpdated, 'short'),
        ]) : null;

        $values = $this->initialValues($taxRate, $store);

        $form = $this->formResolver->resolve(
            $this->buildForm($taxRate, $values, $store),
            new FormContext(values: $values, refreshable: true),
        );

        return $this->cpScreenResponse($store, subnav: false)
            ->title($title)
            ->crumbs($this->crumbs($store, ...($taxRate->id ? [['label' => $title]] : [])))
            ->action('commerce/tax-rates/save')
            ->redirectUrl($store->getStoreSettingsUrl('taxrates'))
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
     * client, so switching the taxable subject, toggling "Included in price?", or checking a
     * tax ID validator can reveal or hide the fields that depend on them without a full page
     * reload.
     */
    public function renderForm(Request $request): JsonResponse
    {
        $request->validate([
            'values' => ['required', 'array'],
            'values.storeId' => ['required', 'integer'],
            'values.taxRateId' => ['nullable', 'integer'],
            'scope' => ['present', 'array', 'size:0'],
        ]);

        $values = $request->input('values');
        $store = app(Stores::class)->getStoreById((int)$values['storeId']);
        abort_if($store === null, 404);

        $taxRateId = $values['taxRateId'] ?? null;
        if ($taxRateId) {
            $taxRate = app(TaxRates::class)->getTaxRateById((int)$taxRateId, $store->id);
            abort_if($taxRate === null, 404);
        } else {
            $taxRate = new TaxRate(['storeId' => $store->id]);
        }

        $values = array_replace($this->initialValues($taxRate, $store), $values);

        $form = $this->formResolver->resolve(
            $this->buildForm($taxRate, $values, $store),
            new FormContext(values: $values, refreshable: true),
        );

        return new JsonResponse(['form' => $form]);
    }

    /** @return array<string, mixed> */
    private function initialValues(TaxRate $taxRate, Store $store): array
    {
        return [
            'storeId' => $store->id,
            'taxRateId' => $taxRate->id,
            'name' => $taxRate->name,
            'code' => $taxRate->code,
            'taxable' => $taxRate->taxable,
            'taxZoneId' => $taxRate->taxZoneId,
            // `taxCategoryId` is required, so a Choice control that never fires a real
            // change event (e.g. the sole option in a brand-new tax rate's dropdown, never
            // explicitly picked) would otherwise post null — a native `<select>` shows its
            // first option regardless of whether anything actually set it. Default to the
            // store's actual default category rather than relying on that visual illusion.
            'taxCategoryId' => $taxRate->taxCategoryId ?? app(TaxCategories::class)->getDefaultTaxCategory()->id,
            'taxIdValidators' => $taxRate->taxIdValidators,
            'rate' => round($taxRate->rate * 100, 6),
            'include' => $taxRate->include,
            'removeIncluded' => $taxRate->removeIncluded,
            'removeVatIncluded' => $taxRate->removeVatIncluded,
            'enabled' => $taxRate->enabled,
        ];
    }

    /** @param array<string, mixed> $values */
    private function buildForm(TaxRate $taxRate, array $values, Store $store): Form
    {
        $showTaxCategory = !in_array($values['taxable'] ?? TaxRateRecord::TAXABLE_PRICE, TaxRateRecord::ORDER_TAXABALES, true);
        $include = (bool) ($values['include'] ?? false);
        $hasTaxIdValidators = !empty($values['taxIdValidators'] ?? []);

        $taxableOptions = [
            ['label' => t('Unit price (minus discounts)', category: 'commerce'), 'value' => TaxRateRecord::TAXABLE_PURCHASABLE],
            ['label' => t('Line item price (minus discounts)', category: 'commerce'), 'value' => TaxRateRecord::TAXABLE_PRICE],
            ['label' => t('Line item shipping cost', category: 'commerce'), 'value' => TaxRateRecord::TAXABLE_SHIPPING],
            ['label' => t('Both (Line item price + Line item shipping costs)', category: 'commerce'), 'value' => TaxRateRecord::TAXABLE_PRICE_SHIPPING],
            ['label' => t('Order total shipping cost', category: 'commerce'), 'value' => TaxRateRecord::TAXABLE_ORDER_TOTAL_SHIPPING],
            ['label' => t('Order total taxable price (Line item subtotal + Total discounts + Total shipping)', category: 'commerce'), 'value' => TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE],
        ];

        $taxZoneOptions = array_values(array_map(
            fn(TaxAddressZone $zone) => ['label' => $zone->name, 'value' => $zone->id],
            app(TaxZones::class)->getAllTaxZones($store->id)->all(),
        ));
        $canCreateTaxZones = app(Taxes::class)->createTaxZones();
        if ($canCreateTaxZones) {
            $taxZoneOptions[] = new ComboboxCreateOption(
                t('Create a tax zone', category: 'commerce'),
                action([TaxZonesController::class, 'edit'], ['storeHandle' => $store->handle]),
                'taxZone',
            );
        }

        $taxCategoryOptions = array_values(array_map(
            fn(TaxCategory $category) => ['label' => $category->name, 'value' => $category->id],
            app(TaxCategories::class)->getAllTaxCategories(),
        ));
        $canCreateTaxCategories = app(Taxes::class)->createTaxCategories();
        if ($canCreateTaxCategories) {
            $taxCategoryOptions[] = new ComboboxCreateOption(
                t('Create a new tax category', category: 'commerce'),
                action([TaxCategoriesController::class, 'edit'], ['storeHandle' => $store->handle]),
                'taxCategory',
            );
        }

        $taxIdValidatorOptions = app(Taxes::class)->getEnabledTaxIdValidators()
            ->map(fn($validator) => ['label' => $validator::displayName(), 'value' => $validator::class])
            ->values()
            ->all();

        $formNodes = [
            HiddenField::make('storeId'),
        ];

        if ($taxRate->id) {
            $formNodes[] = HiddenField::make('taxRateId');
        }

        $formNodes[] = Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())
            ->instructions(t('Enter a human-friendly name for this tax rate to be used in the control panel.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Code', category: 'commerce'), Text::make('code')->monospace())
            ->instructions(t('Can be used as an internal reference.', category: 'commerce'));
        $formNodes[] = Field::make(t('Taxable Subject', category: 'commerce'), Choice::make('taxable')->options($taxableOptions)->reactive())
            ->instructions(t('Select what this rate should be applied to.', category: 'commerce'));
        $taxZoneControl = Combobox::make('taxZoneId')
            ->options($taxZoneOptions)
            ->placeholder(t('Everywhere', category: 'commerce'))
            ->clearable()
            ->requireOptionMatch()
            ->showAllOnEmpty();
        $formNodes[] = Field::make(t('Tax Zone', category: 'commerce'), $taxZoneControl)
            ->instructions(t('Select a tax zone. If empty, this rate will match anywhere.', category: 'commerce'));

        $formNodes[] = Field::make(t('Disqualify with valid business tax ID?', category: 'commerce'), Choice::make('taxIdValidators')->multiple()->options($taxIdValidatorOptions)->reactive())
            ->instructions(t('Do not apply this rate if the order address has any of the selected valid business tax IDs.', category: 'commerce'));

        if ($showTaxCategory) {
            $taxCategoryControl = Combobox::make('taxCategoryId')
                ->options($taxCategoryOptions)
                ->requireOptionMatch()
                ->showAllOnEmpty();
            $formNodes[] = Field::make(t('Tax Category', category: 'commerce'), $taxCategoryControl)
                ->instructions(t('Select a tax category.', category: 'commerce'))
                ->required();
        }

        $formNodes[] = Field::make(t('Rate', category: 'commerce'), Number::make('rate')->step('any')->suffix($this->percentSymbol()))
            ->instructions(t('Enter a percentage like {ex1} or {ex2}.', ['ex1' => '`5`', 'ex2' => '`10.5`'], category: 'commerce'))
            ->required();

        $formNodes[] = Field::make(t('Included in price?', category: 'commerce'), Lightswitch::make('include')->reactive())
            ->instructions(t('Enable if this rate should be built into the taxable subject price instead of adding a cost to the order.', category: 'commerce'));

        if ($include) {
            $formNodes[] = Field::make(t('Adjust price when included rate is disqualified?', category: 'commerce'), Lightswitch::make('removeIncluded'))
                ->instructions(t('If enabled and this rate does not match the order, the rate amount will be removed from the subject price in the cart.', category: 'commerce'));

            if ($hasTaxIdValidators) {
                $formNodes[] = Field::make(t('Remove the included tax when a valid organization tax ID is present?', category: 'commerce'), Lightswitch::make('removeVatIncluded'))
                    ->instructions(t('If enabled and this rate does not match the order, the rate amount will be removed from the subject price in the cart.', category: 'commerce'));
            }
        }

        $formNodes[] = Field::make(t('Enable this tax rate', category: 'commerce'), Lightswitch::make('enabled'));

        return Form::make($formNodes);
    }

    private function percentSymbol(): string
    {
        return I18N::getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);
    }

    private function canDeleteTaxRates(): bool
    {
        return app(Taxes::class)->deleteTaxRates();
    }

    public function save(Request $request): Response
    {
        abort_unless(app(Taxes::class)->editTaxRates(), 403, 'Tax engine does not permit you to perform this action');

        $taxRate = new TaxRate();

        $taxRate->id = $request->input('taxRateId') ? (int)$request->input('taxRateId') : null;
        $taxRate->storeId = $request->input('storeId') ? (int)$request->input('storeId') : null;
        $this->requireStoreAccess($taxRate->storeId);
        $taxRate->name = $request->input('name');
        $taxRate->code = $request->input('code');
        $taxRate->include = (bool)$request->input('include');
        $taxRate->removeIncluded = (bool)$request->input('removeIncluded');
        $taxRate->removeVatIncluded = (bool)$request->input('removeVatIncluded');
        $taxRate->taxable = $request->input('taxable');
        $taxRate->taxCategoryId = (int)$request->input('taxCategoryId') ?: null;
        $taxRate->taxZoneId = (int)$request->input('taxZoneId') ?: null;
        $taxRate->rate = Localization::normalizePercentage($request->input('rate'));
        $taxRate->enabled = (bool)$request->input('enabled');
        $taxRate->taxIdValidators = array_values($request->input('taxIdValidators', []) ?: []);

        if (!app(TaxRates::class)->saveTaxRate($taxRate)) {
            return $this->asModelFailure($taxRate, t('Couldn’t save tax rate.', category: 'commerce'), 'taxRate');
        }

        return $this->asModelSuccess($taxRate, t('Tax rate saved.', category: 'commerce'), 'taxRate');
    }

    public function delete(Request $request): Response
    {
        abort_unless(app(Taxes::class)->deleteTaxRates(), 403, 'Tax engine does not permit you to perform this action');
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        abort_if(!$id, 400, 'Missing tax rate id');

        $taxRate = app(TaxRates::class)->getTaxRateById((int)$id);
        if ($taxRate) {
            $this->requireStoreAccess($taxRate->storeId);
        }

        app(TaxRates::class)->deleteTaxRateById((int)$id);
        return $this->asSuccess();
    }

    public function updateStatus(Request $request): Response
    {
        $ids = $request->input('ids');
        $status = $request->input('status');

        abort_if(empty($ids), 400, 'Missing ids');

        DB::transaction(function() use ($ids, $status) {
            $taxRates = TaxRateRecord::whereIn('id', $ids)->get();

            foreach ($taxRates as $taxRate) {
                $this->requireStoreAccess($taxRate->storeId);
                $taxRate->enabled = ($status == 'enabled');
                $taxRate->save();
            }
        });

        return $this->asSuccess(t('Tax rates updated.', category: 'commerce'));
    }
}
