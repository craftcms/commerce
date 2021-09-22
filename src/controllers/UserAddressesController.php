<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\Address;
use craft\commerce\Plugin;
use craft\commerce\services\Addresses;
use craft\errors\ElementNotFoundException;
use craft\helpers\ArrayHelper;
use Throwable;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class User Addresses Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class UserAddressesController extends BaseController
{
    /**
     * Save User Address
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $addressId = $this->request->getBodyParam('address.id');

        $user = Craft::$app->getUser()->getIdentity();
        $addressIds = ArrayHelper::getColumn($user->getAddresses(), 'id');

        // Ensure any incoming ID is within the editable addresses for a customer:
        if ($addressId && !in_array($addressId, $addressIds, false)) {
            $error = Craft::t('commerce', 'Not allowed to edit that address.');
            if ($this->request->getAcceptsJson()) {
                return $this->asJson(['error' => $error]);
            }
            $this->setFailFlash($error);

            return null;
        }

        // If we make it past the ownership check, and there was actually an ID passed, look it up:
        if ($addressId) {
            $address = Plugin::getInstance()->getAddresses()->getAddressById($addressId);
        } else {
            // Otherwise, set up a new Address model to populate:
            $address = Craft::createObject(Address::class);
        }

        // Attributes we want to merge into the Address model's data:
        $attrs = [
            'attention',
            'title',
            'firstName',
            'lastName',
            'fullName',
            'address1',
            'address2',
            'address3',
            'city',
            'zipCode',
            'phone',
            'alternativePhone',
            'label',
            'notes',
            'businessName',
            'businessTaxId',
            'businessId',
            'countryId',
            'stateValue',
            'custom1',
            'custom2',
            'custom3',
            'custom4',
        ];
        // Set Address attributes to new values (if provided) or use the existing ones for values that aren’t sent:
        foreach ($attrs as $attr) {
            $tmpAttr = $this->request->getBodyParam("address.{$attr}", $address->$attr);
            $tmpAttr = ($attr === 'countryId' && $tmpAttr === '') ? null : $tmpAttr;
            $address->$attr = $tmpAttr;
        }

        if (Plugin::getInstance()->getUsers()->saveAddress($address, $user)) {
            try {
                if ($this->request->getBodyParam('makePrimaryBillingAddress')) {
                    Plugin::getInstance()->getAddresses()->setPrimaryAddressByAddressIdAndType($address->id, Addresses::ADDRESS_TYPE_BILLING);
                }

                if ($this->request->getBodyParam('makePrimaryShippingAddress')) {
                    Plugin::getInstance()->getAddresses()->setPrimaryAddressByAddressIdAndType($address->id, Addresses::ADDRESS_TYPE_SHIPPING);
                }

            } catch (\Exception $e) {
                $error = Craft::t('commerce', 'Unable to update primary address.');
                if ($this->request->getAcceptsJson()) {
                    return $this->asJson(['error' => $error]);
                }
                $this->setFailFlash($error);

                return null;
            }

            // Refresh the cart, if this address was being used.
            $cart = Plugin::getInstance()->getCarts()->getCart(true);
            if ($cart->shippingAddressId == $address->id || $cart->billingAddressId == $address->id) {
                $cart->setFieldValuesFromRequest('fields');

                // We only want to update search indexes if the order is a cart and the developer wants to keep cart search indexes updated.
                $updateCartSearchIndexes = Plugin::getInstance()->getSettings()->updateCartSearchIndexes;
                $updateSearchIndex = ($cart->isCompleted || $updateCartSearchIndexes);

                Craft::$app->getElements()->saveElement($cart, false, false, $updateSearchIndex);
            }

            if ($this->request->getAcceptsJson()) {
                return $this->asJson(['success' => true, 'address' => $address]);
            }

            $this->setSuccessFlash(Craft::t('commerce', 'Address saved.'));

            return $this->redirectToPostedUrl($address);
        }

        $errorMsg = Craft::t('commerce', 'Could not save address.');

        if ($this->request->getAcceptsJson()) {
            return $this->asJson([
                'error' => $errorMsg,
                'errors' => $address->errors,
            ]);
        }

        $this->setFailFlash($errorMsg);

        Craft::$app->getUrlManager()->setRouteParams([
            'address' => $address,
        ]);

        return null;
    }

    /**
     * Remove Address
     *
     * @return Response
     * @throws Exception
     * @throws HttpException
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws BadRequestHttpException
     */
    public function actionDelete(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $currentUser = Craft::$app->getUser()->getIdentity();

        $addressIds = ArrayHelper::getColumn($currentUser->getAddresses(), 'id');
        $cart = Plugin::getInstance()->getCarts()->getCart(true);

        $id = $this->request->getRequiredBodyParam('id');

        if (!$id) {
            throw new HttpException(400);
        }

        // current customer is the owner of the address
        if (in_array($id, $addressIds, false) && Plugin::getInstance()->getAddresses()->deleteAddressById($id)) {
            if ($cart->shippingAddressId == $id) {
                $cart->removeShippingAddress();
            }

            if ($cart->billingAddressId == $id) {
                $cart->removeBillingAddress();
            }

            if ($cart->estimatedShippingAddressId == $id) {
                $cart->removeEstimatedShippingAddress();
            }

            if ($cart->estimatedBillingAddressId == $id) {
                $cart->removeEstimatedBillingAddress();
            }

            // We only want to update search indexes if the order is a cart and the developer wants to keep cart search indexes updated.
            $updateCartSearchIndexes = Plugin::getInstance()->getSettings()->updateCartSearchIndexes;
            $updateSearchIndex = ($cart->isCompleted || $updateCartSearchIndexes);

            Craft::$app->getElements()->saveElement($cart, false, false, $updateSearchIndex);

            if ($this->request->getAcceptsJson()) {
                return $this->asJson(['success' => true]);
            }

            $this->setSuccessFlash(Craft::t('commerce', 'Address removed.'));
            return $this->redirectToPostedUrl();
        }

        $error = Craft::t('commerce', 'Could not delete address.');

        if ($this->request->getAcceptsJson()) {
            return $this->asJson(['error' => $error]);
        }

        $this->setFailFlash($error);

        return null;
    }

    /**
     * Return customer addresses.
     *
     * @return Response
     * @throws BadRequestHttpException
     * @since 3.2.7
     */
    public function actionGetAddresses(): Response
    {
        $this->requireAcceptsJson();
        $this->requireLogin();

        $currentUser = Craft::$app->getUser()->getIdentity();

        return $this->asJson(['success' => true, 'addresses' => $currentUser->getAddresses()]);
    }
}
