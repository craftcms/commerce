<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\Address;
use craft\commerce\Plugin;
use yii\base\Exception;
use yii\web\HttpException;

/**
 * Class Customer Address Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class CustomerAddressesController extends BaseFrontEndController
{
    /**
     * Add New Address
     *
     * @throws Exception
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $address = new Address();

        $attrs = [
            'id',
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
        foreach ($attrs as $attr) {
            $address->$attr = Craft::$app->getRequest()->getParam('address.'.$attr);
        }

        $customerId = Plugin::getInstance()->getCustomers()->getCustomerId();
        $addressIds = Plugin::getInstance()->getCustomers()->getAddressIds($customerId);

        // if this is an existing address
        if ($address->id) {
            if (!in_array($address->id, $addressIds)) {
                $error = Craft::t('commerce', 'Not allowed to edit that address.');
                if (Craft::$app->getRequest()->getAcceptsJson()) {
                    $this->asJson(['error' => $error]);
                }
                Craft::$app->getUser()->setFlash('error', $error);

                return;
            }
        }

        if (Plugin::getInstance()->getCustomers()->saveAddress($address)) {

            // Refresh the cart, if this address was being used.
            $cart = Plugin::getInstance()->getCart()->getCart();
            if ($cart->shippingAddressId == $address->id) {
                $cart->setFieldValuesFromRequest('fields');
                Craft::$app->getElements()->saveElement($cart);
            }

            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson(['success' => true]);
            }
            $this->redirectToPostedUrl();
        } else {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson(['error' => $address->errors]);
            }
            Craft::$app->getUrlManager()->setRouteParams([
                'address' => $address,
            ]);
        }
    }

    /**
     * Remove Address
     *
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();

        $customerId = Plugin::getInstance()->getCustomers()->getCustomerId();
        $addressIds = Plugin::getInstance()->getCustomers()->getAddressIds($customerId);
        $cart = Plugin::getInstance()->getCart()->getCart();

        $id = Craft::$app->getRequest()->getParam('id', 0);

        if (!$id) {
            throw new HttpException(400);
        }

        // current customer is the owner of the address
        if (in_array($id, $addressIds)) {
            if (Plugin::getInstance()->getAddresses()->deleteAddressById($id)) {

                if ($cart->shippingAddressId == $id) {
                    $cart->shippingAddressId = null;
                }

                if ($cart->billingAddressId == $id) {
                    $cart->billingAddressId = null;
                }

                Craft::$app->getElements()->saveElement($cart);

                if (Craft::$app->getRequest()->getAcceptsJson()) {
                    $this->asJson(['success' => true]);
                }
                Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Address removed.'));
                $this->redirectToPostedUrl();
            } else {
                $error = Craft::t('commerce', 'Could not delete address.');
            }
        } else {
            $error = Craft::t('commerce', 'Could not delete address.');
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            $this->asJson(['error' => $error]);
        }
        Craft::$app->getUser()->setFlash('error', $error);
    }
}
