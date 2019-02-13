# Adjusters

Adjusters are classes that return adjustment models to the cart. Adjustment models contain an amount which modifies the price of the order or line item. Adjustment models always belong to the order, but can optionally belong to a line item.

Custom adjusters are only available in the Pro editon of Craft Commerce.

An adjuster class implements the Adjuster Interface found at `vendor/craftcms/commerce/src/base/AdjusterInterface.php`.

## Register a New Adjuster

To have your adjuster class be found, simply append your adjuster class to the `types` array attribute in the `OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS` event model.

```php
Event::on(OrderAdjustments::class, OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS, function(RegisterComponentTypesEvent $event) {
  $event->types[] = MyAdjuster::class;
});
```

The order of the adjustments within the types array is important, as it is the order they will be run through on the order.

You could have a project level event listener, that could reorder these adjusters, and not append any new ones.

## Adjusting

The only method in the Adjuster Interface is the `adjust(Order $order)` method
Each order adjustment model should contain all information about how the adjuster came to its adjustment. For example, the shipping adjuster includes the information about the matching shipping rules used to calculate the shipping cost, and stores the rule information in the `sourceSnapshot` attribute of the adjustment model.

The `amount` value on the Order Adjustment model is used when totalling the cart. Use negative amounts to reduce the price of the order.

If you need to explain in plain text the adjustment made use the `description` field on the Model.

## Included Adjustments

If you mark the adjustment model’s `included` attribute as `true`, the adjustment does not make any changes to the orders total, but simply records an amount that was included in the price of the order.

The only 'included' adjustment we use in the the core of Commerce is included taxes.'

## Order or Line Item adjustment.

An adjustment model always belongs to an order, but can optionally belong to a line item. In your adjuster class, when creating the adjustment model, if the adjustment is for a particular line item, you will need to set it on the adjustment model like this:

`$adjustment->setLineItem($lineItem)`

This ensures that even if the line item is new and has no ID yet, the adjustment can reference the correct line item.

## Example

Below is an example adjuster class that puts a $2 discount on each line item:

```php
<?php 

use Craft;
use craft\base\Component;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderAdjustment;

class MyAdjuster extends Component implements AdjusterInterface
{

  public function adjust(Order $order): array
  {
    $adjustments = [];
    
    foreach ($order->getLineItems() as $item) {
      $adjustment = new OrderAdjustment;
      $adjustment->type = 'discount';
      $adjustment->name = '$2 off';
      $adjustment->description = '$2 off everything in the store';
      $adjustment->sourceSnapshot = [ 'data' => 'value']; // This can contain information about how the adjustment came to be
      $adjustment->amount = -2;
      $adjustment->setOrder($order);
      $adjustment->setLineItem($item);
      
      $adjustments[] = $adjustment;
    }
    
    return $adjustments;
  }
}        
```
