<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use craft\commerce\Plugin;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\RouteToken\RouteTokens;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\SystemMessage\Mailables\SystemMessageMailable;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Helpers\LineItem as LineItemHelper;
use CraftCms\Commerce\Http\Controllers\Concerns\HasCartArray;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;

class CartController
{
    use HasCartArray;
    use RespondsWithFlash;

    private Order $cart;

    private readonly string $cartVariable;

    private ?Lock $mutex = null;

    private ?string $mutexLockName = null;

    public function __construct()
    {
        $this->cartVariable = Plugin::getInstance()->getSettings()->cartVariable;
    }

    public function getCart(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        if ($request->input('peek')) {
            $cart = Plugin::getInstance()->getCarts()->peekCart();
            return $this->asSuccess(data: [
                $this->cartVariable => $cart ? $this->cartArray($cart) : null,
            ]);
        }

        $this->cart = $this->resolveCart($request);

        return $this->asSuccess(data: [
            $this->cartVariable => $this->cartArray($this->cart),
        ]);
    }

    public function updateCart(Request $request): ?Response
    {
        $currentUser = currentUserElement();
        $plugin = Plugin::getInstance();

        $useMutex = $request->input('number') || $plugin->getCarts()->getHasSessionCartNumber();

        if ($useMutex) {
            $lockOrderNumber = $request->input('number') ?: $request->cookie($plugin->getCarts()->cartCookie['name']);

            if ($lockOrderNumber) {
                $this->mutexLockName = "order:$lockOrderNumber";
                $this->mutex = Cache::lock($this->mutexLockName, 5);

                try {
                    $this->mutex->block(5);
                } catch (LockTimeoutException) {
                    abort(500, "Unable to acquire a lock for saving of Order: $lockOrderNumber");
                }
            }
        }

        // Get the cart from the request or from the session.
        $this->cart = $this->resolveCart($request);

        // When we are about to update the cart, we consider it a real cart at this point, and want to actually create it in the DB.
        if ($this->cart->id === null) {
            // Make sure we have a fully saved cart before attempting any mutations.
            $this->cart = $this->resolveCart($request, true);
        }

        // Can clear line items when updating the cart
        $clearLineItems = $request->input('clearLineItems');
        if ($clearLineItems) {
            $this->cart->setLineItems([]);
        }

        // Can clear notices when updating the cart
        if ($request->input('clearNotices') !== null) {
            $this->cart->clearNotices();
        }

        // Set the custom fields submitted
        $this->cart->setFieldValuesFromRequest('fields');

        // Backwards compatible way of adding to the cart
        if ($purchasableId = $request->input('purchasableId')) {
            $note = $request->input('note', '');
            $options = $request->input('options', []); // @TODO Restrict `options` to key/value pairs only in Commerce 6.0 #COM-55
            $qty = (int)$request->input('qty', 1);

            $params = compact('qty', 'note', 'purchasableId', 'options');

            if ($qty > 0) {
                // We only want a new line item if they cleared the cart
                if ($clearLineItems) {
                    $lineItem = Plugin::getInstance()->getLineItems()->create($this->cart, params: $params);
                } else {
                    // we are passing everything into params but need to pass purchasableId and options for now until we refactor
                    $lineItem = Plugin::getInstance()->getLineItems()->resolveLineItem($this->cart, $params['purchasableId'], $params['options'], params: $params);
                }

                // New line items already have a qty of one.
                if ($lineItem->id) {
                    $lineItem->qty += $qty;
                } else {
                    $lineItem->qty = $qty;
                }

                $lineItem->note = $note;

                $this->cart->addLineItem($lineItem);
            }
        }

        // Add multiple items to the cart
        if ($purchasables = $request->input('purchasables')) {
            // Initially combine same purchasables
            $purchasablesByKey = [];
            foreach ($purchasables as $key => $purchasable) {
                $purchasableId = $request->input("purchasables.$key.id");
                $note = $request->input("purchasables.$key.note", '');
                $options = $request->input("purchasables.$key.options", []);
                $qty = (int)$request->input("purchasables.$key.qty", 1);

                $purchasable = [];
                $purchasable['id'] = $purchasableId;
                $purchasable['options'] = is_array($options) ? $options : [];
                $purchasable['note'] = $note;
                $purchasable['qty'] = $qty;

                $key = $purchasableId . '-' . LineItemHelper::generateOptionsSignature($purchasable['options']);
                if (isset($purchasablesByKey[$key])) {
                    $purchasablesByKey[$key]['qty'] += $purchasable['qty'];
                } else {
                    $purchasablesByKey[$key] = $purchasable;
                }
            }

            foreach ($purchasablesByKey as $purchasable) {
                if ($purchasable['id'] == null) {
                    continue;
                }

                // Ignore zero value qty for multi-add forms https://github.com/craftcms/commerce/issues/330#issuecomment-384533139
                if ($purchasable['qty'] > 0) {
                    $params = [
                        'purchasableId' => $purchasable['id'],
                        'options' => $purchasable['options'],
                        'note' => $purchasable['note'],
                        'qty' => $purchasable['qty'],
                    ];

                    // We only want a new line item if they cleared the cart
                    if ($clearLineItems) {
                        $lineItem = Plugin::getInstance()->getLineItems()->create($this->cart, params: $params);
                    } else {
                        $lineItem = Plugin::getInstance()->getLineItems()->resolveLineItem($this->cart, $params['purchasableId'], $params['options'], $params);
                    }

                    // New line items already have a qty of one.
                    if ($lineItem->id) {
                        $lineItem->qty += $purchasable['qty'];
                    } else {
                        $lineItem->qty = $purchasable['qty'];
                    }

                    $lineItem->note = $purchasable['note'];
                    $this->cart->addLineItem($lineItem);
                }
            }
        }

        // Update multiple line items in the cart
        if ($lineItems = $request->input('lineItems')) {
            foreach ($lineItems as $key => $lineItem) {
                $lineItem = $this->getCartLineItemById((int)$key);
                if ($lineItem) {
                    $lineItem->qty = (int)$request->input("lineItems.$key.qty", $lineItem->qty);
                    $lineItem->note = $request->input("lineItems.$key.note", $lineItem->note);
                    $lineItem->setOptions($request->input("lineItems.$key.options", $lineItem->getOptions()));

                    $removeLine = $request->input("lineItems.$key.remove", false);
                    if (($lineItem->qty !== null && $lineItem->qty == 0) || $removeLine) {
                        $this->cart->removeLineItem($lineItem);
                    } else {
                        $this->cart->addLineItem($lineItem);
                    }
                }
            }
        }

        $this->setAddresses($request, $currentUser);

        // Setting email only allowed for guest customers
        if (!$currentUser) {
            // Set guest email address onto guest customers order.
            $email = $request->input('email');
            if ($email && ($this->cart->getEmail() === null || $this->cart->getEmail() != $email)) {
                try {
                    $user = Users::ensureUserByEmail($email);
                    $this->cart->setCustomer($user);
                    if ($user->getIsCredentialed()) {
                        session()->put('commerce:anonymousCartWithCredentialedCustomer:' . $this->cart->number, true);
                    }
                } catch (\Exception $e) {
                    $this->cart->addError('email', $e->getMessage());
                }
            }
        } else {
            session()->forget('commerce:anonymousCartWithCredentialedCustomer:' . $this->cart->number);
        }

        // Set if the customer should be registered on order completion
        $registerUserOnOrderComplete = $request->input('registerUserOnOrderComplete');
        if ($registerUserOnOrderComplete !== null) {
            $this->cart->registerUserOnOrderComplete = (bool)$registerUserOnOrderComplete;
        }

        $saveBillingAddressOnOrderComplete = $request->input('saveBillingAddressOnOrderComplete');
        if ($saveBillingAddressOnOrderComplete !== null) {
            $this->cart->saveBillingAddressOnOrderComplete = (bool)$saveBillingAddressOnOrderComplete;
        }

        $saveShippingAddressOnOrderComplete = $request->input('saveShippingAddressOnOrderComplete');
        if ($saveShippingAddressOnOrderComplete !== null) {
            $this->cart->saveShippingAddressOnOrderComplete = (bool)$saveShippingAddressOnOrderComplete;
        }

        $saveAddressesOnOrderComplete = $request->input('saveAddressesOnOrderComplete');
        if ($saveAddressesOnOrderComplete !== null) {
            $this->cart->saveBillingAddressOnOrderComplete = (bool)$saveAddressesOnOrderComplete;
            $this->cart->saveShippingAddressOnOrderComplete = (bool)$saveAddressesOnOrderComplete;
        }

        // Set payment currency on cart
        if ($currency = $request->input('paymentCurrency')) {
            $this->cart->paymentCurrency = $currency;
        }

        // Set Coupon on Cart. Allow blank string to remove coupon
        if (($couponCode = $request->input('couponCode')) !== null) {
            $this->cart->couponCode = trim($couponCode) ?: null;
        }

        // Set Payment Gateway on cart
        if ($gatewayId = $request->input('gatewayId')) {
            if ($plugin->getGateways()->getGatewayById($gatewayId)) {
                $this->cart->setGatewayId($gatewayId);
            }
        }

        // Submit payment source on cart
        if (($paymentSourceId = $request->input('paymentSourceId')) !== null) {
            if ($paymentSourceId && $paymentSource = $plugin->getPaymentSources()->getPaymentSourceById($paymentSourceId)) {
                // The payment source can only be used by the same user as the cart's user.
                $cartCustomerId = $this->cart->getCustomer()?->id;
                $paymentSourceCustomerId = $paymentSource->getCustomer()?->id;
                $allowedToUsePaymentSource = ($cartCustomerId && $paymentSourceCustomerId && $currentUser && !$request->isCpRequest() && ($paymentSourceCustomerId == $cartCustomerId));
                if ($allowedToUsePaymentSource) {
                    $this->cart->setPaymentSource($paymentSource);
                }
            } else {
                $this->cart->setPaymentSource(null);
            }
        }

        // Set Shipping method on cart.
        if ($shippingMethodHandle = $request->input('shippingMethodHandle')) {
            $this->cart->shippingMethodHandle = $shippingMethodHandle;
        }

        return $this->returnCart($request);
    }

    public function forgetCart(): Response
    {
        Plugin::getInstance()->getCarts()->forgetCart();

        return $this->asSuccess(t('Cart forgotten.', category: 'commerce'));
    }

    public function loadCart(Request $request): ?Response
    {
        $carts = Plugin::getInstance()->getCarts();
        $number = $request->input('number');
        $token = $request->input('code');
        $loadCartRedirectUrl = Plugin::getInstance()->getSettings()->loadCartRedirectUrl ?? '';
        $redirect = Url::siteUrl($loadCartRedirectUrl);

        if (!$number) {
            $error = t('A cart number must be specified.', category: 'commerce');
            if ($request->expectsJson()) {
                return $this->asFailure($error);
            }
            return $request->isMethod('get') ? redirect($redirect) : null;
        }

        $cart = Order::find()->number($number)->isCompleted(false)->one();

        if (!$cart) {
            $error = t('Unable to retrieve cart.', category: 'commerce');
            if ($request->expectsJson()) {
                return $this->asFailure($error);
            }
            return $request->isMethod('get') ? redirect($redirect) : null;
        }

        // Carts without email or addresses don't need token validation
        $hasEmail = (bool)$cart->getEmail();
        $hasAddresses = $cart->billingAddressId || $cart->shippingAddressId;

        if ($hasEmail || $hasAddresses) {
            $currentUser = currentUserElement();
            $hasValidToken = false;

            // Check token if provided
            if ($token) {
                $tokenData = app(RouteTokens::class)->getTokenRoute($token);

                if (!$tokenData || !isset($tokenData[1]['cartNumber']) || $tokenData[1]['cartNumber'] !== $number) {
                    $error = t('The cart recovery link is invalid. Please request a new one.', category: 'commerce');
                    $challengeUrl = Url::actionUrl('commerce/cart/email-challenge', ['number' => $number]);
                    if ($request->expectsJson()) {
                        return $this->asFailure($error, ['challengeUrl' => $challengeUrl]);
                    }
                    return redirect($challengeUrl);
                }

                $hasValidToken = true;
            }

            // Check permissions if no valid token
            if (!$hasValidToken) {
                $challengeUrl = Url::actionUrl('commerce/cart/email-challenge', ['number' => $number]);
                if ($currentUser) {
                    $isCartCustomer = $cart->getCustomer() && $cart->getCustomer()->id === $currentUser->id;
                    if (!$isCartCustomer) {
                        if ($request->expectsJson()) {
                            return $this->asFailure(
                                t('You do not have permission to load this cart.', category: 'commerce'),
                                ['challengeUrl' => $challengeUrl]
                            );
                        }
                        return redirect($challengeUrl);
                    }
                } else {
                    if ($request->expectsJson()) {
                        return $this->asFailure(
                            t('You must be logged in or provide a valid token to load this cart.', category: 'commerce'),
                            ['challengeUrl' => $challengeUrl]
                        );
                    }
                    return redirect($challengeUrl);
                }
            }
        }

        $redirect = Url::siteUrl(path: $loadCartRedirectUrl, siteId: $cart->orderSiteId);
        $carts->forgetCart();
        $carts->setSessionCartNumber($number);

        // Reaching this point means the cart was loaded via a valid token or by an authorized user.
        // Authorize this session to use the cart even if it belongs to a credentialed user who isn't
        // (yet) logged in. If the loader is logged in, Carts::getCart() will acquire the cart to their
        // account on the next retrieval.
        session()->put('commerce:anonymousCartWithCredentialedCustomer:' . $number, true);

        if ($request->expectsJson()) {
            return $this->asSuccess();
        }

        return $request->isMethod('get') ? redirect($redirect) : $this->redirectToPostedUrl();
    }

    public function complete(Request $request): ?Response
    {
        $this->cart = $this->resolveCart($request);
        $errors = [];

        abort_unless($this->cart->getStore()->getAllowCheckoutWithoutPayment(), 401, t('You must make a payment to complete the order.', category: 'commerce'));

        $lock = Cache::lock('completeOrder', 10);

        try {
            $lock->block(10);
        } catch (LockTimeoutException) {
            $this->cart->addError('isComplete', t('Unable to complete order: another request is already in progress.', category: 'commerce'));
            return $this->returnCart($request);
        }

        // Check email address exists on order.
        if (empty($this->cart->email)) {
            $errors['email'] = t('No customer email address exists on this cart.', category: 'commerce');
        }

        if ($this->cart->getStore()->getAllowEmptyCartOnCheckout() && $this->cart->getIsEmpty()) {
            $errors['lineItems'] = t('Order can not be empty.', category: 'commerce');
        }

        if ($this->cart->getStore()->getRequireShippingMethodSelectionAtCheckout() && !$this->cart->shippingMethodHandle) {
            $errors['shippingMethodHandle'] = t('There is no shipping method selected for this order.', category: 'commerce');
        }

        if ($this->cart->getStore()->getRequireBillingAddressAtCheckout() && !$this->cart->billingAddressId) {
            $errors['billingAddressId'] = t('Billing address required.', category: 'commerce');
        }

        if ($this->cart->getStore()->getRequireShippingAddressAtCheckout() && !$this->cart->shippingAddressId) {
            $errors['shippingAddressId'] = t('Shipping address required.', category: 'commerce');
        }

        // Set if the customer should be registered on order completion
        if ($request->input('registerUserOnOrderComplete')) {
            $this->cart->registerUserOnOrderComplete = true;
        }

        if ($request->input('registerUserOnOrderComplete') === 'false') {
            $this->cart->registerUserOnOrderComplete = false;
        }

        if (!empty($errors)) {
            $this->cart->addErrors($errors);
        }

        if (empty($errors)) {
            try {
                $completedSuccess = $this->cart->markAsComplete();
            } catch (\Exception) {
                $completedSuccess = false;
            }

            if (!$completedSuccess) {
                $this->cart->addError('isComplete', t('Completing order failed.', category: 'commerce'));
            }
        }

        $lock->release();

        return $this->returnCart($request);
    }

    public function emailChallenge(Request $request): string
    {
        $number = $request->query('number');
        abort_unless($number, 400, 'Cart number required');

        $cart = Order::find()->number($number)->isCompleted(false)->one();
        abort_if(!$cart || !$cart->getEmail(), 404, 'Cart not found');

        return $this->renderCartEmailChallenge($cart, $number);
    }

    public function cartChallenge(Request $request): string|Response
    {
        $cartNumberHash = $request->input('cartNumberHash');
        abort_unless($cartNumberHash, 400, 'Cart number hash is required');

        try {
            $cartNumber = Crypt::decrypt($cartNumberHash);
        } catch (DecryptException) {
            $cartNumber = false;
        }
        abort_if($cartNumber === false, 400, 'Invalid cart number hash');

        $cart = Order::find()->number($cartNumber)->isCompleted(false)->one();
        abort_if(!$cart, 404, 'Cart not found');

        $loadCartUrl = Plugin::getInstance()->getCarts()->getLoadCartUrl($cart);

        try {
            $sent = Mail::to($cart->email)->send(new SystemMessageMailable(
                key: 'commerce_cart_recovery',
                variables: [
                    'link' => $loadCartUrl,
                    'cart' => $cart,
                ],
            ));
        } catch (Throwable) {
            $sent = null;
        }

        if ($sent === null) {
            session()->flash('error', t('Failed to send email. Please try again.', category: 'commerce'));
            return $this->renderCartEmailChallenge($cart, $cartNumber);
        }

        return redirect(Url::actionUrl('commerce/cart/cart-sent', ['hash' => $cartNumberHash]));
    }

    public function cartSent(Request $request): string
    {
        $cartNumberHash = $request->query('hash');
        abort_unless($cartNumberHash, 400, 'Hash parameter required');

        try {
            $cartNumber = Crypt::decrypt($cartNumberHash);
        } catch (DecryptException) {
            $cartNumber = false;
        }
        abort_if($cartNumber === false, 400, 'Invalid hash parameter');

        $cart = Order::find()->number($cartNumber)->isCompleted(false)->one();
        abort_if(!$cart, 404, 'Cart not found');

        return pageTemplate('commerce/_cart/email-sent', [
            'email' => $cart->getMaskedEmail(),
        ], TemplateMode::Cp);
    }

    private function getCartLineItemById(?int $lineItemId): ?LineItem
    {
        foreach ($this->cart->getLineItems() as $item) {
            if ($item->id && $item->id == $lineItemId) {
                return $item;
            }
        }

        return null;
    }

    private function returnCart(Request $request): ?Response
    {
        $updateCartSearchIndexes = Plugin::getInstance()->getSettings()->updateCartSearchIndexes;

        // Do not clear errors, as errors could be added to the cart before returnCart is called.
        if (!$this->cart->validate(null, false) || !Elements::saveElement($this->cart, false, false, $updateCartSearchIndexes)) {
            $error = t('Unable to update cart.', category: 'commerce');
            $message = $request->input('failMessage') ?? $error;

            $this->mutex?->release();

            $data = [
                $this->cartVariable => $this->cartArray($this->cart),
            ];

            $originalCart = Order::find()->id($this->cart->id)->isCompleted(null)->one();

            if ($originalCart && $this->cart->number == $originalCart->number) {
                $data['original' . ucfirst($this->cartVariable)] = $this->cartArray($originalCart);
            }

            return $this->asModelFailure(
                $this->cart,
                $message,
                'cart',
                $data,
            );
        }

        $cartUpdatedMessage = t('Cart updated.', category: 'commerce');
        $message = $request->input('successMessage') ?? $cartUpdatedMessage;

        $this->mutex?->release();

        return $this->asModelSuccess(
            $this->cart,
            $message,
            'cart',
            [
                $this->cartVariable => $this->cartArray($this->cart),
            ]
        );
    }

    private function resolveCart(Request $request, bool $forceSave = false): Order
    {
        $orderNumber = $request->input('number');

        if ($orderNumber) {
            $cart = Order::find()->number($orderNumber)->isCompleted(false)->one();
            abort_if($cart === null, 404, 'Cart not found');

            return $cart;
        }

        $doForceSave = $forceSave || (bool)$request->input('forceSave');

        return $this->cart = Plugin::getInstance()->getCarts()->getCart($doForceSave);
    }

    private function setAddresses(Request $request, ?User $currentUser): void
    {
        $setShippingAddress = true;
        if ($request->input('clearShippingAddress') !== null) {
            $this->cart->setShippingAddress(null);
            $this->cart->sourceShippingAddressId = null;
            $setShippingAddress = false;
        }

        $setBillingAddress = true;
        if ($request->input('clearBillingAddress') !== null) {
            $this->cart->setBillingAddress(null);
            $this->cart->sourceBillingAddressId = null;
            $setBillingAddress = false;
        }

        if ($request->input('clearAddresses') !== null) {
            $this->cart->setShippingAddress(null);
            $this->cart->sourceShippingAddressId = null;
            $this->cart->setBillingAddress(null);
            $this->cart->sourceBillingAddressId = null;
            $setBillingAddress = false;
            $setShippingAddress = false;
        }

        // Copy address options
        $shippingIsBilling = $request->input('shippingAddressSameAsBilling');
        $billingIsShipping = $request->input('billingAddressSameAsShipping');
        $estimatedBillingIsShipping = $request->input('estimatedBillingAddressSameAsShipping');

        $shippingAddress = $request->input('shippingAddress');
        $estimatedShippingAddress = $request->input('estimatedShippingAddress');
        $billingAddress = $request->input('billingAddress');
        $estimatedBillingAddress = $request->input('estimatedBillingAddress');

        // Use an address ID from the customer address book to populate the address
        $shippingAddressId = $request->input('shippingAddressId');
        $billingAddressId = $request->input('billingAddressId');

        if ($setShippingAddress) {
            // Shipping address
            if ($shippingAddressId && !$shippingIsBilling) {
                /** @var Address|null $userShippingAddress */
                $userShippingAddress = collect($currentUser?->getAddresses())->firstWhere('id', $shippingAddressId);

                // If a user's address ID has been submitted duplicate the address to the order
                if ($userShippingAddress) {
                    $this->cart->sourceShippingAddressId = $shippingAddressId;
                    $validShippingAddress = $userShippingAddress->validate();

                    if (!$validShippingAddress) {
                        $this->cart->addModelErrors($userShippingAddress, 'shippingAddress');
                    } else {
                        /** @var Address $cartShippingAddress */
                        $cartShippingAddress = Elements::duplicateElement($userShippingAddress, [
                            'primaryOwner' => $this->cart,
                            'owner' => $this->cart,
                        ]);
                        $this->cart->setShippingAddress($cartShippingAddress);
                    }

                    if ($billingIsShipping) {
                        $this->cart->sourceBillingAddressId = $userShippingAddress->id;

                        if ($validShippingAddress) {
                            $this->cart->setBillingAddress($cartShippingAddress);
                        }
                    }
                }
            } elseif ($shippingAddress && !$shippingIsBilling) {
                $this->cart->sourceShippingAddressId = null;
                $this->cart->setShippingAddress($shippingAddress);

                if (!empty($shippingAddress['fields']) && $this->cart->getShippingAddress()) {
                    $this->cart->getShippingAddress()->setFieldValues($shippingAddress['fields']);
                }

                if ($billingIsShipping) {
                    $this->cart->sourceBillingAddressId = null;
                    $this->cart->setBillingAddress($this->cart->getShippingAddress());
                }
            }
        }

        // Billing address
        if ($setBillingAddress) {
            if ($billingAddressId && !$billingIsShipping) {
                /** @var Address|null $userBillingAddress */
                $userBillingAddress = collect($currentUser?->getAddresses())->firstWhere('id', $billingAddressId);

                // If a user's address ID has been submitted duplicate the address to the order
                if ($userBillingAddress) {
                    $this->cart->sourceBillingAddressId = $billingAddressId;
                    $validBillingAddress = $userBillingAddress->validate();

                    if (!$validBillingAddress) {
                        $this->cart->addModelErrors($userBillingAddress, 'billingAddress');
                    } else {
                        /** @var Address $cartBillingAddress */
                        $cartBillingAddress = Elements::duplicateElement($userBillingAddress, [
                            'primaryOwner' => $this->cart,
                            'owner' => $this->cart,
                        ]);
                        $this->cart->setBillingAddress($cartBillingAddress);
                    }

                    if ($shippingIsBilling) {
                        $this->cart->sourceShippingAddressId = $userBillingAddress->id;

                        if ($validBillingAddress) {
                            $this->cart->setShippingAddress($cartBillingAddress);
                        }
                    }
                }
            } elseif ($billingAddress && !$billingIsShipping) {
                $this->cart->sourceBillingAddressId = null;
                $this->cart->setBillingAddress($billingAddress);

                if (!empty($billingAddress['fields']) && $this->cart->getBillingAddress()) {
                    $this->cart->getBillingAddress()->setFieldValues($billingAddress['fields']);
                }

                if ($shippingIsBilling) {
                    $this->cart->sourceShippingAddressId = null;
                    $this->cart->setShippingAddress($this->cart->getBillingAddress());
                }
            }
        }

        // Estimated Shipping Address
        if ($estimatedShippingAddress) {
            if ($this->cart->estimatedShippingAddressId) {
                if ($address = Address::findOne($this->cart->estimatedShippingAddressId)) {
                    $address->setAttributes($estimatedShippingAddress);
                    $estimatedShippingAddress = $address;
                }
            }

            $this->cart->setEstimatedShippingAddress($estimatedShippingAddress);
        }

        // Estimated Billing Address
        if ($estimatedBillingAddress) {
            if ($this->cart->estimatedBillingAddressId) {
                if ($address = Address::findOne($this->cart->estimatedBillingAddressId)) {
                    $address->setAttributes($estimatedBillingAddress);
                    $estimatedBillingAddress = $address;
                }
            }

            $this->cart->setEstimatedBillingAddress($estimatedBillingAddress);
        }

        $this->cart->billingSameAsShipping = (bool)$billingIsShipping;
        $this->cart->shippingSameAsBilling = (bool)$shippingIsBilling;
        $this->cart->estimatedBillingSameAsShipping = (bool)$estimatedBillingIsShipping;

        // Set primary addresses
        if ($setShippingAddress) {
            $makePrimaryShippingAddress = $request->input('makePrimaryShippingAddress');
            if ($makePrimaryShippingAddress !== null) {
                $this->cart->makePrimaryShippingAddress = (bool)$makePrimaryShippingAddress;
            }
        }
        if ($setBillingAddress) {
            $makePrimaryBillingAddress = $request->input('makePrimaryBillingAddress');
            if ($makePrimaryBillingAddress !== null) {
                $this->cart->makePrimaryBillingAddress = (bool)$makePrimaryBillingAddress;
            }
        }
    }

    private function renderCartEmailChallenge(Order $cart, string $cartNumber): string
    {
        return pageTemplate('commerce/_cart/email-challenge', [
            'cart' => $cart,
            'cartNumber' => $cartNumber,
        ], TemplateMode::Cp);
    }
}
