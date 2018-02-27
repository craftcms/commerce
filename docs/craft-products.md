# craft.products

## How to get products

You can access your site’s products from your templates via `craft.products`
It returns an [ElementQuery](https://github.com/craftcms/docs/blob/v3/en/element-queries.md) object.

```twig
{% set products = craft.products.type('normal').all() %}

{% for product in products %}
  {% for variant in product.variants %}
    {{ variant.sku }} - {{ variant.salePrice }} <br>
  {% endfor %}
{% endfor %}
```

## Parameters

`craft.products` supports the following parameters:

### ID
The Product's element ID.

### type
Product Type model or handle.

### typeId
Product type ID.

### status
Only fetch products with the given status.

Possible values are 'live', 'pending', 'expired', 'disabled', and null.
The default value is 'live'. null will return all entries regardless of status.

An entry is 'live' if it is enabled, has a `availableOn` in the past and an `expiresOn` Date in the future.
An entry is 'pending' if it is enabled and has `availableOn` and `expiresOn` Dates in the future.
An entry is 'expired' if it is enabled and has `availableOn` and `expiresOn` Dates in the past.

### postDate
Fetch products based on their postDate.

### expiryDate
Fetch products based on their date of expiry.

### after
Fetch products based on available dates after this date.

### before
Fetch products based on their date available.

### defaultWeight
Fetch products based on the default variant's weight

### defaultHeight
Fetch products based on the default variant's height

### defaultLength
Fetch products based on the default variant's length

### defaultWidth
Fetch products based on the default variant's width

### defaultSku
Fetch products based on the default variant's sku

### hasVariant
Only return products where the `hasVariant` params match the product's variants.

For example:

```twig
{% set products = craft.products.type('tshirt').hasVariant({ color: 'red' }) %}
```

## Variant Parameters

There is no way to query all variants directly, but within the `hasVariant` product criteria parameters you have access to all basic element criteria parameters in addition to the following special criteria that apply to variants:

### hasStock
Returns products that have at least one variant in stock.

Accepts: `true` or `false`


For example:

```twig
{% set products = craft.products({
  hasVariant: {
    hasStock: true
  },
}) %}
```

### hasSales
Returns products that have at least one sale available to the current user.

Accepts: `true` or `false`

For example:

```twig
{% set products = craft.products({
  hasSales: true
}) %}
```
