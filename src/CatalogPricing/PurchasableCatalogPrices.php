<?php

declare(strict_types=1);

namespace CraftCms\Commerce\CatalogPricing;

use CraftCms\Commerce\CatalogPricing\Data\CatalogPricingRule;
use CraftCms\Commerce\CatalogPricing\Models\CatalogPricingRule as CatalogPricingRuleRecord;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Store\Data\Store;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * The catalog prices that currently apply to a purchasable, as listed by its price field.
 */
readonly class PurchasableCatalogPrices
{
    /**
     * @return array{
     *     heading: string,
     *     rows: list<array{
     *         id: string,
     *         name: string,
     *         url: string|null,
     *         effect: string,
     *         basedOnPromotionalPrice: bool,
     *         price: string,
     *         isPromotionalPrice: bool,
     *         lowestForEveryone: bool,
     *         specificCustomers: bool,
     *     }>,
     *     jobsRunning: bool,
     * }
     */
    public function data(int $purchasableId, Store $store): array
    {
        $rows = $this->rows($purchasableId, $store);

        return [
            'heading' => t('Pricing rules ({count})', ['count' => count($rows)], category: 'commerce'),
            'rows' => $rows,
            'jobsRunning' => app(CatalogPricing::class)->areCatalogPricingJobsRunning(),
        ];
    }

    public static function canEditRules(): bool
    {
        return self::can('commerce-editCatalogPricingRules');
    }

    public static function canCreateRules(): bool
    {
        return self::can('commerce-createCatalogPricingRules');
    }

    private static function can(string $permission): bool
    {
        $user = currentUserElement();

        return $user !== null
            && $user->can('accessPlugin-commerce')
            && $user->can('commerce-manageStoreSettings')
            && $user->can('commerce-managePromotions')
            && $user->can($permission);
    }

    /**
     * Sorted by price, with the price everyone without a customer-specific rule pays flagged.
     *
     * @return list<array{
     *     id: string,
     *     name: string,
     *     url: string|null,
     *     effect: string,
     *     basedOnPromotionalPrice: bool,
     *     price: string,
     *     isPromotionalPrice: bool,
     *     lowestForEveryone: bool,
     *     specificCustomers: bool,
     * }>
     */
    private function rows(int $purchasableId, Store $store): array
    {
        $catalogPrices = app(CatalogPricing::class)->createCatalogPricesQuery(storeId: $store->id, allPrices: true)
            ->select(['price', 'isPromotionalPrice', 'catalogPricingRuleId'])
            ->where('cp.purchasableId', $purchasableId)
            ->get();

        $currencyIso = $store->getCurrency()->getCode();
        $canEditRules = self::canEditRules();
        $lowestBasePrice = null;
        $rows = [];

        foreach ($catalogPrices as $catalogPrice) {
            $price = (float)$catalogPrice->price;

            if ($catalogPrice->catalogPricingRuleId === null) {
                $lowestBasePrice = min($lowestBasePrice ?? $price, $price);
                continue;
            }

            $rule = app(CatalogPricingRules::class)->getCatalogPricingRuleById((int)$catalogPrice->catalogPricingRuleId, $store->id);

            if (!$rule instanceof CatalogPricingRule) {
                continue;
            }

            $rows[] = [
                'id' => $rule->id . ($catalogPrice->isPromotionalPrice ? '-promotional' : ''),
                'name' => t($rule->name, category: 'site'),
                'url' => $canEditRules ? $rule->getCpEditUrl() : null,
                'effect' => $rule->getEffectLabel($currencyIso),
                'basedOnPromotionalPrice' => $rule->apply !== CatalogPricingRuleRecord::APPLY_TO_FLAT
                    && $rule->applyPriceType === CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PROMOTIONAL_PRICE,
                'amount' => $price,
                'price' => Currency::formatAsCurrency($price, $store->getCurrency()),
                'isPromotionalPrice' => (bool)$catalogPrice->isPromotionalPrice,
                'lowestForEveryone' => false,
                'specificCustomers' => $rule->hasCustomerCondition(),
            ];
        }

        usort($rows, fn(array $a, array $b) => [$a['amount'], $a['name']] <=> [$b['amount'], $b['name']]);

        foreach ($rows as $i => $row) {
            if ($row['specificCustomers']) {
                continue;
            }

            if ($lowestBasePrice === null || $row['amount'] < $lowestBasePrice) {
                $rows[$i]['lowestForEveryone'] = true;
            }

            break;
        }

        return array_map(function(array $row) {
            unset($row['amount']);

            return $row;
        }, $rows);
    }
}
