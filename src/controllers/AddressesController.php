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
use craft\errors\MissingComponentException;
use craft\helpers\AdminTable;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
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
     * @throws ForbiddenHttpException
     */
    public function init(): void
    {
        parent::init();
        $this->requirePermission('commerce-manageOrders');
    }

    /**
     * @param int|null $addressId
     * @param AddressModel|null $address
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionEdit(int $addressId = null, AddressModel $address = null): Response
    {
        $variables = compact('addressId', 'address');
        $variables['customerId'] = Craft::$app->getRequest()->getQueryParam('customerId');
        $variables['customer'] = $variables['customerId'] ? Plugin::getInstance()->getCustomers()->getCustomerById($variables['customerId']) : null;

        if (!$variables['address']) {
            $variables['address'] = null;

            if ($variables['addressId']) {
                $variables['address'] = Plugin::getInstance()->getAddresses()->getAddressById($variables['addressId']);
            } else if ($variables['customerId']) {
                $variables['address'] = new AddressModel();
            }

            if (!$variables['address']) {
                throw new NotFoundHttpException('Address not found.');
            }
        }

        $variables['title'] = $variables['addressId']
            ? Craft::t('commerce', 'Edit Address', ['id' => $variables['addressId']])
            : Craft::t('commerce', 'New address');

        $variables['countries'] = Plugin::getInstance()->getCountries()->getAllEnabledCountriesAsList();
        $variables['states'] = Plugin::getInstance()->getStates()->getAllEnabledStatesAsList();

        if (!$variables['customerId']) {
            $variables['customerId'] = (new Query())
                ->from(Table::CUSTOMERS_ADDRESSES)
                ->select(['customerId'])
                ->where(['addressId' => $variables['address']->id])
                ->scalar();
        }

        if (!$variables['customer'] && $variables['customerId']) {
            $variables['customer'] = Plugin::getInstance()->getCustomers()->getCustomerById($variables['customerId']);
        }
        $variables['redirect'] = 'commerce/customers' . ($variables['customerId'] ? '/' . $variables['customerId'] : '');

        if ($redirect = Craft::$app->getRequest()->getQueryParam('redirect')) {
            $variables['redirect'] = $redirect;
        }

        return $this->renderTemplate('commerce/addresses/_edit', $variables);
    }

    /**
     * @return Response
     * @throws Exception
     * @throws NotFoundHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $address = null;

        $id = Craft::$app->getRequest()->getBodyParam('id');
        $customerId = Craft::$app->getRequest()->getValidatedBodyParam('customerId');
        $customer = $customerId ? Plugin::getInstance()->getCustomers()->getCustomerById((int)$customerId) : null;

        if ($id && $customer) {
            $address = Plugin::getInstance()->getAddresses()->getAddressByIdAndCustomerId((int)$id, (int)$customer->id);

            if (!$address) {
                if (Craft::$app->getRequest()->getAcceptsJson()) {
                    return $this->asErrorJson('Address not found.');
                }

                throw new NotFoundHttpException('Address not found.');
            }
        } else if ($id) {
            $address = Plugin::getInstance()->getAddresses()->getAddressById((int)$id);
        }

        if (!$address) {
            $address = new AddressModel();
        }

        // @TODO namespace inputs, and use setAttributes on the model #COM-30
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

        // @todo remove forked save of address. This is currently here for backwards compatibility #COM-31
        $result = $customer ? Plugin::getInstance()->getCustomers()->saveAddress($address, $customer) : Plugin::getInstance()->getAddresses()->saveAddress($address);

        // Save it
        if (!$result) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'error' => Craft::t('commerce', 'Couldn’t save address.'),
                    'errors' => $address->errors,
                ]);
            }

            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save address.'));

            // Send the model back to the template
            Craft::$app->getUrlManager()->setRouteParams(['address' => $address]);

            return null;
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson(['success' => true, 'address' => $address]);
        }

        $this->setSuccessFlash(Craft::t('commerce', 'Address saved.'));

        return $this->redirectToPostedUrl($address);
    }

    /**
     * Set the primary billing or shipping address for a customer
     *
     * @throws BadRequestHttpException
     * @throws Exception
     * @throws MissingComponentException
     * @since 3.0.4
     */
    public function actionSetPrimaryAddress(): ?Response
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();
        $type = $request->getRequiredParam('type');
        $ids = $request->getRequiredParam('ids');

        if (empty($ids) || !$id = $ids[0] ?? null) {
            $this->setFailFlash(Craft::t('commerce', 'An address ID is required.'));
            return null;
        }

        $address = Plugin::getInstance()->getAddresses()->getAddressById($id);

        if (!$address) {
            $this->setFailFlash(Craft::t('commerce', 'Unable to find address.'));
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
            $this->setFailFlash(Craft::t('commerce', 'Cannot find customer.'));
            return null;
        }

        if ($type == 'billing') {
            $customer->primaryBillingAddressId = $address->id;
        } else if ($type == 'shipping') {
            $customer->primaryShippingAddressId = $address->id;
        }

        if (Plugin::getInstance()->getCustomers()->saveCustomer($customer)) {
            $this->setSuccessFlash(Craft::t('commerce', 'Primary address updated.'));
        } else {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t update primary address.'));
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
    public function actionGetCustomerAddresses(): Response
    {
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $customerId = $request->getRequiredParam('customerId');
        $page = $request->getParam('page', 1);
        $limit = $request->getParam('per_page', 10);
        $offset = ($page - 1) * $limit;

        $customer = Plugin::getInstance()->getCustomers()->getCustomerById($customerId);

        if (!$customer) {
            return $this->asErrorJson(Craft::t('commerce', 'Unable to retrieve customer.'));
        }

        $addresses = Plugin::getInstance()->getAddresses()->getAddressesByCustomerId($customerId);

        $total = count($addresses);

        $rows = [];

        foreach (array_slice($addresses, $offset, $limit) as $row) {
            /** @var AddressModel $row */
            $rows[] = [
                'id' => $row->id,
                'title' => $row->address1 ?: Craft::t('commerce', 'No Address Line 1'),
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
        $addressPost = $request->getParam('address');

        if (!$addressPost) {
            return $this->asErrorJson(Craft::t('commerce', 'An address must be provided.'));
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
        $addressId = $request->getParam('id');

        if (!$addressId) {
            return $this->asErrorJson(Craft::t('commerce', 'Address ID is required.'));
        }

        if (!is_numeric($addressId)) {
            return $this->asErrorJson(Craft::t('commerce', 'Address ID must be numeric.'));
        }

        $address = Plugin::getInstance()->getAddresses()->getAddressById((int)$addressId);

        if (!$address) {
            return $this->asErrorJson(Craft::t('commerce', 'Couldn’t retrieve address.'));
        }

        return $this->asJson([
            'success' => true,
            'address' => $address,
        ]);
    }
}
