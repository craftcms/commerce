<?php
namespace craft\commerce\controllers;

use Craft;
use yii\web\HttpException;

/**
 * Class BaseCp
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class BaseCpController extends BaseController
{
    protected $allowAnonymous = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc BaseController::init()
     *
     * @throws HttpException
     * @return null
     */
    public function init()
    {
        // All system setting actions require access to commerce
        $this->requirePermission('accessPlugin-commerce');
    }
}
