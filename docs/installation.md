# Installation

## Pre-flight check

Before installing Craft Commerce, make sure you’ve got everything you need:

* A web host that meets Commerce’s [minimum server requirements](https://github.com/craftcms/commerce-docs/blob/v2/en/server-requirements.md).
* Craft CMS 3.0 or later (see Craft’s [installation instructions](https://docs.craftcms.com/v3/installation.html) for details).

Craft Commerce can only be installed from the plugin store, or through composer.

## Plugin Store

Log into your control panel and and click on 'Plugin Store'. Search for 'Commerce'.

## Composer

Ensure you have composer [installed correctly](https://docs.craftcms.com/v3/installation.html#downloading-with-composer) in your Craft 3 project.

Run the following composer command from within your Craft 3 project:

```bash
composer require craftcms/commerce 
``` 

> Please note, while Commerce 2 is Beta, to install Commerce you may need to change your composer.json [`mininum-stability`](https://getcomposer.org/doc/04-schema.md#minimum-stability) to be `dev` or `beta` depending on your requirements. 

then

```bash
composer update
``` 
