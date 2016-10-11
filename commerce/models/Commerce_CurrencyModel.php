<?php
namespace Craft;

use JsonSerializable;

/**
 * Currency model.
 *
 * @property string $alphabeticCode
 * @property string $currency
 * @property string $entity
 * @property int    $minorUnit
 * @property int    $numericCode
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.2
 */
class Commerce_CurrencyModel extends BaseModel implements JsonSerializable
{

    /**
     * @return string
     */
    function __toString()
    {
        return $this->iso;
    }

    /**
     * @return array
     */
    function jsonSerialize()
    {
        $data = [];
        $data['alphabeticCode'] = $this->getAttribute('alphabeticCode');
        $data['currency'] = $this->getAttribute('currency');
        $data['entity'] = $this->getAttribute('entity');
        $data['minorUnit'] = $this->getAttribute('minorUnit');
        $data['numericCode'] = $this->getAttribute('numericCode');

        return $data;
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return ['alphabeticCode' => AttributeType::String,
                'currency'       => AttributeType::String,
                'entity'         => AttributeType::String,
                'minorUnit'      => AttributeType::Number,
                'numericCode'    => AttributeType::Number];
    }

}