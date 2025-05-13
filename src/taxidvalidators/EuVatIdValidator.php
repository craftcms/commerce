<?php

namespace craft\commerce\taxidvalidators;

use Craft;
use craft\commerce\base\TaxIdValidatorInterface;
use DvK\Vat\Validator;

/**
 * EuVatIdValidator checks if a given VAT ID is valid in the EU.
 * Valid Test number: PL7272445205
 * @since 4.8.0
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class EuVatIdValidator implements TaxIdValidatorInterface
{
    public const API_URL = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

    private Validator $_vatValidator;

    public function __construct()
    {
        $this->_vatValidator = new Validator();
    }

    public static function displayName(): string
    {
        return \Craft::t('commerce', 'EU VAT ID');
    }

    public function validateFormat(string $idNumber): bool
    {
        return $this->_vatValidator->validateFormat($idNumber);
    }

    public function validateExistence(string $idNumber): bool
    {
        $vatNumber = strtoupper($idNumber);
        $country = substr($vatNumber, 0, 2);
        $number = substr($vatNumber, 2);

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
