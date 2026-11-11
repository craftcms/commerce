<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\DigitalProducts;

use CraftCms\Cms\Cp\RequestedSite;
use CraftCms\Cms\Cp\SiteSwitcher;
use CraftCms\Cms\Element\Drafts;
use CraftCms\Cms\Element\ElementHelper;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Http\Controllers\Concerns\RedirectsToShownSource;
use CraftCms\Cms\Http\Requests\ElementIndexRequest;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Site\Sites;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Users;
use CraftCms\Commerce\DigitalProduct\DigitalProductTypes;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;
use CraftCms\Commerce\Http\ViewModels\DigitalProductIndexViewModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

readonly class DigitalProductsController
{
    use RedirectsToShownSource;
    use RespondsWithFlash;

    public function __construct(
        private DigitalProductTypes $types,
    ) {
    }

    public function index(ElementIndexRequest $request, ?string $typeHandle = null): InertiaResponse|RedirectResponse
    {
        abort_if(empty($this->types->getEditableDigitalProductTypeIds()), 403, 'User is not permitted to manage any digital product types.');

        $viewModel = new DigitalProductIndexViewModel($request, $typeHandle);

        if ($viewModel->showSiteMenu()) {
            app(SiteSwitcher::class)->scopeToSite();
        }

        return $this->shownSourceRedirect($request, $viewModel, $typeHandle !== null && $typeHandle !== '')
            ?? Inertia::render('commerce::digital-products/Index', [$viewModel]);
    }

    /**
     * Creates a new digital product as an unpublished draft and sends the user to its edit screen.
     */
    public function create(Request $request, Drafts $drafts, Users $users, Sites $sites, string $type): Response
    {
        $productType = $this->types->getDigitalProductTypeByHandle($type);
        abort_if($productType === null, 400, "Invalid digital product type handle: $type");

        $user = $request->craftUser();
        abort_if(!$user, 401);

        $siteId = $request->integer('siteId') ?: app(RequestedSite::class)->get()?->id;
        $siteIds = $productType->getSiteIds();

        if (!$siteId || !in_array($siteId, $siteIds, true)) {
            $siteId = $sites->isMultiSite()
                ? collect($siteIds)->intersect($sites->getEditableSiteIds())->first()
                : $sites->getPrimarySite()->id;
        }

        abort_if(!$siteId, 403, 'User not permitted to edit content in any sites supported by this digital product type.');

        $product = new DigitalProduct();
        $product->siteId = $siteId;
        $product->typeId = $productType->id;
        $product->title = $request->input('title');
        $product->slug = $product->title
            ? ElementHelper::generateSlug($product->title, null, $sites->getSiteById($siteId)?->getLanguage())
            : ElementHelper::tempSlug();

        Gate::forUser($users->getUserById($user->getCraftUserId()))->authorize('save', $product);

        $product->ruleset->useScenario(ElementRules::SCENARIO_ESSENTIALS);

        if (!$drafts->saveElementAsDraft($product, $user->getCraftUserId(), markAsSaved: false)) {
            return $this->asModelFailure($product, mb_ucfirst(t('Couldn’t create {type}.', [
                'type' => DigitalProduct::lowerDisplayName(),
            ])), 'product');
        }

        $editUrl = $product->getCpEditUrl();

        $response = $this->asModelSuccess($product, t('{type} created.', [
            'type' => DigitalProduct::displayName(),
        ]), 'product', array_filter([
            'cpEditUrl' => $request->isCpRequest() ? $editUrl : null,
        ]));

        if (!$request->wantsJson()) {
            $response->headers->set('Location', Url::urlWithParams($editUrl, ['fresh' => 1]));
        }

        return $response;
    }
}
