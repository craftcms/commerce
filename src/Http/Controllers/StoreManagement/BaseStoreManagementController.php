<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\StoreManagement;

use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\Form\FormResolver;
use CraftCms\Cms\Form\Nodes\Table;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Shared\Enums\Color;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tax\Taxes;
use DateTime;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

/**
 * Shared ancestor for Commerce's store-management settings controllers (shipping, tax,
 * promotions, payment currencies, etc) that have been converted to the Form system.
 *
 * `getSectionCrumb()` is a real abstract method — every direct subclass of this class must
 * implement it, enforced at class-load time. A controller not yet converted to the Form system
 * should extend {@see LegacyStoreManagementController} instead, which satisfies that
 * requirement with a stub so it isn't forced to implement Form-system-only crumb logic before
 * its turn — see that class's docblock for the one-line change a conversion makes.
 *
 * This class still `use`s {@see HasStoreManagementScreen} itself (not just the legacy shim)
 * because a converted controller can still need the legacy trait's `resolveStore()` and other
 * helpers even once its own screens use `crumbs()`/`subnav()`/`cpScreenResponse()` instead of
 * `storeManagementCpScreen()`.
 *
 * Unlike settings data, none of what these controllers manage is project-config-tracked —
 * it's regular database content gated by its own granular permission (`commerce-manageShipping`,
 * `commerce-manageTaxes`, etc.), not by `allowAdminChanges` — so there's deliberately no
 * `$readOnly`/`GeneralConfig` handling here.
 *
 * Every controller still resolves its own `Store` from a `storeHandle` route segment per action
 * method, not via the constructor — there's no framework-level route binding for handle-scoped
 * resources in cms-6 — so `resolveStore()` (from the trait) stays a plain method taking
 * `$storeHandle` as a parameter rather than a constructor-injected dependency.
 */
abstract readonly class BaseStoreManagementController
{
    use HasStoreManagementScreen;
    use RespondsWithFlash;

    public function __construct(
        protected FormResolver $formResolver,
    ) {
    }

    /**
     * Returns this controller's own section crumb, scoped to the given store — its label and
     * URL, as if it were always going to be linked. {@see self::crumbs()} decides whether it
     * actually links, based on whether anything follows it.
     *
     * @return array{label: string, href: string}
     */
    abstract protected function getSectionCrumb(Store $store): array;

    /**
     * Whether {@see crumbs()} includes the store-switcher crumb. Almost every screen is scoped
     * to the current store, so this defaults to `true` — override it to return `false` for the
     * rare screen whose data isn't actually store-specific (e.g. tax categories, which are
     * shared across every store even though they're reached through a store-handled URL), where
     * a switcher would wrongly imply switching stores shows something different.
     */
    protected function showsStoreSwitcher(): bool
    {
        return true;
    }

    /**
     * Builds "Commerce / <store switcher> / <section>[ / ...$trail]", where `$trail` is any
     * crumbs beyond the section (e.g. the record being edited). The last crumb never links.
     *
     * @param array{label: string, url?: ?string} ...$trail
     * @return list<array<string, mixed>>
     */
    final protected function crumbs(Store $store, array ...$trail): array
    {
        $sectionCrumb = $this->getSectionCrumb($store);

        $crumbs = [
            ['label' => t('Commerce', category: 'commerce'), 'href' => cp_url('commerce')],
            ...($this->showsStoreSwitcher() ? [$this->storeCrumb($store)] : []),
            [...$sectionCrumb, 'items' => $this->sectionMenu($store, $sectionCrumb['href'])],
            ...array_map(fn(array $crumb) => ['label' => $crumb['label'], 'href' => $crumb['url'] ?? null], $trail),
        ];

        $crumbs[array_key_last($crumbs)]['href'] = null;

        return $crumbs;
    }

    /**
     * The section crumb's switcher: every section in {@see subnav()}, with its groups as
     * headings, the way an element index's source crumb lists its sources.
     *
     * @return list<array<string, mixed>>
     */
    private function sectionMenu(Store $store, string $sectionUrl): array
    {
        $link = fn(NavItem $item) => [
            'type' => 'link',
            'label' => $item->label,
            'href' => $item->href,
            'selected' => $item->href === $sectionUrl,
        ];

        return array_map(fn(NavItem $item) => $item->group
            ? ['type' => 'group', 'heading' => $item->label, 'items' => array_map($link, $item->subnav ?: [])]
            : $link($item), $this->subnav($store));
    }

    /**
     * The store-switcher crumb — a store icon and name that opens a menu of every store the
     * current user can access, modelled on {@see \CraftCms\Cms\Cp\SiteSwitcher::crumb()}.
     *
     * It never links anywhere itself: a crumb with a URL has its menu replaced with the main
     * navigation on the client (`withNavCrumbMenus()`), so switching stores happens entirely
     * through its menu. Each store links to the same section of that store, falling back to
     * the section's index from a record's edit screen, since records belong to a single store.
     * The menu is left off when there's only one store to choose from.
     *
     * @return array<string, mixed>
     */
    private function storeCrumb(Store $store): array
    {
        $switchableStores = app(Stores::class)->getAllStores()->filter(function(Store $s) {
            foreach ($s->getSites() as $site) {
                if (currentUser()?->can('editSite:' . $site->uid)) {
                    return true;
                }
            }

            return false;
        })->values();

        $section = explode('/', request()->craftPath())[3] ?? null;

        return [
            'label' => t($store->getName(), category: 'site'),
            'icon' => 'store',
            'href' => null,
            'items' => $switchableStores->count() > 1
                ? $switchableStores->map(fn(Store $s) => [
                    'type' => 'link',
                    'label' => t($s->getName(), category: 'site'),
                    'href' => $s->getStoreSettingsUrl($section),
                    'selected' => $s->id === $store->id,
                ])->all()
                : [],
        ];
    }

    /** @return NavItem[] */
    protected function subnav(Store $store): array
    {
        $currentPath = request()->craftPath();
        $selected = fn(?string $path = null) => $currentPath === "commerce/store-management/{$store->handle}" . ($path ? "/{$path}" : '');

        $items = [];

        if (currentUser()?->can('commerce-manageGeneralStoreSettings')) {
            $items[] = new NavItem()
                ->label(t('General', category: 'commerce'))
                ->url($store->getStoreSettingsUrl())
                ->selected($selected());
        }

        if (currentUser()?->can('commerce-managePaymentCurrencies')) {
            $items[] = new NavItem()
                ->label(t('Payment Currencies', category: 'commerce'))
                ->url($store->getStoreSettingsUrl('payment-currencies'))
                ->selected($selected('payment-currencies'));
        }

        if (currentUser()?->can('commerce-managePromotions')) {
            $pricingItems = [
                new NavItem()
                    ->label(t('Discounts', category: 'commerce'))
                    ->url($store->getStoreSettingsUrl('discounts'))
                    ->selected($selected('discounts')),
            ];

            $pricingItems[] = app(CatalogPricingRules::class)->canUseCatalogPricingRules()
                ? new NavItem()
                    ->label(t('Pricing Rules', category: 'commerce'))
                    ->url($store->getStoreSettingsUrl('pricing-rules'))
                    ->selected($selected('pricing-rules'))
                : new NavItem()
                    ->label(t('Sales', category: 'commerce'))
                    ->url($store->getStoreSettingsUrl('sales'))
                    ->selected($selected('sales'));

            $items[] = new NavItem()->label(t('Pricing', category: 'commerce'))->group(true)->subnav($pricingItems);
        }

        if (currentUser()?->can('commerce-manageShipping')) {
            $items[] = new NavItem()->label(t('Shipping', category: 'commerce'))->group(true)->subnav([
                new NavItem()
                    ->label(t('Shipping Methods', category: 'commerce'))
                    ->url($store->getStoreSettingsUrl('shippingmethods'))
                    ->selected($selected('shippingmethods')),
                new NavItem()
                    ->label(t('Shipping Zones', category: 'commerce'))
                    ->url($store->getStoreSettingsUrl('shippingzones'))
                    ->selected($selected('shippingzones')),
                new NavItem()
                    ->label(t('Shipping Categories', category: 'commerce'))
                    ->url($store->getStoreSettingsUrl('shippingcategories'))
                    ->selected($selected('shippingcategories')),
            ]);
        }

        if (currentUser()?->can('commerce-manageTaxes')) {
            $taxItems = [];

            if (app(Taxes::class)->viewTaxRates()) {
                $taxItems[] = new NavItem()
                    ->label(t('Tax Rates', category: 'commerce'))
                    ->url($store->getStoreSettingsUrl('taxrates'))
                    ->selected($selected('taxrates'));
            }

            if (app(Taxes::class)->viewTaxZones()) {
                $taxItems[] = new NavItem()
                    ->label(t('Tax Zones', category: 'commerce'))
                    ->url($store->getStoreSettingsUrl('taxzones'))
                    ->selected($selected('taxzones'));
            }

            if (app(Taxes::class)->viewTaxCategories()) {
                $taxItems[] = new NavItem()
                    ->label(t('Tax Categories', category: 'commerce'))
                    ->url($store->getStoreSettingsUrl('taxcategories'))
                    ->selected($selected('taxcategories'));
            }

            if ($taxItems !== []) {
                $items[] = new NavItem()->label(t('Tax', category: 'commerce'))->group(true)->subnav($taxItems);
            }
        }

        return $items;
    }

    /**
     * `$subnav = false` for a screen reached by drilling into one record from an index's own
     * table (an edit screen, say) — matching `cms`'s own precedent (e.g. Settings > Sites,
     * whose edit screen also drops the sites subnav).
     */
    protected function cpScreenResponse(Store $store, bool $subnav = true): CpScreenResponse
    {
        return new CpScreenResponse()
            ->subnav($subnav ? $this->subnav($store) : null);
    }

    /**
     * The color values shared by any component keyed to {@see Color} as its category color
     * (tax and shipping categories today), for a {@see \CraftCms\Cms\Form\Controls\ColorSelect}
     * control's `->colors()` — narrows its default (the shared UI palette, which includes a
     * couple of colors outside this enum) down to exactly what {@see Color::tryFrom()} accepts.
     *
     * @return list<string>
     */
    protected function colorPalette(): array
    {
        return array_map(fn(Color $color) => $color->value, Color::cases());
    }

    /**
     * The value shape a {@see \CraftCms\Cms\Form\Controls\DateTime} control expects,
     * with empty strings for an unset date.
     *
     * @return array{date: string, time: string, timezone: string}
     */
    protected function dateTimeControlValue(?DateTime $value): array
    {
        return [
            'date' => $value?->format('Y-m-d') ?? '',
            'time' => $value?->format('H:i') ?? '',
            'timezone' => $value?->getTimezone()->getName() ?? date_default_timezone_get(),
        ];
    }

    /**
     * The Enabled/Disabled pair every store-management {@see Table} index's "Set status"
     * menu uses today (shipping methods, discounts), for {@see Table::statusActions()} —
     * posts `{status: 'enabled'|'disabled'}` to `$url`.
     *
     * @return list<array<string, mixed>>
     */
    protected function statusActions(string $url): array
    {
        return [
            ['label' => t('Enabled', category: 'commerce'), 'url' => $url, 'params' => ['status' => 'enabled'], 'fill' => 'success'],
            ['label' => t('Disabled', category: 'commerce'), 'url' => $url, 'params' => ['status' => 'disabled'], 'fill' => 'danger'],
        ];
    }
}
