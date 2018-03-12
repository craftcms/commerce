<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\commerce\models\Country;
use craft\commerce\models\State;

/**
 * Zone Interface defines the common interface to be implemented by zones in commerce.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
interface AddressZoneInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Whether this zone is based on countries only.
     *
     * @return bool
     */
    public function getIsCountryBased(): bool;

    /**
     * Return the array of Commerce Country IDs this zone contains
     *
     * @return int[]
     */
    public function getCountryIds(): array;

    /**
     * Return the array of Commerce State IDs this zone contains
     *
     * @return int[]
     */
    public function getStateIds(): array;

    /**
     * Return the array of Commerce States this zone contains
     *
     * @return State[]
     */
    public function getStates(): array;

    /**
     * Return the array of Commerce Countries this zone contains
     *
     * @return Country[]
     */
    public function getCountries(): array;
}
