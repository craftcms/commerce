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
class Commerce_AddressesController extends Commerce_BaseAdminController
{
	/**
	 * Edit Address
	 *
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit (array $variables = [])
    {
        if (empty($variables['address']))
        {
            if (empty($variables['id'])) {
                throw new HttpException(404);
            }

            $id = $variables['id'];
            $variables['address'] = craft()->commerce_addresses->getAddressById($id);

            if (!$variables['address']->id) {
                throw new HttpException(404);
            }
        }

        $variables['title'] = Craft::t('Edit Address', ['id' => $variables['id']]);

        $variables['countries'] = craft()->commerce_countries->getFormList();
        $variables['states'] = craft()->commerce_states->getGroupedByCountries();

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

        if (!$address->id) {
            throw new HttpException(400);
        }

        // Shared attributes
        $attrs = [
            'firstName',
            'lastName',
            'address1',
            'address2',
            'city',
            'zipCode',
            'phone',
            'alternativePhone',
            'company',
            'countryId',
            'stateValue'
        ];
        foreach ($attrs as $attr) {
            $address->$attr = craft()->request->getPost($attr);
        }

        // Save it
        if (craft()->commerce_addresses->saveAddress($address)) {
            craft()->userSession->setNotice(Craft::t('Address saved.'));
            $this->redirectToPostedUrl();
        } else {
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
