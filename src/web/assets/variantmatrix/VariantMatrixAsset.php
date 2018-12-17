<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\web\assets\variantmatrix;

use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\web\AssetBundle;
use craft\web\View;

/**
 * Variant Matrix asset bundle.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class VariantMatrixAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            CommerceCpAsset::class,
        ];

        $this->js = [
            'js/VariantMatrix.js',
        ];

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function registerAssetFiles($view)
    {
        parent::registerAssetFiles($view);

        if ($view instanceof View) {
            $view->registerTranslations('commerce', [
                'Actions',
                'Add a variant',
                'Add variant above',
                'Are you sure you want to delete the selected variants?',
                'Collapse',
                'Default',
                'Disable',
                'Disabled',
                'Enable',
                'Expand',
                'Set as the default variant',
            ]);
        }
    }
}
