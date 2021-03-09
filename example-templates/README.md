<p align="center"><img src="../src/icon.svg" width="100" height="100" alt="Craft Commerce icon"></p>

## Craft Commerce Example Templates

These templates are an example of all features available to the front end of your Commerce site.

These example templates can only be copied to your project's `templates/` directory by using the Craft console command:

```bash
php craft commerce/example-templates/generate
````

Do not copy the `example-templates/src/shop` folder directly.

The templates use pure inline [Tailwind 2](https://tailwindcss.com/) classes for all styling, making it easier to extract 
parts of the example templates for your own use.

All example javascript in the templates is written with zero external dependencies. We don't use jQuery or Vue.js but we do 
rely on modern browser APIs like [`fetch`](https://caniuse.com/#feat=fetch).

Other than tailwind classes, we have utility classes and IDs prepended with `js-` to show they are to be used by the example javascript.

## Development of the example templates

A copy of the fully generated example templates should be made and copied to the `exmaple-templates/build` folder if any changes are made.

```bash
php craft commerce/example-templates --overwrite=true --baseColor=blue --folderName=shop
cp -r templates/shop vendor/craftcms/commerce/example-templates/
```


 