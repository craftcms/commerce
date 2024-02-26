<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\helpers\Localization;
use craft\commerce\models\LiteTaxSettings;
use craft\commerce\Plugin;
use craft\i18n\Locale;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Class Settings Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 * @deprecated in 4.5.0
 */
class LiteTaxController extends BaseStoreSettingsController
{
    /**
     * Commerce Settings Form
     */
    public function actionEdit(LiteTaxSettings $settings = null): Response
    {
        if ($settings === null) {
            $settings = new LiteTaxSettings([
                'taxRate' => 0,
                'taxName' => 'Tax',
                'taxInclude' => false,
            ]);
        }

        $taxRate = Plugin::getInstance()->getTaxRates()->getLiteTaxRate();
        $settings->taxName = $taxRate->name;
        $settings->taxRate = $taxRate->rate;
        $settings->taxInclude = $taxRate->include;

        $variables = compact('settings');
        $variables['percentSymbol'] = Craft::$app->getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);

        return $this->renderTemplate('commerce/store-settings/tax/index', $variables);
    }

    /**
     * @throws Exception
     * @throws BadRequestHttpException
     */
    public function actionSaveSettings(): ?Response
    {
        $this->requirePostRequest();

        $settings = new LiteTaxSettings();
        $settings->taxName = $this->request->getBodyParam('taxName');
        $settings->taxInclude = (bool)$this->request->getBodyParam('taxInclude');
        $settings->taxRate = Localization::normalizePercentage($this->request->getBodyParam('taxRate'));

        if (!$settings->validate()) {
            return $this->asModelFailure(
                $settings,
                Craft::t('commerce', 'Couldn’t save settings.'),
                'settings'
            );
        }

        $taxRate = Plugin::getInstance()->getTaxRates()->getLiteTaxRate();
        $taxRate->rate = $settings->taxRate;
        $taxRate->name = $settings->taxName;
        $taxRate->include = $settings->taxInclude;
        $taxSaved = Plugin::getInstance()->getTaxRates()->saveLiteTaxRate($taxRate, false);

        if (!$taxSaved) {
            throw new Exception('Could not save internal tax rate for lite tax.');
        }

        $this->setSuccessFlash(Craft::t('commerce', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
