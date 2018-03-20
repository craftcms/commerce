# Adjusters

Adjusters are classes that return adjustment models to the cart. Ajustment models contain an amount which modifies the price of the order. Adjustment models can belong to 

An Adjuster class implements the Adjuster Interface found at `vendor/craftcms/commerce/src/base/AdjusterInterface.php`.

A plugin will registers the adjusters it wants to be run on the order by providing an array of adjusters objects from your main Plugin file.

## Register an Adjuster

Simply append your adjuster class to the types array in the `OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS` event model.

```
Event::on(OrderAdjustments::class, OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS, function(RegisterComponentTypesEvent $event) {
  $event->types[] = MyAdjuster::class;
});
```

The order of the adjustments within the types array is important, as it is the order they will be run through on the order. 

You could have a project level event listener, that could reorder these adjusters, and not append any new ones.

## Adjusting

Each order adjustment model should contain all information about the modifications made to the cart.

The `amount` value on the Order Adjustment Model is used when totalling the cart. Use negative amounts to reduce the price of the order.

If you need to explain in plain text the adjustment made use the `description` field on the Model.

## Included Adjustments

If you mark the adjustment model's `included` attribute as `true`, the adjustment does not make any changes to the orders total, but simply records an amount that was included in the price of the order.

The only included adjustment we use in the core commerce at the moment is included taxes.
