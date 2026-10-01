<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Auth\SessionAuth;
use CraftCms\Cms\Element\ElementHelper;
use CraftCms\Cms\Element\Elements;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Http\Controllers\Elements\Concerns\SavesElement;
use CraftCms\Cms\Http\Requests\ElementRequest;
use CraftCms\Commerce\Http\ViewModels\ProductEditViewModel;
use CraftCms\Commerce\Product\Elements\Product;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the Inertia product edit screen for the canonical product, its drafts, and its revisions.
 */
class EditProductController
{
    use SavesElement;

    public function __construct(
        protected readonly ElementRequest $request,
        private readonly Elements $elements,
    ) {
    }

    public function __invoke(): Response|InertiaResponse
    {
        $element = $this->request->element(
            ['id' => $this->request->route('id') ?? $this->request->integer('elementId')],
            checkForProvisionalDraft: true,
            strictSite: false,
        );

        if ($element instanceof Response) {
            return $element;
        }

        if (!$element instanceof Product) {
            abort(400, 'No product was identified by the request.');
        }

        $mergedCanonicalChanges = (
            $element::trackChanges() &&
            $element->getIsDraft() &&
            !$element->getIsUnpublishedDraft() &&
            ElementHelper::isOutdated($element)
        );

        if ($mergedCanonicalChanges) {
            $this->elements->mergeCanonicalChanges($element);
        }

        $this->applyParamsToElement($element);

        if ($this->request->boolean('prevalidate') && $element->enabled && $element->getEnabledForSite()) {
            $element->ruleset->useScenario(ElementRules::SCENARIO_LIVE);
            $element->validate();
        }

        if ($element->id && $element->getPreviewTargets() !== []) {
            match (true) {
                $element->getIsDraft() && !$element->isProvisionalDraft => SessionAuth::authorize("previewDraft:$element->draftId"),
                $element->getIsRevision() => SessionAuth::authorize("previewRevision:$element->revisionId"),
                default => SessionAuth::authorize('previewElement:' . $element->getCanonicalId()),
            };
        }

        return Inertia::render('commerce::products/Edit', new ProductEditViewModel(
            element: $element,
            request: $this->request,
            canSave: $this->canSave($element, $this->request->craftUser()),
            mergedCanonicalChanges: $mergedCanonicalChanges,
        ));
    }
}
