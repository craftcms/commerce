<?php
namespace Craft;

/**
 * @author    Make with Morph. <support@makewithmorph.com>
 * @copyright Copyright (c) 2015, Luke Holder.
 * @license   http://makewithmorph.com/market/license Market License Agreement
 * @see       http://makewithmorph.com
 * @package   craft.plugins.market.controllers
 * @since     0.1
 */
class Market_CustomerController extends Market_BaseController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $customers = craft()->market_customer->getAll(['with' => 'user']);
        $this->renderTemplate('market/customers/index', compact('customers'));
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

            $id                    = $variables['id'];
            $variables['customer'] = craft()->market_customer->getById($id);

            if (!$variables['customer']->id) {
                throw new HttpException(404);
            }
        }

        $variables['title'] = Craft::t('Customer #{id}',
            ['id' => $variables['id']]);

        $this->renderTemplate('market/customers/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $id       = craft()->request->getRequiredPost('id');
        $customer = craft()->market_customer->getById($id);

        if (!$customer->id) {
            throw new HttpException(400);
        }

        // Shared attributes
        $customer->email = craft()->request->getPost('email');

        // Save it
        if (craft()->market_customer->save($customer)) {
            craft()->userSession->setNotice(Craft::t('Customer saved.'));
            $this->redirectToPostedUrl();
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save customer.'));
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables(['customer' => $customer]);
    }

}