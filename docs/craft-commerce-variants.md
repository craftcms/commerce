## How to get variants

Usually you will only want to retrieve products, giving you access to their variants. Sometimes you might want to query for variants directly.

You can access your site’s variants from your templates via `craft.commerce.variants`
It returns an [ElementCriteriaModel](http://buildwithcraft.com/docs/templating/elementcriteriamodel) object.

```twig
{% set variants = craft.commerce.variants.id(8376).first() %}

{{ variant.sku }} - {{ variant.salePrice }}
```

## Parameters

`craft.commerce.variants` supports the following parameters:

### ID
The variant's element ID.

### productId
The product ID this variant belongs to.

### sku
The variant's SKU

### default
Whether the variant is the product's default variant

Accepts: boolean (`true` or `false`)

### stock
The stock amount

Accepts: integer

### hasStock
Whether the variant has unlimited stock or stock greater than zero.

Accepts: boolean (`true` or `false`)
