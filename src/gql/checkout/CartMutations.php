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
use craft\commerce\models\Checkout;
use craft\commerce\Plugin;
use craft\elements\Address;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use GraphQL\Error\UserError;
use Throwable;

/**
 * The operations the hosted checkout can perform on a cart.
 *
 * Each one is deliberately narrow: customers can change quantities, contact details, addresses, the coupon, the
 * shipping method and the gateway, and nothing else. Prices, statuses and ownership are never writable here.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class CartMutations
{
    /**
     * @var Order[] Carts that have already been loaded this request, indexed by number
     */
    private static array $_carts = [];

    /**
     * Loads the cart a checkout token is bound to.
     *
     * @throws UserError if the cart doesn’t exist or has already been completed
     */
    public static function loadCart(string $number): Order
    {
        $cart = self::$_carts[$number] ??= Plugin::getInstance()->getOrders()->getOrderByNumber($number);

        if (!$cart) {
            throw new UserError('This cart no longer exists.');
        }

        return $cart;
    }

    /**
     * Runs a callback against the cart, then saves it and returns a payload for the checkout.
     *
     * The callback modifies the cart, and returns the names of the attributes that should be validated (an empty
     * array skips validation). It may also return `false` after adding errors to the cart, to reject the change.
     *
     * @param array $context The checkout context (`number` and `user`)
     * @param callable(Order): (string[]|false) $callback
     * @return array{cart: Order, errors: array}
     */
    public static function run(array $context, callable $callback): array
    {
        $number = $context['number'];
        $lockName = "order:$number";
        $mutex = Craft::$app->getMutex();

        if (!$mutex->acquire($lockName, 10)) {
            throw new UserError('Your cart is busy. Please try again.');
        }

        try {
            unset(self::$_carts[$number]);
            $cart = self::loadCart($number);

            if ($cart->isCompleted) {
                throw new UserError('This order has already been placed.');
            }

            $cart->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
            $attributes = $callback($cart);

            if ($attributes === false || ($attributes !== [] && !$cart->validate($attributes, false))) {
                $errors = self::errors($cart);
                // Reload so the response reflects what’s stored, not the rejected change
                unset(self::$_carts[$number]);

                return ['cart' => self::loadCart($number), 'errors' => $errors];
            }

            $updateSearchIndex = Plugin::getInstance()->getSettings()->updateCartSearchIndexes;
            if (!Craft::$app->getElements()->saveElement($cart, false, false, $updateSearchIndex)) {
                $errors = self::errors($cart);
                unset(self::$_carts[$number]);

                return ['cart' => self::loadCart($number), 'errors' => $errors];
            }

            self::$_carts[$number] = $cart;

            return ['cart' => $cart, 'errors' => []];
        } finally {
            $mutex->release($lockName);
        }
    }

    /**
     * Returns the gateways the customer can pay with. The checkout has its own payment form for each gateway type
     * it supports, so gateways it doesn’t know about are left out. A checkout can narrow them further.
     *
     * @return GatewayInterface[]
     */
    public static function availableGateways(Order $cart, ?Checkout $checkout = null): array
    {
        // Gateways are first narrowed by their own settings (enabled for the front end, and their order conditions),
        // then by the ones this checkout allows
        return Plugin::getInstance()->getGateways()->getAllCustomerEnabledGatewaysAndAvailableForUseWithOrder($cart)
            ->filter(fn(GatewayInterface $g) => CheckoutSchema::gatewayType($g) !== null && (!$checkout || $checkout->allowsGateway($g)))
            ->values()
            ->all();
    }

    /**
     * Sets a line item’s quantity. A quantity of `0` removes it.
     */
    public static function updateLineItem(Order $cart, int $id, int $qty): array|false
    {
        $lineItem = ArrayHelper::firstWhere($cart->getLineItems(), 'id', $id);

        if (!$lineItem) {
            $cart->addError('lineItems', 'That item isn’t in your cart.');
            return false;
        }

        if ($qty <= 0) {
            $cart->removeLineItem($lineItem);
        } else {
            $lineItem->qty = $qty;
        }

        return ['lineItems'];
    }

    /**
     * Sets a guest’s email address. Signed-in customers keep their account’s email.
     */
    public static function updateEmail(Order $cart, string $email, ?User $user): array|false
    {
        if ($user) {
            return [];
        }

        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $cart->addError('email', 'Enter a valid email address.');
            return false;
        }

        if ($cart->getEmail() === $email) {
            return [];
        }

        try {
            $customer = Craft::$app->getUsers()->ensureUserByEmail($email);
            $cart->setCustomer($customer);

            // Mirrors CartController: guests using a credentialed account’s email must verify before recovering the cart
            if ($customer->getIsCredentialed()) {
                Craft::$app->getSession()->set('commerce:anonymousCartWithCredentialedCustomer:' . $cart->number, true);
            }
        } catch (Throwable $e) {
            Craft::warning($e->getMessage(), __METHOD__);
            $cart->addError('email', 'We couldn’t use that email address.');
            return false;
        }

        return [];
    }

    public static function updateCoupon(Order $cart, ?string $code): array|false
    {
        $code = trim((string)$code) ?: null;
        $cart->couponCode = $code;

        if ($code) {
            // An unusable coupon is dropped with a notice rather than a validation error, so surface it as one
            $cart->validate(['couponCode'], false);

            if ($cart->couponCode === null) {
                $notice = ArrayHelper::firstWhere($cart->getNotices(), 'attribute', 'couponCode');
                // The notice reads “Coupon removed: …”, which doesn’t suit a code that was never applied
                $reason = $notice ? trim(preg_replace('/^Coupon removed:\s*/i', '', $notice->message)) : '';
                $cart->addError('couponCode', $reason !== '' ? $reason : 'That discount code isn’t valid.');

                return false;
            }
        }

        return ['couponCode'];
    }

    public static function updateShippingMethod(Order $cart, string $handle): array
    {
        $cart->shippingMethodHandle = $handle;

        return ['shippingMethodHandle'];
    }

    public static function updateGateway(Order $cart, int $id, ?Checkout $checkout = null): array|false
    {
        $gateway = ArrayHelper::firstWhere(self::availableGateways($cart, $checkout), 'id', $id);

        if (!$gateway) {
            $cart->addError('gatewayId', 'That payment method isn’t available.');
            return false;
        }

        $cart->setGatewayId($id);

        return ['gatewayId'];
    }

    /**
     * Sets the cart’s addresses, either from submitted values or from the signed-in customer’s address book.
     *
     * @param array<string, mixed> $args `shippingAddress`, `billingAddress`, `shippingAddressId`, `billingAddressId`, `billingSameAsShipping`
     */
    public static function updateAddresses(Order $cart, array $args, ?User $user): array
    {
        $attributes = [];
        $sameAsShipping = $args['billingSameAsShipping'] ?? false;

        foreach (['shipping', 'billing'] as $type) {
            $idKey = "{$type}AddressId";
            $dataKey = "{$type}Address";

            if (empty($args[$idKey]) && !array_key_exists($dataKey, $args)) {
                continue;
            }

            if ($type === 'billing' && $sameAsShipping) {
                continue;
            }

            if (!empty($args[$idKey])) {
                $source = $user ? ArrayHelper::firstWhere($user->getAddresses(), 'id', $args[$idKey]) : null;

                if (!$source) {
                    $cart->addError($dataKey, 'That saved address couldn’t be found.');
                    continue;
                }

                /** @var Address $copy */
                $copy = Craft::$app->getElements()->duplicateElement($source, [
                    'primaryOwner' => $cart,
                    'owner' => $cart,
                ]);
                $cart->{$type === 'shipping' ? 'sourceShippingAddressId' : 'sourceBillingAddressId'} = $source->id;
                $type === 'shipping' ? $cart->setShippingAddress($copy) : $cart->setBillingAddress($copy);
            } elseif ($args[$dataKey] === null) {
                $type === 'shipping' ? $cart->setShippingAddress(null) : $cart->setBillingAddress(null);
            } else {
                $cart->{$type === 'shipping' ? 'sourceShippingAddressId' : 'sourceBillingAddressId'} = null;
                $type === 'shipping' ? $cart->setShippingAddress($args[$dataKey]) : $cart->setBillingAddress($args[$dataKey]);
            }

            $attributes[] = $dataKey;
        }

        if ($sameAsShipping && $cart->getShippingAddress()) {
            $cart->sourceBillingAddressId = $cart->sourceShippingAddressId;
            $cart->setBillingAddress($cart->getShippingAddress());
            $attributes[] = 'billingAddress';
        }

        return $attributes ?: [];
    }

    /**
     * Saves an address to the customer’s address book.
     *
     * @return array{address: Address|null, addresses: array, errors: array}
     */
    public static function saveAddress(?User $user, ?int $id, array $values): array
    {
        $user = self::requireUser($user);
        $address = null;

        if ($id) {
            $address = ArrayHelper::firstWhere($user->getAddresses(), 'id', $id);

            if (!$address) {
                throw new UserError('That address couldn’t be found.');
            }
        } else {
            $address = new Address();
            $address->setPrimaryOwner($user);
            $address->setOwner($user);
        }

        $address->setAttributes($values);

        // Craft doesn't require anything beyond a country, so apply the country's own rules for what an address needs
        if ($missing = self::missingAddressFields($address)) {
            return ['address' => null, 'addresses' => $user->getAddresses()->all(), 'errors' => $missing];
        }

        // Address-book entries need a label, which customers aren’t asked for at checkout
        if (trim((string)$address->title) === '') {
            $address->title = trim((string)($address->addressLine1 ?: $address->locality ?: $address->getCountryCode()));
        }

        if (!Craft::$app->getElements()->saveElement($address)) {
            return ['address' => null, 'addresses' => $user->getAddresses()->all(), 'errors' => self::errors($address)];
        }

        return ['address' => $address, 'addresses' => self::freshAddresses($user), 'errors' => []];
    }

    /**
     * Deletes an address from the customer’s address book.
     *
     * @return array{address: null, addresses: array, errors: array}
     */
    public static function deleteAddress(?User $user, int $id): array
    {
        $user = self::requireUser($user);
        $address = ArrayHelper::firstWhere($user->getAddresses(), 'id', $id);

        if (!$address) {
            throw new UserError('That address couldn’t be found.');
        }

        try {
            Craft::$app->getElements()->deleteElement($address);
        } catch (Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);
            throw new UserError('That address couldn’t be deleted.');
        }

        return ['address' => null, 'addresses' => self::freshAddresses($user), 'errors' => []];
    }

    /**
     * Returns an error for each field the address’s country requires that hasn’t been filled in.
     *
     * @return array<int, array{field: string, message: string}>
     */
    private static function missingAddressFields(Address $address): array
    {
        $countryCode = $address->getCountryCode();

        if (!$countryCode) {
            return [['field' => 'countryCode', 'message' => 'Choose a country.']];
        }

        $labels = [
            'givenName' => ['firstName', 'First name'],
            'familyName' => ['lastName', 'Last name'],
            'organization' => ['organization', 'Company'],
            'addressLine1' => ['addressLine1', 'Address'],
            'addressLine2' => ['addressLine2', 'Apartment, suite, etc.'],
            'locality' => ['locality', 'City'],
            'administrativeArea' => ['administrativeArea', 'State / province'],
            'postalCode' => ['postalCode', 'Postal code'],
        ];

        try {
            $required = Craft::$app->getAddresses()->getAddressFormatRepository()->get($countryCode)->getRequiredFields();
        } catch (Throwable) {
            return [];
        }

        $errors = [];
        foreach ($required as $field) {
            // Depending on the version of the addressing library, fields are strings or enums
            $format = CheckoutSchema::addressFieldName($field);
            [$attribute, $label] = $labels[$format] ?? [null, null];

            if ($attribute && trim((string)$address->$attribute) === '') {
                $errors[] = ['field' => $attribute, 'message' => "$label is required."];
            }
        }

        return $errors;
    }

    private static function requireUser(?User $user): User
    {
        if (!$user) {
            throw new UserError('You need to be signed in to manage saved addresses.');
        }

        return $user;
    }

    /**
     * @return array
     */
    private static function freshAddresses(User $user): array
    {
        return array_values(Address::find()->owner($user)->all());
    }

    /**
     * @return array<int, array{field: string|null, message: string}>
     */
    private static function errors(object $model): array
    {
        $errors = [];

        foreach ($model->getErrors() as $attribute => $messages) {
            foreach ((array)$messages as $message) {
                // Nested errors, such as an address’s, are keyed by their own attribute names
                foreach ((array)$message as $nested) {
                    $errors[] = ['field' => $attribute, 'message' => (string)$nested];
                }
            }
        }

        return $errors;
    }
}
