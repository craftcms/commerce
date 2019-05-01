<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\LiteSettings;
use craft\commerce\models\LiteShippingSettings;
use craft\commerce\Plugin;
use craft\errors\WrongEditionException;
use yii\web\Response;

/**
 * Class Settings Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class LiteShippingController extends BaseStoreSettingsController
{
    // Public Methods
    // =========================================================================

    /**
     * @throws WrongEditionException
     */
    public function init()
    {
        if (!Plugin::getInstance()->is(Plugin::EDITION_LITE)) {
            throw new WrongEditionException('Lite settings editable when using the lite edition only');
        }

        parent::init();
    }

    /**
     * Commerce Settings Form
     */
    public function actionEdit(): Response
    {
        $settings = new LiteShippingSettings(['shippingBaseRate' => 0, 'shippingPerItemRate' => 0]);

        $shippingRule = Plugin::getInstance()->getShippingRules()->getLiteShippingRule();
        $settings->shippingBaseRate = $shippingRule->getBaseRate();
        $settings->shippingPerItemRate = $shippingRule->getPerItemRate();

        return $this->renderTemplate('commerce/store-settings/shipping/index', compact('settings'));
    }

    /**
     * @return Response|null
     */
    public function actionSaveSettings()
    {
        $this->requirePostRequest();

        $settings = new LiteShippingSettings();
        $settings->shippingPerItemRate = Craft::$app->getRequest()->getBodyParam('shippingPerItemRate');
        $settings->shippingBaseRate = Craft::$app->getRequest()->getBodyParam('shippingBaseRate');

        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save shipping settings.'));
            return $this->renderTemplate('commerce/store-settings/shipping', compact('settings'));
        }

        $shippingMethod = Plugin::getInstance()->getShippingMethods()->getLiteShippingMethod();
        $shippingMethodSaved = Plugin::getInstance()->getShippingMethods()->saveLiteShippingMethod($shippingMethod, false);

        $shippingRule = Plugin::getInstance()->getShippingRules()->getLiteShippingRule();
        $shippingRule->baseRate = $settings->shippingBaseRate;
        $shippingRule->perItemRate = $settings->shippingPerItemRate;
        $shippingRule->methodId = $shippingMethod->id;
        $shippingRuleSaved = Plugin::getInstance()->getShippingRules()->saveLiteShippingRule($shippingRule, false);

        if (!$shippingMethodSaved || !$shippingRuleSaved) {
            throw new \yii\base\Exception('Could not save internal shipping method or rule for lite shipping');
        }

        Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
