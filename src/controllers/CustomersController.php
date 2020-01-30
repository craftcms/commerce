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
use craft\helpers\AdminTable;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
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
     * @throws HttpException
     */
    public function actionSave(): Response
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
     * @throws \yii\web\BadRequestHttpException
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

        $customersQuery = (new Query())
            ->select([
                'customers.id as id',
                'userId',
                'orders.email as email',
                'primaryBillingAddressId',
                'billing.firstName as billingFirstName',
                'billing.lastName as billingLastName',
                'billing.fullName as billingFullName',
                'billing.address1 as billingAddress',
                'shipping.firstName as shippingFirstName',
                'shipping.lastName as shippingLastName',
                'shipping.fullName as shippingFullName',
                'shipping.address1 as shippingAddress',
                'primaryShippingAddressId',
            ])
            ->from(Table::CUSTOMERS . ' customers')
            ->innerJoin(Table::ORDERS . ' orders' , '[[orders.customerId]] = [[customers.id]]')
            ->leftJoin(CraftTable::USERS . ' users', '[[users.id]] = [[customers.userId]]')
            ->leftJoin(Table::ADDRESSES . ' billing', '[[billing.id]] = [[customers.primaryBillingAddressId]]')
            ->leftJoin(Table::ADDRESSES . ' shipping', '[[shipping.id]] = [[customers.primaryShippingAddressId]]')
            ->groupBy([
                'customers.id',
                'orders.email',
                'billing.firstName',
                'billing.lastName',
                'billing.fullName',
                'billing.address1',
                'shipping.firstName',
                'shipping.lastName',
                'shipping.fullName',
                'shipping.address1',
            ])

            // Exclude customer records without a user or where there isn't any data
            ->where(['or',
                ['not', ['userId' => null]],
                ['and',
                    ['userId' => null],
                    ['or',
                        ['not', ['primaryBillingAddressId' => null]],
                        ['not', ['primaryShippingAddressId' => null]],
                    ]
                ]
            ])->andWhere(['[[orders.isCompleted]]' => 1]);

        if ($search) {
            $likeOperator = Craft::$app->getDb()->getIsPgsql() ? 'ILIKE' : 'LIKE';
            $customersQuery->andWhere([
                'or',
                [$likeOperator, '[[billing.address1]]', $search],
                [$likeOperator, '[[billing.firstName]]', $search],
                [$likeOperator, '[[billing.fullName]]', $search],
                [$likeOperator, '[[billing.lastName]]', $search],
                [$likeOperator, '[[orders.email]]', $search],
                [$likeOperator, '[[orders.reference]]', $search],
                [$likeOperator, '[[orders.number]]', $search],
                [$likeOperator, '[[shipping.address1]]', $search],
                [$likeOperator, '[[shipping.firstName]]', $search],
                [$likeOperator, '[[shipping.fullName]]', $search],
                [$likeOperator, '[[shipping.lastName]]', $search],
                [$likeOperator, '[[users.username]]', $search],
            ]);
        }

        $total = $customersQuery->count();

        $customersQuery->offset($offset);
        $customersQuery->limit($limit);

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
