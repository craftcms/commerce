<?php
namespace Craft;

/**
 * Class Commerce_CustomerAddressesController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_CustomerAddressesController extends Commerce_BaseFrontEndController
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

        $address = new Commerce_AddressModel;
        $address->attributes = craft()->request->getPost('address');

        $customerId = craft()->commerce_customers->getCustomerId();
        $addressIds = craft()->commerce_customers->getAddressIds($customerId);

        // if this is an existing address
        if ($address->id) {
            if (!in_array($address->id, $addressIds)) {
                $error = Craft::t('Not allowed to edit that address.');
                if (craft()->request->isAjaxRequest) {
                    $this->returnJson(['error' => $error]);
                }
                craft()->userSession->setFlash('error', $error);
                return;
            }
        }

        if (craft()->commerce_customers->saveAddress($address)) {
            if (craft()->request->isAjaxRequest) {
                $this->returnJson(['success' => true]);
            }
            $this->redirectToPostedUrl();
        } else {
            if (craft()->request->isAjaxRequest) {
                $this->returnJson(['error' => $address->getAllErrors()]);
            }
            craft()->urlManager->setRouteVariables([
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

        $customerId = craft()->commerce_customers->getCustomerId();
        $addressIds = craft()->commerce_customers->getAddressIds($customerId);
        $cart = craft()->commerce_cart->getCart();

        $id = craft()->request->getPost('id', 0);

        if (!$id) {
            throw new HttpException(400);
        }

        // current customer is the owner of the address
        if (in_array($id, $addressIds)) {
            if (craft()->commerce_addresses->deleteAddressById($id)) {

                if ($cart->shippingAddressId == $id) {
                    $cart->shippingAddressId = null;
                }

                if ($cart->billingAddressId == $id) {
                    $cart->billingAddressId = null;
                }

                craft()->commerce_orders->saveOrder($cart);

                if (craft()->request->isAjaxRequest) {
                    $this->returnJson(['success' => true]);
                }
                $this->redirectToPostedUrl();
            }
            craft()->userSession->setNotice(Craft::t('Address removed.'));
        } else {
            $error = Craft::t('Not allowed to remove that address.');
            if (craft()->request->isAjaxRequest) {
                $this->returnJson(['error' => $error]);
            }
            craft()->userSession->setFlash('error', $error);
        }
    }
}
