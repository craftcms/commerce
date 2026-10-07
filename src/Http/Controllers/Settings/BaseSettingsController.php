<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\Form\FormResolver;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;

use CraftCms\Commerce\Http\Controllers\Concerns\HasColorPalette;
use CraftCms\Commerce\Http\Controllers\Concerns\HasSubnavCrumbMenu;
use CraftCms\Commerce\Plugin;
use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

abstract class BaseSettingsController
{
    use HasColorPalette;
    use HasSubnavCrumbMenu;
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
     * The screens listed alongside this one. Most settings screens stand alone.
     *
     * @return NavItem[]
     */
    protected function subnav(): array
    {
        return [];
    }

    /**
     * Returns this controller's own section crumb (e.g. "Emails"). {@see self::crumbs()}
     * decides whether it actually links, based on whether anything follows it.
     *
     * @return array{label: string, href: string}
     */
    abstract protected function getSectionCrumb(): array;

    /**
     * Builds "Commerce / Settings / <section>[ / ...$trail]", where `$trail` is any crumbs beyond the
     * section (e.g. the record being edited). The last crumb never links.
     *
     * @param array{label: string, url?: ?string, items?: list<array<string, mixed>>} ...$trail
     * @return list<array<string, mixed>>
     */
    final protected function crumbs(array ...$trail): array
    {
        return $this->crumbsForSection($this->getSectionCrumb(), ...$trail);
    }

    /**
     * {@see crumbs()} for a screen that isn't this controller's own section, e.g. the Sites
     * screen, which {@see StoresController} serves but the subnav lists alongside Stores.
     *
     * @param array{label: string, href: string} $sectionCrumb
     * @param array{label: string, url?: ?string, items?: list<array<string, mixed>>} ...$trail
     * @return list<array<string, mixed>>
     */
    final protected function crumbsForSection(array $sectionCrumb, array ...$trail): array
    {
        $crumbs = [
            ['label' => t('Commerce', category: 'commerce'), 'href' => cp_url('commerce')],
            ['label' => t('Settings'), 'href' => cp_url('settings')],
            [...$sectionCrumb, 'items' => $this->subnavCrumbMenu($this->subnav(), $sectionCrumb['href'])],
            ...array_map(fn(array $crumb) => [
                'label' => $crumb['label'],
                'href' => $crumb['url'] ?? null,
                ...(empty($crumb['items']) ? [] : ['items' => $crumb['items']]),
            ], $trail),
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
