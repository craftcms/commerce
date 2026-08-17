<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\Plugin;
use CraftCms\Cms\Element\Enums\PropagationMethod;
use craft\web\assets\editsection\EditSectionAsset;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Catalog\Elements\Variant;
use CraftCms\Commerce\Catalog\Models\ProductTypeSite;
use CraftCms\Commerce\Catalog\ProductType\Data\ProductType;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

readonly class ProductTypesController
{
    use RespondsWithFlash;

    public function productTypeIndex(): CpScreenResponse
    {
        $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();

        return new CpScreenResponse()
            ->contentTemplate('commerce/settings/producttypes/index', [
                'productTypes' => $productTypes,
            ]);
    }

    public function editProductType(?int $productTypeId = null): CpScreenResponse
    {
        $brandNewProductType = false;

        if ($productTypeId) {
            $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($productTypeId);
            abort_if(!$productType, 404);
        } else {
            $productType = new ProductType();
            $brandNewProductType = true;
        }

        $title = $productTypeId ? $productType->name : t('Create a new product type', category: 'commerce');

        \Craft::$app->getView()->registerAssetBundle(EditSectionAsset::class);

        return new CpScreenResponse()
            ->title($title)
            ->crumbs([
                ['label' => t('Commerce', category: 'commerce'), 'url' => 'commerce'],
                ['label' => t('Settings'), 'url' => 'commerce/settings', 'ariaLabel' => t('Commerce Settings', category: 'commerce')],
                ['label' => t('Product Types', category: 'commerce'), 'url' => 'commerce/settings/producttypes'],
            ])
            ->tabs([
                'productTypeSettings' => [
                    'label' => t('Settings'),
                    'url' => '#product-type-settings',
                ],
                'taxAndShipping' => [
                    'label' => t('Tax & Shipping', category: 'commerce'),
                    'url' => '#tax-and-shipping',
                ],
                'productFields' => [
                    'label' => t('Product Fields', category: 'commerce'),
                    'url' => '#product-fields',
                ],
                'variantFields' => [
                    'label' => t('Variant Fields', category: 'commerce'),
                    'url' => '#variant-fields',
                ],
            ])
            ->selectedSubnavItem('settings')
            ->action('commerce/product-types/save-product-type')
            ->submitButtonLabel(t('Save'))
            ->redirectUrl('commerce/settings/producttypes')
            ->contentTemplate('commerce/settings/producttypes/_edit', [
                'productTypeId' => $productTypeId,
                'productType' => $productType,
                'brandNewProductType' => $brandNewProductType,
                'title' => $title,
                'selectedTab' => 'productTypeSettings',
            ]);
    }

    public function saveProductType(Request $request): ?Response
    {
        abort_unless(currentUser()?->can('manageCommerce'), 403, t('This action is not allowed for the current user.', category: 'commerce'));

        $productTypeId = $request->input('productTypeId');

        if ($productTypeId) {
            $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById((int)$productTypeId);
            abort_unless($productType, 400, "Invalid section ID: $productTypeId");
        } else {
            $productType = new ProductType();
        }

        // Shared attributes
        $productType->id = $productTypeId;
        $productType->name = $request->input('name');
        $productType->handle = $request->input('handle');
        $productType->enableVersioning = $request->input('enableVersioning') ?? $productType->enableVersioning;
        $productType->hasDimensions = (bool)$request->input('hasDimensions');
        $productType->hasProductTitleField = (bool)$request->input('hasProductTitleField');
        $productType->productTitleFormat = $request->input('productTitleFormat');
        $productType->productUiLabelFormat = $request->input('productUiLabelFormat');
        $productType->productTitleTranslationMethod = $request->input('productTitleTranslationMethod', $productType->productTitleTranslationMethod);
        $productType->productTitleTranslationKeyFormat = $request->input('productTitleTranslationKeyFormat', $productType->productTitleTranslationKeyFormat);
        $productType->showSlugField = (bool)$request->input('showSlugField', $productType->showSlugField);
        $productType->slugTranslationMethod = $request->input('slugTranslationMethod', $productType->slugTranslationMethod);
        $productType->slugTranslationKeyFormat = $request->input('slugTranslationKeyFormat', $productType->slugTranslationKeyFormat);
        $productType->maxVariants = $request->input('maxVariants') ?: null;
        $productType->hasVariantTitleField = $request->input('hasVariantTitleField', false);
        $productType->variantTitleFormat = $request->input('variantTitleFormat');
        $productType->variantUiLabelFormat = $request->input('variantUiLabelFormat');
        $productType->variantTitleTranslationMethod = $request->input('variantTitleTranslationMethod', $productType->variantTitleTranslationMethod);
        $productType->variantTitleTranslationKeyFormat = $request->input('variantTitleTranslationKeyFormat', $productType->variantTitleTranslationKeyFormat);
        $productType->skuFormat = $request->input('skuFormat');
        $productType->descriptionFormat = $request->input('descriptionFormat');
        $productType->propagationMethod = PropagationMethod::tryFrom($request->input('propagationMethod') ?? '') ?? PropagationMethod::All;
        $productType->isStructure = $request->input('isStructure');
        $maxLevels = (int)$request->input('maxLevels');
        $productType->maxLevels = $maxLevels ?: null; // zero should be null
        $productType->defaultPlacement = $request->input('defaultPlacement');
        $productType->previewTargets = $request->input('previewTargets') ?: [];

        // Site-specific settings
        $allSiteSettings = [];

        foreach (Sites::getAllSites() as $site) {
            $postedSettings = $request->input('sites.' . $site->handle);

            // Skip disabled sites if this is a multi-site install
            if (Sites::isMultiSite() && empty($postedSettings['enabled'])) {
                continue;
            }

            $siteSettings = new ProductTypeSite();
            $siteSettings->siteId = $site->id;
            $siteSettings->hasUrls = !empty($postedSettings['uriFormat']);

            $siteSettings->enabledByDefault = (bool)$postedSettings['enabledByDefault'];

            if ($siteSettings->hasUrls) {
                $siteSettings->uriFormat = $postedSettings['uriFormat'];
                $siteSettings->template = $postedSettings['template'];
            } else {
                $siteSettings->uriFormat = null;
                $siteSettings->template = null;
            }

            $allSiteSettings[$site->id] = $siteSettings;
        }

        $productType->setSiteSettings($allSiteSettings);

        // Set the product type field layout
        $fieldLayout = Fields::assembleLayoutFromPost();
        $fieldLayout->type = Product::class;
        $productType->setProductFieldLayout($fieldLayout);

        // Set the variant field layout
        $variantFieldLayout = Fields::assembleLayoutFromPost('variant-layout');
        $variantFieldLayout->type = Variant::class;
        $productType->setVariantFieldLayout($variantFieldLayout);

        // Save it
        if (Plugin::getInstance()->getProductTypes()->saveProductType($productType)) {
            return $this->asSuccess(t('Product type saved.', category: 'commerce'));
        }

        return $this->asModelFailure($productType, t('Couldn\'t save product type.', category: 'commerce'), 'productType');
    }

    public function deleteProductType(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $productTypeId = $request->input('id');
        abort_if(!$productTypeId, 400, 'Missing product type id');

        Plugin::getInstance()->getProductTypes()->deleteProductTypeById((int)$productTypeId);

        return $this->asSuccess();
    }
}
