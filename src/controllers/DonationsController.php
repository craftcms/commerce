<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Donation;
use craft\commerce\Plugin;
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
class DonationsController extends BaseStoreManagementController
{
    public function actionEdit(): Response
    {
        $donation = Donation::find()->status(null)->one();

        if ($donation === null) {
            $donation = new Donation();
            $donation->sku = 'DONATION-CC5';
            $donation->availableForPurchase = true;
            $donation->enabled = true;
        }

        $store = Plugin::getInstance()->getStores()->getPrimaryStore();

        return $this->renderTemplate('commerce/store-management/donation/_edit', compact('donation', 'store'));
    }

    /**
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws MissingComponentException
     * @throws Exception
     * @throws BadRequestHttpException
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        // Not using a service to save a donation yet. Always editing the only donation.
        /** @var Donation|null $donation */
        $donation = Donation::find()->status(null)->one();

        if ($donation === null) {
            $donation = new Donation();
            $donation->siteId = Craft::$app->getSites()->getPrimarySite()->id;
        }

        $donation->sku = $this->request->getBodyParam('sku');
        $donation->availableForPurchase = (bool)$this->request->getBodyParam('availableForPurchase');
        $donation->enabled = (bool)$this->request->getBodyParam('enabled');

        if (!Craft::$app->getElements()->saveElement($donation)) {
            return $this->renderTemplate('commerce/store-management/donation/_edit', compact('donation'));
        }

        $this->setSuccessFlash(Craft::t('commerce', 'Donation settings saved.'));
        return $this->redirectToPostedUrl();
    }
}
