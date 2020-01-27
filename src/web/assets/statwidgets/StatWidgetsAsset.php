<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\web\assets\statwidgets;

use Craft;
use craft\commerce\Plugin;
use craft\commerce\web\assets\chartjs\ChartJsAsset;
use craft\commerce\web\assets\deepmerge\DeepMergeAsset;
use craft\web\AssetBundle;
use craft\web\View;

/**
 * Asset bundle for the Stat widgets
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class StatWidgetsAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            DeepMergeAsset::class,
            ChartJsAsset::class,
        ];

        $this->js[] = 'js/CommerceChart.js';
        $this->css[] = 'css/statwidgets.css';

        parent::init();
    }

    public function registerAssetFiles($view)
    {
        parent::registerAssetFiles($view);

        $currency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        $language = Craft::$app->getUser()->getIdentity()->getPreferredLanguage() ?? 'en';

        $js = <<<JS
window.commerceCurrency = '$currency';
window.commerceCurrentLocale = '$language';
JS;

        $view->registerJs($js, View::POS_HEAD);
    }
}
