# Events

Craft Commerce provides a multitude of events for extending its functionality.

## Product related events

### The `beforeCaptureVariantSnapshot` event

Plugins can get notified before we capture a variant’s field data, and customize which fields are included. We do not  
include custom fields by default.

```php
use craft\commerce\elements\Variant;
use craft\commerce\events\CustomizeVariantSnapshotFieldsEvent;

Event::on(Variant::class, Variant::EVENT_BEFORE_CAPTURE_VARIANT_SNAPSHOT, function(CustomizeVariantSnapshotFieldsEvent $e) {
    $variant = $e->variant;
    $fields = $e->fields;
    
    // Add every custom field to the snapshot (huge amount of data and will increase your DB size
    if (($fieldLayout = $variant->getFieldLayout()) !== null) {
        foreach ($fieldLayout->getFields() as $field) {
            $fields[] = $field->handle;
        }
    }
    
    $e->fields = $fields;
});
```

### The `afterCaptureVariantSnapshot` event

Plugins can get notified after we capture a variant’s field data, and customize, extend, or redact the data to be persisted.

```php
use craft\commerce\elements\Variant;
use craft\commerce\events\CustomizeVariantSnapshotDataEvent;

Event::on(Variant::class, Variant::EVENT_AFTER_CAPTURE_VARIANT_SNAPSHOT, function(CustomizeVariantSnapshotFieldsEvent $e) {
    $variant = $e->variant;
    $data = $e->fieldData;
    // Modify or redact captured `$data`...
});
```

### The `beforeCaptureProductSnapshot` event

Plugins can get notified before we capture a product’s field data, and customize which fields are included. We do not  
include custom fields by default.

```php
use craft\commerce\elements\Variant;
use craft\commerce\events\CustomizeProductSnapshotFieldsEvent;

Event::on(Variant::class, Variant::EVENT_BEFORE_CAPTURE_PRODUCT_SNAPSHOT, function(CustomizeProductSnapshotFieldsEvent $e) {
    $product = $e->product;
    $fields = $e->fields;
    
    // Add every custom field to the snapshot (huge amount of data and will increase your DB size) Don't recommend.
    if (($fieldLayout = $product->getFieldLayout()) !== null) {
        foreach ($fieldLayout->getFields() as $field) {
            $fields[] = $field->handle;
        }
    }
    
    $e->fields = $fields;
});
```

### The `afterCaptureProductSnapshot` event

Plugins can get notified after we capture a product’s field data, and customize, extend, or redact the data to be persisted.

```php
use craft\commerce\elements\Variant;
use craft\commerce\events\CustomizeProductSnapshotDataEvent;

Event::on(Variant::class, Variant::EVENT_AFTER_CAPTURE_PRODUCT_SNAPSHOT, function(CustomizeProductSnapshotFieldsEvent $e) {
    $product = $e->product;
    $data = $e->fieldData;
    // Modify or redact captured `$data`
});
```

### The `beforeMatchPurchasableSale` event

You may set the `isValid` property to `false` on the event to prevent the application of the matched sale.

Plugins can get notified when a purchasable matches a sale.

```php
use craft\commerce\events\SaleMatchEvent;
use craft\commerce\services\Sales;
use yii\base\Event;

Event::on(Sales::class, Sales::EVENT_BEFORE_MATCH_PURCHASABLE_SALE, function(SaleMatchEvent $e) {
     // Perhaps prevent the purchasable match with sale based on some business logic.
});
```

## Order related events

### The `afterAddLineItem` event

Plugins can get notified after a line item has been added to the order

```php
use craft\commerce\elements\Order;
use yii\base\Event;

Event::on(Order::class, Order::EVENT_AFTER_ADD_LINE_ITEM, function(Event $e) {
    $lineItem = $e->lineItem;
    $isNew = $e->isNew;
    // ...
});
```

### The `beforeCompleteOrder` event

Plugins can get notified before an order is completed.

```php
use craft\commerce\elements\Order;
use yii\base\Event;

Event::on(Order::class, Order::EVENT_BEFORE_COMPLETE_ORDER, function(Event $e) {
    // @var Order $order
    $order = $e->sender;
    // ...
});
```
### The `afterCompleteOrder` event

Plugins can get notified after an order is completed

```php
use craft\commerce\elements\Order;
use yii\base\Event;

Event::on(Order::class, Order::EVENT_AFTER_COMPLETE_ORDER, function(Event $e) {
    // @var Order $order
    $order = $e->sender;
    // ...
});
```

### The `afterOrderPaid` event

Plugins can get notified after an order is paid and completed

```php
use craft\commerce\elements\Order;
use yii\base\Event;

Event::on(Order::class, Order::EVENT_AFTER_ORDER_PAID, function(Event $e) {
    // @var Order $order
    $order = $e->sender;
    // ...
});
```

### The `afterDiscountAdjustmentsCreated` event

Plugins can get notified before a line item is being saved

```php
use craft\commerce\adjusters\Discount;
use yii\base\Event;

Event::on(Discount::class, Discount::EVENT_AFTER_DISCOUNT_ADJUSTMENTS_CREATED, function(DiscountAdjustmentsEvent $e) {
    // Do something - perhaps use a third party to check order data and modify the adjustments.
});
```

### The `beforeMatchLineItem` event

You may set the `isValid` property to `false` on the event to prevent the application of the matched discount.
Plugins can get notified before an item is removed from the cart.

```php
use craft\commerce\events\MatchLineItemEvent;
use craft\commerce\services\Discounts;
use yii\base\Event;

Event::on(Discounts::class, Discounts::EVENT_BEFORE_MATCH_LINE_ITEM, function(MatchLineItemEvent $e) {
     // Maybe check some business rules and prevent a match from happening in some cases.
});
```

### The `beforeSaveLineItem` event

Plugins can get notified before a line item is being saved

```php
use craft\commerce\events\LineItemEvent;
use craft\commerce\services\LineItems;
use yii\base\Event;

Event::on(LineItems::class, LineItems::EVENT_DEFAULT_ORDER_STATUS, function(LineItemEvent $e) {
    // Do something - perhaps let a third party service know about changes to an order
});
```

### The `afterSaveLineItem` event

Plugins can get notified after a line item is being saved

```php
use craft\commerce\events\LineItemEvent;
use craft\commerce\services\LineItems;
use yii\base\Event;

Event::on(LineItems::class, LineItems::EVENT_DEFAULT_ORDER_STATUS, function(LineItemEvent $e) {
    // Do something - perhaps reserve the stock
});
```

### The `populateLineItem` event

Plugins can get notified as a line item is being populated from a purchasable.

```php
use craft\commerce\events\LineItemEvent;
use craft\commerce\services\LineItems;
use yii\base\Event;

Event::on(LineItems::class, LineItems::EVENT_POPULATE_LINE_ITEM, function(LineItemEvent $e) {
    // Do something - perhaps modify the price of a line item
});
```

### The `createLineItem` event

Plugins can get notified after a line item has been created from a purchasable

```php
use craft\commerce\events\LineItemEvent;
use craft\commerce\services\LineItems;
use yii\base\Event;

Event::on(LineItems::class, LineItems::EVENT_CREATE_LINE_ITEM, function(LineItemEvent $e) {
    // Do something - perhaps call a third party service according to the line item options
});
```

### The `registerOrderAdjusters` event

Plugins can register their own adjusters.

```php
use craft\events\RegisterComponentTypesEvent;
use craft\commerce\services\OrderAdjustments;
use yii\base\Event;

Event::on(OrderAdjustments::class, OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS, function(RegisterComponentTypesEvent $e) {
    $e->types[] = MyAdjuster::class;
});
```

### The `orderStatusChange` event

Plugins can get notified when an order status is changed

```php
use craft\commerce\events\OrderStatusEvent;
use craft\commerce\services\OrderHistories;
use yii\base\Event;

Event::on(OrderHistories::class, OrderHistories::EVENT_ORDER_STATUS_CHANGE, function(OrderStatusEvent $e) {
     // Perhaps, let the delivery department know that the order is ready to be delivered.
});
```

### The `defaultOrderStatus` event

You may set the `orderStatus` property to a desired OrderStatus to override the default status set in CP

Plugins can get notified when a default order status is being fetched

```php
use craft\commerce\events\DefaultOrderStatusEvent;
use craft\commerce\services\OrderStatuses;
use yii\base\Event;

Event::on(OrderStatuses::class, OrderStatuses::EVENT_DEFAULT_ORDER_STATUS, function(DefaultOrderStatusEvent $e) {
    // Do something - perhaps figure out a better default order statues than the one set in CP
});
```

## Payment related events

### The `registerGatewayTypes` event

Plugins can register their own gateways.

```php
use craft\events\RegisterComponentTypesEvent;
use craft\commerce\services\Purchasables;
use yii\base\Event;

Event::on(Gateways::class, Gateways::EVENT_REGISTER_GATEWAY_TYPES, function(RegisterComponentTypesEvent $e) {
    $e->types[] = MyGateway::class;
});
```

### The `afterPaymentTransaction` event

Plugins can get notified after a payment transaction is made

```php
use craft\commerce\events\TransactionEvent;
use craft\commerce\services\Payments;
use yii\base\Event;

Event::on(Payments::class, Payments::EVENT_AFTER_PAYMENT_TRANSACTION, function(TransactionEvent $e) {
    // Do something - perhaps check if that was a authorize transaction and make sure that warehouse team is on top of it
});
```

### The `beforeCaptureTransaction` event

Plugins can get notified before a payment transaction is captured

```php
use craft\commerce\events\TransactionEvent;
use craft\commerce\services\Payments;
use yii\base\Event;

Event::on(Payments::class, Payments::EVENT_BEFORE_CAPTURE_TRANSACTION, function(TransactionEvent $e) {
    // Do something - maybe check if the shipment is really ready before capturing
});
```

### The `afterCaptureTransaction` event

Plugins can get notified after a payment transaction is captured

```php
use craft\commerce\events\TransactionEvent;
use craft\commerce\services\Payments;
use yii\base\Event;

Event::on(Payments::class, Payments::EVENT_AFTER_CAPTURE_TRANSACTION, function(TransactionEvent $e) {
    // Do something - probably notify warehouse that we're ready to ship
});
```

### The `beforeRefundTransaction` event

Plugins can get notified before a transaction is refunded

```php
use craft\commerce\events\RefundTransactionEvent;
use craft\commerce\services\Payments;
use yii\base\Event;

Event::on(Payments::class, Payments::EVENT_BEFORE_REFUND_TRANSACTION, function(RefundTransactionEvent $e) {
    // Do something - perhaps check if refund amount more than half the transaction and do something based on that
});
```

### The `afterRefundTransaction` event

Plugins can get notified after a transaction is refunded

```php
use craft\commerce\events\RefundTransactionEvent;
use craft\commerce\services\Payments;
use yii\base\Event;

Event::on(Payments::class, Payments::EVENT_AFTER_REFUND_TRANSACTION, function(RefundTransactionEvent $e) {
    // Do something - perhaps check if refund amount more than half the transaction and do something based on that
});
```

### The `beforeProcessPaymentEvent` event

You may set the `isValid` property to `false` on the event to prevent the payment from being processed

Plugins can get notified before a payment is being processed

```php
use craft\commerce\events\ProcessPaymentEvent;
use craft\commerce\services\Payments;
use yii\base\Event;

Event::on(Payments::class, Payments::EVENT_BEFORE_PROCESS_PAYMENT, function(ProcessPaymentEvent $e) {
    // Do something - perhaps check if the transaction is allowed for the order based on some business rules.
});
```

### The `afterProcessPaymentEvent` event

Plugins can get notified after a payment is processed

```php
use craft\commerce\events\ProcessPaymentEvent;
use craft\commerce\services\Payments;
use yii\base\Event;

Event::on(Payments::class, Payments::EVENT_AFTER_PROCESS_PAYMENT, function(ProcessPaymentEvent $e) {
    // Do something - maybe let accounting dept. know that a transaction went through for an order.
});
```

### The `deletePaymentSource` event

Plugins can get notified when a payment source is deleted.

```php
use craft\commerce\events\PaymentSourceEvent;
use craft\commerce\services\PaymentSources;
use yii\base\Event;

Event::on(PaymentSources::class, PaymentSources::EVENT_DELETE_PAYMENT_SOURCE, function(PaymentSourceEvent $e) {
    // Do something - perhaps warn a user they have no valid payment sources saved.
});
```

### The `beforeSavePaymentSource` event

Plugins can get notified before a payment source is added.

```php
use craft\commerce\events\PaymentSourceEvent;
use craft\commerce\services\PaymentSources;
use yii\base\Event;

Event::on(PaymentSources::class, PaymentSources::EVENT_BEFORE_SAVE_PAYMENT_SOURCE, function(PaymentSourceEvent $e) {
    // Do something
});
```

### The `afterSavePaymentSource` event

Plugins can get notified after a payment source is added.

```php
use craft\commerce\events\PaymentSourceEvent;
use craft\commerce\services\PaymentSources;
use yii\base\Event;

Event::on(PaymentSources::class, PaymentSources::EVENT_BEFORE_SAVE_PAYMENT_SOURCE, function(PaymentSourceEvent $e) {
    // Do something - perhaps settle any outstanding balance
});
```

### The `afterSaveTransaction` event

Plugins can get notified after a transaction has been saved.

```php
use craft\commerce\events\TransactionEvent;
use craft\commerce\services\Transactions;
use yii\base\Event;

Event::on(Transactions::class, Transactions::EVENT_AFTER_SAVE_TRANSACTION, function(TransactionEvent $e) {
    // Do something - perhaps run our custom logic for failed transactions
});
```

### The `afterCreateTransaction` event

Plugins can get notified after a transaction has been created.

```php
use craft\commerce\events\TransactionEvent;
use craft\commerce\services\Transactions;
use yii\base\Event;

Event::on(Transactions::class, Transactions::EVENT_AFTER_CREATE_TRANSACTION, function(TransactionEvent $e) {
    // Do something - perhaps run our custom logic depending on the transaction type
});
```

## Subscription related events

### The `afterExpireSubscription` event

Plugins can get notified when a subscription is being expired.

```php
use craft\commerce\events\SubscriptionEvent;
use craft\commerce\services\Subscriptions;
use yii\base\Event;

Event::on(Subscriptions::class, Subscriptions::EVENT_AFTER_EXPIRE_SUBSCRIPTION, function(SubscriptionEvent $e) {
    // Do something about it - perhaps make a call to third party service to de-authorize a user.
});
```

### The `beforeCreateSubscription` event

You may set the `isValid` property to `false` on the event to prevent the user from being subscribed to the plan.

Plugins can get notified before a subscription is created.

```php
use craft\commerce\events\CreateSubscriptionEvent;
use craft\commerce\services\Subscriptions;
use yii\base\Event;

Event::on(Subscriptions::class, Subscriptions::EVENT_BEFORE_CREATE_SUBSCRIPTION, function(CreateSubscriptionEvent $e) {
    // Set the trial days based on some business logic
});
```

### The `afterCreateSubscription` event

Plugins can get notified after a subscription is created.

```php
use craft\commerce\events\SubscriptionEvent;
use craft\commerce\services\Subscriptions;
use yii\base\Event;

Event::on(Subscriptions::class, Subscriptions::EVENT_AFTER_CREATE_SUBSCRIPTION, function(SubscriptionEvent $e) {
    // Do something about it - perhaps make a call to third party service to authorize a user
});
```

### The `beforeReactivateSubscription` event

You may set the `isValid` property to `false` on the event to prevent the subscription from being reactivated

Plugins can get notified before a subscription gets reactivated.

```php
use craft\commerce\events\SubscriptionEvent;
use craft\commerce\services\Subscriptions;
use yii\base\Event;

Event::on(Subscriptions::class, Subscriptions::EVENT_BEFORE_REACTIVATE_SUBSCRIPTION, function(SubscriptionEvent $e) {
    // Do something - maybe the user does not qualify for reactivation due to some business logic.
});
```

### The `afterReactivateSubscription` event

Plugins can get notified before a subscription gets reactivated.

```php
use craft\commerce\events\SubscriptionEvent;
use craft\commerce\services\Subscriptions;
use yii\base\Event;

Event::on(Subscriptions::class, Subscriptions::EVENT_AFTER_REACTIVATE_SUBSCRIPTION, function(SubscriptionEvent $e) {
    // Do something - maybe the user needs to be re-authorized with a third party service.
});
```

### The `beforeSwitchSubscriptionPlan` event

You may set the `isValid` property to `false` on the event to prevent the switch from happening

Plugins can get notified before a subscription is switched to a different plan.

```php
use craft\commerce\events\SubscriptionSwitchPlansEvent;
use craft\commerce\services\Subscriptions;
use yii\base\Event;

Event::on(Subscriptions::class, Subscriptions::EVENT_BEFORE_SWITCH_SUBSCRIPTION_PLAN, function(SubscriptionSwitchPlansEvent $e) {
    // Do something - maybe mody the switch parameters based on some business logic.
});
```

### The `afterSwitchSubscriptionPlan` event

Plugins can get notified after a subscription gets switched to a different plan.

```php
use craft\commerce\events\SubscriptionSwitchPlansEvent;
use craft\commerce\services\Subscriptions;
use yii\base\Event;

Event::on(Subscriptions::class, Subscriptions::EVENT_AFTER_SWITCH_SUBSCRIPTION_PLAN, function(SubscriptionSwitchPlansEvent $e) {
    // Do something - maybe the user needs their permissions adjusted on a third party service.
});
```

### The `beforeCancelSubscription` event

You may set the `isValid` property to `false` on the event to prevent the subscription from being canceled

Plugins can get notified before a subscription is canceled.

```php
use craft\commerce\events\CancelSubscriptionEvent;
use craft\commerce\services\Subscriptions;
use yii\base\Event;

Event::on(Subscriptions::class, Subscriptions::EVENT_BEFORE_CANCEL_SUBSCRIPTION, function(CancelSubscriptionEvent $e) {
    // Do something - maybe the user is not permitted to cancel the subscription for some reason.
});
```

### The `afterCancelSubscription` event

Plugins can get notified after a subscription gets canceled.

```php
use craft\commerce\events\CancelSubscriptionEvent;
use craft\commerce\services\Subscriptions;
use yii\base\Event;

Event::on(Subscriptions::class, Subscriptions::EVENT_AFTER_CANCEL_SUBSCRIPTION, function(CancelSubscriptionEvent $e) {
    // Do something - maybe refund the user for the remainder of the subscription.
});
```

### The `beforeUpdateSubscription` event

Plugins can get notified before a subscription gets updated. Typically this event is fired when subscription data is updated on the gateway.

```php
use craft\commerce\events\SubscriptionEvent;
use craft\commerce\services\Subscriptions;
use yii\base\Event;

Event::on(Subscriptions::class, Subscriptions::EVENT_BEFORE_UPDATE_SUBSCRIPTION, function(SubscriptionEvent $e) {
    // Do something - maybe refund the user for the remainder of the subscription.
});
```

### The `receiveSubscriptionPayment` event

Plugins can get notified when a subscription payment is received.

```php
use craft\commerce\events\SubscriptionPaymentEvent;
use craft\commerce\services\Subscriptions;
use yii\base\Event;

Event::on(Subscriptions::class, Subscriptions::EVENT_RECEIVE_SUBSCRIPTION_PAYMENT, function(SubscriptionPaymentEvent $e) {
    // Do something - perhaps update the loyalty reward data.
});
```

## Other events

### The `registerAddressValidationRules` event

Plugins can add additional address validation rules.

```php
use craft\commerce\events\RegisterAddressRulesEvent;
use craft\commerce\models\Address;

Event::on(Address::class, Address::EVENT_REGISTER_ADDRESS_VALIDATION_RULES, function(RegisterAddressRulesEvent $event) {
     $event->rules[] = [['attention'], 'required'];
});
```

### The `beforeSaveAddress` event

Plugins can get notified before an address is being saved

```php
use craft\commerce\events\AddressEvent;
use craft\commerce\services\Addresses;
use yii\base\Event;

Event::on(Addresses::class, Addresses::EVENT_BEFORE_SAVE_ADDRESS, function(AddressEvent $e) {
    // Do something - perhaps let an external CRM system know about a client's new address
});
```

### The `afterSaveAddress` event

Plugins can get notified before an address is being saved

```php
use craft\commerce\events\AddressEvent;
use craft\commerce\services\Addresses;
use yii\base\Event;

Event::on(Addresses::class, Addresses::EVENT_AFTER_SAVE_ADDRESS, function(AddressEvent $e) {
    // Do something - perhaps set this address as default in an external CRM system
});
```

### The `beforeSendEmail` event

You may set the `isValid` property to `false` on the event to prevent the email from being sent.
Plugins can get notified before an email is being sent out.

```php
use craft\commerce\events\MailEvent;
use craft\commerce\services\Emails;
use yii\base\Event;

Event::on(Emails::class, Emails::EVENT_BEFORE_SEND_MAIL, function(MailEvent $e) {
     // Maybe prevent the email based on some business rules or client preferences.
});
```

### The `afterSendEmail` event

Plugins can get notified after an email has been sent out.

```php
use craft\commerce\events\MailEvent;
use craft\commerce\services\Emails;
use yii\base\Event;

Event::on(Emails::class, Emails::EVENT_AFTER_SEND_MAIL, function(MailEvent $e) {
     // Perhaps add the email to a CRM system
});
```

### The `beforeRenderPdf` event

Event handlers can override Commerce’s PDF generation by setting the `pdf` property on the event to a custom-rendered PDF.
Plugins can get notified before the PDF or an order is being rendered.

```php
use craft\commerce\events\PdfEvent;
use craft\commerce\services\Pdf;
use yii\base\Event;

Event::on(Pdf::class, Pdf::EVENT_BEFORE_RENDER_PDF, function(PdfEvent $e) {
     // Roll out our own custom PDF
});
```

### The `afterRenderPdf` event

Plugins can get notified after the PDF or an order has been rendered.

```php
use craft\commerce\events\PdfEvent;
use craft\commerce\services\Pdf;
use yii\base\Event;

Event::on(Pdf::class, Pdf::EVENT_AFTER_RENDER_PDF, function(PdfEvent $e) {
     // Add a watermark to the PDF or forward it to the accounting dpt.
});
```

### The `beforeSaveProductType` event

Plugins can get notified before a product type is being saved.

```php
use craft\commerce\events\ProductTypeEvent;
use craft\commerce\services\ProductTypes;
use yii\base\Event;

Event::on(ProductTypes::class, ProductTypes::EVENT_BEFORE_SAVE_PRODUCTTYPE, function(ProductTypeEvent $e) {
     // Maybe create an audit trail of this action.
});
```

### The `afterSaveProductType` event

Plugins can get notified after a product type has been saved.

```php
use craft\commerce\events\ProductTypeEvent;
use craft\commerce\services\ProductTypes;
use yii\base\Event;

Event::on(ProductTypes::class, ProductTypes::EVENT_AFTER_SAVE_PRODUCTTYPE, function(ProductTypeEvent $e) {
     // Maybe prepare some third party system for a new product type
});
```

### The `registerPurchasableElementTypes` event

Plugins can register their own purchasables.

```php
use craft\events\RegisterComponentTypesEvent;
use craft\commerce\services\Purchasables;
use yii\base\Event;

Event::on(Purchasables::class, Purchasables::EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES, function(RegisterComponentTypesEvent $e) {
    $e->types[] = MyPurchasable::class;
});
```

### The `registerAvailableShippingMethods` event

Plugins can register their own shipping methods.

```php
use craft\events\RegisterComponentTypesEvent;
use craft\commerce\services\ShippingMethods;
use yii\base\Event;

Event::on(ShippingMethods::class, ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS, function(RegisterComponentTypesEvent $e) {
    $e->shippingMethods[] = MyShippingMethod::class;
});
```
