<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

/**
 * Class BaseShippingSettingController
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class BaseShippingSettingsController extends BaseStoreManagementController
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        // All system setting actions require access to commerce
        $this->requirePermission('commerce-manageShipping');
    }
}
