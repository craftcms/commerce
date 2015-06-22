<?php
namespace Craft;

/**
 *
 *
 * @author    Make with Morph. <support@makewithmorph.com>
 * @copyright Copyright (c) 2015, Luke Holder.
 * @license   http://makewithmorph.com/market/license Market License Agreement
 * @see       http://makewithmorph.com
 * @package   craft.plugins.market.controllers
 * @since     0.1
 */
class Market_DashboardController extends Market_BaseController
{

    public function actionIndex()
    {
        $variables = [];
        $this->renderTemplate('market/index', $variables);
    }

    public function actionSetup()
    {
        $variables = [];
        $this->renderTemplate('market/setup', $variables);
    }
} 