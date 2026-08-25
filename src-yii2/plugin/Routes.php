<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\plugin;

use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;

/**
 * Trait Routes
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
trait Routes
{
    /**
     * @since 3.1.10
     * @deprecated the webhook route is now registered in routes/web.php and routes/actions.php.
     */
    private function _registerSiteRoutes(): void
    {
    }

    /**
     * @since 2.0
     */
    private function _registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            // Bare "commerce" index still targets a src-yii2/templates/ Twig template that
            // hasn't moved to src/ yet — stays here until the CP template migration lands.
            $event->rules['commerce'] = ['template' => 'commerce/index'];

            // User edit screen ("myaccount/commerce" / "users/<id>/commerce") is now registered
            // in routes/cp.php

            // Products / Variants — index, create, and the element-edit routes (previously
            // Craft core's generic `elements/edit` action) are now all registered in
            // routes/cp.php via EditElementController.

            // Product Types are now registered in routes/cp.php

            // Orders are now registered in routes/cp.php

            // Settings

            // commerce/settings/stores* and commerce/settings/sites are now registered in routes/cp.php

            // commerce/settings/general, ordersettings, and transfers are now registered in routes/cp.php

            // commerce/settings/gateways* is now registered in routes/cp.php

            // Emails and PDFs are now registered in routes/cp.php

            // Order Statuses and Line Item Statuses are now registered in routes/cp.php

            // Store Settings and Payment Currencies are now registered in routes/cp.php

            // Shipping is now registered in routes/cp.php

            // Taxes are now registered in routes/cp.php

            // Sales, Discounts, and Pricing Rules are now registered in routes/cp.php

            // Inventory, Inventory Locations, and Transfers index/edit (including the
            // element-edit route, previously Craft core's generic `elements/edit` action) are
            // now all registered in routes/cp.php.

            // commerce/donations is now registered in routes/cp.php
        });
    }
}
