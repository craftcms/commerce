<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\StoreManagement;

use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Cp\Html\ContentHtml;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\Number;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Enums\ControlMode;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Form\Nodes\Table;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Translation\Formatter;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Currencies;
use CraftCms\Commerce\Payment\Data\PaymentCurrency;
use CraftCms\Commerce\Payment\PaymentCurrencies;
use CraftCms\Commerce\Store\Data\Store;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\t;

readonly class PaymentCurrenciesController extends BaseStoreManagementController
{
    protected function getSectionCrumb(Store $store): array
    {
        return ['label' => t('Payment Currencies', category: 'commerce'), 'href' => $store->getStoreSettingsUrl('payment-currencies')];
    }

    public function index(?string $storeHandle = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        $rows = app(PaymentCurrencies::class)->getAllPaymentCurrencies($store->id)
            ->map(fn(PaymentCurrency $currency) => [
                'id' => $currency->id,
                // getName() is just the ISO code today (there's no display-name lookup yet) —
                // matches the "Code" column below exactly, which is a pre-existing redundancy,
                // not something introduced here.
                'name' => $currency->primary
                    ? ['html' => Html::encode(t('{name} (Primary)', ['name' => t($currency->getName(), category: 'site')], category: 'commerce'))]
                    : ['html' => Html::a(Html::encode(t($currency->getName(), category: 'site')), $currency->getCpEditUrl(), ['class' => 'cell-bold'])],
                'handle' => ['html' => FormFields::copytextHtml(['value' => $currency->iso, 'monospace' => true])],
                'rate' => $currency->primary
                    ? ['html' => Html::tag('span', Html::encode(t('Base', category: 'commerce')), ['class' => 'token'])]
                    : (string) $currency->rate,
                '_deletable' => !$currency->primary,
            ])
            ->values()
            ->all();

        $nodes = [
            Table::make('payment-currencies')
                ->columns([
                    ['key' => 'name', 'label' => t('Currency', category: 'commerce')],
                    ['key' => 'handle', 'label' => t('Code', category: 'commerce')],
                    ['key' => 'rate', 'label' => t('Conversion Rate', category: 'commerce')],
                ])
                ->rows($rows)
                ->emptyMessage(t('No additional payment currencies exist yet.', category: 'commerce'))
                ->createAction(t('New currency', category: 'commerce'), $store->getStoreSettingsUrl('payment-currencies/new'))
                ->createActionInPageHeader()
                ->deletable(
                    action([self::class, 'delete']),
                    t('Warning, deleting this currency will stop all payments and refunds in this currency, are you sure you want to delete it?', category: 'commerce'),
                ),
        ];

        $title = t('Payment Currencies', category: 'commerce');

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
            $currency = app(PaymentCurrencies::class)->getPaymentCurrencyById($id, $store->id);
            abort_if($currency === null || $currency->storeId !== $store->id, 404);
        } else {
            $currency = new PaymentCurrency(['storeId' => $store->id]);
        }

        // @TODO Use the full currency name instead of the ISO code for the page title
        $title = $currency->id ? $currency->iso : t('Create a new currency', category: 'commerce');

        $currencyOptions = app(Currencies::class)->getAllCurrenciesList();
        $hasCompletedOrders = Order::find()->isCompleted(true)->exists();
        $isoLocked = $currency->id && $currency->primary && $hasCompletedOrders;

        $formatter = app(Formatter::class);
        $metaSidebarHtml = $currency->id ? app(ContentHtml::class)->metadataHtml([
            t('Created at') => $formatter->asDateTime($currency->dateCreated, 'short'),
            t('Updated at') => $formatter->asDateTime($currency->dateUpdated, 'short'),
        ]) : '';

        $formNodes = [
            HiddenField::make('storeId'),
        ];

        if ($currency->id) {
            $formNodes[] = HiddenField::make('currencyId');
        }

        if ($isoLocked) {
            // A disabled/readonly Choice never gets a `name` attribute (see
            // FormHtmlRenderer::renderControl()), so it wouldn't submit at all — this
            // pairs a plain readonly display with a HiddenField carrying the real value
            // through, mirroring the old template's readonly-select-plus-hidden-input pair.
            $formNodes[] = HiddenField::make('iso');
            $formNodes[] = Field::make(t('Currency Code', category: 'commerce'), Text::make('isoDisplay')
                ->mode(ControlMode::ReadOnly)
                ->value($currency->iso))
                ->instructions(t('Choose the currency’s ISO code.', category: 'commerce'))
                ->warning(t('The primary currency cannot be changed after orders are placed.', category: 'commerce'));
        } else {
            $formNodes[] = Field::make(t('Currency Code', category: 'commerce'), Choice::make('iso')->options($currencyOptions))
                ->instructions(t('Choose the currency’s ISO code.', category: 'commerce'))
                ->required();
        }

        $formNodes[] = Field::make(t('Conversion Rate', category: 'commerce'), Number::make('rate')
            ->mode($currency->primary ? ControlMode::ReadOnly : ControlMode::Editable))
            ->instructions(t('The conversion rate that will be used when converting an amount to this currency. For example, if an item costs {amount1}, a conversion rate of {rate} would result in {amount2} in the alternate currency.', [
                'amount1' => 10,
                'rate' => 1.5,
                'amount2' => 15,
            ], category: 'commerce'));

        $values = [
            'storeId' => $store->id,
            'currencyId' => $currency->id,
            'iso' => $currency->iso,
            'rate' => $currency->rate ?: 1,
        ];

        $form = $this->formResolver->resolve(Form::make($formNodes), new FormContext(values: $values));

        return $this->cpScreenResponse($store, subnav: false)
            ->title($title)
            ->crumbs($this->crumbs($store, ...($currency->id ? [['label' => $title]] : [])))
            ->action('commerce/payment-currencies/save')
            ->redirectUrl($store->getStoreSettingsUrl('payment-currencies'))
            ->inertiaPage('Form', [
                'form' => $form,
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'save']),
                ],
                'metadataHtml' => $metaSidebarHtml ?: null,
            ]);
    }

    public function save(Request $request): Response
    {
        $currency = new PaymentCurrency();

        $currency->id = $request->input('currencyId') ? (int)$request->input('currencyId') : null;
        $currency->storeId = (int)$request->input('storeId');
        $this->requireStoreAccess($currency->storeId);
        $currency->iso = $request->input('iso');
        $currency->rate = (float)$request->input('rate', 1);

        if (app(PaymentCurrencies::class)->savePaymentCurrency($currency)) {
            return $this->asModelSuccess($currency, t('Currency saved.', category: 'commerce'), 'currency');
        }

        return $this->asModelFailure($currency, t('Couldn’t save currency.', category: 'commerce'), 'currency');
    }

    public function delete(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        abort_if(!$id, 400, 'Missing currency id');

        $currency = app(PaymentCurrencies::class)->getPaymentCurrencyById((int)$id);
        if ($currency) {
            $this->requireStoreAccess($currency->storeId);
        }

        if (!app(PaymentCurrencies::class)->deletePaymentCurrencyById((int)$id)) {
            return $this->asFailure();
        }

        return $this->asSuccess();
    }
}
