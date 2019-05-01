# Variant Queries

You can fetch variants in your templates or PHP code using **variant queries**.

::: code
```twig
{# Create a new variant query #}
{% set myVariantQuery = craft.variants() %}
```
```php
// Create a new variant query
$myVariantQuery = \craft\commerce\elements\Variant::find();
```
:::

Once you’ve created a variant query, you can set [parameters](#parameters) on it to narrow down the results, and then [execute it](https://docs.craftcms.com/v3/dev/element-queries/#executing-element-queries) by calling `.all()`. An array of [Variant](api:craft\commerce\elements\Variant) objects will be returned.

::: tip
See [Introduction to Element Queries](https://docs.craftcms.com/v3/dev/element-queries/) in the Craft docs to learn about how element queries work.
:::

## Example

We can display a specific variant by its ID by doing the following:

1. Create a variant query with `craft.variants()`.
2. Set the [id](#id) parameter on it.
3. Fetch the variant with `.one()`.
4. Output information about the variant as HTML.

```twig
{# Get the requested variant ID from the query string #}
{% set variantId = craft.app.request.getQueryParam('id') %}

{# Create a variant query with the 'id' parameter #}
{% set myVariantQuery = craft.variants()
    .id(variantId) %}

{# Fetch the variant #}
{% set variant = myVariantQuery.one() %}

{# Make sure it exists #}
{% if not variant %}
    {% exit 404 %}
{% endif %}

{# Display the variant #}
<h1>{{ variant.title }}</h1>
<!-- ... -->
```

<!-- BEGIN PARAMS -->

### `anyStatus`

Clears out the [status()](https://docs.craftcms.com/api/v3/craft-elements-db-elementquery.html#method-status) and [enabledForSite()](https://docs.craftcms.com/api/v3/craft-elements-db-elementquery.html#method-enabledforsite) parameters.





::: code
```twig
{# Fetch all variants, regardless of status #}
{% set variants = craft.variants()
    .anyStatus()
    .all() %}
```

```php
// Fetch all variants, regardless of status
$variants = \craft\commerce\elements\Variant::find()
    ->anyStatus()
    ->all();
```
:::


### `asArray`

Causes the query to return matching variants as arrays of data, rather than [Variant](api:craft\commerce\elements\Variant) objects.





::: code
```twig
{# Fetch variants as arrays #}
{% set variants = craft.variants()
    .asArray()
    .all() %}
```

```php
// Fetch variants as arrays
$variants = \craft\commerce\elements\Variant::find()
    ->asArray()
    ->all();
```
:::


### `dateCreated`

Narrows the query results based on the variants’ creation dates.



Possible values include:

| Value | Fetches variants…
| - | -
| `'>= 2018-04-01'` | that were created on or after 2018-04-01.
| `'< 2018-05-01'` | that were created before 2018-05-01
| `['and', '>= 2018-04-04', '< 2018-05-01']` | that were created between 2018-04-01 and 2018-05-01.



::: code
```twig
{# Fetch variants created last month #}
{% set start = date('first day of last month')|atom %}
{% set end = date('first day of this month')|atom %}

{% set variants = craft.variants()
    .dateCreated(['and', ">= #{start}", "< #{end}"])
    .all() %}
```

```php
// Fetch variants created last month
$start = (new \DateTime('first day of last month'))->format(\DateTime::ATOM);
$end = (new \DateTime('first day of this month'))->format(\DateTime::ATOM);

$variants = \craft\commerce\elements\Variant::find()
    ->dateCreated(['and', ">= {$start}", "< {$end}"])
    ->all();
```
:::


### `dateUpdated`

Narrows the query results based on the variants’ last-updated dates.



Possible values include:

| Value | Fetches variants…
| - | -
| `'>= 2018-04-01'` | that were updated on or after 2018-04-01.
| `'< 2018-05-01'` | that were updated before 2018-05-01
| `['and', '>= 2018-04-04', '< 2018-05-01']` | that were updated between 2018-04-01 and 2018-05-01.



::: code
```twig
{# Fetch variants updated in the last week #}
{% set lastWeek = date('1 week ago')|atom %}

{% set variants = craft.variants()
    .dateUpdated(">= #{lastWeek}")
    .all() %}
```

```php
// Fetch variants updated in the last week
$lastWeek = (new \DateTime('1 week ago'))->format(\DateTime::ATOM);

$variants = \craft\commerce\elements\Variant::find()
    ->dateUpdated(">= {$lastWeek}")
    ->all();
```
:::


### `fixedOrder`

Causes the query results to be returned in the order specified by [id](#id).





::: code
```twig
{# Fetch variants in a specific order #}
{% set variants = craft.variants()
    .id([1, 2, 3, 4, 5])
    .fixedOrder()
    .all() %}
```

```php
// Fetch variants in a specific order
$variants = \craft\commerce\elements\Variant::find()
    ->id([1, 2, 3, 4, 5])
    ->fixedOrder()
    ->all();
```
:::


### `hasProduct`

Narrows the query results to only variants for certain products.

Possible values include:

| Value | Fetches variants…
| - | -
| a [ProductQuery](api:craft\commerce\elements\db\ProductQuery) object | for products that match the query.




### `hasSales`

Narrows the query results to only variants that are on sale.

Possible values include:

| Value | Fetches variants…
| - | -
| `true` | on sale
| `false` | not on sale




### `hasStock`

Narrows the query results to only variants that have stock.

Possible values include:

| Value | Fetches variants…
| - | -
| `true` | with stock.
| `false` | with no stock.




### `id`

Narrows the query results based on the variants’ IDs.



Possible values include:

| Value | Fetches variants…
| - | -
| `1` | with an ID of 1.
| `'not 1'` | not with an ID of 1.
| `[1, 2]` | with an ID of 1 or 2.
| `['not', 1, 2]` | not with an ID of 1 or 2.



::: code
```twig
{# Fetch the variant by its ID #}
{% set variant = craft.variants()
    .id(1)
    .one() %}
```

```php
// Fetch the variant by its ID
$variant = \craft\commerce\elements\Variant::find()
    ->id(1)
    ->one();
```
:::



::: tip
This can be combined with [fixedOrder](#fixedorder) if you want the results to be returned in a specific order.
:::


### `inReverse`

Causes the query results to be returned in reverse order.





::: code
```twig
{# Fetch variants in reverse #}
{% set variants = craft.variants()
    .inReverse()
    .all() %}
```

```php
// Fetch variants in reverse
$variants = \craft\commerce\elements\Variant::find()
    ->inReverse()
    ->all();
```
:::


### `isDefault`

Narrows the query results to only default variants.



::: code
```twig
{# Fetch default variants #}
{% set variants = {twig-function}
    .isDefault()
    .all() %}
```

```php
// Fetch default variants
$variants = \craft\commerce\elements\Variant::find()
    ->isDefault()
    ->all();
```
:::


### `limit`

Determines the number of variants that should be returned.



::: code
```twig
{# Fetch up to 10 variants  #}
{% set variants = craft.variants()
    .limit(10)
    .all() %}
```

```php
// Fetch up to 10 variants
$variants = \craft\commerce\elements\Variant::find()
    ->limit(10)
    ->all();
```
:::


### `offset`

Determines how many variants should be skipped in the results.



::: code
```twig
{# Fetch all variants except for the first 3 #}
{% set variants = craft.variants()
    .offset(3)
    .all() %}
```

```php
// Fetch all variants except for the first 3
$variants = \craft\commerce\elements\Variant::find()
    ->offset(3)
    ->all();
```
:::


### `orderBy`

Determines the order that the variants should be returned in.



::: code
```twig
{# Fetch all variants in order of date created #}
{% set variants = craft.variants()
    .orderBy('dateCreated asc')
    .all() %}
```

```php
// Fetch all variants in order of date created
$variants = \craft\commerce\elements\Variant::find()
    ->orderBy('dateCreated asc')
    ->all();
```
:::


### `price`

Narrows the query results based on the variants’ price.

Possible values include:

| Value | Fetches variants…
| - | -
| `100` | with a price of 100.
| `'>= 100'` | with a price of at least 100.
| `'< 100'` | with a price of less than 100.




### `product`

Narrows the query results based on the variants’ product.

Possible values include:

| Value | Fetches variants…
| - | -
| a [Product](api:craft\commerce\elements\Product) object | for a product represented by the object.




### `productId`

Narrows the query results based on the variants’ products’ IDs.

Possible values include:

| Value | Fetches variants…
| - | -
| `1` | for a product with an ID of 1.
| `[1, 2]` | for product with an ID of 1 or 2.
| `['not', 1, 2]` | for product not with an ID of 1 or 2.




### `relatedTo`

Narrows the query results to only variants that are related to certain other elements.



See [Relations](https://docs.craftcms.com/v3/relations.html) for a full explanation of how to work with this parameter.



::: code
```twig
{# Fetch all variants that are related to myCategory #}
{% set variants = craft.variants()
    .relatedTo(myCategory)
    .all() %}
```

```php
// Fetch all variants that are related to $myCategory
$variants = \craft\commerce\elements\Variant::find()
    ->relatedTo($myCategory)
    ->all();
```
:::


### `search`

Narrows the query results to only variants that match a search query.



See [Searching](https://docs.craftcms.com/v3/searching.html) for a full explanation of how to work with this parameter.



::: code
```twig
{# Get the search query from the 'q' query string param #}
{% set searchQuery = craft.app.request.getQueryParam('q') %}

{# Fetch all variants that match the search query #}
{% set variants = craft.variants()
    .search(searchQuery)
    .all() %}
```

```php
// Get the search query from the 'q' query string param
$searchQuery = \Craft::$app->request->getQueryParam('q');

// Fetch all variants that match the search query
$variants = \craft\commerce\elements\Variant::find()
    ->search($searchQuery)
    ->all();
```
:::


### `site`

Determines which site the variants should be queried in.



The current site will be used by default.

Possible values include:

| Value | Fetches variants…
| - | -
| `'foo'` | from the site with a handle of `foo`.
| a `\craft\commerce\elements\db\Site` object | from the site represented by the object.



::: code
```twig
{# Fetch variants from the Foo site #}
{% set variants = craft.variants()
    .site('foo')
    .all() %}
```

```php
// Fetch variants from the Foo site
$variants = \craft\commerce\elements\Variant::find()
    ->site('foo')
    ->all();
```
:::


### `siteId`

Determines which site the variants should be queried in, per the site’s ID.



The current site will be used by default.



::: code
```twig
{# Fetch variants from the site with an ID of 1 #}
{% set variants = craft.variants()
    .siteId(1)
    .all() %}
```

```php
// Fetch variants from the site with an ID of 1
$variants = \craft\commerce\elements\Variant::find()
    ->siteId(1)
    ->all();
```
:::


### `sku`

Narrows the query results based on the variants’ SKUs.

Possible values include:

| Value | Fetches variants…
| - | -
| `'foo'` | with a SKU of `foo`.
| `'foo*'` | with a SKU that begins with `foo`.
| `'*foo'` | with a SKU that ends with `foo`.
| `'*foo*'` | with a SKU that contains `foo`.
| `'not *foo*'` | with a SKU that doesn’t contain `foo`.
| `['*foo*', '*bar*'` | with a SKU that contains `foo` or `bar`.
| `['not', '*foo*', '*bar*']` | with a SKU that doesn’t contain `foo` or `bar`.



::: code
```twig
{# Get the requested variant SKU from the URL #}
{% set requestedSlug = craft.app.request.getSegment(3) %}

{# Fetch the variant with that slug #}
{% set variant = craft.variants()
    .sku(requestedSlug|literal)
    .one() %}
```

```php
// Get the requested variant SKU from the URL
$requestedSlug = \Craft::$app->request->getSegment(3);

// Fetch the variant with that slug
$variant = \craft\commerce\elements\Variant::find()
    ->sku(\craft\helpers\Db::escapeParam($requestedSlug))
    ->one();
```
:::


### `stock`

Narrows the query results based on the variants’ stock.

Possible values include:

| Value | Fetches variants…
| - | -
| `0` | with no stock.
| `'>= 5'` | with a stock of at least 5.
| `'< 10'` | with a stock of less than 10.




### `title`

Narrows the query results based on the variants’ titles.



Possible values include:

| Value | Fetches variants…
| - | -
| `'Foo'` | with a title of `Foo`.
| `'Foo*'` | with a title that begins with `Foo`.
| `'*Foo'` | with a title that ends with `Foo`.
| `'*Foo*'` | with a title that contains `Foo`.
| `'not *Foo*'` | with a title that doesn’t contain `Foo`.
| `['*Foo*', '*Bar*'` | with a title that contains `Foo` or `Bar`.
| `['not', '*Foo*', '*Bar*']` | with a title that doesn’t contain `Foo` or `Bar`.



::: code
```twig
{# Fetch variants with a title that contains "Foo" #}
{% set variants = craft.variants()
    .title('*Foo*')
    .all() %}
```

```php
// Fetch variants with a title that contains "Foo"
$variants = \craft\commerce\elements\Variant::find()
    ->title('*Foo*')
    ->all();
```
:::


### `trashed`

Narrows the query results to only variants that have been soft-deleted.





::: code
```twig
{# Fetch trashed variants #}
{% set variants = {twig-function}
    .trashed()
    .all() %}
```

```php
// Fetch trashed variants
$variants = \craft\commerce\elements\Variant::find()
    ->trashed()
    ->all();
```
:::


### `typeId`

Narrows the query results based on the variants’ product types, per their IDs.

Possible values include:

| Value | Fetches variants…
| - | -
| `1` | for a product of a type with an ID of 1.
| `[1, 2]` | for product of a type with an ID of 1 or 2.
| `['not', 1, 2]` | for product of a type not with an ID of 1 or 2.




### `uid`

Narrows the query results based on the variants’ UIDs.





::: code
```twig
{# Fetch the variant by its UID #}
{% set variant = craft.variants()
    .uid('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
    .one() %}
```

```php
// Fetch the variant by its UID
$variant = \craft\commerce\elements\Variant::find()
    ->uid('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
    ->one();
```
:::


### `with`

Causes the query to return matching variants eager-loaded with related elements.



See [Eager-Loading Elements](https://docs.craftcms.com/v3/dev/eager-loading-elements.html) for a full explanation of how to work with this parameter.



::: code
```twig
{# Fetch variants eager-loaded with the "Related" field’s relations #}
{% set variants = craft.variants()
    .with(['related'])
    .all() %}
```

```php
// Fetch variants eager-loaded with the "Related" field’s relations
$variants = \craft\commerce\elements\Variant::find()
    ->with(['related'])
    ->all();
```
:::



<!-- END PARAMS -->
