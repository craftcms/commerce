<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax\Models;

use CraftCms\Commerce\Tax\Contracts\TaxIdValidatorInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use function CraftCms\Cms\t;

/**
 * Checks if a given VAT ID is valid in the EU.
 * Valid Test number: PL7272445205
 */
class EuVatIdValidator implements TaxIdValidatorInterface
{
    public const string API_URL = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

    /**
     * Regular expression patterns per country code
     *
     * @var array<string, string>
     * @link http://ec.europa.eu/taxation_customs/vies/faq.html?locale=lt#item_11
     */
    private array $patterns = [
        'AT' => 'U[A-Z\d]{8}',
        'BE' => '(0|1)\d{9}',
        'BG' => '\d{9,10}',
        'CY' => '\d{8}[A-Z]',
        'CZ' => '\d{8,10}',
        'DE' => '\d{9}',
        'DK' => '(\d{2} ?){3}\d{2}',
        'EE' => '\d{9}',
        'EL' => '\d{9}',
        'ES' => '([A-Z]\d{7}[A-Z]|\d{8}[A-Z]|[A-Z]\d{8})',
        'EU' => '\d{9}',
        'FI' => '\d{8}',
        'FR' => '[A-Z\d]{2}\d{9}',
        'GB' => '(\d{9}|\d{12}|(GD|HA)\d{3})',
        'HR' => '\d{11}',
        'HU' => '\d{8}',
        'IE' => '((\d{7}[A-Z]{1,2})|(\d[A-Z]\d{5}[A-Z]))',
        'IT' => '\d{11}',
        'LT' => '(\d{9}|\d{12})',
        'LU' => '\d{8}',
        'LV' => '\d{11}',
        'MT' => '\d{8}',
        'NL' => '\d{9}B\d{2}',
        'PL' => '\d{10}',
        'PT' => '\d{9}',
        'RO' => '\d{2,10}',
        'SE' => '\d{12}',
        'SI' => '\d{8}',
        'SK' => '\d{10}',
        'SM' => '\d{5}',
    ];

    #[\Override]
    public static function displayName(): string
    {
        return t('EU VAT ID', category: 'commerce');
    }

    /** @return array{0: string, 1: string} */
    private function splitNumber(string $idNumber): array
    {
        $vatNumber = strtoupper($idNumber);
        $country = substr($vatNumber, 0, 2);
        $number = substr($vatNumber, 2);

        return [$country, $number];
    }

    #[\Override]
    public function validateFormat(string $idNumber): bool
    {
        [$country, $number] = $this->splitNumber($idNumber);

        if (!isset($this->patterns[$country])) {
            return false;
        }

        return preg_match('/^' . $this->patterns[$country] . '$/', $number) > 0;
    }

    #[\Override]
    public function validateExistence(string $idNumber): bool
    {
        [$country, $number] = $this->splitNumber($idNumber);

        try {
            $response = Http::asJson()->post(self::API_URL, [
                'countryCode' => $country,
                'vatNumber' => $number,
            ]);

            if (!$response->successful()) {
                return false;
            }

            return $response->json('valid') === true;
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }

        return false;
    }

    #[\Override]
    public static function isEnabled(): bool
    {
        return true;
    }

    #[\Override]
    public function validate(string $idNumber): bool
    {
        try {
            return $this->validateFormat($idNumber) && $this->validateExistence($idNumber);
        } catch (Exception $e) {
            Log::error('Error validating EU VAT ID: ' . $e->getMessage());
            return false;
        }
    }
}
