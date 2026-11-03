<?php

declare(strict_types=1);

namespace craft\commerce\mcp\elements;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\elements\db\UserQuery;
use craft\elements\User;

class OrderAdapter extends CommerceElementAdapter
{
    public static function handle(): string
    {
        return 'orders';
    }

    public static function elementType(): string
    {
        return Order::class;
    }

    protected function createAttributesSchema(): array
    {
        $schema = $this->updateAttributesSchema();
        $schema['properties']['storeId'] = ['type' => 'integer', 'minimum' => 1];
        $schema['required'] = ['storeId'];
        return $schema;
    }

    protected function updateAttributesSchema(): array
    {
        $address = ['type' => 'object', 'additionalProperties' => false, 'properties' => array_fill_keys([
            'firstName', 'lastName', 'fullName', 'organization', 'organizationTaxId', 'countryCode', 'administrativeArea', 'locality', 'dependentLocality', 'postalCode', 'sortingCode', 'addressLine1', 'addressLine2', 'addressLine3',
        ], ['type' => 'string'])];
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'message' => ['type' => ['string', 'null']],
            'couponCode' => ['type' => ['string', 'null']],
            'orderLanguage' => ['type' => 'string'],
            'email' => ['type' => 'string', 'format' => 'email'],
            'shippingMethodHandle' => ['type' => ['string', 'null']],
            'shippingAddress' => $address,
            'billingAddress' => $address,
        ]];
    }

    protected function newElement(array $context, User $actor): ElementInterface
    {
        return new Order([...$context, 'number' => Plugin::getInstance()->getCarts()->generateCartNumber(), 'origin' => Order::ORIGIN_CP]);
    }

    protected function populate(ElementInterface $element, array $attributes, array $fields, User $actor): void
    {
        if ($element instanceof Order && isset($attributes['storeId'])) {
            $element->storeId = $attributes['storeId'];
            unset($attributes['storeId']);
        }
        $this->authorizeSave($actor, $element);
        $email = $attributes['email'] ?? null;
        unset($attributes['email']);
        parent::populate($element, $attributes, $fields, $actor);
        if ($element instanceof Order && $email !== null && $element->getCustomer()?->email !== $email) {
            // Unlike Order::setEmail(), preparing an element must not persist a new customer.
            $customer = User::find()->email($email)->status(UserQuery::STATUS_CREDENTIALED)->one()
                ?? User::find()->email($email)->status(User::STATUS_INACTIVE)->one()
                ?? new User(['email' => $email]);
            $element->setCustomer($customer);
        }
    }

    protected function saveElement(ElementInterface $element, User $actor): ElementInterface
    {
        $this->authorizeSave($actor, $element);
        return Craft::$app->getDb()->transaction(function() use ($element, $actor): ElementInterface {
            if ($element instanceof Order && ($customer = $element->getCustomer()) && !$customer->id) {
                $element->setCustomer(Craft::$app->getUsers()->ensureUserByEmail($customer->email));
            }
            return parent::saveElement($element, $actor);
        });
    }

    protected function criteriaProperties(): array
    {
        return [
            'storeId' => ['type' => 'integer'],
            'customerId' => ['type' => 'integer'],
            'isCompleted' => ['type' => 'boolean'],
            'orderStatusId' => ['type' => ['integer', 'array'], 'items' => ['type' => 'integer']],
            'dateOrdered' => ['type' => ['string', 'array'], 'items' => ['type' => 'string']],
        ];
    }

    protected function fieldSchemaContextSchema(): array
    {
        return ['type' => 'object', 'properties' => ['storeId' => ['type' => 'integer', 'minimum' => 1]], 'required' => ['storeId'], 'additionalProperties' => false];
    }

    protected function notes(): array
    {
        return ['Orders and carts share this type. Use criteria.isCompleted to distinguish them.', 'Order numbers are bearer credentials and are not returned. Use id or uid.', 'Use commerce.orders tools for line items, status changes, completion, email, transactions and fulfillment. Totals and payment state are computed by Commerce.'];
    }
}
