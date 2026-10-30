<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Cp\RequestedSite;
use CraftCms\Cms\Element\Drafts;
use CraftCms\Cms\Element\ElementHelper;
use CraftCms\Cms\Element\Enums\PropagationMethod;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Site\Sites;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\Facades\Structures;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Users;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Products;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

/**
 * Creates a new product as an unpublished draft and sends the user to its edit screen.
 */
readonly class CreateProductController
{
    use RespondsWithFlash;

    public function __construct(
        private Request $request,
        private ProductTypes $productTypes,
        private Products $products,
        private Sites $sites,
    ) {
    }

    public function __invoke(Drafts $drafts, Users $users): Response
    {
        $productType = $this->getProductType();
        $site = $this->getSite($productType);

        if ($site instanceof Response) {
            return $site;
        }

        $user = $this->request->craftUser();
        abort_if(!$user, 401);

        $product = new Product();
        $product->siteId = $site->id;
        $product->typeId = $productType->id;

        $this->setStatus($product, $productType);

        if ($productType->isStructure && (int)$productType->maxLevels !== 1) {
            $product->setParentId($this->request->input('parentId'));
        }

        Gate::forUser($users->getUserById($user->getCraftUserId()))->authorize('save', $product);

        $this->setTitleAndSlug($product, $site);

        // Pause time so the post date matches the date created when it isn't given
        DateTimeHelper::pause();

        try {
            $this->setDates($product);

            foreach ($product->getFieldLayout()->getCustomFields() as $field) {
                if (($value = $this->request->input($field->handle)) !== null) {
                    $product->setFieldValue($field->handle, $value);
                }
            }

            $product->ruleset->useScenario(ElementRules::SCENARIO_ESSENTIALS);
            $success = $drafts->saveElementAsDraft($product, $user->getCraftUserId(), markAsSaved: false);
        } finally {
            DateTimeHelper::resume();
        }

        if (!$success) {
            return $this->asModelFailure($product, mb_ucfirst(t('Couldn’t create {type}.', [
                'type' => Product::lowerDisplayName(),
            ])), 'product');
        }

        $this->setPositionInStructure($product, $productType, $site);

        $editUrl = $product->getCpEditUrl();

        $response = $this->asModelSuccess($product, t('{type} created.', [
            'type' => Product::displayName(),
        ]), 'product', array_filter([
            'cpEditUrl' => $this->request->isCpRequest() ? $editUrl : null,
        ]));

        if (!$this->request->wantsJson()) {
            $response->headers->set('Location', Url::urlWithParams($editUrl, ['fresh' => 1]));
        }

        return $response;
    }

    private function getProductType(): ProductType
    {
        $handle = $this->request->route('productType');

        if (!$handle) {
            $handle = $this->request->validate([
                'productType' => ['required', 'string'],
            ])['productType'];
        }

        $productType = $this->productTypes->getProductTypeByHandle($handle);

        abort_if(is_null($productType), 400, "Invalid product type handle: $handle");

        return $productType;
    }

    private function getSite(ProductType $productType): Site|Response
    {
        $siteId = $this->request->integer('siteId');

        if ($siteId) {
            $site = $this->sites->getSiteById($siteId);
            abort_if(is_null($site), 400, "Invalid site ID: $siteId");
        } else {
            $site = app(RequestedSite::class)->get();
            abort_if(is_null($site), 403, 'User not authorized to edit content in any sites.');
        }

        $editableSiteIds = $this->editableSiteIds($productType);

        abort_if($editableSiteIds->isEmpty(), 403, 'User not permitted to edit content in any sites supported by this product type');

        if ($editableSiteIds->doesntContain($site->id)) {
            if ($editableSiteIds->count() > 1 && $productType->propagationMethod !== PropagationMethod::All) {
                return response(template('_special/sitepicker', [
                    'siteIds' => $editableSiteIds->all(),
                    'baseUrl' => "commerce/products/$productType->handle/new",
                ]));
            }

            return $this->sites->getSiteById($editableSiteIds->first());
        }

        return $site;
    }

    /**
     * @return Collection<int, int>
     */
    private function editableSiteIds(ProductType $productType): Collection
    {
        if (!$this->sites->isMultiSite()) {
            return collect([$this->sites->getPrimarySite()->id]);
        }

        return collect($productType->getSiteIds())
            ->intersect($this->sites->getEditableSiteIds())
            ->values();
    }

    private function setStatus(Product $product, ProductType $productType): void
    {
        if (($status = $this->request->input('status')) !== null) {
            $enabled = $status === 'enabled';
        } else {
            $enabled = ($productType->getSiteSettings()[$product->siteId] ?? null)->enabledByDefault ?? true;
        }

        if ($this->sites->isMultiSite() && count($product->getSupportedSites()) > 1) {
            $product->enabled = true;
            $product->setEnabledForSite($enabled);
        } else {
            $product->enabled = $enabled;
            $product->setEnabledForSite(true);
        }
    }

    private function setTitleAndSlug(Product $product, Site $site): void
    {
        $product->title = $this->request->input('title');
        $product->slug = $this->request->input('slug');

        if ($product->title && !$product->slug) {
            $product->slug = ElementHelper::generateSlug($product->title, null, $site->getLanguage());
        }

        if (!$product->slug) {
            $product->slug = ElementHelper::tempSlug();
        }
    }

    private function setDates(Product $product): void
    {
        $product->postDate = $this->dateInput('postDate') ?? now();
        $product->expiryDate = $this->dateInput('expiryDate');
    }

    private function dateInput(string $name): ?DateTime
    {
        $value = $this->request->input($name);
        $date = $value !== null ? DateTimeHelper::toDateTime($value) : false;

        if (!$date) {
            return null;
        }

        return $date instanceof DateTime ? $date : DateTime::createFromInterface($date);
    }

    private function setPositionInStructure(Product $product, ProductType $productType, Site $site): void
    {
        if (!$productType->isStructure) {
            return;
        }

        if ($nextId = $this->request->input('before')) {
            Structures::moveBefore($productType->structureId, $product, $this->products->getProductById((int)$nextId, $site->id, [
                'structureId' => $productType->structureId,
            ]));

            return;
        }

        if ($prevId = $this->request->input('after')) {
            Structures::moveAfter($productType->structureId, $product, $this->products->getProductById((int)$prevId, $site->id, [
                'structureId' => $productType->structureId,
            ]));
        }
    }
}
