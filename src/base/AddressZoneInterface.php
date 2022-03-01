<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

/**
 * Zone Interface defines the common interface to be implemented by zones in commerce.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
interface AddressZoneInterface
{
    /**
     * Whether this zone is based on countries only.
     */
    public function getIsCountryBased(): bool;

    /**
     * If this zone is not countries based, then it is based on administrative areas, which can only have one country.
     */
    public function getCountryCode(): ?string;

    /**
     * Return the array of Country Codes this zone contains
     *
     * @return string[]
     */
    public function getCountries(): array;

    /**
     * Return the array of States (Codes or Names) this zone contains
     *
     * @return string[]
     */
    public function getAdministrativeAreas(): array;

    /**
     * Return the zip code match
     *
     * @since 2.2
     */
    public function getZipCodeConditionFormula(): ?string;
}
