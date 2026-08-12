<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use craft\commerce\Plugin;
use craft\commerce\web\assets\productindex\ProductIndexAsset;
use CraftCms\Cms\Element\ElementHelper;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Catalog\Elements\Product;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;

readonly class ProductsController
{
    use RespondsWithFlash;

    public function __construct()
    {
        abort_if(empty(Plugin::getInstance()->getProductTypes()->getViewableProductTypeIds(true)), 403, 'User is not permitted to view any product types.');
    }

    public function productIndex(?string $productTypeHandle = null): string
    {
        \Craft::$app->getView()->registerAssetBundle(ProductIndexAsset::class);

        return pageTemplate('commerce/products/_index', [
            'productTypeHandle' => $productTypeHandle,
        ], TemplateMode::Cp);
    }

    public function create(Request $request, ?string $productType = null): Response
    {
        $productTypeHandle = $productType ?? $request->input('productType');
        abort_if(!$productTypeHandle, 400, 'Missing productType');

        $productType = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle($productTypeHandle);
        abort_unless($productType, 400, "Invalid product type handle: $productTypeHandle");

        $sitesService = \Craft::$app->getSites();
        $siteId = $request->input('siteId');

        if ($siteId) {
            $site = $sitesService->getSiteById($siteId);
            abort_unless($site, 400, "Invalid site ID: $siteId");
        } else {
            $site = \craft\helpers\Cp::requestedSite();
            abort_unless($site, 403, 'User not authorized to edit content in any sites.');
        }

        $editableSiteIds = $sitesService->getEditableSiteIds();
        if (!in_array($site->id, $editableSiteIds)) {
            // Go with the first one
            $site = $sitesService->getSiteById($editableSiteIds[0]);
        }

        $user = currentUserElement();
        abort_unless($user, 401);

        // Create & populate the draft
        $product = \Craft::createObject(Product::class);
        $product->siteId = $site->id;
        $product->typeId = $productType->id;
        $product->enabled = true;

        // Structure parent
        if (
            $productType->isStructure &&
            (int)$productType->maxLevels !== 1
        ) {
            // Set the initially selected parent
            $product->setParentId($request->input('parentId'));
        }

        // Make sure the user is allowed to create this entry
        abort_unless($product->canSave($user), 403, 'User not authorized to create this product.');

        // Title & slug
        $product->title = $request->input('title');
        $product->slug = $request->input('slug');
        if ($product->title && !$product->slug) {
            $product->slug = ElementHelper::generateSlug($product->title, null, $site->language);
        }
        if (!$product->slug) {
            $product->slug = ElementHelper::tempSlug();
        }

        // Pause time so postDate will definitely be equal to dateCreated, if not explicitly defined
        DateTimeHelper::pause();

        // Post & expiry dates
        if (($postDate = $request->input('postDate')) !== null) {
            $product->postDate = DateTimeHelper::toDateTime($postDate);
        } else {
            $product->postDate = now();
        }

        if (($expiryDate = $request->input('expiryDate')) !== null) {
            $product->expiryDate = DateTimeHelper::toDateTime($expiryDate);
        }

        // Custom fields
        foreach ($product->getFieldLayout()->getCustomFields() as $field) {
            if (($value = $request->input($field->handle)) !== null) {
                $product->setFieldValue($field->handle, $value);
            }
        }

        // Save it
        $product->ruleset->useScenario(ElementRules::SCENARIO_ESSENTIALS);
        $success = \Craft::$app->getDrafts()->saveElementAsDraft($product, $user->id, markAsSaved: false);

        // Resume time
        DateTimeHelper::resume();

        if (!$success) {
            return $this->asModelFailure($product, t('Couldn\'t create {type}.', [
                'type' => Product::lowerDisplayName(),
            ]), 'product');
        }

        // Set its position in the structure if a before/after param was passed
        if ($productType->isStructure) {
            if ($nextId = $request->input('before')) {
                $nextEntry = Plugin::getInstance()->getProducts()->getProductById((int)$nextId, $site->id, [
                    'structureId' => $productType->structureId,
                ]);
                \Craft::$app->getStructures()->moveBefore($productType->structureId, $product, $nextEntry);
            } elseif ($prevId = $request->input('after')) {
                $prevEntry = Plugin::getInstance()->getProducts()->getProductById((int)$prevId, $site->id, [
                    'structureId' => $productType->structureId,
                ]);
                \Craft::$app->getStructures()->moveAfter($productType->structureId, $product, $prevEntry);
            }
        }

        $editUrl = $product->getCpEditUrl();

        $response = $this->asModelSuccess($product, t('{type} created.', [
            'type' => Product::displayName(),
        ]), 'product', array_filter([
            'cpEditUrl' => $request->isCpRequest() ? $editUrl : null,
        ]));

        if (!$request->expectsJson()) {
            return redirect(\craft\helpers\UrlHelper::urlWithParams($editUrl, [
                'fresh' => 1,
            ]));
        }

        return $response;
    }
}
