<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\web\assets\deepmerge;

use craft\web\AssetBundle;

/**
 * Asset bundle for the Chart JS
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class DeepMergeAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = '@commerceLib/deepmerge';

        $this->js[] = 'umd.js';

        parent::init();
    }
}
