<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Twig;

use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Helpers\PaymentForm;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;

class Extension extends AbstractExtension implements GlobalsInterface
{
    public function getName(): string
    {
        return 'Craft Commerce Twig Extension';
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('commerceCurrency', Currency::formatAsCurrency(...)),
            new TwigFilter('commercePaymentFormNamespace', PaymentForm::getPaymentFormNamespace(...)),
        ];
    }

    public function getGlobals(): array
    {
        /** @phpstan-ignore-next-line method.notFound (getStore() is added to Site via a Macroable macro registered in Plugin::registerBehaviorMacros(), not visible to static analysis) */
        $currentStore = Sites::getCurrentSite()->getStore();

        return [
            'currentStore' => $currentStore,
        ];
    }
}
