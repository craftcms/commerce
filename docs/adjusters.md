# Adjusters

Adjusters are a way to adjust values in the cart. The core Tax, Shipping, and Discount systems are all adjusters.

An Adjuster meets the Adjuster Interface found at `plugins/commerce/Commerce/Adjusters/Commerce_AdjusterInterface.php`.

A plugin will registers the adjusters it wants to be run on the order by providing an array of adjusters objects from your main Plugin file.

## Register an Adjuster

In your main plugin file provide a method called `commerce_registerOrderAdjusters`

```
public function commerce_registerOrderAdjusters(){
  return [
    new MyAdjusterClass
  ]
}
```

The function returns an array of new adjuster objects. The system then runs the order through each Adjusters `adjust` method.

## Adjusting

The Adjuster can add values to the Line Items and Order, but must record the adjustments by returning an array of `Commerce_OrderAdjustmentModel`s.
Each order adjustment model should contain all information about the modifications made to the cart.

The `amount` value on the Order Adjustment Model is not used when totalling the cart currently, only to display the total amount of adjustments made.
If you need to explain in plain text the adjustment made use the `description` field on the Model.

## Adjustable values

Any changes you make to a line item or order value (from the list of allowed values below) within an Adjuster class needs to be reflected within the `Commerce_OrderAdjustmentModel::amount` that you return from your adjuster's interface class.

For example, if your adjuster adds some shipping costs to a line item's `shippingCost` field, it also needs to add that amount to a new Commerce_OrderAdjustmentModel `amount` that your adjuster interface class returns.

To clarify further, the total of all the order's adjustments should always add up to the total of the changes made to the following values:

```
Commerce_LineItemModel::shippingCost
Commerce_LineItemModel::tax
Commerce_LineItemModel::discount
Commerce_OrderModel::baseShippingCost
Commerce_OrderModel::baseTax
Commerce_OrderModel::baseDiscount
```

The above fields are the only things that an adjuster should adjust.

In effect, this allows the system to use the total of the adjustment models 'amount' plus the total of the purchasable salePrice * qty, to get to the same value at the order `totalPrice`. This is what we do when building the `ItemBag` we send to the gateways.


## Example 1


```
class BusinessLogic_TaxRemover implements Commerce_AdjusterInterface {

    public function adjust(Commerce_OrderModel &$order, array $lineItems = []){

        $myAdjuster = new Commerce_OrderAdjustmentModel();

        $order->baseTax = $order->baseTax - 5;

        $myAdjuster->type = "Tax";
        $myAdjuster->name = "Australian GST Remover";
        $myAdjuster->description = "Removes $5 of Tax";
        $myAdjuster->amount = -5.0;
        $myAdjuster->orderId = $order->id;
        $myAdjuster->optionsJson = ['lineItemsAffected' => null];
        $myAdjuster->included = false;

        return [$myAdjuster];

    }

}
```

You can see above that the adjuster class above is not only removing 5 dollars from the `OrderModel::baseTax` but also storing the same `amount` on the adjustment model.

## Example 2


```
class BusinessLogic_Discounter implements Commerce_AdjusterInterface
{

    public function adjust(Commerce_OrderModel &$order, array $lineItems = [])
    {

        $myAdjuster = new Commerce_OrderAdjustmentModel();

        if ($order->totalQty >= 10) {
            $order->baseDiscount = $order->baseDiscount - 5;
            $myAdjuster->type = "Discount";
            $myAdjuster->name = "Discount for more than 10 items";
            $myAdjuster->description = "Discount of $5 for more than 10 items in the cart";
            $myAdjuster->amount = -5.0;
            $myAdjuster->orderId = $order->id;
            $myAdjuster->optionsJson = ['lineItemsAffected' => null];
            $myAdjuster->included = false;

            return [$myAdjuster];
        }

        return [];
    }
}
```

## Included Adjustments

If you mark the adjustment model's `included` attribute as `true`, the adjustment does not make any changes to the orders value, but simply records an amount that was included in the price of the order.

The only included adjustment we use in the core system at the moment is included taxes.

## Ordering Adjustments

The array you return with your plugin's `commerce_registerOrderAdjusters()` hook can be a simple array of objects that meet the Adjuster Interface. 

If you return a simple array, your adjusters will run after the standard adjusters or after any other plugins that provide adjusters and are loaded before your plugin.

You can also key the array of adjusters to insert between the standard adjusters.

Before running the adjusters on the order, we sort by array key. For example:

The standard adjusters have the following array keys:

```
$adjusters = [
  200 => new Commerce_ShippingAdjuster,
  400 => new Commerce_DiscountAdjuster,
  600 => new Commerce_TaxAdjuster,
];
```

This means, if you had two adjusters that needed to run between the standard adjusters you would return and array from your `commerce_registerOrderAdjusters()` function like this:

```
public function commerce_registerOrderAdjusters(){
  return [
    201 => new MyShippingAdjuster,
    601 => new MyTaxAdjuster
  ];
}
```

This would put your shipping adjuster after the core shipping adjuster, and your tax adjuster after the core tax adjuster.
