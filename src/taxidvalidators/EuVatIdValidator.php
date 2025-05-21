<?php

namespace craft\commerce\taxidvalidators;

use Craft;
use craft\commerce\base\TaxIdValidatorInterface;

/**
 * EuVatIdValidator checks if a given VAT ID is valid in the EU.
 * Valid Test number: PL7272445205
 * @since 5.3.0
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class EuVatIdValidator implements TaxIdValidatorInterface
{
    public const API_URL = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

    /**
     * Regular expression patterns per country code
     *
     * @var array
     * @link http://ec.europa.eu/taxation_customs/vies/faq.html?locale=lt#item_11
     */
    private array $_patterns = [
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

    public static function displayName(): string
    {
        return \Craft::t('commerce', 'EU VAT ID');
    }

    private function _splitNumber(string $idNumber): array
    {
        $vatNumber = strtoupper($idNumber);
        $country = substr($vatNumber, 0, 2);
        $number = substr($vatNumber, 2);

        return [$country, $number];
    }

    public function validateFormat(string $idNumber): bool
    {
        list($country, $number) = $this->_splitNumber($idNumber);

        if (!isset($this->_patterns[$country])) {
            return false;
        }

        return preg_match('/^' . $this->_patterns[$country] . '$/', $number) > 0;
    }

    public function validateExistence(string $idNumber): bool
    {
        list($country, $number) = $this->_splitNumber($idNumber);

        try {
            $client = Craft::createGuzzleClient();
            $response = $client->post(self::API_URL, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'countryCode' => $country,
                    'vatNumber' => $number,
                ]),
            ]);

            $responseBody = json_decode($response->getBody(), true);
            if ($response->getStatusCode() !== 200) {
                return false;
            }

            if (!isset($responseBody['valid']) || $responseBody['valid'] !== true) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            \Craft::error($e->getMessage(), __METHOD__);
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public static function isEnabled(): bool
    {
        return true;
    }

    public function validate(string $idNumber): bool
    {
        try {
            return $this->validateFormat($idNumber) && $this->validateExistence($idNumber);
        } catch (\Exception $e) {
            \Craft::error('Error validating EU VAT ID: ' . $e->getMessage());
            return false;
        }
    }
}
