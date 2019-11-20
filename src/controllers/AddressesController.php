<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\Address as AddressModel;
use craft\commerce\Plugin;
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
    // Public Methods
    // =========================================================================

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

        $variables['title'] = Craft::t('commerce', 'Edit Address', ['id' => $variables['addressId']]);

        $variables['countries'] = Plugin::getInstance()->getCountries()->getAllCountriesAsList();
        $variables['states'] = Plugin::getInstance()->getStates()->getAllStatesAsList();

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

            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Address saved.'));
            $this->redirectToPostedUrl();
        } else {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'error' => Craft::t('commerce', 'Couldn’t save address.'),
                    'errors' => $address->errors
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save address.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['address' => $address]);

        return null;
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
}
