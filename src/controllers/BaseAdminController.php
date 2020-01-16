<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

/**
 * Class Base Admin Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class BaseAdminController extends BaseCpController
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->requireAdmin();

        parent::init();
    }
}
