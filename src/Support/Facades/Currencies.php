<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \Money\Teller getTeller(\Money\Currency|string $currency)
 * @method static \Money\Currency|null getCurrencyByIso(string $iso)
 * @method static \Illuminate\Support\Collection getAllCurrencies()
 * @method static array getAllCurrenciesList()
 * @method static int getSubunitFor(\Money\Currency|string $currency)
 * @method static int numericCodeFor(\Money\Currency|string $currency)
 *
 * @see \CraftCms\Commerce\Payment\Currencies
 */
class Currencies extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Payment\Currencies::class;
    }
}
