<?php

namespace craft\commerce\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\commerce\Plugin;
use craft\elements\User;
use craft\commerce\models\Customer as CustomerModel;

/**
 * Class Customer
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.fieldtypes
 * @since     1.0
 */
class Customer extends Field
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc IComponentType::getName()
     *
     * @return string
     */
    public function getName(): string
    {
        return Craft::t('commerce', 'Commerce Customer Info');
    }

    /**
     * @inheritdoc
     */
    public static function hasContentColumn(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        if (!($element instanceof User)) {
            return '<span style="color: #da5a47">'.Craft::t('commerce', 'Commerce Customer Info field is for user profiles only.').'</span>';
        }

        return Craft::$app->getView()->render('commerce/_fieldtypes/customer/_input', [
            'customer' => $this->getCustomer()
        ]);
    }

    /**
     * @param mixed                 $value
     * @param ElementInterface|null $element
     *
     * @return CustomerModel
     */
    public function normalizeValue($value, ElementInterface $element = null): CustomerModel
    {
        if ($element && $element->id) {
            return Plugin::getInstance()->getCustomers()->getCustomerByUserId($element->id);
        }

        return null;
    }
}
