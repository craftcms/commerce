# Donations

Donations can be added to the cart using the donation purchasable. This is a separate element type than products and variants, 
and the donation element does not belong to a “product type”.

There is a single donation element installed when you install Craft Commerce. 
The donation element settings are found in the Control Panel within Commerce → Store Settings → Donations.

Within the donation settings, the SKU of the donation purchasable can be changed, and donations can be turned off.

Within the front end templates, you can get the donation purchasable with `craft.commerce.donation`. This will return the single donation purchasable.

### Adding the donation to the cart.

Since the donation purchasable has no default price, a price must be supplied with the donation when adding to the cart. 
This is done through [line item options](adding-to-and-updating-the-cart.md#line-item-options-and-notes) by submitting a `donationAmount` option parameter.

The form to add the donation to the cart would look like this:

 ```twig
  {% set donation = craft.commerce.donation %}
  {% if donation and donation.isAvailable %}
  <form method="POST" class="pt-2 pb-8 text-center">
      <input type="hidden" name="action" value="commerce/cart/update-cart">
      {{ redirectInput('shop/cart') }}
      {{ csrfInput() }}
      <input type="hidden" name="purchasableId" value="{{ craft.commerce.donation.id }}">
      <input type="text" name="options[donationAmount]" value="" placeholder="Donation">
      <input type="submit" value="Donate Now" />
  </form>
  {% endif %}
```

The `donationAmount` option parameter is required when adding the donation purchasable to the cart. The value submitted must also be numeric.

Customers can add more than one donation to the cart but the line item will be replaced if the 
[`optionSignature`](adding-to-and-updating-the-cart.md#options-uniqueness) remains the same, just like it does for any purchasables added to the cart.

Once the donation is in the cart, the donation amount can also be updated using the standard line item option updating form. You would usually hide the qty field, 
which still continues to work, but it would not make sense to show to the customer.

### Promotions, Shipping, and Tax

Donations can not be promoted with a sale or discount. Donations use the default shipping category and tax category, but the donation is marked as 
`taxFree` and not `shippable`, so under normal usage of the tax and shipping system, they will not have shipping or tax costs associated with them. 