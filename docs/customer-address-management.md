# Customer Address Management

When a customer checks out with a new address, the address is added to their address book.

If the customer is a guest, they have no need to manage an address book.

Customers can only add and remove addresses from the front-end while they are logged in.

## Get all addresses belonging to the current customer

```twig
{% addresses = craft.commerce.customer.addresses %}
{% for address in addresses %}
  {address.firstName}<br/>
  ...
{% endfor %}
```

See <api:craft\commerce\models\Address> to learn about the fields available on an address.

## Updating or creating a new customer address

```twig
<form method="post">
  <input type="hidden" name="action" value="commerce/customer-addresses/save">
  <input type="hidden" name="redirect" value="commerce/customer/addresses">
  <input type="text" name="address[firstName]" value="{{ address ? address[firstName] : '' }}">
  <input type="text" name="address[lastName]" value="{{ address ? address[lastName] : '' }}">
  ...
  <input type="submit" value="Save"/>
</form>
```

Including an `address[id]` param with a valid address ID that belongs to the current customer will update that address.

## Deleting a customer's address

```twig
<form method="POST">
  <input type="hidden" name="action" value="commerce/customer-addresses/delete">
  <input type="hidden" name="redirect" value="commerce/customer/addresses">
  <input type="hidden" name="id" value="{{ address.id }}"/>
  <input type="submit" value="delete"/>
</form>
```

Deleting an address that is currently applied as the billing or shipping address of the current customer's cart will result in the addresses on the cart being removed.
