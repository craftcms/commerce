<?php
namespace Craft;

/**
 * Class Market_AddressController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Market_AddressController extends Market_BaseController
{
    /**
     * Edit Address
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        $this->requireAdmin();

        if (empty($variables['address'])) {
            if (empty($variables['id'])) {
                throw new HttpException(404);
            }

            $id                   = $variables['id'];
            $variables['address'] = craft()->market_address->getAddressById($id);

            if (!$variables['address']->id) {
                throw new HttpException(404);
            }
        }

        $variables['title'] = Craft::t('Address #{id}',
            ['id' => $variables['id']]);

        $variables['countries'] = craft()->market_country->getFormList();
        $variables['states']    = craft()->market_state->getGroupedByCountries();

        $this->renderTemplate('market/customers/addresses/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requireAdmin();

        $this->requirePostRequest();

        $id      = craft()->request->getRequiredPost('id');
        $address = craft()->market_address->getAddressById($id);

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
        if (craft()->market_address->saveAddress($address)) {
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
        $this->requireAdmin();

        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        craft()->market_address->deleteAddressById($id);
        $this->returnJson(['success' => true]);
    }
}
