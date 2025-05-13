<?php

/**
 * @link http://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license http://craftcms.com/license
 */

namespace craft\commerce\behaviors;

use craft\commerce\base\HasStoreInterface;
use craft\commerce\helpers\Currency;
use craft\commerce\Plugin;
use craft\events\DefineFieldsEvent;
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
 *
 * @property string $defaultCurrency
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
    public array $currencyAttributes;

    /**
     * @var ?string default currency
     * @uses setDefaultCurrency()
     * @uses getDefaultCurrency()
     */
    private ?string $_defaultCurrency;

    /**
     * @var array mapping of attribute => currency if the default is not desired
     */
    public array $attributeCurrencyMap = [];

    /**
     * @inheritdoc
     */
    public function events(): array
    {
        return [
            \craft\base\Model::EVENT_DEFINE_FIELDS => 'defineFields',
        ];
    }

    /**
     * @param DefineFieldsEvent $event
     * @return void
     */
    public function defineFields(DefineFieldsEvent $event): void
    {
        $fields = $event->fields;
        $event->fields = array_merge($fields, $this->currencyFields());
    }

    /**
     * @inheritdoc
     */
    public function __call($name, $params)
    {
        if (StringHelper::endsWith($name, 'AsCurrency', false)) {
            $attributeName = $this->_attributeNameWithoutAsCurrency($name);
            if (in_array($attributeName, $this->currencyAttributes, false)) {
                $amount = $this->owner->$attributeName ?? 0;

                $currency = $params[0] ?? $this->attributeCurrencyMap[$attributeName] ?? $this->getDefaultCurrency();
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
    public function hasMethod($name): bool
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
                $currency = $this->attributeCurrencyMap[$attributeName] ?? $this->getDefaultCurrency();
                return Currency::formatAsCurrency($amount, $currency);
            }
        }
        return parent::__get($name);
    }

    /**
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true): bool
    {
        if (StringHelper::endsWith($name, 'AsCurrency', false)) {
            $attributeName = $this->_attributeNameWithoutAsCurrency($name);
            if ($checkVars && in_array($attributeName, $this->currencyAttributes, false)) {
                return true;
            }
        }

        return parent::canGetProperty($name, $checkVars);
    }

    public function currencyFields(): array
    {
        $fields = [];

        foreach ($this->currencyAttributes as $attribute) {
            $fields[$attribute . 'AsCurrency'] = function($model, $attribute) {
                $attributeName = $this->_attributeNameWithoutAsCurrency($attribute);
                $amount = $this->owner->$attributeName ?? 0;
                $currency = $this->attributeCurrencyMap[$attributeName] ?? $this->getDefaultCurrency();
                return Currency::formatAsCurrency($amount, $currency);
            };
        }

        return $fields;
    }

    /**
     * @param ?string $value
     * @return void
     * @since 5.0.0
     */
    public function setDefaultCurrency(?string $value): void
    {
        $this->_defaultCurrency = $value;
    }

    /**
     * @return string
     * @since 5.0.0
     */
    public function getDefaultCurrency(): string
    {
        // Should always be the owners currency
        if ($this->owner instanceof HasStoreInterface) {
            $this->_defaultCurrency = $this->owner->getStore()->getCurrency();
        }

        // Let's default to the current store primary currency
        if (!isset($this->_defaultCurrency)) {
            $this->_defaultCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        }

        return $this->_defaultCurrency;
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
