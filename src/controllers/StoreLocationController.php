<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\Address;
use craft\commerce\Plugin;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class StoreLocationController extends BaseStoreSettingsController
{
    /**
     * @return Response
     */
    public function actionEditLocation(): Response
    {
        $storeLocation = Plugin::getInstance()->getAddresses()->getStoreLocationAddress();

        if (!$storeLocation) {
            $storeLocation = new Address();
        }

        $variables = [
            'storeLocation' => $storeLocation,
        ];

        return $this->renderTemplate('commerce/store-settings/location/index', $variables);
    }


    /**
     * Saves the store location setting
     *
     * @return Response
     * @throws Exception
     * @throws BadRequestHttpException
     */
    public function actionSaveStoreLocation(): Response
    {
        $this->requirePostRequest();

        $id = (int)Craft::$app->getRequest()->getBodyParam('id');

        $address = Plugin::getInstance()->getAddresses()->getAddressById($id);

        if (!$address) {
            $address = new Address();
        }

        // Shared attributes
        $attributes = [
            'attention',
            'title',
            'firstName',
            'lastName',
            'fullName',
            'addressLine1',
            'address2',
            'address3',
            'city',
            'zipCode',
            'phone',
            'alternativePhone',
            'businessName',
            'businessTaxId',
            'businessId',
            'countryId',
            'stateValue',
            'phone',
            'label',
            'notes',
            'custom1',
            'custom2',
            'custom3',
            'custom4',
        ];
        foreach ($attributes as $attr) {
            $address->$attr = Craft::$app->getRequest()->getParam($attr);
        }

        $address->isStoreLocation = true;

        if ($address->validate() && Plugin::getInstance()->getAddresses()->saveAddress($address)) {
            $this->setSuccessFlash(Craft::t('commerce', 'Store Location saved.'));

            return $this->redirectToPostedUrl();
        }

        $this->setFailFlash(Craft::t('commerce', 'Couldn’t save Store Location.'));

        $variables = [
            'storeLocation' => $address,
        ];

        // Send the model back to the template
        return $this->renderTemplate('commerce/store-settings/location/index', $variables);
    }
}
