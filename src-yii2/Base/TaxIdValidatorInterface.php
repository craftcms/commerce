<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

/**
 * Interface for Tax ID Validators.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.3.0
 */
interface TaxIdValidatorInterface
{
    /**
     * The display name of this tax ID type.
     *
     * @return string
     * @since 5.3.0
     */
    public static function displayName(): string;

    /**
     * Tests if the ID looks generally correct. This would usually be something like a regex check.
     *
     * @param string $idNumber
     * @return bool
     * @since 5.3.0
     */
    public function validateFormat(string $idNumber): bool;

    /**
     * Tests if the ID exists as valid in the country's tax system. This would usually be an API call.
     *
     * @param string $idNumber
     * @return bool
     * @since 5.3.0
     */
    public function validateExistence(string $idNumber): bool;

    /**
     * This would usually just call validateFormat() and then validateExistence() and return the result.
     *
     * @param string $idNumber
     * @return bool
     * @since 5.3.0
     */
    public function validate(string $idNumber): bool;

    /**
     * Tests if the validator is available for use by tax rates.
     * This would usually be a check against the existence or settings or API keys so that the validator can be used.
     *
     * @return bool
     * @since 5.3.0
     */
    public static function isEnabled(): bool;
}
