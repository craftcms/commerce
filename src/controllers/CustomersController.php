<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\models\Customer;
use craft\commerce\Plugin;
use craft\commerce\records\CustomerAddress;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\errors\MissingComponentException;
use craft\helpers\AdminTable;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
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
    /**
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\ForbiddenHttpException
     */
    public function init()
    {
        $this->requirePermission('commerce-manageCustomers');
        parent::init();
    }

    /**
     * @throws HttpException
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('commerce/customers/_index');
    }

    /**
     * @param int|null $id
     * @param Customer|null $customer
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, Customer $customer = null): Response
    {
        $variables = compact('id', 'customer');

        if (!$variables['customer']) {
            $variables['customer'] = Plugin::getInstance()->getCustomers()->getCustomerById($variables['id']);

            if (!$variables['customer']) {
                throw new HttpException(404);
            }
        }

        $variables['title'] = Plugin::t('Customer #{id}', ['id' => $variables['id']]);

        return $this->renderTemplate('commerce/customers/_edit', $variables);
    }

    /**
     * @return Response|null
     * @throws HttpException
     * @throws MissingComponentException
     * @throws Exception
     * @throws BadRequestHttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');
        $customer = Plugin::getInstance()->getCustomers()->getCustomerById($id);

        if (!$customer) {
            throw new HttpException(400, Plugin::t('Cannot find customer.'));
        }

        // Save it
        if (Plugin::getInstance()->getCustomers()->saveCustomer($customer)) {
            Craft::$app->getSession()->setNotice(Plugin::t('Customer saved.'));
            $this->redirectToPostedUrl();
        } else {
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t save customer.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['customer' => $customer]);

        return null;
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionCustomersTable(): Response
    {
        $this->requireAcceptsJson();
        $request = Craft::$app->getRequest();

        $page = $request->getParam('page', 1);
        $sort = $request->getParam('sort', null);
        $limit = $request->getParam('per_page', 10);
        $search = $request->getParam('search', null);
        $offset = ($page - 1) * $limit;

        $customersQuery = Plugin::getInstance()->getCustomers()->getCustomersQuery($search);

        $total = $customersQuery->count();

        $customersQuery->offset($offset);
        $customersQuery->limit($limit);

        if ($sort) {
            list($sortField, $sortDir) = explode('|', $sort);
            if ($sortField && $sortDir) {
                $customersQuery->orderBy('[['.$sortField.']] '.$sortDir);
            }
        }

        // Get number of addresses for customers
        $customerIds = $customersQuery->column();
        $addressCountByCustomerId = [];

        if (!empty($customerIds)) {
            $addressCountByCustomerId = CustomerAddress::find()
                ->select(['COUNT(*) as noAddresses', 'customerId'])
                ->alias('customerAddresses')
                ->innerJoin(Table::ADDRESSES . ' addresses', '[[addresses.id]] = [[customerAddresses.addressId]]')
                ->groupBy('[[customerId]]')
                ->where(['isEstimated' => 0])
                ->andWhere(['customerId' => $customerIds])
                ->indexBy('customerId')
                ->asArray()
                ->column();
        }

        $customers = $customersQuery->all();

        $rows = [];
        foreach ($customers as $customer) {
            $billingName = trim($customer['billingFirstName'] . ' ' . $customer['billingLastName']);
            $billingName = $billingName && $customer['billingFullName'] ? $billingName . ' - ' . $customer['billingFullName'] : $billingName . $customer['billingFullName'];
            $shippingName = trim($customer['shippingFirstName'] . ' ' . $customer['shippingLastName']);
            $shippingName = $shippingName && $customer['shippingFullName'] ? $shippingName . ' - ' . $customer['shippingFullName'] : $shippingName . $customer['shippingFullName'];

            // Get user for Customer. This creates an n+1 query but we are paginating the results.
            $user = $customer['userId'] ? Craft::$app->getUsers()->getUserById($customer['userId']) : null;

            $rows[] = [
                'id' => $customer['id'],
                'title' => Html::encode($customer['email']),
                'url' => UrlHelper::cpUrl('commerce/customers/' . $customer['id']),
                'user' => $user ? [
                    'title' => $user ? $user->__toString() : null,
                    'url' => $user ? $user->getCpEditUrl() : null,
                    'status' => $user ? $user->getStatus() : null,
                ] : null,
                'photo' => $user ? $user->getThumbUrl(30) : null,
                'addresses' => $addressCountByCustomerId[$customer['id']] ?? 0,
                'billing' => Html::encode($billingName) . '<br>' . Html::encode($customer['billingAddress']),
                'shipping' => Html::encode($shippingName) . '<br>' . Html::encode($customer['shippingAddress']),
            ];
        }

        return $this->asJson([
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $rows,
        ]);
    }
}
