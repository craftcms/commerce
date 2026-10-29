<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types\input;

use Craft;
use craft\gql\GqlEntityRegistry;
use craft\gql\types\input\Addresses;
use GraphQL\Type\Definition\InputObjectType;

/**
 * Class Address
 *
 * The input type for an order’s billing or shipping address.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Address extends InputObjectType
{
    /**
     * @return mixed
     */
    public static function getType(): mixed
    {
        $typeName = 'OrderAddressInput';

        return GqlEntityRegistry::getOrCreate($typeName, fn() => new InputObjectType([
            'name' => $typeName,
            'fields' => function() {
                // Same fields as the `addresses` field on users, minus the ID, since order addresses belong to the order
                /** @var InputObjectType $addressesType */
                $addressesType = Addresses::getType();
                $fields = [];
                foreach ($addressesType->getFields() as $name => $field) {
                    if ($name !== 'id') {
                        $fields[$name] = $field->config;
                    }
                }
                return $fields;
            },
            'normalizeValue' => [self::class, 'normalizeValue'],
        ]));
    }

    /**
     * Moves custom field values into a `fields` key, as expected by [[\craft\commerce\elements\Order::setBillingAddress()]].
     *
     * @param mixed $value
     * @return mixed
     */
    public static function normalizeValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $customFields = [];
        foreach (Craft::$app->getAddresses()->getFieldLayout()->getCustomFields() as $field) {
            if (array_key_exists($field->handle, $value)) {
                $customFields[$field->handle] = $value[$field->handle];
                unset($value[$field->handle]);
            }
        }

        if (!empty($customFields)) {
            $value['fields'] = $customFields;
        }

        return $value;
    }
}
