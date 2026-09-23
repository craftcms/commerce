<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\Form\FormResolver;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;

use CraftCms\Commerce\Plugin;
use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

abstract class BaseSettingsController
{
    use RespondsWithFlash;

    protected bool $readOnly;

    public function __construct(
        protected GeneralConfig $generalConfig,
        protected FormResolver $formResolver,
        protected readonly Plugin $plugin,
    ) {
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

    /**
     * Returns this controller's own section crumb (e.g. "Emails"). {@see self::crumbs()}
     * decides whether it actually links, based on whether anything follows it.
     *
     * @return array{label: string, href: string}
     */
    abstract protected function getSectionCrumb(): array;

    /**
     * Builds "Settings / <section>[ / ...$trail]". Pass one entry per crumb
     * beyond the section (usually zero, for an index; one, for a record being
     * edited). Whichever crumb ends up last never links, since it's the page
     * already showing.
     *
     * @param array{label: string, url?: ?string} ...$trail
     * @return list<array<string, string>>
     */
    final protected function crumbs(array ...$trail): array
    {
        $crumbs = [
            ['label' => t('Settings'), 'href' => cp_url('commerce/settings')],
            $this->getSectionCrumb(),
            ...array_map(fn(array $crumb) => ['label' => $crumb['label'], 'href' => $crumb['url'] ?? null], $trail),
        ];

        $crumbs[array_key_last($crumbs)]['href'] = null;

        return $crumbs;
    }

    /**
     * `$subnav = false` for a screen reached by drilling into one record from an index's own
     * table (an edit screen, say) — matching `cms`'s own precedent (e.g. Settings > Sites,
     * whose edit screen also drops the sites subnav).
     */
    protected function cpScreenResponse(bool $subnav = true): CpScreenResponse
    {
        return new CpScreenResponse()
            ->subnav($subnav ? $this->subnav() : null);
    }
}
