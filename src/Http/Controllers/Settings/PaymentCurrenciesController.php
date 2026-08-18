<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\helpers\Cp;
use craft\i18n\Formatter;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Html;
use CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Currencies;
use CraftCms\Commerce\Payment\Models\PaymentCurrency;
use CraftCms\Commerce\Payment\PaymentCurrencies;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\t;

readonly class PaymentCurrenciesController
{
    use HasStoreManagementScreen;
    use RespondsWithFlash;

    public function index(?string $storeHandle = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);
        $storeHandle = $store->handle;

        $currencies = app(PaymentCurrencies::class)->getAllPaymentCurrencies($store->id);

        return $this->storeManagementCpScreen($storeHandle)
            ->additionalButtonsHtml(Html::a(
                t('New currency', category: 'commerce'), "commerce/store-management/$storeHandle/payment-currencies/new",
                ['class' => 'btn submit add icon']
            ))
            ->contentTemplate('commerce/store-management/paymentcurrencies/index', ['currencies' => $currencies, 'store' => $store]);
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);
        $storeHandle = $store->handle;

        if ($id) {
            $currency = app(PaymentCurrencies::class)->getPaymentCurrencyById($id, $store->id);
            abort_if($currency === null || $currency->storeId !== $store->id, 404);
        } else {
            $currency = \Craft::createObject([
                'class' => PaymentCurrency::class,
                'storeId' => $store->id,
            ]);
        }

        // @TODO Use the full currency name instead of the ISO code for the page title
        $title = $currency->id ? $currency->iso : t('Create a new currency', category: 'commerce');

        $storeCurrency = app(PaymentCurrencies::class)->getPrimaryPaymentCurrencyIso();
        $currencyOptions = app(Currencies::class)->getAllCurrenciesList();
        $hasCompletedOrders = Order::find()->isCompleted(true)->exists();

        $formatter = I18N::getFormatter();

        $metaSidebarHtml = $currency->id ? Cp::metadataHtml([
            t('Created at') => $formatter->asDateTime($currency->dateCreated, Formatter::FORMAT_WIDTH_SHORT),
            t('Updated at') => $formatter->asDateTime($currency->dateUpdated, Formatter::FORMAT_WIDTH_SHORT),
        ]) : '';

        return $this->storeManagementCpScreen($storeHandle, false)
            ->addCrumb(t('Payment Currencies', category: 'commerce'), "commerce/store-management/$storeHandle/payment-currencies")
            ->metaSidebarHtml($metaSidebarHtml)
            ->action('commerce/payment-currencies/save')
            ->redirectUrl("commerce/store-management/$storeHandle/payment-currencies")
            ->submitButtonLabel(t('Save'))
            ->contentTemplate('commerce/store-management/paymentcurrencies/_edit', [
                'id' => $id,
                'currency' => $currency,
                'title' => $title,
                'storeCurrency' => $storeCurrency,
                'currencyOptions' => $currencyOptions,
                'store' => $store,
                'hasCompletedOrders' => $hasCompletedOrders,
            ]);
    }

    public function save(Request $request): Response
    {
        $currency = new PaymentCurrency();

        $currency->id = $request->input('currencyId') ? (int)$request->input('currencyId') : null;
        $currency->storeId = (int)$request->input('storeId');
        $currency->iso = $request->input('iso');
        $currency->rate = (float)$request->input('rate', 1);

        if (app(PaymentCurrencies::class)->savePaymentCurrency($currency)) {
            return $this->asModelSuccess($currency, t('Currency saved.', category: 'commerce'), 'currency');
        }

        return $this->asModelFailure($currency, t('Couldn\'t save currency.', category: 'commerce'), 'currency');
    }

    public function delete(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        abort_if(!$id, 400, 'Missing currency id');

        if (!app(PaymentCurrencies::class)->deletePaymentCurrencyById((int)$id)) {
            return $this->asFailure();
        }

        return $this->asSuccess();
    }
}
