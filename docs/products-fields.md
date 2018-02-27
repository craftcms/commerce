# Products Fields

Products fields allow you to relate products to a parent element.

# Settings

<img src="assets/products-field-settings.png" width="422" alt="Product Field Settings.">

Products fields have the following settings:

* *Sources* – The product types you want to relate entries from. (Default is “All”.)
* *Limit* – The maximum number of products that can be related with the field at once. (Default is no limit.)
* *Selection Label* – The label that should be used on the field’s selection button. (Default is "Add a product")

# The Field

Products fields list all of the currently selected products, with a button to select new ones:

<img src="assets/product-field-example.png" width="297" alt="Product Field Example.">

Clicking the “Add an entry” button will bring up a modal window where you can find and select additional entries:

<img src="assets/product-field-modal.png" width="600" alt="Product Field Modal.">

# Templating

If you have an element with a Products field in your template, you can access its selected products using your Product field’s handle:

```twig
{% set products = entry.productsFieldHandle %}
```

That will give you an ElementCriteriaModel object, prepped to output all of the selected roductsp for the given field. In other words, the line above is really just a shortcut for this:

```twig
{% set products = craft.products({
    relatedTo: { sourceElement: entry, field: "productsFieldHandle" },
    order:     "sortOrder",
    limit:     null
}) %}
```

(See [Relations](https://craftcms.com/docs/relations) for more info on the relatedTo param.)

# Examples

To check if your Products field has any selected products, you can use the length filter:

```twig
{% if entry.productsFieldHandle | length %}
    ...
{% endif %}
```

To loop through the selected products, you can treat the field like an array:

```twig
{% for product in entry.productsFieldHandle %}
    ...
{% endfor %}
```

Rather than typing “entry.productsFieldHandle” every time, you can call it once and set it to another variable:

```twig
{% set products = entry.productsFieldHandle %}

{% if products | length %}

    <h3>Some great products</h3>
    {% for product in products %}
        ...
    {% endfor %}

{% endif %}
```

You can add parameters to the ElementCriteriaModel object as well:

```twig
{% set clothingProducts = entry.productsFieldHandle.type('clothing') %}
```

If your Products field is only meant to have a single product selected, remember that calling your Products field will still give you the same ElementCriteriaModel, not the selected product. To get the first (and only) product selected, use `first()`:

```twig
{% set product = entry.productsFieldHandle.one() %}

{% if product %}
    ...
{% endif %}
```
