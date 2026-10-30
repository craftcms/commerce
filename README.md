<p align="center"><img src="./src/icon.svg" width="100" height="100" alt="Craft Commerce icon"></p>

<h1 align="center">Craft Commerce</h1>

Craft Commerce is an amazingly powerful and flexible ecommerce platform for [Craft CMS](https://craftcms.com).

You can learn all about it at [craftcms.com/commerce](https://craftcms.com/commerce), and documentation is available at [craftcms.com](https://craftcms.com/docs/commerce/5.x/).

## Requirements

This plugin requires Craft CMS 5.6 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Commerce”. Then click on the “Install” button in its modal window.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require craftcms/commerce

# tell Craft to install the plugin
php craft plugin/install commerce

# optional: copy the Craft Commerce example templates to your project’s templates folder
php craft commerce/example-templates
```

## Development workbench

The [Orchestra Workbench](https://packages.tools/workbench) runs Commerce inside a local Craft app. It keeps its SQLite database, environment, project config, and runtime files under `workbench/`.

```bash
composer install
pnpm install
pnpm build
composer workbench:setup
composer serve
```

The current CMS feature branch needs frontend assets built from the same CMS checkout. Build that checkout's frontend, set `WORKBENCH_CMS_ASSETS_PATH` in `workbench/.env` to its absolute `cms-assets/resources` path, and rerun `composer workbench:setup`. This overrides the published CMS assets during setup.

Open <http://localhost:8125/> to sign in automatically as the seeded admin and open the control panel. To test manual login, visit `/admin/login` and use `admin` / `craftcms2018!!`.

`workbench:setup` creates `workbench/.env` with a random app key, installs Craft and Commerce, and publishes their assets. You can run it again without resetting the database. Edit `workbench/.env` before the first setup to change the site URL or admin credentials.

Run `pnpm dev` in a second terminal for Commerce component updates. After rebuilding with `pnpm build`, run `composer workbench:setup` again to publish the updated assets.

To reset the local site, stop the server, delete `workbench/database/database.sqlite*` and `workbench/config/craft/project`, then run `composer workbench:setup`.

## Resources

We highly recommend you check out these resources as you’re getting started with Craft Commerce:

- **[Craft Commerce Docs](https://craftcms.com/docs/commerce/5.x/)** – the official documentation.
- **[Craft Discord](https://craftcms.com/discord)** – one of the most friendly and helpful Discords on the planet.
- **[Craft Stack Exchange](http://craftcms.stackexchange.com/)** – community-run Q&A for Craft developers.

---

<p>
<img src="https://github.com/craftcms/cms/workflows/ci/badge.svg?branch=main" alt="Build Status">
<a href="https://packagist.org/packages/craftcms/commerce"><img src="https://img.shields.io/packagist/dt/craftcms/commerce.svg?label=downloads" alt="Total Packagist Downloads"></a>
<a href="https://github.com/craftcms/commerce/releases"><img src="https://img.shields.io/github/tag/craftcms/commerce.svg?label=stable" alt="Latest Stable Version"></a>
</p>
