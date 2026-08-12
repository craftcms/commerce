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
            $event->rules['commerce'] = ['template' => 'commerce/index'];

            // User edit screen
            $event->rules['myaccount/commerce'] = 'commerce/users/index';
            $event->rules['users/<userId:\d+>/commerce'] = 'commerce/users/index';

            // Products / Variants — index and create are now registered in routes/cp.php; the
            // element-edit rules below are Craft core's own generic element-editing route, not a
            // Commerce controller, so they stay here.
            $event->rules['commerce/variants/<elementId:\d+><slug:(?:-[^\/]*)?>'] = 'elements/edit';
            $event->rules['commerce/products/<productTypeHandle:{handle}>/<elementId:\d+><slug:(?:-[^\/]*)?>'] = 'elements/edit';

            // Subscriptions and Subscription Plans are now registered in routes/cp.php

            // Product Types are now registered in routes/cp.php

            // Orders are now registered in routes/cp.php

            // Settings

            // commerce/settings/stores* and commerce/settings/sites are now registered in routes/cp.php

            // commerce/settings/general, ordersettings, transfers, and subscriptions are now registered in routes/cp.php

            // commerce/settings/gateways* is now registered in routes/cp.php

            $event->rules['commerce/settings/emails'] = 'commerce/emails/index';
            $event->rules['commerce/settings/emails/<storeHandle:{handle}>/new'] = 'commerce/emails/edit';
            $event->rules['commerce/settings/emails/<storeHandle:{handle}>/<id:\d+>'] = 'commerce/emails/edit';

            $event->rules['commerce/settings/pdfs'] = 'commerce/pdfs/index';
            $event->rules['commerce/settings/pdfs/<storeHandle:{handle}>/new'] = 'commerce/pdfs/edit';
            $event->rules['commerce/settings/pdfs/<storeHandle:{handle}>/<id:\d+>'] = 'commerce/pdfs/edit';

            // Order Statuses and Line Item Statuses are now registered in routes/cp.php

            // Store Settings and Payment Currencies are now registered in routes/cp.php

            // Shipping is now registered in routes/cp.php

            // Taxes are now registered in routes/cp.php

            // Sales, Discounts, and Pricing Rules are now registered in routes/cp.php

            // Inventory, Inventory Locations, and Transfers index/edit are now registered in
            // routes/cp.php — the element-edit rule below is Craft core's own generic
            // element-editing route, not a Commerce controller, so it stays here.
            $event->rules['commerce/inventory/transfers/<elementId:\\d+>'] = 'elements/edit';

            // commerce/donations is now registered in routes/cp.php
        });
    }
}
