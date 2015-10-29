<?php
namespace Craft;

/**
 * Class Commerce_CustomersController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_CustomersController extends Commerce_BaseCpController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $customers = craft()->commerce_customers->getAll(['with' => 'user']);
        $this->renderTemplate('commerce/customers/index', compact('customers'));
    }

    /**
     * Edit Customer
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['customer'])) {
            if (empty($variables['id'])) {
                throw new HttpException(404);
            }

            $id = $variables['id'];
            $variables['customer'] = craft()->commerce_customers->getById($id);

            if (!$variables['customer']->id) {
                throw new HttpException(404);
            }
        }

        $variables['title'] = Craft::t('Customer #{id}',
            ['id' => $variables['id']]);

        $this->renderTemplate('commerce/customers/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $id = craft()->request->getRequiredPost('id');
        $customer = craft()->commerce_customers->getById($id);

        if (!$customer->id) {
            throw new HttpException(400);
        }

        // Shared attributes
        $customer->email = craft()->request->getPost('email');

        // Save it
        if (craft()->commerce_customers->save($customer)) {
            craft()->userSession->setNotice(Craft::t('Customer saved.'));
            $this->redirectToPostedUrl();
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save customer.'));
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables(['customer' => $customer]);
    }

}
