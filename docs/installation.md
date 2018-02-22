# Installation

## Pre-flight check

Before installing Craft Commerce, make sure you’ve got everything you need:

* A web host that meets Commerce’s [minimum requirements]({entry:docs/requirements:url}) (hint: you’ll need PHP 5.4 or later).
* Craft CMS 2.6 or later (see Craft’s [installation instructions](https://craftcms.com/docs/installing) for details).
* The latest version of Commerce, downloaded from [craftcommerce.com](https://craftcommerce.com).

## Upload the files

Extract the Commerce zip somewhere on your computer. You’ll notice that it contains two folders:

- commerce
- templates

Upload the ‘commerce’ folder to your ‘craft/plugins’ folder within your Craft CMS install.

The ‘templates’ folder contains example templates that demonstrate how to create a basic e-commerce site with a cart and checkout process. If you’d like to use them, just upload the ‘templates/shop’ folder to your ‘craft/templates’ folder.

## Install the plugin

With your files uploaded, log into your Craft site’s Control Panel (located at `http://example.com/admin`), and navigate to Settings → Plugins. Click the “Install” button next to “Commerce” and wait a few seconds. When the page reloads, Commerce will be fully installed!

You will find some example products located in Commerce → Products, and if you’ve uploaded the example templates, you’ll be able to view them right away on the front end.