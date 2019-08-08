# Rolling changelog for Craft Commerce 2.2

## Added

- Added the possibility for subscriptions to be suspended due to payment issues.
- Added the ability to resolve payment issues for subscriptions.
- Added `craft\commerce\base\SubscriptionGatewayInterface::getHasBillingIssues()`.
- Added `craft\commerce\base\SubscriptionGatewayInterface::getBillingIssueDescription()`.
- Added `craft\commerce\base\SubscriptionGatewayInterface::getBillingIssueResolveFormHtml()`.
- Added the `updateBillingDetailsUrl` config setting.
- Added the `suspended` status for Subscriptions.
- Added `craft\commerce\elements\Subscription::$dateSuspended`.
- Added `craft\commerce\elements\Subscription::$hasStarted`.
- Added `craft\commerce\elements\Subscription::$isSuspended`.
- Added `craft\commerce\elements\Subscription::getBillingIssueDescription()`.
- Added `craft\commerce\elements\Subscription::getBillingIssueResolveFormHtml()`.
- Added `craft\commerce\elements\Subscription::getHasBillingIssues()`.
- Added `craft\commerce\elements\db\SubscriptionQuery::$dateSuspended`.
- Added `craft\commerce\elements\db\SubscriptionQuery::$hasStarted`.
- Added `craft\commerce\elements\db\SubscriptionQuery::$isSuspended`.
- Added `craft\commerce\elements\db\SubscriptionQuery::anyStatus()`.
- Added `craft\commerce\elements\db\SubscriptionQuery::dateSuspended()`.
- Added `craft\commerce\elements\db\SubscriptionQuery::hasStarted()`.
- Added `craft\commerce\elements\db\SubscriptionQuery::isSuspended()`.

## Changed
- Added the "Subscriptions on hold" source group to Subscription index page with two sources for suspended subscriptions.
