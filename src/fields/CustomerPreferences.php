<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\commerce\behaviors\CustomerBehavior;
use craft\commerce\Plugin;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\Html;
use Illuminate\Support\Collection;

/**
 * Customer preferences field
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 *
 * @property-read array $contentGqlType
 */
class CustomerPreferences extends Field
{
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Commerce Customer Preference');
    }

    /**
     * @inheritdoc
     */
    public static function hasContentColumn(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function afterElementSave(ElementInterface $element, bool $isNew): void
    {
        // TODO, get values and save them to customer
        //        Plugin::getInstance()->getCustomers()->savePrimaryBillingAddressId($element. $addressId )
        //        Plugin::getInstance()->getCustomers()->savePrimaryShippingAddressId($element. $addressId )
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        return $value;
    }

    /**
     * @inerhitdoc
     */
    protected function inputHtml(mixed $value, ?ElementInterface $element = null): string
    {
        $view = Craft::$app->getView();

        $options = Collection::make($element->getAddresses())->mapWithKeys(function($item) {
            return [$item->id => $item->title];
        })->toArray();

        $handle = $this->handle;
        return $view->namespaceInputs(function() use ($view, $handle, $options) {
        /** @var User|CustomerBehavior $element */
        $primaryBillingAddressIdSelectField = Cp::selectFieldHtml([
            'instructions' => Craft::t('commerce', 'Primary billing address'),
            'name' => 'primaryBillingAddressId',
            'options' => $options,
            'value' => $element->primaryBillingAddressId ?? ArrayHelper::firstKey($options),
        ]);

        $primaryShippingAddressIdSelectField = Cp::selectFieldHtml([
            'instructions' => Craft::t('commerce', 'Primary shipping address'),
            'name' => 'primaryBillingAddressId',
            'options' => $options,
            'value' => $element->primaryShippingAddressId ?? ArrayHelper::firstKey($options),
        ]);

        return Html::beginTag('div', ['class' => '']) .
            Html::beginTag('div', ['class' => '']) .
            $primaryShippingAddressIdSelectField .
            Html::endTag('div') .
            Html::beginTag('div', ['class' => '']) .
            $primaryBillingAddressIdSelectField .
            Html::endTag('div') .
            Html::endTag('div');
        });
    }
}