<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Element\ElementHelper;
use CraftCms\Cms\Element\Elements;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Http\Controllers\Elements\Concerns\SavesElement;
use CraftCms\Cms\Http\Requests\ElementRequest;
use CraftCms\Commerce\Http\ViewModels\TransferEditViewModel;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class EditTransferController
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
            strictSite: false,
        );

        if ($element instanceof Response) {
            return $element;
        }

        if (!$element instanceof Transfer) {
            abort(400, 'No transfer was identified by the request.');
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

        if ($this->request->boolean('prevalidate')) {
            $element->ruleset->useScenario(ElementRules::SCENARIO_LIVE);
            $element->validate();
        }

        return Inertia::render('commerce::inventory/transfers/Edit', new TransferEditViewModel(
            element: $element,
            request: $this->request,
            canSave: $this->canSave($element, $this->request->craftUser()),
            mergedCanonicalChanges: $mergedCanonicalChanges,
        ));
    }
}
