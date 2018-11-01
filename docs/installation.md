# Installation

## Pre-flight check

Before installing Craft Commerce, make sure you’ve got everything you need:

* A web host that meets Commerce’s [minimum server requirements](requirements.md).
* Craft CMS 3.0 or later (see Craft’s [installation instructions](https://docs.craftcms.com/v3/installation.html) for details).

Craft Commerce can only be installed from the plugin store, or through Composer.

## Plugin Store

Log into your Control Panel and and click on “Plugin Store”. Search for “Commerce”.

## Composer

Ensure you have Composer [installed correctly](https://docs.craftcms.com/v3/installation.html#downloading-with-composer) in your Craft 3 project.

Run the following Composer command from within your Craft 3 project:

```bash
composer require craftcms/commerce
```

## Example Templates

If you’d like to use Commerce’s sample store templates as a starting point, you can copy them from your `vendor/craftcms/commerce/templates/shop/` folder to your `templates/shop/` folder.
