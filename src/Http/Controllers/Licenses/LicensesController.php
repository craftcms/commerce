<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Licenses;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Element\Drafts;
use CraftCms\Cms\Element\Queries\AssetQuery;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Http\Controllers\Concerns\RedirectsToShownSource;
use CraftCms\Cms\Http\Requests\ElementIndexRequest;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Http\ViewModels\LicenseIndexViewModel;
use CraftCms\Commerce\License\Elements\License;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function CraftCms\Cms\t;

readonly class LicensesController
{
    use RedirectsToShownSource;
    use RespondsWithFlash;

    public function index(ElementIndexRequest $request): InertiaResponse|RedirectResponse
    {
        $viewModel = new LicenseIndexViewModel($request);

        return $this->shownSourceRedirect($request, $viewModel, false)
            ?? Inertia::render('commerce::licenses/Index', [$viewModel]);
    }

    /**
     * Creates a new license as an unpublished draft and sends the user to its edit screen.
     */
    public function create(Request $request, Drafts $drafts): Response
    {
        $user = $request->craftUser();
        abort_if(!$user, 401);

        $license = new License();
        $license->ruleset->useScenario(ElementRules::SCENARIO_ESSENTIALS);

        if (!$drafts->saveElementAsDraft($license, $user->getCraftUserId(), markAsSaved: false)) {
            return $this->asModelFailure($license, mb_ucfirst(t('Couldn’t create {type}.', [
                'type' => License::lowerDisplayName(),
            ])), 'license');
        }

        $editUrl = $license->getCpEditUrl();

        if (!$request->expectsJson()) {
            return redirect(Url::urlWithParams($editUrl, ['fresh' => 1]));
        }

        return $this->asModelSuccess($license, t('{type} created.', [
            'type' => License::displayName(),
        ]), 'license', ['cpEditUrl' => $editUrl]);
    }

    /**
     * Streams a licensed asset to whoever holds the license key, without exposing the file’s URL.
     *
     * The route is anonymous on purpose: the license key is the credential. If the product type has a License
     * Download Field, the asset must be in that field on the licensed variant (or its product, for a product
     * field), and `assetId` defaults to the field’s first asset. Otherwise `assetId` is required, and any asset
     * related to the variant or its product can be downloaded.
     */
    public function download(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'licenseKey' => ['required', 'string'],
            'assetId' => ['nullable', 'integer'],
        ]);

        /** @var License|null $license */
        $license = License::find()->licenseKey($validated['licenseKey'])->one();
        $variant = $license?->getVariant();
        $product = $variant?->getOwner();
        abort_if($variant === null || $product === null, 404);

        $productType = $product->getType();
        $assetId = isset($validated['assetId']) ? (int)$validated['assetId'] : null;

        if ($productType->licenseDownloadFieldUid) {
            // A download field that's no longer in either field layout must not widen access to every related asset
            $downloadField = $productType->getLicenseDownloadField();
            abort_if($downloadField === null, 404);

            $element = $productType->getVariantFieldLayout()->getFieldByUid($downloadField->uid) ? $variant : $product;

            /** @var AssetQuery $assetQuery */
            $assetQuery = clone $element->getFieldValue($downloadField->handle);

            if ($assetId) {
                $assetQuery->id($assetId);
            }
        } else {
            abort_if($assetId === null, 400, 'Missing assetId.');
            $assetQuery = Asset::find()->id($assetId)->relatedTo(['or', $variant, $product]);
        }

        /** @var Asset|null $asset */
        $asset = $assetQuery->one();
        abort_if($asset === null, 404);

        return response()->streamDownload(function() use ($asset) {
            $stream = $asset->getStream();
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $asset->getFilename(), array_filter([
            'Content-Type' => $asset->getMimeType(),
            'Content-Length' => $asset->size,
        ]));
    }
}
