<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;

class Subdivision extends Model
{
    /**
     * @var int|null Country ID
     */
    public $countryId;
    
    /**
     * @var string|null Name
     */
    public $name;

    /**
     * @var string|null ISO Code
     */
    public $isoCode;

    /**
     * @var string|null Postal Code Pattern
     */
    public $postalCodePattern;
}