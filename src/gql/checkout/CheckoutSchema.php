<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\checkout;

use Craft;
use craft\commerce\base\GatewayInterface;
use craft\commerce\elements\Order;
use craft\commerce\gateways\Dummy;
use craft\commerce\gateways\Manual;
use craft\commerce\helpers\PaymentForm;
use craft\commerce\models\Checkout;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\ShippingMethodOption;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Throwable;

/**
 * The GraphQL schema behind the hosted checkout.
 *
 * Unlike Craft’s own schemas, this one is not driven by schema components. Every field resolves against the single
 * cart (and customer) the request’s checkout token is bound to, so there is nothing to query or mutate beyond that.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class CheckoutSchema
{
    private const STRIPE_GATEWAY = 'craft\\commerce\\stripe\\gateways\\PaymentIntents';

    /**
     * Creates the schema.
     */
    public static function create(): Schema
    {
        $money = new ObjectType([
            'name' => 'CheckoutMoney',
            'fields' => [
                'amount' => Type::nonNull(Type::float()),
                'formatted' => Type::nonNull(Type::string()),
            ],
        ]);

        $addressFields = [
            'id' => Type::int(),
            'title' => ['type' => Type::string(), 'description' => 'The label of an address-book entry, such as “Home”.'],
            'fullName' => Type::string(),
            'firstName' => Type::string(),
            'lastName' => Type::string(),
            'organization' => Type::string(),
            'organizationTaxId' => Type::string(),
            'addressLine1' => Type::string(),
            'addressLine2' => Type::string(),
            'addressLine3' => Type::string(),
            'locality' => Type::string(),
            'administrativeArea' => Type::string(),
            'postalCode' => Type::string(),
            'countryCode' => Type::string(),
        ];

        $address = new ObjectType([
            'name' => 'CheckoutAddress',
            'fields' => $addressFields,
        ]);

        $addressInputFields = $addressFields;
        unset($addressInputFields['id']);
        $addressInput = new InputObjectType([
            'name' => 'CheckoutAddressInput',
            'fields' => $addressInputFields,
        ]);

        $fieldError = new ObjectType([
            'name' => 'CheckoutError',
            'fields' => [
                'field' => Type::string(),
                'message' => Type::nonNull(Type::string()),
            ],
        ]);

        $lineItem = new ObjectType([
            'name' => 'CheckoutLineItem',
            'fields' => [
                'id' => Type::nonNull(Type::int()),
                'description' => Type::nonNull(Type::string()),
                'sku' => Type::string(),
                'qty' => Type::nonNull(Type::int()),
                'options' => ['type' => Type::listOf(Type::nonNull(Type::string())), 'resolve' => fn(LineItem $i) => self::optionLabels($i)],
                'imageUrl' => ['type' => Type::string(), 'resolve' => fn(LineItem $i) => self::imageUrl($i)],
                'price' => ['type' => Type::nonNull($money), 'resolve' => fn(LineItem $i) => self::money($i->getOrder(), $i->price)],
                'salePrice' => ['type' => Type::nonNull($money), 'resolve' => fn(LineItem $i) => self::money($i->getOrder(), $i->salePrice)],
                'subtotal' => ['type' => Type::nonNull($money), 'resolve' => fn(LineItem $i) => self::money($i->getOrder(), $i->getSubtotal())],
                'total' => ['type' => Type::nonNull($money), 'resolve' => fn(LineItem $i) => self::money($i->getOrder(), $i->getTotal())],
                'isShippable' => ['type' => Type::nonNull(Type::boolean()), 'resolve' => fn(LineItem $i) => $i->getIsShippable()],
            ],
        ]);

        $adjustment = new ObjectType([
            'name' => 'CheckoutAdjustment',
            'fields' => [
                'type' => Type::nonNull(Type::string()),
                'name' => Type::nonNull(Type::string()),
                'description' => Type::string(),
                'included' => Type::nonNull(Type::boolean()),
                'amount' => ['type' => Type::nonNull($money), 'resolve' => fn(OrderAdjustment $a) => self::money($a->getOrder(), $a->amount)],
            ],
        ]);

        $shippingMethod = new ObjectType([
            'name' => 'CheckoutShippingMethod',
            'fields' => [
                'handle' => Type::nonNull(Type::string()),
                'name' => Type::nonNull(Type::string()),
                'price' => ['type' => Type::nonNull($money), 'resolve' => fn(ShippingMethodOption $o, $args, array $ctx) => self::money(CartMutations::loadCart($ctx['number']), $o->getPrice())],
            ],
        ]);

        $gateway = new ObjectType([
            'name' => 'CheckoutGateway',
            'fields' => [
                'id' => Type::nonNull(Type::int()),
                'handle' => Type::nonNull(Type::string()),
                'name' => Type::nonNull(Type::string()),
                'type' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => 'The kind of payment form the checkout shows: `dummy`, `manual` or `stripe`.',
                    'resolve' => fn(GatewayInterface $g) => self::gatewayType($g),
                ],
                'fieldNamespace' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => 'The namespace the payment action expects the gateway’s form fields under.',
                    'resolve' => fn(GatewayInterface $g) => PaymentForm::getPaymentFormNamespace($g->handle),
                ],
                'supportsPartialPayment' => [
                    'type' => Type::nonNull(Type::boolean()),
                    'resolve' => fn(GatewayInterface $g) => $g->supportsPartialPayment(),
                ],
                'publishableKey' => [
                    'type' => Type::string(),
                    'description' => 'Stripe gateways only.',
                    'resolve' => fn(GatewayInterface $g) => self::gatewayType($g) === 'stripe' && method_exists($g, 'getPublishableKey') ? $g->getPublishableKey() : null,
                ],
            ],
        ]);

        $country = new ObjectType([
            'name' => 'CheckoutCountry',
            'fields' => [
                'code' => Type::nonNull(Type::string()),
                'name' => Type::nonNull(Type::string()),
                'usedFields' => Type::listOf(Type::nonNull(Type::string())),
                'requiredFields' => Type::listOf(Type::nonNull(Type::string())),
                'administrativeAreas' => Type::listOf(new ObjectType([
                    'name' => 'CheckoutAdministrativeArea',
                    'fields' => ['code' => Type::nonNull(Type::string()), 'name' => Type::nonNull(Type::string())],
                ])),
            ],
        ]);

        $partialPayment = new ObjectType([
            'name' => 'CheckoutPartialPayment',
            'fields' => [
                'percentage' => Type::nonNull(Type::int()),
                'amount' => ['type' => Type::nonNull($money), 'resolve' => fn(array $p) => $p['money']],
                'token' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => 'Sent as `paymentAmount` to the payment action, which only accepts signed amounts.',
                ],
            ],
        ]);

        $cart = new ObjectType([
            'name' => 'CheckoutCart',
            'fields' => [
                'number' => Type::nonNull(Type::string()),
                'email' => Type::string(),
                'currency' => ['type' => Type::nonNull(Type::string()), 'resolve' => fn(Order $o) => $o->currency],
                'couponCode' => Type::string(),
                'isCompleted' => Type::nonNull(Type::boolean()),
                'totalQty' => ['type' => Type::nonNull(Type::int()), 'resolve' => fn(Order $o) => $o->getTotalQty()],
                'requiresShipping' => [
                    'type' => Type::nonNull(Type::boolean()),
                    'resolve' => fn(Order $o) => (bool)array_filter($o->getLineItems(), fn(LineItem $i) => $i->getIsShippable()),
                ],
                'requireShippingAddress' => ['type' => Type::nonNull(Type::boolean()), 'resolve' => fn(Order $o) => (bool)$o->getStore()->getRequireShippingAddressAtCheckout()],
                'requireBillingAddress' => ['type' => Type::nonNull(Type::boolean()), 'resolve' => fn(Order $o, $args, array $ctx) => $ctx['checkout']->getRequireBillingAddress($o->getStore())],
                'canCheckout' => [
                    'type' => Type::nonNull(Type::boolean()),
                    'description' => 'Whether the checkout will take payment for the order, given its order requirements.',
                    'resolve' => fn(Order $o, $args, array $ctx) => $ctx['checkout']->getBlockedReason($o) === null,
                ],
                'blockedReason' => [
                    'type' => Type::string(),
                    'resolve' => fn(Order $o, $args, array $ctx) => $ctx['checkout']->getBlockedReason($o),
                ],
                'partialPayments' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($partialPayment))),
                    'description' => 'The up-front amounts customers can choose to pay, when the checkout and the store allow partial payments.',
                    'resolve' => fn(Order $o, $args, array $ctx) => self::partialPayments($o, $ctx['checkout']),
                ],
                'storeName' => ['type' => Type::nonNull(Type::string()), 'resolve' => fn(Order $o) => $o->getStore()->getName()],
                'lineItems' => ['type' => Type::nonNull(Type::listOf(Type::nonNull($lineItem))), 'resolve' => fn(Order $o) => $o->getLineItems()],
                'adjustments' => ['type' => Type::nonNull(Type::listOf(Type::nonNull($adjustment))), 'resolve' => fn(Order $o) => $o->getAdjustments() ?? []],
                'itemSubtotal' => ['type' => Type::nonNull($money), 'resolve' => fn(Order $o) => self::money($o, $o->getItemSubtotal())],
                'totalDiscount' => ['type' => Type::nonNull($money), 'resolve' => fn(Order $o) => self::money($o, $o->getTotalDiscount())],
                'totalShippingCost' => ['type' => Type::nonNull($money), 'resolve' => fn(Order $o) => self::money($o, $o->getTotalShippingCost())],
                'totalTax' => ['type' => Type::nonNull($money), 'resolve' => fn(Order $o) => self::money($o, $o->getTotalTax())],
                'totalTaxIncluded' => ['type' => Type::nonNull($money), 'resolve' => fn(Order $o) => self::money($o, $o->getTotalTaxIncluded())],
                'total' => ['type' => Type::nonNull($money), 'resolve' => fn(Order $o) => self::money($o, $o->getTotalPrice())],
                'shippingAddress' => ['type' => $address, 'resolve' => fn(Order $o) => $o->getShippingAddress()],
                'billingAddress' => ['type' => $address, 'resolve' => fn(Order $o) => $o->getBillingAddress()],
                'billingSameAsShipping' => [
                    'type' => Type::nonNull(Type::boolean()),
                    'description' => 'Whether the billing address has the same contents as the shipping address. They’re stored as separate copies, so the IDs differ.',
                    'resolve' => function(Order $o) {
                        $shipping = $o->getShippingAddress();
                        $billing = $o->getBillingAddress();

                        if (!$shipping || !$billing) {
                            return false;
                        }

                        foreach (['firstName', 'lastName', 'organization', 'organizationTaxId', 'addressLine1', 'addressLine2', 'addressLine3', 'locality', 'administrativeArea', 'postalCode', 'countryCode'] as $attribute) {
                            if (($shipping->$attribute ?? null) != ($billing->$attribute ?? null)) {
                                return false;
                            }
                        }

                        return true;
                    },
                ],
                'shippingMethodHandle' => Type::string(),
                'shippingMethods' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($shippingMethod))),
                    'resolve' => fn(Order $o) => array_values(array_filter($o->getAvailableShippingMethodOptions(), fn(ShippingMethodOption $m) => $m->matchesOrder)),
                ],
                'gatewayId' => Type::int(),
                'gateways' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($gateway))),
                    'resolve' => fn(Order $o, $args, array $ctx) => CartMutations::availableGateways($o, $ctx['checkout']),
                ],
                'outstandingBalance' => ['type' => Type::nonNull($money), 'resolve' => fn(Order $o) => self::money($o, $o->getOutstandingBalance())],
            ],
        ]);

        $payload = new ObjectType([
            'name' => 'CheckoutCartPayload',
            'fields' => [
                'cart' => $cart,
                'errors' => Type::nonNull(Type::listOf(Type::nonNull($fieldError))),
            ],
        ]);

        $addressPayload = new ObjectType([
            'name' => 'CheckoutAddressPayload',
            'fields' => [
                'address' => $address,
                'addresses' => Type::nonNull(Type::listOf(Type::nonNull($address))),
                'errors' => Type::nonNull(Type::listOf(Type::nonNull($fieldError))),
            ],
        ]);

        $mutation = fn(array $args, callable $fn): array => [
            'type' => Type::nonNull($payload),
            'args' => $args,
            'resolve' => fn($root, array $a, array $ctx) => CartMutations::run($ctx, fn(Order $cart) => $fn($cart, $a, $ctx)),
        ];

        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'cart' => [
                        'type' => Type::nonNull($cart),
                        'resolve' => fn($root, $args, array $ctx) => CartMutations::loadCart($ctx['number']),
                    ],
                    'countries' => [
                        'type' => Type::nonNull(Type::listOf(Type::nonNull($country))),
                        'resolve' => fn($root, $args, array $ctx) => self::countries(CartMutations::loadCart($ctx['number'])),
                    ],
                    'savedAddresses' => [
                        'type' => Type::nonNull(Type::listOf(Type::nonNull($address))),
                        'description' => 'The signed-in customer’s address book. Empty for guests.',
                        'resolve' => fn($root, $args, array $ctx) => $ctx['user'] ? $ctx['user']->getAddresses()->all() : [],
                    ],
                ],
            ]),
            'mutation' => new ObjectType([
                'name' => 'Mutation',
                'fields' => [
                    'updateLineItem' => $mutation(
                        ['id' => Type::nonNull(Type::int()), 'qty' => Type::nonNull(Type::int())],
                        fn(Order $cart, array $a) => CartMutations::updateLineItem($cart, $a['id'], $a['qty']),
                    ),
                    'removeLineItem' => $mutation(
                        ['id' => Type::nonNull(Type::int())],
                        fn(Order $cart, array $a) => CartMutations::updateLineItem($cart, $a['id'], 0),
                    ),
                    'updateEmail' => $mutation(
                        ['email' => Type::nonNull(Type::string())],
                        fn(Order $cart, array $a, array $ctx) => CartMutations::updateEmail($cart, $a['email'], $ctx['user']),
                    ),
                    'updateAddresses' => $mutation(
                        [
                            'shippingAddress' => $addressInput,
                            'billingAddress' => $addressInput,
                            'shippingAddressId' => Type::int(),
                            'billingAddressId' => Type::int(),
                            'billingSameAsShipping' => Type::boolean(),
                        ],
                        fn(Order $cart, array $a, array $ctx) => CartMutations::updateAddresses($cart, $a, $ctx['user']),
                    ),
                    'updateCoupon' => $mutation(
                        ['code' => Type::string()],
                        fn(Order $cart, array $a) => CartMutations::updateCoupon($cart, $a['code'] ?? null),
                    ),
                    'updateShippingMethod' => $mutation(
                        ['handle' => Type::nonNull(Type::string())],
                        fn(Order $cart, array $a) => CartMutations::updateShippingMethod($cart, $a['handle']),
                    ),
                    'updateGateway' => $mutation(
                        ['id' => Type::nonNull(Type::int())],
                        fn(Order $cart, array $a, array $ctx) => CartMutations::updateGateway($cart, $a['id'], $ctx['checkout']),
                    ),
                    'saveAddress' => [
                        'type' => Type::nonNull($addressPayload),
                        'description' => 'Saves an address to the signed-in customer’s address book.',
                        'args' => ['id' => Type::int(), 'address' => Type::nonNull($addressInput)],
                        'resolve' => fn($root, array $a, array $ctx) => CartMutations::saveAddress($ctx['user'], $a['id'] ?? null, $a['address']),
                    ],
                    'deleteAddress' => [
                        'type' => Type::nonNull($addressPayload),
                        'description' => 'Deletes an address from the signed-in customer’s address book.',
                        'args' => ['id' => Type::nonNull(Type::int())],
                        'resolve' => fn($root, array $a, array $ctx) => CartMutations::deleteAddress($ctx['user'], $a['id']),
                    ],
                ],
            ]),
        ]);
    }

    /**
     * @return array{amount: float, formatted: string}
     */
    private static function money(Order $order, float $amount): array
    {
        return [
            'amount' => $amount,
            'formatted' => Craft::$app->getFormatter()->asCurrency($amount, $order->currency),
        ];
    }

    /**
     * @return string[]
     */
    private static function optionLabels(LineItem $item): array
    {
        $labels = [];
        foreach ($item->getOptions() as $key => $value) {
            if (is_scalar($value) && $value !== '') {
                $labels[] = "$key: $value";
            }
        }

        return $labels;
    }

    private static function imageUrl(LineItem $item): ?string
    {
        $purchasable = $item->getPurchasable();

        if (!$purchasable || !method_exists($purchasable, 'getCheckoutImageUrl')) {
            return null;
        }

        return $purchasable->getCheckoutImageUrl();
    }

    /**
     * @return array<int, array{percentage: int, money: array, token: string}>
     */
    private static function partialPayments(Order $order, Checkout $checkout): array
    {
        if (!$checkout->allowsPartialPayment($order->getStore())) {
            return [];
        }

        $balance = $order->getOutstandingBalance();
        $options = [];

        foreach ($checkout->partialPaymentPercentages as $percentage) {
            $amount = round($balance * $percentage / 100, 2);

            if ($amount <= 0 || $amount >= $balance) {
                continue;
            }

            $options[] = [
                'percentage' => $percentage,
                'money' => self::money($order, $amount),
                'token' => Craft::$app->getSecurity()->hashData((string)$amount),
            ];
        }

        return $options;
    }

    /**
     * Returns the kind of payment form the checkout has for a gateway, or `null` if it isn’t supported.
     */
    public static function gatewayType(GatewayInterface $gateway): ?string
    {
        return match (true) {
            $gateway instanceof Dummy => 'dummy',
            $gateway instanceof Manual => 'manual',
            // Stripe is a separate plugin, so it’s matched by name rather than by class reference
            is_a($gateway, self::STRIPE_GATEWAY) => 'stripe',
            default => null,
        };
    }

    /**
     * @return array[]
     */
    private static function countries(Order $cart): array
    {
        $settings = $cart->getStore()->getSettings();
        $areas = $settings->getAdministrativeAreasListByCountryCode();
        $addresses = Craft::$app->getAddresses();
        $countries = [];

        foreach ($settings->getCountriesList() as $code => $name) {
            $used = $required = null;
            try {
                $format = $addresses->getAddressFormatRepository()->get($code);
                $used = $format->getUsedFields();
                $required = $format->getRequiredFields();
            } catch (Throwable) {
            }

            $countries[] = [
                'code' => $code,
                'name' => $name,
                'usedFields' => $used,
                'requiredFields' => $required,
                'administrativeAreas' => array_map(
                    fn($areaCode, $areaName) => ['code' => $areaCode, 'name' => $areaName],
                    array_keys($areas[$code] ?? []),
                    array_values($areas[$code] ?? []),
                ),
            ];
        }

        return $countries;
    }

    /**
     * Throws a client-visible error.
     */
    public static function fail(string $message): never
    {
        throw new UserError($message);
    }
}
