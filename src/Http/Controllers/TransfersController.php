<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Http\Controllers\Concerns\RedirectsToShownSource;
use CraftCms\Cms\Http\Requests\ElementIndexRequest;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Support\Facades\Drafts;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Ui\UiResolver;
use CraftCms\Commerce\Form\Controls\TransferReceive;
use CraftCms\Commerce\Http\ViewModels\TransferIndexViewModel;
use CraftCms\Commerce\Transfer\Data\TransferDetail;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use CraftCms\Commerce\Transfer\Transfers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

readonly class TransfersController
{
    use RedirectsToShownSource;
    use RespondsWithFlash;

    public function create(): Response
    {
        $user = currentUserElement();
        abort_unless($user !== null, 401);

        $transfer = new Transfer();

        abort_unless($transfer->canSave($user), 403, 'User not authorized to save this transfer.');

        $transfer->ruleset->useScenario(ElementRules::SCENARIO_ESSENTIALS);
        $success = Drafts::saveElementAsDraft($transfer, $user->id, null, null, false);

        if (!$success) {
            return $this->asModelFailure($transfer, t('Couldn’t create {type}.', [
                'type' => Transfer::lowerDisplayName(),
            ]), 'transfer');
        }

        $editUrl = $transfer->getCpEditUrl();

        $response = $this->asModelSuccess($transfer, t('{type} created.', [
            'type' => Transfer::displayName(),
        ]), 'transfer', array_filter([
            'cpEditUrl' => request()->isCpRequest() ? $editUrl : null,
        ]));

        if (!request()->expectsJson()) {
            return redirect(\CraftCms\Cms\Support\Url::urlWithParams($editUrl, [
                'fresh' => 1,
            ]));
        }

        return $response;
    }

    public function index(ElementIndexRequest $request): InertiaResponse|RedirectResponse
    {
        $viewModel = new TransferIndexViewModel($request);

        return $this->shownSourceRedirect($request, $viewModel, false)
            ?? Inertia::render('commerce::inventory/transfers/Index', [$viewModel]);
    }

    public function markAsPending(Request $request): Response
    {
        $transferId = $request->integer('transferId');
        abort_if(!$transferId, 400, 'Missing transferId');

        $transfer = Transfer::find()->id($transferId)->one();
        abort_if($transfer === null, 404);

        if (!app(Transfers::class)->markAsPending($transfer)) {
            return $this->asFailure(t('Couldn’t mark transfer as pending.', category: 'commerce'));
        }

        return $this->asSuccess(t('Transfer marked as pending.', category: 'commerce'));
    }

    /**
     * The Form shown by the “Receive Inventory” modal. Its values are posted to {@see receiveTransfer()}
     * along with the `transferId`.
     */
    public function prepareReceiveModal(Request $request, UiResolver $formResolver): JsonResponse
    {
        abort_unless($request->expectsJson(), 400);

        $transfer = $this->resolveReceivableTransfer($request);

        $rows = array_map(fn(TransferDetail $detail) => [
            'uid' => $detail->uid,
            'label' => $detail->inventoryItemDescription,
            'quantity' => $detail->quantity,
            'accepted' => $detail->quantityAccepted,
            'rejected' => $detail->quantityRejected,
            'deletedMessage' => $detail->inventoryItemId === null
                ? t('“{name}” deleted.', ['name' => $detail->inventoryItemDescription])
                : null,
        ], $transfer->getDetails());

        // TODO: Add “Accept all remaining” / “Reject all remaining” shortcuts.
        $form = Ui::make([
            Field::make(null, TransferReceive::make('details')->rows($rows)),
        ]);

        return new JsonResponse([
            'ui' => $formResolver->resolve($form, new UiContext()),
            'title' => t('Receive Transfer', category: 'commerce'),
            'submitLabel' => t('Receive', category: 'commerce'),
        ]);
    }

    public function receiveTransfer(Request $request): Response
    {
        $transfer = $this->resolveReceivableTransfer($request);

        // TODO: Look into validating received quantities (e.g. no negatives, accepted + rejected not exceeding
        // what's still to be received). Legacy receiving never validated them.
        try {
            app(Transfers::class)->receive($transfer, $request->input('details') ?? []);
        } catch (\Throwable $e) {
            Log::error('Failed to save transfer details: ' . $e->getMessage(), ['exception' => $e]);
            return $this->asFailure(t('Failed to receive transfer: {error}', ['error' => $e->getMessage()], category: 'commerce'));
        }

        return $this->asSuccess(t('Updated', category: 'commerce'));
    }

    private function resolveReceivableTransfer(Request $request): Transfer
    {
        $transferId = $request->integer('transferId');
        abort_if(!$transferId, 400, 'Missing transferId');

        $transfer = Transfer::find()->id($transferId)->one();
        abort_if($transfer === null, 404);
        Gate::authorize('save', $transfer);
        abort_unless($transfer->canBeReceived(), 400, 'Only a pending transfer can be received.');

        return $transfer;
    }
}
