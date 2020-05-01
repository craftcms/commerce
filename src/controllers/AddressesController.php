<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\models\Address as AddressModel;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\helpers\AdminTable;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Address Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class AddressesController extends BaseCpController
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->requirePermission('commerce-manageOrders');

        parent::init();
    }

    /**
     * @param int|null $addressId
     * @param AddressModel|null $address
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $addressId = null, AddressModel $address = null): Response
    {
        $variables = compact('addressId', 'address');

        if (!$variables['address']) {
            $variables['address'] = $variables['addressId'] ? Plugin::getInstance()->getAddresses()->getAddressById($variables['addressId']) : null;

            if (!$variables['address']) {
                throw new HttpException(404);
            }
        }

        $variables['title'] = Plugin::t('Edit Address', ['id' => $variables['addressId']]);

        $variables['countries'] = Plugin::getInstance()->getCountries()->getAllEnabledCountriesAsList();
        $variables['states'] = Plugin::getInstance()->getStates()->getAllEnabledStatesAsList();

        $variables['customerId'] = (new Query())
            ->from(Table::CUSTOMERS_ADDRESSES)
            ->select(['customerId'])
            ->where(['addressId' => $variables['address']->id])
            ->scalar();

        $variables['redirect'] = 'commerce/customers' . ($variables['customerId'] ? '/' . $variables['customerId'] : '');

        if ($redirect = Craft::$app->getRequest()->getQueryParam('redirect')) {
            $variables['redirect'] = $redirect;
        }

        return $this->renderTemplate('commerce/addresses/_edit', $variables);
    }

    /**
     * @return Response
     * @throws Exception
     * @throws BadRequestHttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $id = (int)Craft::$app->getRequest()->getRequiredBodyParam('id');

        $address = Plugin::getInstance()->getAddresses()->getAddressById($id);

        if (!$address) {
            $address = new AddressModel();
        }

        // Shared attributes
        $attributes = [
            'attention',
            'title',
            'firstName',
            'lastName',
            'fullName',
            'address1',
            'address2',
            'address3',
            'city',
            'zipCode',
            'phone',
            'alternativePhone',
            'label',
            'notes',
            'businessName',
            'businessTaxId',
            'businessId',
            'countryId',
            'stateValue',
            'custom1',
            'custom2',
            'custom3',
            'custom4',
        ];
        foreach ($attributes as $attr) {
            $address->$attr = Craft::$app->getRequest()->getParam($attr);
        }

        // Save it
        if (Plugin::getInstance()->getAddresses()->saveAddress($address)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson(['success' => true, 'address' => $address]);
            }

            Craft::$app->getSession()->setNotice(Plugin::t('Address saved.'));
            $this->redirectToPostedUrl();
        } else {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'error' => Plugin::t('Couldn’t save address.'),
                    'errors' => $address->errors
                ]);
            }

            Craft::$app->getSession()->setError(Plugin::t('Couldn’t save address.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['address' => $address]);

        return null;
    }

    /**
     * Set the primary billing or shipping address for a customer
     *
     * @throws BadRequestHttpException
     * @throws Exception
     * @throws \craft\errors\MissingComponentException
     * @since 3.0.4
     */
    public function actionSetPrimaryAddress()
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();
        $type = $request->getRequiredParam('type');
        $ids = $request->getRequiredParam('ids');

        if (empty($ids) || !$id = $ids[0] ?? null) {
            Craft::$app->getSession()->setError(Plugin::t('An address ID is required.'));
            return null;
        }

        $address = Plugin::getInstance()->getAddresses()->getAddressById($id);

        if (!$address) {
            Craft::$app->getSession()->setError(Plugin::t('Unable to find address.'));
            return null;
        }

        $customerId = (new Query())
            ->select(['[[ca.customerId]]'])
            ->from(Table::CUSTOMERS_ADDRESSES . ' ca')
            ->innerJoin(Table::CUSTOMERS . ' c', '[[c.id]] = [[ca.customerId]]')
            ->where(['[[ca.addressId]]' => $address->id])
            ->andWhere(['not', ['[[c.id]]' => null]])
            ->scalar();

        if (!$customerId || !$customer = Plugin::getInstance()->getCustomers()->getCustomerById($customerId)) {
            Craft::$app->getSession()->setError(Plugin::t('Cannot find customer.'));
            return null;
        }

        if ($type == 'billing') {
            $customer->primaryBillingAddressId = $address->id;
        } else if ($type == 'shipping') {
            $customer->primaryShippingAddressId = $address->id;
        }

        if (Plugin::getInstance()->getCustomers()->saveCustomer($customer)) {
            Craft::$app->getSession()->setNotice(Plugin::t('Primary address updated.'));
        } else {
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t update primary address.'));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Plugin::getInstance()->getAddresses()->deleteAddressById($id);
        return $this->asJson(['success' => true]);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @since 3.1
     */
    public function actionGetCustomerAddresses()
    {
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $customerId = $request->getRequiredParam('customerId');
        $page = $request->getParam('page', 1);
        $sort = $request->getParam('sort', null);
        $limit = $request->getParam('per_page', 10);
        $search = $request->getParam('search', null);
        $offset = ($page - 1) * $limit;

        $customer = Plugin::getInstance()->getCustomers()->getCustomerById($customerId);

        if (!$customer) {
            return $this->asErrorJson(Plugin::t('Unable to retrieve customer.'));
        }

        $addresses = Plugin::getInstance()->getAddresses()->getAddressesByCustomerId($customerId);

        $total = count($addresses);

        $rows = [];

        foreach (array_slice($addresses, 0, $limit) as $row) {
            /** @var AddressModel $row */
            $rows[] = [
                'id' => $row->id,
                'title' => $row->address1,
                'zipCode' => $row->zipCode,
                'billing' => ($row->id == $customer->primaryBillingAddressId),
                'shipping' => ($row->id == $customer->primaryShippingAddressId),
                'address' => $row,
            ];
        }

        return $this->asJson([
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $rows,
        ]);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @since 3.1
     */
    public function actionValidate(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $addressPost = $request->getParam('address', null);

        if (!$addressPost) {
            return $this->asErrorJson(Plugin::t('An address must be provided.'));
        }

        $addressPost = Plugin::getInstance()->getAddresses()->removeReadOnlyAttributesFromArray($addressPost);

        $address = new AddressModel($addressPost);

        if (!$address->validate()) {
            return $this->asJson([
                'success' => false,
                'errors' => $address->getErrors(),
            ]);
        }

        return $this->asJson(['success' => true]);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @since 3.1
     */
    public function actionGetAddressById(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $addressId = $request->getParam('id', null);

        if (!$addressId) {
            return $this->asErrorJson(Plugin::t('Address ID is required.'));
        }

        if (!is_numeric($addressId)) {
            return $this->asErrorJson(Plugin::t('Address ID must be numeric.'));
        }

        $address = Plugin::getInstance()->getAddresses()->getAddressById((int)$addressId);

        if (!$address) {
            return $this->asErrorJson(Plugin::t('Couldn’t retrieve address.'));
        }

        return $this->asJson([
            'success' => true,
            'address' => $address,
        ]);
    }
}
