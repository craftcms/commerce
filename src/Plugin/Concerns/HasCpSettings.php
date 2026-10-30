<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Concerns;

use CraftCms\Cms\Cp\Settings as CpSettings;
use CraftCms\Cms\Support\File;
use CraftCms\Commerce\Plugin;
use Override;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

/**
 * @mixin Plugin
 */
trait HasCpSettings
{
    private function registerCpSettings(): void
    {
        $settings = [
            'general' => ['gear', fn() => t('General', category: 'commerce')],
            'stores' => ['store', fn() => t('Stores', category: 'commerce')],
            'producttypes' => ['boxes-stacked', fn() => t('Product Types', category: 'commerce')],
            'orders' => ['receipt', fn() => t('Orders', category: 'commerce')],
            'pdfs' => ['file-pdf', fn() => t('PDFs', category: 'commerce')],
            'emails' => ['envelopes', fn() => t('Emails', category: 'commerce')],
            'gateways' => ['credit-card', fn() => t('Gateways', category: 'commerce')],
            'transfers' => ['truck-arrow-right', fn() => t('Transfers', category: 'commerce')],
        ];

        foreach ($settings as $handle => [$icon, $label]) {
            $setting = fn() => [
                'label' => $label(),
                'url' => cp_url("commerce/settings/$handle"),
                'icon' => File::get(dirname($this->getBasePath()) . "/resources/icons/light/$icon.svg"),
            ];

            app(CpSettings::class)->registerSetting('Commerce', $handle, $setting);
            app(CpSettings::class)->registerReadOnlySetting('Commerce', $handle, $setting);
        }
    }

    #[Override]
    public function getSettingsResponse(): mixed
    {
        return redirect('commerce/settings/general');
    }

    #[Override]
    public function getReadOnlySettingsResponse(): mixed
    {
        return redirect('commerce/settings/general');
    }
}
