<?php

/**
 * @link http://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license http://craftcms.com/license
 */

namespace craft\commerce\behaviors;

use craft\commerce\helpers\Currency;
use craft\helpers\StringHelper;
use yii\base\Behavior;

/**
 * CurrencyAttributeBehavior provides an ability of automatic add *AsCurrency() methods to your models for currency attributes.
 *
 * You should specify exact attribute types via [[currencyAttributes]].
 *
 * For example:
 *
 * ```php
 * use craft\commerce\behaviors\CurrencyAttributeBehavior;
 *
 * class LineItem extends Model
 * {
 *     public function behaviors()
 *     {
 *         return [
 *             'asCurrency' => [
 *                 'class' => CurrencyAttributeBehavior::className(),
 *                 'currencyAttributes' => [
 *                     'salePrice'
 *                     'subtotal'
 *                 ],
 *                 'defaultCurrency' => 'usd'
 *             ],
 *         ];
 *     }
 *
 *     // ...
 * }
 * ```
 *
 */
class CurrencyAttributeBehavior extends Behavior
{
    /**
     * @var array currency attributes
     * For example:
     *
     * ```php
     * [
     *  'salePrice'
     *  'subtotal'
     * ]
     * ```
     */
    public $currencyAttributes;

    /**
     * @var string default currency
     */
    public $defaultCurrency;

    /**
     * @var array mapping of attribute => currency if the default is not desired
     */
    public $attributeCurrencyMap = [];

    /**
     * @inheritdoc
     */
    public function __call($name, $params)
    {
        if (StringHelper::endsWith($name, 'AsCurrency', false)) {
            $attributeName = $this->_attributeNameWithoutAsCurrency($name);
            if (in_array($attributeName, $this->currencyAttributes, false)) {
                $amount = $this->owner->$attributeName ?? 0;

                $currency = $params[0] ?? $this->attributeCurrencyMap[$attributeName] ?? $this->defaultCurrency;
                $convert = $params[1] ?? false;
                $format = $params[2] ?? true;
                $stripZeros = $params[3] ?? false;

                return Currency::formatAsCurrency($amount, $currency, $convert, $format, $stripZeros);
            }
        }

        return parent::__call($name, $params);
    }

    /**
     * @inheritdoc
     */
    public function hasMethod($name)
    {
        if (StringHelper::endsWith($name, 'AsCurrency', false)) {
            $attributeName = $this->_attributeNameWithoutAsCurrency($name);
            if (in_array($attributeName, $this->currencyAttributes, false)) {
                return true;
            }
        }
        return parent::hasMethod($name);
    }

    /**
     * @inheritdoc
     */
    public function __isset($name)
    {
        if (StringHelper::endsWith($name, 'AsCurrency', false)) {
            $attributeName = $this->_attributeNameWithoutAsCurrency($name);
            if (in_array($attributeName, $this->currencyAttributes, false)) {
                return true;
            }
        }

        return parent::__isset($name);
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        if (StringHelper::endsWith($name, 'AsCurrency', false)) {
            $attributeName = $this->_attributeNameWithoutAsCurrency($name);
            if (in_array($attributeName, $this->currencyAttributes, false)) {
                $amount = $this->owner->$attributeName ?? 0;
                $currency = $this->attributeCurrencyMap[$attributeName] ?? $this->defaultCurrency;
                return Currency::formatAsCurrency($amount, $currency);
            }
        }
        return parent::__get($name);
    }

    /**
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true)
    {
        if (StringHelper::endsWith($name, 'AsCurrency', false)) {
            $attributeName = $this->_attributeNameWithoutAsCurrency($name);
            if ($checkVars && in_array($attributeName, $this->currencyAttributes, false)) {
                return true;
            }
        }

        return parent::canGetProperty($name, $checkVars);
    }

    /**
     * @return mixed
     */
    public function currencyFields()
    {
        $fields = [];

        foreach ($this->currencyAttributes as $attribute) {
            $fields[$attribute . 'AsCurrency'] = function($model, $attribute) {
                $attributeName = $this->_attributeNameWithoutAsCurrency($attribute);
                $amount = $this->owner->$attributeName ?? 0;
                $currency = $this->attributeCurrencyMap[$attributeName] ?? $this->defaultCurrency;
                return Currency::formatAsCurrency($amount, $currency);
            };
        }

        return $fields;
    }

    /**
     * @param $name
     * @return string
     */
    private function _attributeNameWithoutAsCurrency($name): string
    {
        if (StringHelper::endsWithAny($name, ['AsCurrency'], false)) {
            $name = StringHelper::removeRight($name, 'AsCurrency');
        }

        return $name;
    }
}
