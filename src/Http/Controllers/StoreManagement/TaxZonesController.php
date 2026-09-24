<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\StoreManagement;

use CraftCms\Cms\Cp\Html\ContentHtml;
use CraftCms\Cms\Form\Controls\ConditionBuilder;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Form\Nodes\Table;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Translation\Formatter;
use CraftCms\Commerce\Address\Conditions\ZoneAddressCondition;
use CraftCms\Commerce\Formula\Formulas;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Tax\Data\TaxAddressZone;
use CraftCms\Commerce\Tax\TaxZones;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\t;

readonly class TaxZonesController extends BaseStoreManagementController
{
    protected function getSectionCrumb(Store $store): array
    {
        return ['label' => t('Tax Zones', category: 'commerce'), 'href' => $store->getStoreSettingsUrl('taxzones')];
    }

    public function index(?string $storeHandle = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        $rows = app(TaxZones::class)->getAllTaxZones($store->id)
            ->map(fn(TaxAddressZone $taxZone) => [
                'id' => $taxZone->id,
                'name' => ['html' => Html::a(Html::encode(t($taxZone->name, category: 'site')), $taxZone->getCpEditUrl(), ['class' => 'cell-bold'])],
                'description' => t($taxZone->description, category: 'site'),
                'default' => $taxZone->default ? ['icon' => 'check', 'label' => t('Yes')] : '',
            ])
            ->values()
            ->all();

        $nodes = [
            Table::make('tax-zones')
                ->columns([
                    ['key' => 'name', 'label' => t('Name')],
                    ['key' => 'description', 'label' => t('Description', category: 'commerce')],
                    ['key' => 'default', 'label' => t('Default Zone', category: 'commerce')],
                ])
                ->rows($rows)
                ->emptyMessage(t('No tax zones exist yet.', category: 'commerce'))
                ->createAction(t('New tax zone', category: 'commerce'), $store->getStoreSettingsUrl('taxzones/new'))
                ->createActionInPageHeader()
                ->deletable(action([self::class, 'delete'])),
        ];

        $title = t('Tax Zones', category: 'commerce');

        return $this->cpScreenResponse($store)
            ->title($title)
            ->crumbs($this->crumbs($store))
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve(Form::make($nodes), new FormContext()),
                'contentMaxWidth' => false,
            ]);
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        if ($id) {
            $taxZone = app(TaxZones::class)->getTaxZoneById($id, $store->id);
            abort_if($taxZone === null, 404);
        } else {
            $taxZone = new TaxAddressZone(['storeId' => $store->id]);
        }

        $title = $taxZone->id ? $taxZone->name : t('Create a tax zone', category: 'commerce');

        $defaultLabel = $store->getUseBillingAddressForTax()
            ? t('Default to this tax zone when no billing address is set', category: 'commerce')
            : t('Default to this tax zone when no shipping address is set', category: 'commerce');

        $formatter = app(Formatter::class);
        $metadataHtml = $taxZone->id ? app(ContentHtml::class)->metadataHtml([
            t('Created at') => $formatter->asDateTime($taxZone->dateCreated, 'short'),
            t('Updated at') => $formatter->asDateTime($taxZone->dateUpdated, 'short'),
        ]) : null;

        $formNodes = [
            HiddenField::make('storeId'),
        ];

        if ($taxZone->id) {
            $formNodes[] = HiddenField::make('taxZoneId');
        }

        $formNodes[] = Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())
            ->instructions(t('What this tax zone will be called in the control panel.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Description', category: 'commerce'), Text::make('description'))
            ->instructions(t('Describe this tax zone.', category: 'commerce'));
        $formNodes[] = Field::make($defaultLabel, Lightswitch::make('default'));
        // Zones aren't project-config-tracked (Zone::setCondition() hardcodes forProjectConfig
        // to false), so this deliberately doesn't call ->forProjectConfig() either.
        $formNodes[] = Field::make(t('Address Condition'), ConditionBuilder::make('condition')
            ->conditionClass(ZoneAddressCondition::class)
            ->value($taxZone->getCondition()->getConfig()));

        $values = [
            'storeId' => $store->id,
            'taxZoneId' => $taxZone->id,
            'name' => $taxZone->name,
            'description' => $taxZone->description,
            'default' => $taxZone->default,
        ];

        $form = $this->formResolver->resolve(Form::make($formNodes), new FormContext(values: $values));

        return $this->cpScreenResponse($store, subnav: false)
            ->title($title)
            ->crumbs($this->crumbs($store, ...($taxZone->id ? [['label' => $title]] : [])))
            ->action('commerce/tax-zones/save')
            ->redirectUrl($store->getStoreSettingsUrl('taxzones'))
            ->inertiaPage('Form', [
                'form' => $form,
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'save']),
                ],
                'metadataHtml' => $metadataHtml,
            ]);
    }

    public function save(Request $request): Response
    {
        $taxZone = new TaxAddressZone();

        $taxZone->id = $request->input('taxZoneId') ? (int)$request->input('taxZoneId') : null;
        $taxZone->storeId = $request->input('storeId') ? (int)$request->input('storeId') : null;
        $this->requireStoreAccess($taxZone->storeId);
        $taxZone->name = $request->input('name');
        $taxZone->description = $request->input('description');
        $taxZone->default = (bool)$request->input('default');
        $taxZone->setCondition($request->input('condition'));

        if ($taxZone->validate() && app(TaxZones::class)->saveTaxZone($taxZone)) {
            return $this->asModelSuccess(
                $taxZone,
                t('Tax zone saved.', category: 'commerce'),
                'taxZone',
                data: [
                    'id' => $taxZone->id,
                    'name' => $taxZone->name,
                ]
            );
        }

        return $this->asModelFailure(
            $taxZone,
            t('Couldn’t save tax zone.', category: 'commerce'),
            'taxZone'
        );
    }

    public function delete(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        abort_if(!$id, 400, 'Missing tax zone id');

        $taxZone = app(TaxZones::class)->getTaxZoneById((int)$id);
        if ($taxZone) {
            $this->requireStoreAccess($taxZone->storeId);
        }

        app(TaxZones::class)->deleteTaxZoneById((int)$id);
        return $this->asSuccess();
    }

    public function testZip(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $zipCodeFormula = (string)$request->input('zipCodeConditionFormula');
        $testZipCode = (string)$request->input('testZipCode');

        $params = ['zipCode' => $testZipCode];
        if (!app(Formulas::class)->evaluateCondition($zipCodeFormula, $params)) {
            return $this->asFailure('failed');
        }

        return $this->asSuccess();
    }
}
