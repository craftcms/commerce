<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\web\assets\commerceui;

/**
 * Commerce Order Edit bundle for the Control Panel
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class CommerceOrderAsset extends CommerceUiAsset
{
    /**
     * @inheritdoc
     */
    protected $appJs = 'order.js';

    /**
     * @inheritdoc
     */
    protected $appCss = 'order.css';
}
