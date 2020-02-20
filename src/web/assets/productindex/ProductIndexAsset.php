<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\web\assets\productindex;

use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\web\AssetBundle;

/**
 * Edit Product edit asset bundle
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ProductIndexAsset extends AssetBundle
{
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
            'js/CommerceProductIndex.js',
        ];

        parent::init();
    }
}
