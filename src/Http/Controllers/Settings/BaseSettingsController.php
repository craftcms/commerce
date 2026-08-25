<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\Form\FormResolver;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

abstract class BaseSettingsController
{
    use RespondsWithFlash;

    protected bool $readOnly;

    public function __construct(
        protected GeneralConfig $generalConfig,
        protected FormResolver $formResolver,
    )
    {
        $this->readOnly = !$generalConfig->allowAdminChanges;
    }

    /**
     * @return NavItem[]
     */
    protected function subnav(): array
    {
        $path = request()->craftPath();

        return [
            new NavItem()
                ->label(t('General Settings', category: 'commerce'))
                ->url(cp_url('commerce/settings/general'))
                ->selected($path === 'commerce/settings/general'),

            new NavItem()
                ->label(t('Stores & Sites', category: 'commerce'))
                ->group(true)
                ->subnav([
                    new NavItem()
                        ->label(t('Stores', category: 'commerce'))
                        ->url(cp_url('commerce/settings/stores'))
                        ->selected($path === 'commerce/settings/stores'),
                    new NavItem()
                        ->label(t('Sites'))
                        ->url(cp_url('commerce/settings/sites'))
                        ->selected($path === 'commerce/settings/sites'),
                ]),

            new NavItem()
                ->label(t('Products', category: 'commerce'))
                ->group(true)
                ->subnav([
                    new NavItem()
                        ->label(t('Product Types', category: 'commerce'))
                        ->url(cp_url('commerce/settings/producttypes'))
                        ->selected($path === 'commerce/settings/producttypes'),
                ]),

            new NavItem()
                ->label(t('Orders', category: 'commerce'))
                ->group(true)
                ->subnav([
                    new NavItem()
                        ->label(t('Order Fields', category: 'commerce'))
                        ->url(cp_url('commerce/settings/ordersettings'))
                        ->selected($path === 'commerce/settings/ordersettings'),
                    new NavItem()
                        ->label(t('Order Statuses', category: 'commerce'))
                        ->url(cp_url('commerce/settings/orderstatuses'))
                        ->selected($path === 'commerce/settings/orderstatuses'),
                    new NavItem()
                        ->label(t('Line Item Statuses', category: 'commerce'))
                        ->url(cp_url('commerce/settings/lineitemstatuses'))
                        ->selected($path === 'commerce/settings/lineitemstatuses'),
                ]),

            new NavItem()
                ->label(t('PDFs & Emails', category: 'commerce'))
                ->group(true)
                ->subnav([
                    new NavItem()
                        ->label(t('PDFs', category: 'commerce'))
                        ->url(cp_url('commerce/settings/pdfs'))
                        ->selected($path === 'commerce/settings/pdfs'),
                    new NavItem()
                        ->label(t('Emails', category: 'commerce'))
                        ->url(cp_url('commerce/settings/emails'))
                        ->selected($path === 'commerce/settings/emails'),
                ]),

            new NavItem()
                ->label(t('Payments', category: 'commerce'))
                ->group(true)
                ->subnav([
                    new NavItem()
                        ->label(t('Gateways', category: 'commerce'))
                        ->url(cp_url('commerce/settings/gateways'))
                        ->selected($path === 'commerce/settings/gateways'),
                ]),

            new NavItem()
                ->label(t('Transfers', category: 'commerce'))
                ->group(true)
                ->subnav([
                    new NavItem()
                        ->label(t('Transfer Fields', category: 'commerce'))
                        ->url(cp_url('commerce/settings/transfers'))
                        ->selected($path === 'commerce/settings/transfers'),
                ]),
        ];
    }

    /** @return list<array<string, string>> */
    protected function crumbs(string $title, ?string $url = null): array
    {
        return [
            ['label' => t('Settings'), 'href' => cp_url('settings')],
            array_filter(['label' => $title, 'href' => $url]),
        ];
    }

    protected function cpScreenResponse(): CpScreenResponse
    {
        return new CpScreenResponse()
            ->subnav($this->subnav());
    }
}
