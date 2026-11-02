<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Assets;

use CraftCms\Cms\Support\Json;
use CraftCms\Cms\View\Enums\Position;
use CraftCms\Cms\View\HtmlStack;
use CraftCms\Cms\View\LegacyAssets\LegacyAssetInterface;
use CraftCms\Commerce\Payment\PaymentCurrencies;
use CraftCms\Commerce\Plugin;

use function CraftCms\Cms\currentUserElement;

/**
 * The charts shown by the stat dashboard widgets.
 */
class StatWidgetsAsset implements LegacyAssetInterface
{
    public array $depends = [
        DeepMergeAsset::class,
        ChartJsAsset::class,
    ];

    public function register(HtmlStack $htmlStack): void
    {
        $plugin = Plugin::getInstance();

        $htmlStack->jsFile($plugin->asset('legacy/statwidgets/CommerceChart.js'));
        $htmlStack->cssFile($plugin->asset('legacy/statwidgets/css/CommerceChart.css'));

        $currency = Json::encode(app(PaymentCurrencies::class)->getPrimaryPaymentCurrencyIso());
        $locale = Json::encode(currentUserElement()?->getPreferredLanguage() ?? 'en');

        $htmlStack->js("window.commerceCurrency = $currency;\nwindow.commerceCurrentLocale = $locale;", Position::Head);
    }
}
