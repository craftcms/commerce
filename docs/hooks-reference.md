# Hooks Reference

Craft Commerce provides several hooks that give plugins the opportunity to get involved in various areas of the system.

### commerce_modifyPaymentRequest

Example:
```php
public function commerce_modifyPaymentRequest(array $request){

  $request['description'] = "My Order Info";
  
  return $request;
}
```

The hook must return an array that is merged into the original `$request` array.

### commerce_modifyGatewayRequestData

The hook must return the raw modified data object that is applicable to the gateway being used.

Example:
```php
public function commerce_modifyPaymentRequest($data, $type, $transaction){

  // Modify the $data
  return $data;
}
```

This hook differs from the `modifyPaymentRequest` hook, in that it not the basic data array before being passed to the omnipay gateway, but is the actual raw data object being sent to the gateway. This might be a SimpleXML object or any other type of data object native to the gateway being used. You would only want to modify this if the omnipay gateway does not expose the getters and setters on to the request

For more information on modifying the raw data, see the [omnipay changelog](http://omnipay.thephpleague.com/changelog/#additions) and the omnipay [pull request](https://github.com/thephpleague/omnipay/pull/162).


### commerce_modifyItemBag

The hook can modify the omnipay ItemBag object that will be sent to the gateway.

Example:
```php
public function commerce_modifyItemBag($items, $order){
	
  // Modify the $items
	
}
```


### commerce_modifyOrderSources

Gives plugins a chance to customize and modify the sources on the order listing screen. By default the sources are order statuses and active and inactive carts.

```php
public function commerce_modifyOrderSources($sources, $context)
{
    unset($sources['carts:inactive']); // remove the inactive carts from the sources.
}
```

### commerce_getOrderTableAttributeHtml

Gives plugins a chance to customize the HTML of the table cells on the order index page.

```php
public function commerce_getOrderTableAttributeHtml(Commerce_OrderModel $order, $attribute)
{
    if ($attribute == 'totalPaid')
    {
        return $order->totalPaid * 100;
    }
}
```

### commerce_getProductTableAttributeHtml

Gives plugins a chance to customize the HTML of the table cells on the product index page.

```php
public function commerce_getProductTableAttributeHtml(Commerce_ProductModel $product, $attribute)
{
    if ($attribute == 'freeShipping')
    {
        return 'FREE';
    }
}
```

### commerce_defaultCartShippingAddress

If there is no shipping address on the cart, you can optionally return a default address that should be set on the cart.

The hook should return a `Commerce_AddressModel` of a real address existing in the `commerce_address` database table.

Example:
```php
public function commerce_defaultCartShippingAddress(){
  
	$address = craft()->commerce_addresses->getAddressById($myId);
	return $address;
  
}
```

### commerce_defaultCartBillingAddress

If there is no billing address on the cart, you can optionally return a default address that should be set on the cart.

The hook should return a `Commerce_AddressModel` of a real address existing in the `commerce_address` database table.

Example:
```php
public function commerce_defaultCartBillingAddress(){
  
	$address = craft()->commerce_addresses->getAddressById($myId);
	return $address;
  
}
```

### commerce_defineAdditionalOrderTableAttributes

Gives plugins a chance to make additional table columns available to order indexes.

```php
public function commerce_defineAdditionalOrderTableAttributes()
{
    return array(
        'foo' => "Foo",
        'bar' => "Bar",
    );
}
```

### commerce_defineAdditionalProductTableAttributes

Gives plugins a chance to make additional table columns available to product indexes.

```php
public function commerce_defineAdditionalProductTableAttributes()
{
    return array(
        'foo' => "Foo",
        'bar' => "Bar",
    );
}
```

### commerce_modifyEmail

Example:
```php
public function commerce_modifyEmail(EmailModel &$email, $order){
  
  $email->subject = "new Subject";
  
}
```
Hook must modify the `$email` Email Model directly and not return anything.
You may use information contained on the `$order` to determine changes to the email.


### commerce_registerOrderAdjusters

Example:
```php
public function commerce_registerOrderAdjusters(){
  
	$myAdjuster = new MyAdjuster;
	return [$myAdjuster];
  
}
```
