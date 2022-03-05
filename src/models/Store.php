<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\helpers\Json;

/**
 * Store record.
 *
 * @property int $id
 * @property int $locationAddressId
 * @property array $countries
 * @property array $administrativeAreas
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class Store extends Model
{
    /**
     * @var int
     */
    public int $id;

    /**
     * @var int|null
     */
    public ?int $locationAddressId = null;

    /**
     * @var array
     */
    private array $_countries = [];

    /**
     * @var array
     */
    private array $_administrativeAreas = [];

    /**
     * @return array
     */
    public function getCountries(): array
    {
        return $this->_countries;
    }

    /**
     * @param string[]|string $countries
     * @return void
     */
    public function setCountries(mixed $countries): void
    {
        $countries = Json::decodeIfJson($countries);
        $this->_countries = $countries;
    }

    /**
     * @return array
     */
    public function getAdministrativeAreas(): array
    {
        return $this->_administrativeAreas;
    }

    /**
     * @param string[]|string $administrativeAreas
     * @return void
     */
    public function setAdministrativeAreas(mixed $administrativeAreas): void
    {
        $administrativeAreas = Json::decodeIfJson($administrativeAreas);
        $this->_administrativeAreas = $administrativeAreas;
    }
}
