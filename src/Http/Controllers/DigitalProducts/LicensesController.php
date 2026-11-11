<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\DigitalProducts;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Element\Drafts;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Http\Controllers\Concerns\RedirectsToShownSource;
use CraftCms\Cms\Http\Requests\ElementIndexRequest;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\DigitalProduct\Elements\License;
use CraftCms\Commerce\Http\ViewModels\LicenseIndexViewModel;
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
     * Streams an asset related to a license’s digital product, without exposing the file’s URL.
     *
     * Anyone with the license key can download the product’s related assets.
     */
    public function download(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'licenseKey' => ['required', 'string'],
            'assetId' => ['required', 'integer'],
        ]);

        /** @var License|null $license */
        $license = License::find()->licenseKey($data['licenseKey'])->one();
        $product = $license?->getProduct();
        abort_if($product === null, 404);

        /** @var Asset|null $asset */
        $asset = Asset::find()
            ->id((int)$data['assetId'])
            ->relatedTo($product)
            ->one();
        abort_if($asset === null, 404);

        $stream = $asset->getStream();

        return response()->streamDownload(function() use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, $asset->getFilename(), array_filter([
            'Content-Type' => $asset->getMimeType(),
        ]));
    }
}
