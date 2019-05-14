<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\LiteTaxSettings;
use craft\commerce\Plugin;
use craft\errors\WrongEditionException;
use craft\i18n\Locale;
use yii\web\Response;

/**
 * Class Settings Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class LiteTaxController extends BaseStoreSettingsController
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

        $settings = new LiteTaxSettings([
            'taxRate' => 0,
            'taxName' => 'Tax',
            'taxInclude' => false,
        ]);

        $taxRate = Plugin::getInstance()->getTaxRates()->getLiteTaxRate();
        $settings->taxName = $taxRate->name;
        $settings->taxRate = $taxRate->rate;
        $settings->taxInclude = $taxRate->include;

        return $this->renderTemplate('commerce/store-settings/tax/index', compact('settings'));
    }

    /**
     * @return Response|null
     */
    public function actionSaveSettings()
    {
        $this->requirePostRequest();

        $settings = new LiteTaxSettings();
        $settings->taxName = Craft::$app->getRequest()->getBodyParam('taxName');
        $settings->taxInclude = (bool)Craft::$app->getRequest()->getBodyParam('taxInclude');

        $percentSign = Craft::$app->getLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);
        $rate = Craft::$app->getRequest()->getBodyParam('taxRate');
        if (strpos($rate, $percentSign) || $rate >= 1) {
            $settings->taxRate = (float)$rate / 100;
        } else {
            $settings->taxRate = (float)$rate;
        }

        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save settings.'));
            return $this->renderTemplate('commerce/store-settings/tax', compact('settings'));
        }

        $taxRate = Plugin::getInstance()->getTaxRates()->getLiteTaxRate();
        $taxRate->rate = $settings->taxRate;
        $taxRate->name = $settings->taxName;
        $taxRate->include = $settings->taxInclude;
        $taxSaved = Plugin::getInstance()->getTaxRates()->saveLiteTaxRate($taxRate, false);

        if (!$taxSaved) {
            throw new \yii\base\Exception('Could not save internal tax rate for lite tax.');
        }

        Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
