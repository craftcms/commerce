<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\StoreManagement;

use craft\helpers\Cp;
use CraftCms\Cms\Cp\Html\ContentHtml;
use CraftCms\Cms\Form\Controls\ColorSelect;
use CraftCms\Cms\Form\Controls\ConditionBuilder;
use CraftCms\Cms\Form\Controls\Handle;
use CraftCms\Cms\Form\Controls\IconPicker;
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
use CraftCms\Commerce\Customer\Conditions\ShippingMethodCustomerCondition;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Order\Conditions\ShippingMethodOrderCondition;
use CraftCms\Commerce\Shipping\Data\ShippingMethod;
use CraftCms\Commerce\Shipping\Data\ShippingRule;
use CraftCms\Commerce\Shipping\Models\ShippingMethod as ShippingMethodRecord;
use CraftCms\Commerce\Shipping\ShippingMethods;
use CraftCms\Commerce\Shipping\ShippingRules;
use CraftCms\Commerce\Store\Data\Store;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\t;

readonly class ShippingMethodsController extends BaseStoreManagementController
{
    protected function getSectionCrumb(Store $store): array
    {
        return ['label' => t('Shipping Methods', category: 'commerce'), 'href' => $store->getStoreSettingsUrl('shippingmethods')];
    }

    public function index(?string $storeHandle = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        $shippingMethods = app(ShippingMethods::class)->getAllShippingMethods($store->id);

        $rows = $shippingMethods
            ->map(fn(ShippingMethod $shippingMethod) => [
                'id' => $shippingMethod->id,
                'name' => ['html' => Cp::chipHtml($shippingMethod, [
                    'showStatus' => true,
                    'showThumb' => true,
                    'labelHtml' => Html::a(Html::encode(t($shippingMethod->name, category: 'site')), $shippingMethod->getCpEditUrl(), ['class' => 'cell-bold']),
                ])],
                'handle' => $shippingMethod->handle,
                'type' => $shippingMethod->getType(),
            ])
            ->values()
            ->all();

        $nodes = [
            Table::make('shipping-methods')
                ->columns([
                    ['key' => 'name', 'label' => t('Name')],
                    ['key' => 'handle', 'label' => t('Handle')],
                    ['key' => 'type', 'label' => t('Type', category: 'commerce')],
                ])
                ->rows($rows)
                ->emptyMessage(t('No shipping methods exist yet.', category: 'commerce'))
                ->createAction(t('New shipping method', category: 'commerce'), $store->getStoreSettingsUrl('shippingmethods/new'))
                ->deletable(action([self::class, 'delete']), bulk: true)
                ->statusActions($this->statusActions(action([self::class, 'updateStatus']))),
        ];

        $title = t('Shipping Methods', category: 'commerce');

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
            $shippingMethod = app(ShippingMethods::class)->getShippingMethodById($id, $store->id);
            abort_if($shippingMethod === null, 404);
        } else {
            $shippingMethod = new ShippingMethod(['storeId' => $store->id]);
        }

        $title = $shippingMethod->id ? $shippingMethod->name : t('Create a new shipping method', category: 'commerce');

        $formatter = app(Formatter::class);
        $metadataHtml = $shippingMethod->id ? app(ContentHtml::class)->metadataHtml([
            t('Created at') => $formatter->asDateTime($shippingMethod->dateCreated, 'short'),
            t('Updated at') => $formatter->asDateTime($shippingMethod->dateUpdated, 'short'),
        ]) : null;

        $handle = Handle::make('handle');
        if (!$shippingMethod->id) {
            $handle->source('name');
        }

        $formNodes = [
            HiddenField::make('storeId'),
        ];

        if ($shippingMethod->id) {
            $formNodes[] = HiddenField::make('shippingMethodId');
        }

        $formNodes[] = Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())
            ->required();
        $formNodes[] = Field::make(t('Handle', category: 'commerce'), $handle)
            ->instructions(t('How this shipping method will be referred to in templates and forms.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Icon', category: 'app'), IconPicker::make('icon'));
        $formNodes[] = Field::make(t('Color', category: 'commerce'), ColorSelect::make('color')
            ->colors($this->colorPalette())
            ->allowTransparent()
            ->blankLabel(t('No color', category: 'app')));

        // Neither condition is project-config-tracked (both hardcode forProjectConfig = false
        // in their setters, same as zones), so no ->forProjectConfig() here either.
        $formNodes[] = Field::make(t('Match Order', category: 'commerce'), ConditionBuilder::make('orderCondition')
            ->conditionClass(ShippingMethodOrderCondition::class)
            ->value($shippingMethod->getOrderCondition()->getConfig()))
            ->instructions(t('Conditions here are matched against an order before looking through the rules. This is useful if you want to qualify a method’s availability early, or if there are common conditions to all rules for this method.', category: 'commerce'));

        $formNodes[] = Field::make(t('Match Customer', category: 'commerce'), ConditionBuilder::make('customerCondition')
            ->conditionClass(ShippingMethodCustomerCondition::class)
            ->value($shippingMethod->getCustomerCondition()->getConfig()))
            ->instructions(t('Conditions here are matched against the order’s customer before looking through the rules. This is useful if you want qualify a method’s availability early or if there are common conditions to all rules for this method.', category: 'commerce'));

        $formNodes[] = Field::make(t('Enable this shipping method on the front end', category: 'commerce'), Lightswitch::make('enabled'));

        if ($shippingMethod->id) {
            $shippingRules = app(ShippingRules::class)->getAllShippingRulesByShippingMethodId($shippingMethod->id);

            $rows = collect($shippingRules)
                ->map(fn(ShippingRule $rule) => [
                    'id' => $rule->id,
                    'name' => ['label' => t($rule->name, category: 'site'), 'url' => $store->getStoreSettingsUrl("shippingmethods/{$shippingMethod->id}/shippingrules/{$rule->id}")],
                    'description' => t($rule->description, category: 'site'),
                    'details' => $this->ruleDetailsCell($rule),
                    'enabled' => $rule->enabled ? ['icon' => 'check', 'label' => t('Yes')] : '',
                ])
                ->values()
                ->all();

            $formNodes[] = Table::make('shipping-rules')
                ->columns([
                    ['key' => 'name', 'label' => t('Shipping Rule', category: 'commerce')],
                    ['key' => 'description', 'label' => t('Description', category: 'commerce')],
                    ['key' => 'details', 'label' => t('Details', category: 'commerce')],
                    ['key' => 'enabled', 'label' => t('Enabled?', category: 'commerce')],
                ])
                ->rows($rows)
                ->emptyMessage(t('No shipping rules exist yet.', category: 'commerce'))
                ->createAction(t('New shipping rule', category: 'commerce'), $store->getStoreSettingsUrl("shippingmethods/{$shippingMethod->id}/shippingrules/new"))
                ->reorderable(action([ShippingRulesController::class, 'reorder']))
                ->deletable(action([ShippingRulesController::class, 'delete']));
        }

        $values = [
            'storeId' => $store->id,
            'shippingMethodId' => $shippingMethod->id,
            'name' => $shippingMethod->name,
            'handle' => $shippingMethod->handle,
            'icon' => $shippingMethod->icon,
            'color' => $shippingMethod->color ?? '',
            'orderCondition' => $shippingMethod->getOrderCondition()->getConfig(),
            'customerCondition' => $shippingMethod->getCustomerCondition()->getConfig(),
            'enabled' => $shippingMethod->enabled,
        ];

        $form = $this->formResolver->resolve(Form::make($formNodes), new FormContext(values: $values));

        return $this->cpScreenResponse($store, subnav: false)
            ->title($title)
            ->crumbs($this->crumbs($store, ...($shippingMethod->id ? [['label' => $title]] : [])))
            ->action('commerce/shipping-methods/save')
            ->redirectUrl($store->getStoreSettingsUrl('shippingmethods/{id}#rules'))
            ->submitButtonLabel($shippingMethod->id ? t('Save and set rules', category: 'commerce') : t('Save'))
            ->inertiaPage('Form', [
                'form' => $form,
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'save']),
                ],
                'metadataHtml' => $metadataHtml,
            ]);
    }

    /** @return array<string, mixed>|string */
    private function ruleDetailsCell(ShippingRule $rule): array|string
    {
        $lines = [];

        if ($rule->baseRate > 0) {
            $lines[] = t('Base Rate', category: 'commerce') . ': ' . Currency::formatAsCurrency($rule->baseRate);
        }
        if ($rule->minRate > 0) {
            $lines[] = t('Minimum Total Shipping Cost', category: 'commerce') . ': ' . Currency::formatAsCurrency($rule->minRate);
        }
        if ($rule->maxRate > 0) {
            $lines[] = t('Maximum Total Shipping Cost', category: 'commerce') . ': ' . Currency::formatAsCurrency($rule->maxRate);
        }
        if ($rule->perItemRate > 0) {
            $lines[] = t('Default Per Item Rate', category: 'commerce') . ': ' . Currency::formatAsCurrency($rule->perItemRate);
        }
        if ($rule->weightRate > 0) {
            $lines[] = t('Default Weight Rate', category: 'commerce') . ': ' . Currency::formatAsCurrency($rule->weightRate);
        }
        if ($rule->percentageRate > 0) {
            $lines[] = t('Default Percentage Rate', category: 'commerce') . ': ' . rtrim(rtrim((string) $rule->percentageRate, '0'), '.');
        }

        if ($lines === []) {
            return '';
        }

        return ['html' => Html::tag('span', '', [
            'data-icon' => 'info',
            'title' => implode("\n", $lines),
        ])];
    }

    public function save(Request $request): Response
    {
        $shippingMethod = new ShippingMethod();

        $shippingMethodId = $request->input('shippingMethodId');
        $shippingMethod->id = $shippingMethodId ? (int)$shippingMethodId : null;
        $shippingMethod->name = $request->input('name');
        $shippingMethod->handle = $request->input('handle');
        $shippingMethod->icon = $request->input('icon');
        // '__blank__' is ColorSelect's internal sentinel for "no color" selected — it should
        // never reach here (the client translates it back to '' before posting), but guard
        // against it anyway for a genuinely JS-less submission.
        $color = $request->input('color');
        $shippingMethod->color = ($color && $color !== '__blank__') ? $color : null;
        $storeId = $request->input('storeId');
        $shippingMethod->storeId = $storeId ? (int)$storeId : null;
        $this->requireStoreAccess($shippingMethod->storeId);
        $shippingMethod->setOrderCondition($request->input('orderCondition'));
        $shippingMethod->setCustomerCondition($request->input('customerCondition'));
        $shippingMethod->enabled = (bool)$request->input('enabled');

        if (!app(ShippingMethods::class)->saveShippingMethod($shippingMethod)) {
            return $this->asModelFailure($shippingMethod, t('Couldn’t save shipping method.', category: 'commerce'), 'shippingMethod');
        }

        return $this->asModelSuccess($shippingMethod, t('Shipping method saved.', category: 'commerce'), 'shippingMethod');
    }

    public function delete(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        $ids = $request->input('ids');
        abort_if((!$id && empty($ids)) || ($id && !empty($ids)), 400, 'id or ids must be specified.');

        if ($id) {
            $ids = [$id];
        }

        $failedIds = [];
        foreach ($ids as $deleteId) {
            $shippingMethod = app(ShippingMethods::class)->getShippingMethodById((int)$deleteId);
            if ($shippingMethod) {
                $this->requireStoreAccess($shippingMethod->storeId);
            }

            if (!$shippingMethod || !app(ShippingMethods::class)->deleteShippingMethodById((int)$deleteId)) {
                $failedIds[] = $deleteId;
            }
        }

        if (!empty($failedIds)) {
            return $this->asFailure(t('Could not delete {count, number} shipping {count, plural, one{method} other{methods}} and rules.', [
                'count' => count($failedIds),
            ], category: 'commerce'));
        }

        return $this->asSuccess(t('Shipping methods and rules deleted.', category: 'commerce'));
    }

    public function updateStatus(Request $request): Response
    {
        $ids = $request->input('ids');
        $status = $request->input('status');

        abort_if(empty($ids), 400, 'Missing ids');

        DB::transaction(function() use ($ids, $status) {
            $shippingMethods = ShippingMethodRecord::whereIn('id', $ids)->get();

            foreach ($shippingMethods as $shippingMethod) {
                $this->requireStoreAccess($shippingMethod->storeId);
                $shippingMethod->enabled = ($status == 'enabled');
                $shippingMethod->save();
            }
        });

        return $this->asSuccess(t('Shipping methods updated.', category: 'commerce'));
    }
}
