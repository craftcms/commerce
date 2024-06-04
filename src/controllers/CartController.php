<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
use Craft;
use craft\base\Element;
use craft\commerce\elements\Order;
use craft\commerce\enums\LineItemType;
use craft\commerce\helpers\LineItem as LineItemHelper;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\elements\Address;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\errors\MissingComponentException;
use craft\helpers\UrlHelper;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\mutex\Mutex;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class Cart Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CartController extends BaseFrontEndController
{
    /**
     * @var Order The cart element
     */
    protected Order $_cart;

    /**
     * @var string the name of the cart variable
     */
    protected string $_cartVariable;

    /**
     * @var User|null
     */
    protected ?User $_currentUser = null;

    /**
     * @var Mutex|null
     */
    private ?Mutex $_mutex = null;

    /**
     * @var string|null
     */
    private ?string $_mutexLockName = null;

    /**
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        $this->_cartVariable = Plugin::getInstance()->getSettings()->cartVariable;
        $this->_currentUser = Craft::$app->getUser()->getIdentity();

        parent::init();
    }

    /**
     * Returns the cart as JSON
     *
     * @throws BadRequestHttpException
     */
    public function actionGetCart(): Response
    {
        $this->requireAcceptsJson();

        $this->_cart = $this->_getCart();

        return $this->asSuccess(data: [
            $this->_cartVariable => $this->cartArray($this->_cart),
        ]);
    }

    /**
     * Updates the cart by adding purchasables to the cart, updating line items, or updating various cart attributes.
     *
     * @throws BadRequestHttpException
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws NotFoundHttpException
     * @throws Throwable
     */
    public function actionUpdateCart(): ?Response
    {
        $this->requirePostRequest();
        $isSiteRequest = $this->request->getIsSiteRequest();
        $isConsoleRequest = $this->request->getIsConsoleRequest();
        $currentUser = Craft::$app->getUser()->getIdentity();
        /** @var Plugin $plugin */
        $plugin = Plugin::getInstance();

        $useMutex = (!$isConsoleRequest && Craft::$app->getRequest()->getBodyParam('number')) || (!$isConsoleRequest && $plugin->getCarts()->getHasSessionCartNumber());

        if ($useMutex) {
            $lockOrderNumber = null;
            if ($bodyNumber = Craft::$app->getRequest()->getBodyParam('number')) {
                $lockOrderNumber = $bodyNumber;
            } elseif (!$isConsoleRequest) {
                $request = Craft::$app->getRequest();
                $requestCookies = $request->getCookies();
                $cookieNumber = $requestCookies->getValue($plugin->getCarts()->cartCookie['name']);

                if ($cookieNumber) {
                    $lockOrderNumber = $cookieNumber;
                }
            }

            if ($lockOrderNumber) {
                $this->_mutexLockName = "order:$lockOrderNumber";
                $this->_mutex = Craft::$app->getMutex();
                if (!$this->_mutex->acquire($this->_mutexLockName, 5)) {
                    throw new Exception('Unable to acquire a lock for saving of Order: ' . $lockOrderNumber);
                }
            }
        }

        // Get the cart from the request or from the session.
        // When we are about to update the cart, we consider it a real cart at this point, and want to actually create it in the DB.
        $this->_cart = $this->_getCart(true);

        // Can clear line items when updating the cart
        $clearLineItems = $this->request->getParam('clearLineItems');
        if ($clearLineItems) {
            $this->_cart->setLineItems([]);
        }

        // Can clear notices when updating the cart
        if ($this->request->getParam('clearNotices') !== null) {
            $this->_cart->clearNotices();
        }

        // Set the custom fields submitted
        $this->_cart->setFieldValuesFromRequest('fields');

        // Backwards compatible way of adding to the cart
        if ($purchasableId = $this->request->getParam('purchasableId')) {
            $note = $this->request->getParam('note', '');
            $options = $this->request->getParam('options', []); // TODO Commerce 4 should only support key value only #COM-55
            $qty = (int)$this->request->getParam('qty', 1);

            if ($qty > 0) {
                // We only want a new line item if they cleared the cart
                if ($clearLineItems) {
                    $lineItem = Plugin::getInstance()->getLineItems()->create($this->_cart, compact('purchasableId', 'options'));
                } else {
                    $lineItem = Plugin::getInstance()->getLineItems()->resolveLineItem($this->_cart, $purchasableId, $options);
                }

                // New line items already have a qty of one.
                if ($lineItem->id) {
                    $lineItem->qty += $qty;
                } else {
                    $lineItem->qty = $qty;
                }

                $lineItem->note = $note;

                $this->_cart->addLineItem($lineItem);
            }
        }

        // Add multiple items to the cart
        if ($purchasables = $this->request->getParam('purchasables')) {
            // Initially combine same purchasables
            $purchasablesByKey = [];
            foreach ($purchasables as $key => $purchasable) {
                $purchasableId = $this->request->getParam("purchasables.$key.id");
                $note = $this->request->getParam("purchasables.$key.note", '');
                $options = $this->request->getParam("purchasables.$key.options", []);
                $qty = (int)$this->request->getParam("purchasables.$key.qty", 1);

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

                    // We only want a new line item if they cleared the cart
                    if ($clearLineItems) {
                        $lineItem = Plugin::getInstance()->getLineItems()->create($this->_cart, [
                            'purchasableId' => $purchasable['id'],
                            'options' => $purchasable['options'],
                        ]);
                    } else {
                        $lineItem = Plugin::getInstance()->getLineItems()->resolveLineItem($this->_cart, $purchasable['id'], $purchasable['options']);
                    }

                    // New line items already have a qty of one.
                    if ($lineItem->id) {
                        $lineItem->qty += $purchasable['qty'];
                    } else {
                        $lineItem->qty = $purchasable['qty'];
                    }

                    $lineItem->note = $purchasable['note'];
                    $this->_cart->addLineItem($lineItem);
                }
            }
        }

        // Update multiple line items in the cart
        if ($lineItems = $this->request->getParam('lineItems')) {
            foreach ($lineItems as $key => $lineItem) {
                $lineItem = $this->_getCartLineItemById($key);
                if ($lineItem) {
                    $lineItem->qty = (int)$this->request->getParam("lineItems.$key.qty", $lineItem->qty);
                    $lineItem->note = $this->request->getParam("lineItems.$key.note", $lineItem->note);
                    $lineItem->setOptions($this->request->getParam("lineItems.$key.options", $lineItem->getOptions()));

                    $removeLine = $this->request->getParam("lineItems.$key.remove", false);
                    if (($lineItem->qty !== null && $lineItem->qty == 0) || $removeLine) {
                        $this->_cart->removeLineItem($lineItem);
                    } else {
                        $this->_cart->addLineItem($lineItem);
                    }
                }
            }
        }

        $this->_setAddresses();

        // Setting email only allowed for guest customers
        if (!$currentUser) {
            // Set guest email address onto guest customers order.
            $email = $this->request->getParam('email');
            if ($email && ($this->_cart->getEmail() === null || $this->_cart->getEmail() != $email)) {
                try {
                    $user = Craft::$app->getUsers()->ensureUserByEmail($email);
                    $this->_cart->setCustomer($user);
                } catch (\Exception $e) {
                    $this->_cart->addError('email', $e->getMessage());
                }
            }
        }

        // Set if the customer should be registered on order completion
        $registerUserOnOrderComplete = $this->request->getBodyParam('registerUserOnOrderComplete');
        if ($registerUserOnOrderComplete !== null) {
            $this->_cart->registerUserOnOrderComplete = (bool)$registerUserOnOrderComplete;
        }

        $saveBillingAddressOnOrderComplete = $this->request->getBodyParam('saveBillingAddressOnOrderComplete');
        if ($saveBillingAddressOnOrderComplete !== null) {
            $this->_cart->saveBillingAddressOnOrderComplete = (bool)$saveBillingAddressOnOrderComplete;
        }

        $saveShippingAddressOnOrderComplete = $this->request->getBodyParam('saveShippingAddressOnOrderComplete');
        if ($saveShippingAddressOnOrderComplete !== null) {
            $this->_cart->saveShippingAddressOnOrderComplete = (bool)$saveShippingAddressOnOrderComplete;
        }

        $saveAddressesOnOrderComplete = $this->request->getBodyParam('saveAddressesOnOrderComplete');
        if ($saveAddressesOnOrderComplete !== null) {
            $this->_cart->saveBillingAddressOnOrderComplete = (bool)$saveAddressesOnOrderComplete;
            $this->_cart->saveShippingAddressOnOrderComplete = (bool)$saveAddressesOnOrderComplete;
        }

        // Set payment currency on cart
        if ($currency = $this->request->getParam('paymentCurrency')) {
            $this->_cart->paymentCurrency = $currency;
        }

        // Set Coupon on Cart. Allow blank string to remove coupon
        if (($couponCode = $this->request->getParam('couponCode')) !== null) {
            $this->_cart->couponCode = trim($couponCode) ?: null;
        }

        // Set Payment Gateway on cart
        if ($gatewayId = $this->request->getParam('gatewayId')) {
            if ($plugin->getGateways()->getGatewayById($gatewayId)) {
                $this->_cart->setGatewayId($gatewayId);
            }
        }

        // Submit payment source on cart
        if (($paymentSourceId = $this->request->getParam('paymentSourceId')) !== null) {
            if ($paymentSourceId && $paymentSource = $plugin->getPaymentSources()->getPaymentSourceById($paymentSourceId)) {
                // The payment source can only be used by the same user as the cart's user.
                $cartCustomerId = $this->_cart->getCustomer() ? $this->_cart->getCustomer()->id : null;
                $paymentSourceCustomerId = $paymentSource->getCustomer()?->id;
                $allowedToUsePaymentSource = ($cartCustomerId && $paymentSourceCustomerId && $this->_currentUser && $isSiteRequest && ($paymentSourceCustomerId == $cartCustomerId));
                if ($allowedToUsePaymentSource) {
                    $this->_cart->setPaymentSource($paymentSource);
                }
            } else {
                $this->_cart->setPaymentSource(null);
            }
        }

        // Set Shipping method on cart.
        if ($shippingMethodHandle = $this->request->getParam('shippingMethodHandle')) {
            $this->_cart->shippingMethodHandle = $shippingMethodHandle;
        }

        return $this->_returnCart();
    }

    /**
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     * @since 4.3
     */
    public function actionForgetCart(): ?Response
    {
        $this->requirePostRequest();
        Plugin::getInstance()->getCarts()->forgetCart();
        $this->setSuccessFlash(Craft::t('commerce', 'Cart forgotten.'));
        return $this->redirectToPostedUrl();
    }

    /**
     * @throws BadRequestHttpException
     * @throws Exception
     * @throws MissingComponentException
     * @since 3.1
     */
    public function actionLoadCart(): ?Response
    {
        $carts = Plugin::getInstance()->getCarts();
        $number = $this->request->getParam('number');
        $loadCartRedirectUrl = Plugin::getInstance()->getSettings()->loadCartRedirectUrl ?? '';
        $redirect = UrlHelper::siteUrl($loadCartRedirectUrl);

        if (!$number) {
            $error = Craft::t('commerce', 'A cart number must be specified.');

            if ($this->request->getAcceptsJson()) {
                return $this->asFailure($error);
            }

            $this->setFailFlash($error);
            return $this->request->getIsGet() ? $this->redirect($redirect) : null;
        }

        $cart = Order::find()->number($number)->isCompleted(false)->one();

        if (!$cart) {
            $error = Craft::t('commerce', 'Unable to retrieve cart.');

            if ($this->request->getAcceptsJson()) {
                return $this->asFailure($error);
            }

            $this->setFailFlash($error);
            return $this->request->getIsGet() ? $this->redirect($redirect) : null;
        }

        // If we have a cart, use the site for that cart for the URL redirect.
        $redirect = UrlHelper::siteUrl(path: $loadCartRedirectUrl, siteId: $cart->orderSiteId);

        $carts->forgetCart();
        $carts->setSessionCartNumber($number);

        if ($this->request->getAcceptsJson()) {
            return $this->asSuccess();
        }

        return $this->request->getIsGet() ? $this->redirect($redirect) : $this->redirectToPostedUrl();
    }

    /**
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws HttpException
     * @throws NotFoundHttpException
     * @throws Throwable
     * @since 3.3
     */
    public function actionComplete(): ?Response
    {
        /** @var Plugin $plugin */
        $plugin = Plugin::getInstance();
        $this->requirePostRequest();

        $this->_cart = $this->_getCart();
        $errors = [];

        if (!$this->_cart->getStore()->getAllowCheckoutWithoutPayment()) {
            throw new HttpException(401, Craft::t('commerce', 'You must make a payment to complete the order.'));
        }

        // Check email address exists on order.
        if (empty($this->_cart->email)) {
            $errors['email'] = Craft::t('commerce', 'No customer email address exists on this cart.');
        }

        if ($this->_cart->getStore()->getAllowEmptyCartOnCheckout() && $this->_cart->getIsEmpty()) {
            $errors['lineItems'] = Craft::t('commerce', 'Order can not be empty.');
        }

        if ($this->_cart->getStore()->getRequireShippingMethodSelectionAtCheckout() && !$this->_cart->shippingMethodHandle) {
            $errors['shippingMethodHandle'] = Craft::t('commerce', 'There is no shipping method selected for this order.');
        }

        if ($this->_cart->getStore()->getRequireBillingAddressAtCheckout() && !$this->_cart->billingAddressId) {
            $errors['billingAddressId'] = Craft::t('commerce', 'Billing address required.');
        }

        if ($this->_cart->getStore()->getRequireShippingAddressAtCheckout() && !$this->_cart->shippingAddressId) {
            $errors['shippingAddressId'] = Craft::t('commerce', 'Shipping address required.');
        }

        // Set if the customer should be registered on order completion
        if ($this->request->getBodyParam('registerUserOnOrderComplete')) {
            $this->_cart->registerUserOnOrderComplete = true;
        }

        if ($this->request->getBodyParam('registerUserOnOrderComplete') === 'false') {
            $this->_cart->registerUserOnOrderComplete = false;
        }

        if (!empty($errors)) {
            $this->_cart->addErrors($errors);
        }


        if (empty($errors)) {
            try {
                $completedSuccess = $this->_cart->markAsComplete();
            } catch (\Exception) {
                $completedSuccess = false;
            }

            if (!$completedSuccess) {
                $this->_cart->addError('isComplete', Craft::t('commerce', 'Completing order failed.'));
            }
        }

        return $this->_returnCart();
    }

    /**
     * @param $lineItemId |null
     */
    private function _getCartLineItemById(?int $lineItemId): ?LineItem
    {
        $lineItem = null;

        foreach ($this->_cart->getLineItems() as $item) {
            if ($item->id && $item->id == $lineItemId) {
                $lineItem = $item;
            }
        }

        return $lineItem;
    }

    /**
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    private function _returnCart(): ?Response
    {
        // Allow validation of custom fields when passing this param
        $validateCustomFields = Plugin::getInstance()->getSettings()->validateCartCustomFieldsOnSubmission;

        // Do we want to validate fields submitted
        $customFieldAttributes = [];

        if ($validateCustomFields) {
            // $fields will be null so
            if ($submittedFields = $this->request->getBodyParam('fields')) {
                $this->_cart->setScenario(Element::SCENARIO_LIVE);

                $vp = new VersionParser();
                $currentCraftVersion = $vp->normalize(Craft::$app->getVersion());
                $v44 = $vp->normalize('4.4.0');

                // since Craft 4.4.0, custom fields passed to Element::validate() need to be prepended with 'field:'
                // @TODO remove at next breaking change/version bump
                if (Comparator::greaterThanOrEqualTo($currentCraftVersion, $v44)) {
                    $customFieldAttributes = array_map(
                        fn($value) => 'field:' . $value,
                        array_keys($submittedFields)
                    );
                } else {
                    $customFieldAttributes = array_keys($submittedFields);
                }
            }
        }

        $attributes = array_merge($this->_cart->activeAttributes(), $customFieldAttributes);

        $updateCartSearchIndexes = Plugin::getInstance()->getSettings()->updateCartSearchIndexes;

        // Do not clear errors, as errors could be added to the cart before _returnCart is called.
        if (!$this->_cart->validate($attributes, false) || !Craft::$app->getElements()->saveElement($this->_cart, false, false, $updateCartSearchIndexes)) {
            $error = Craft::t('commerce', 'Unable to update cart.');
            $message = $this->request->getValidatedBodyParam('failMessage') ?? $error;

            if ($this->_mutex && $this->_mutexLockName) {
                $this->_mutex->release($this->_mutexLockName);
            }

            return $this->asModelFailure(
                $this->_cart,
                $message,
                'cart',
                [
                    $this->_cartVariable => $this->cartArray($this->_cart),
                ],
                [
                    $this->_cartVariable => $this->_cart,
                ]
            );
        }

        $cartUpdatedMessage = Craft::t('commerce', 'Cart updated.');
        $message = $this->request->getValidatedBodyParam('successMessage') ?? $cartUpdatedMessage;

        Craft::$app->getUrlManager()->setRouteParams([
            $this->_cartVariable => $this->_cart,
        ]);

        if ($this->_mutex && $this->_mutexLockName) {
            $this->_mutex->release($this->_mutexLockName);
        }

        return $this->asModelSuccess(
            $this->_cart,
            $message,
            'cart',
            [
                $this->_cartVariable => $this->cartArray($this->_cart),
            ]
        );
    }

    /**
     * @param bool $forceSave Force the cart to save to the DB
     *
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws NotFoundHttpException
     * @throws Throwable
     */
    private function _getCart(bool $forceSave = false): Order
    {
        $orderNumber = $this->request->getBodyParam('number');

        if ($orderNumber) {
            // Get the cart from the order number
            $cart = Order::find()->number($orderNumber)->isCompleted(false)->one();

            if (!$cart) {
                throw new NotFoundHttpException('Cart not found');
            }

            return $cart;
        }

        $requestForceSave = (bool)$this->request->getBodyParam('forceSave');
        $doForceSave = ($requestForceSave || $forceSave);

        return Plugin::getInstance()->getCarts()->getCart($doForceSave);
    }

    /**
     * Set addresses on the cart.
     */
    private function _setAddresses(): void
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        $setShippingAddress = true;
        if ($this->request->getParam('clearShippingAddress') !== null) {
            $this->_cart->setShippingAddress(null);
            $this->_cart->sourceShippingAddressId = null;
            $setShippingAddress = false;
        }

        $setBillingAddress = true;
        if ($this->request->getParam('clearBillingAddress') !== null) {
            $this->_cart->setBillingAddress(null);
            $this->_cart->sourceBillingAddressId = null;
            $setBillingAddress = false;
        }

        if ($this->request->getParam('clearAddresses') !== null) {
            $this->_cart->setShippingAddress(null);
            $this->_cart->sourceShippingAddressId = null;
            $this->_cart->setBillingAddress(null);
            $this->_cart->sourceBillingAddressId = null;
            $setBillingAddress = false;
            $setShippingAddress = false;
        }

        // Copy address options
        $shippingIsBilling = $this->request->getParam('shippingAddressSameAsBilling');
        $billingIsShipping = $this->request->getParam('billingAddressSameAsShipping');
        $estimatedBillingIsShipping = $this->request->getParam('estimatedBillingAddressSameAsShipping');

        $shippingAddress = $this->request->getParam('shippingAddress');
        $estimatedShippingAddress = $this->request->getParam('estimatedShippingAddress');
        $billingAddress = $this->request->getParam('billingAddress');
        $estimatedBillingAddress = $this->request->getParam('estimatedBillingAddress');

        // Use an address ID from the customer address book to populate the address
        $shippingAddressId = $this->request->getParam('shippingAddressId');
        $billingAddressId = $this->request->getParam('billingAddressId');

        if ($setShippingAddress) {
            // Shipping address
            if ($shippingAddressId && !$shippingIsBilling) {
                /** @var Address|null $userShippingAddress */
                $userShippingAddress = Collection::make($currentUser->getAddresses())->firstWhere('id', $shippingAddressId);

                // If a user's address ID has been submitted duplicate the address to the order
                if ($userShippingAddress) {
                    $this->_cart->sourceShippingAddressId = $shippingAddressId;

                    /** @var Address $cartShippingAddress */
                    $cartShippingAddress = Craft::$app->getElements()->duplicateElement(
                        $userShippingAddress,
                        ['primaryOwner' => $this->_cart]
                    );
                    $this->_cart->setShippingAddress($cartShippingAddress);

                    if ($billingIsShipping) {
                        $this->_cart->sourceBillingAddressId = $userShippingAddress->id;
                        $this->_cart->setBillingAddress($cartShippingAddress);
                    }
                }
            } elseif ($shippingAddress && !$shippingIsBilling) {
                $this->_cart->sourceShippingAddressId = null;
                $this->_cart->setShippingAddress($shippingAddress);

                if (!empty($shippingAddress['fields']) && $this->_cart->getShippingAddress()) {
                    $this->_cart->getShippingAddress()->setFieldValues($shippingAddress['fields']);
                }

                if ($billingIsShipping) {
                    $this->_cart->sourceBillingAddressId = null;
                    $this->_cart->setBillingAddress($this->_cart->getShippingAddress());
                }
            }
        }

        // Billing address
        if ($setBillingAddress) {
            if ($billingAddressId && !$billingIsShipping) {
                /** @var Address|null $userBillingAddress */
                $userBillingAddress = Collection::make($currentUser->getAddresses())->firstWhere('id', $billingAddressId);

                // If a user's address ID has been submitted duplicate the address to the order
                if ($userBillingAddress) {
                    $this->_cart->sourceBillingAddressId = $billingAddressId;

                    /** @var Address $cartBillingAddress */
                    $cartBillingAddress = Craft::$app->getElements()->duplicateElement(
                        $userBillingAddress,
                        ['primaryOwner' => $this->_cart]
                    );
                    $this->_cart->setBillingAddress($cartBillingAddress);

                    if ($shippingIsBilling) {
                        $this->_cart->sourceShippingAddressId = $userBillingAddress->id;
                        $this->_cart->setShippingAddress($cartBillingAddress);
                    }
                }
            } elseif ($billingAddress && !$billingIsShipping) {
                $this->_cart->sourceBillingAddressId = null;
                $this->_cart->setBillingAddress($billingAddress);

                if (!empty($billingAddress['fields']) && $this->_cart->getBillingAddress()) {
                    $this->_cart->getBillingAddress()->setFieldValues($billingAddress['fields']);
                }

                if ($shippingIsBilling) {
                    $this->_cart->sourceShippingAddressId = null;
                    $this->_cart->setShippingAddress($this->_cart->getBillingAddress());
                }
            }
        }

        // Estimated Shipping Address
        if ($estimatedShippingAddress) {
            if ($this->_cart->estimatedShippingAddressId) {
                if ($address = Address::findOne($this->_cart->estimatedShippingAddressId)) {
                    $address->setAttributes($estimatedShippingAddress);
                    $estimatedShippingAddress = $address;
                }
            }

            $this->_cart->setEstimatedShippingAddress($estimatedShippingAddress);
        }

        // Estimated Billing Address
        if ($estimatedBillingAddress) {
            if ($this->_cart->estimatedBillingAddressId) {
                if ($address = Address::findOne($this->_cart->estimatedBillingAddressId)) {
                    $address->setAttributes($estimatedBillingAddress);
                    $estimatedBillingAddress = $address;
                }
            }

            $this->_cart->setEstimatedBillingAddress($estimatedBillingAddress);
        }


        $this->_cart->billingSameAsShipping = (bool)$billingIsShipping;
        $this->_cart->shippingSameAsBilling = (bool)$shippingIsBilling;
        $this->_cart->estimatedBillingSameAsShipping = (bool)$estimatedBillingIsShipping;

        // Set primary addresses
        if ($setShippingAddress) {
            if ($this->request->getBodyParam('makePrimaryShippingAddress')) {
                $this->_cart->makePrimaryShippingAddress = true;
            }
        }
        if ($setBillingAddress) {
            if ($this->request->getBodyParam('makePrimaryBillingAddress')) {
                $this->_cart->makePrimaryBillingAddress = true;
            }
        }
    }
}
