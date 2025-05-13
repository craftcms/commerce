<?php

namespace craft\commerce\taxidvalidators;

use craft\commerce\base\TaxIdValidatorInterface;
use DvK\Vat\Validator;

/**
 * EuVatIdValidator checks if a given VAT ID is valid in the EU.
 * Valid Test number: PL7272445205
 * @since 5.3.0
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class EuVatIdValidator implements TaxIdValidatorInterface
{
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
        return $this->_vatValidator->validateExistence($idNumber);
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
            return $this->_vatValidator->validate($idNumber);
        } catch (\Exception $e) {
            \Craft::error('Error validating EU VAT ID: ' . $e->getMessage());
            return false;
        }
    }
}
