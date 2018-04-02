<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\Customer;
use craft\commerce\Plugin;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Customers Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CustomersController extends BaseCpController
{
    // Public Methods
    // =========================================================================

    /**
     * @throws HttpException
     */
    public function actionIndex(): Response
    {
        $customers = Plugin::getInstance()->getCustomers()->getAllCustomers();
        return $this->renderTemplate('commerce/customers/index', compact('customers'));
    }

    /**
     * @param int|null $id
     * @param Customer|null $customer
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, Customer $customer = null): Response
    {
        $variables = [
            'id' => $id,
            'customer' => $customer,
        ];

        if (!$variables['customer']) {
            $variables['customer'] = Plugin::getInstance()->getCustomers()->getCustomerById($variables['id']);

            if (!$variables['customer']) {
                throw new HttpException(404);
            }
        }

        $variables['title'] = Craft::t('commerce', 'Customer #{id}', ['id' => $variables['id']]);

        return $this->renderTemplate('commerce/customers/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');
        $customer = Plugin::getInstance()->getCustomers()->getCustomerById($id);

        if (!$customer) {
            throw new HttpException(400, Craft::t('commerce', 'Cannot find customer.'));
        }

        // Save it
        if (Plugin::getInstance()->getCustomers()->saveCustomer($customer)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Customer saved.'));
            $this->redirectToPostedUrl();
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save customer.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['customer' => $customer]);
    }
}
