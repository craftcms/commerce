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
use craft\errors\ElementNotFoundException;
use Throwable;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Customer Address Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CustomerAddressesController extends BaseFrontEndController
{
    // Public Methods
    // =========================================================================

    /**
     * Add New Address
     *
     * @return Response
     * @throws Exception
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $addressId = Craft::$app->getRequest()->getBodyParam('address.id');

        $customerService = Plugin::getInstance()->getCustomers();
        $customerId = $customerService->getCustomerId();
        $addressIds = $customerService->getAddressIds($customerId);
        $customer = $customerService->getCustomerById($customerId);

        // Ensure any incoming ID is within the editable addresses for a customer:
        if ($addressId && !in_array($addressId, $addressIds, false)) {
            $error = Craft::t('commerce', 'Not allowed to edit that address.');
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson(['error' => $error]);
            }
            Craft::$app->getSession()->setError($error);

            return null;
        }

        // If we make it past the ownership check, and there was actually an ID passed, look it up:
        if ($addressId) {
            $address = Plugin::getInstance()->getAddresses()->getAddressById($addressId);
        } else {
            // Otherwise, set up a new Address model to populate:
            $address = new Address();
        }

        // Attributes we want to merge into the Address model's data:
        $attrs = [
            'attention',
            'title',
            'firstName',
            'lastName',
            'address1',
            'address2',
            'city',
            'zipCode',
            'phone',
            'alternativePhone',
            'businessName',
            'businessTaxId',
            'businessId',
            'countryId',
            'stateId',
            'stateName',
            'stateValue'
        ];

        // Set Address attributes to new values (if provided) or use the existing ones for values that aren’t sent:
        foreach ($attrs as $attr) {
            $address->$attr = Craft::$app->getRequest()->getBodyParam("address.{$attr}", $address->$attr);
        }

        if ($customerService->saveAddress($address)) {
            $request = Craft::$app->getRequest();
            $updatedCustomer = false;

            if ($request->getBodyParam('makePrimaryBillingAddress') || !$customer->primaryBillingAddressId) {
                $customer->primaryBillingAddressId = $address->id;
                $updatedCustomer = true;
            }

            if ($request->getBodyParam('makePrimaryShippingAddress') || !$customer->primaryShippingAddressId) {
                $customer->primaryShippingAddressId = $address->id;
                $updatedCustomer = true;
            }

            if ($updatedCustomer && !$customerService->saveCustomer($customer)) {
                $error = Craft::t('commerce', 'Unable to update primary address.');
                if (Craft::$app->getRequest()->getAcceptsJson()) {
                    return $this->asJson(['error' => $error]);
                }
                Craft::$app->getSession()->setError($error);

                return null;
            }

            // Refresh the cart, if this address was being used.
            $cart = Plugin::getInstance()->getCarts()->getCart(true);
            if ($cart->shippingAddressId == $address->id) {
                $cart->setFieldValuesFromRequest('fields');
                Craft::$app->getElements()->saveElement($cart);
            }

            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson(['success' => true, 'address' => $address]);
            }

            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Address saved.'));

            $this->redirectToPostedUrl();
        } else {
            $errorMsg = Craft::t('commerce', 'Could not save address.');

            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'error' => $errorMsg,
                    'errors' => $address->errors
                ]);
            }

            Craft::$app->getSession()->setError($errorMsg);

            Craft::$app->getUrlManager()->setRouteParams([
                'address' => $address,
            ]);
        }

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
    public function actionDelete()
    {
        $this->requirePostRequest();

        $customerId = Plugin::getInstance()->getCustomers()->getCustomerId();
        $addressIds = Plugin::getInstance()->getCustomers()->getAddressIds($customerId);
        $cart = Plugin::getInstance()->getCarts()->getCart(true);

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (!$id) {
            throw new HttpException(400);
        }

        // current customer is the owner of the address
        if (in_array($id, $addressIds, false) && Plugin::getInstance()->getAddresses()->deleteAddressById($id)) {
            if ($cart->shippingAddressId == $id) {
                $cart->shippingAddressId = null;
            }

            if ($cart->billingAddressId == $id) {
                $cart->billingAddressId = null;
            }

            Craft::$app->getElements()->saveElement($cart);

            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson(['success' => true]);
            }

            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Address removed.'));
            return $this->redirectToPostedUrl();
        } else {
            $error = Craft::t('commerce', 'Could not delete address.');
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson(['error' => $error]);
        }

        Craft::$app->getSession()->setError($error);

        return null;
    }
}
