<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Services;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Singleton]
class Vat
{
    private const string CACHE_KEY_PREFIX = 'commerce:validVatId:';

    public function isValidVatId(string $vatId): bool
    {
        $validOrganizationTaxId = Cache::has(self::CACHE_KEY_PREFIX . $vatId);

        if (!$validOrganizationTaxId) {
            try {
                $validators = app(Taxes::class)->getEnabledTaxIdValidators();
                foreach ($validators as $validator) {
                    if ($validator->validateFormat($vatId) && $validator->validate($vatId)) {
                        $validOrganizationTaxId = true;
                        break;
                    }
                }
            } catch (Throwable $e) {
                Log::error('Communication with VAT API failed: ' . $e->getMessage());

                $validOrganizationTaxId = false;
            }
        }

        if (!$validOrganizationTaxId) {
            Cache::forget(self::CACHE_KEY_PREFIX . $vatId);
            return false;
        }

        Cache::forever(self::CACHE_KEY_PREFIX . $vatId, '1');
        return true;
    }
}
