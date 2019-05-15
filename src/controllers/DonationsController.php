<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Donation;
use craft\errors\ElementNotFoundException;
use craft\errors\MissingComponentException;
use Throwable;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Class Donations Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class DonationsController extends BaseStoreSettingsController
{
    // Public Methods
    // =========================================================================

    /**
     * @param array $variables
     * @return Response
     */
    public function actionEdit(array $variables = []): Response
    {
        $donation = Donation::find()->one();

        if ($donation === null) {
            $donation = new Donation();
        }

        return $this->renderTemplate('commerce/store-settings/donation/_edit', compact('donation'));
    }

    /**
     * @return Response
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws MissingComponentException
     * @throws Exception
     * @throws BadRequestHttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        // Not using a service to save a donation yet. Always editing the only donation.
        $donation = Donation::find()->one();

        if ($donation === null) {
            $donation = new Donation();
        }

        $donation->sku = Craft::$app->getRequest()->getBodyParam('sku');
        $donation->availableForPurchase = (bool)Craft::$app->getRequest()->getBodyParam('availableForPurchase');

        if (!Craft::$app->getElements()->saveElement($donation)) {
            return $this->renderTemplate('commerce/store-settings/donation/_edit', compact('donation'));
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Donation settings saved.'));
        return $this->redirectToPostedUrl();
    }
}
