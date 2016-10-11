<?php
namespace Craft;

/**
 * Class Commerce_AddressesController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_AddressesController extends Commerce_BaseCpController
{

    /**
     * @throws HttpException
     */
    public function init()
    {
        craft()->userSession->requirePermission('commerce-manageOrders');
        parent::init();
    }

    /**
     * Edit Address
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['address']))
        {
            if (empty($variables['addressId']))
            {
                throw new HttpException(404);
            }

            $id = $variables['addressId'];
            $variables['address'] = craft()->commerce_addresses->getAddressById($id);

            if (!$variables['address'])
            {
                throw new HttpException(404);
            }
        }

        $variables['title'] = Craft::t('Edit Address', ['id' => $variables['addressId']]);

        $variables['countries'] = craft()->commerce_countries->getAllCountriesListData();
        $variables['states'] = craft()->commerce_states->getStatesGroupedByCountries();

        $this->renderTemplate('commerce/addresses/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $id = craft()->request->getRequiredPost('id');
        $address = craft()->commerce_addresses->getAddressById($id);

        if (!$address)
        {
            $address = new Commerce_AddressModel();
        }

        // Shared attributes
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
            'stateValue'
        ];
        foreach ($attrs as $attr)
        {
            $address->$attr = craft()->request->getPost($attr);
        }

        // Save it
        if (craft()->commerce_addresses->saveAddress($address))
        {

            if (craft()->request->isAjaxRequest)
            {
                $this->returnJson(['success' => true, 'address' => $address]);
            }

            craft()->userSession->setNotice(Craft::t('Address saved.'));
            $this->redirectToPostedUrl();
        }
        else
        {
            if (craft()->request->isAjaxRequest)
            {
                $this->returnJson([
                    'error'  => Craft::t("Couldn’t save address."),
                    'errors' => $address->errors
                ]);
            }

            craft()->userSession->setError(Craft::t('Couldn’t save address.'));
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables(['address' => $address]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        craft()->commerce_addresses->deleteAddressById($id);
        $this->returnJson(['success' => true]);
    }
}
