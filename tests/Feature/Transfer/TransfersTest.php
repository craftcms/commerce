<?php

declare(strict_types=1);

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Http\Controllers\Elements\SaveElementController;
use CraftCms\Cms\Http\Controllers\Elements\UpdateFieldLayoutController;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Inventory\Data\InventoryLocation;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use CraftCms\Commerce\Transfer\Enums\TransferStatusType;
use CraftCms\Commerce\Transfer\Transfers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function createTransferInventoryLocation(string $handle): InventoryLocation
{
    $address = new Address();
    $address->countryCode = 'US';
    $address->title = $handle;
    Elements::saveElement($address);

    $inventoryLocation = new InventoryLocation();
    $inventoryLocation->name = ucfirst($handle);
    $inventoryLocation->handle = $handle;
    $inventoryLocation->setAddress($address);

    if (!app(InventoryLocations::class)->saveInventoryLocation($inventoryLocation)) {
        throw new RuntimeException('Could not save inventory location: ' . json_encode($inventoryLocation->errors()->all()));
    }

    return $inventoryLocation;
}

/**
 * @param array<int, array{variant: Variant, quantity: int}> $lines
 */
function createDraftTransfer(InventoryLocation $origin, InventoryLocation $destination, array $lines): Transfer
{
    $transfer = new Transfer();
    $transfer->originLocationId = $origin->id;
    $transfer->destinationLocationId = $destination->id;
    $transfer->setDetails(array_map(fn(array $line) => [
        'inventoryItemId' => $line['variant']->inventoryItemId,
        'quantity' => $line['quantity'],
    ], $lines));

    if (!Elements::saveElement($transfer)) {
        throw new RuntimeException('Could not save transfer: ' . json_encode($transfer->errors()->all()));
    }

    return Transfer::find()->id($transfer->id)->one();
}

beforeEach(function() {
    $this->withoutVite();
    $fixture = ProductConditionsFixture::seed();
    $this->hoodieVariant = $fixture->hoodieVariant;
    $this->tShirtVariant = $fixture->tShirtVariant;
    $this->origin = app(InventoryLocations::class)->getAllInventoryLocations()->first();
    $this->destination = createTransferInventoryLocation('warehouse');

    foreach ([$this->hoodieVariant, $this->tShirtVariant] as $variant) {
        app(Inventory::class)->updatePurchasableInventoryLevel($variant, 10);
    }
});

test('each saved transfer detail gets its own uid', function() {
    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 2],
        ['variant' => $this->tShirtVariant, 'quantity' => 3],
    ]);

    $uids = DB::table(Table::TRANSFERDETAILS)->where('transferId', $transfer->id)->pluck('uid')->all();

    expect($uids)->toHaveCount(2)
        ->and(array_unique($uids))->toHaveCount(2)
        ->and($uids)->not->toContain('0');
});

test('marking a transfer as pending moves stock to incoming and links the transactions to the transfer', function() {
    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 4],
    ]);

    expect(app(Transfers::class)->markAsPending($transfer))->toBeTrue();

    $transactions = DB::table(Table::INVENTORYTRANSACTIONS)
        ->where('inventoryItemId', $this->hoodieVariant->inventoryItemId)
        ->where('transferId', $transfer->id)
        ->get(['inventoryLocationId', 'type', 'quantity']);

    expect(Transfer::find()->id($transfer->id)->one()->getTransferStatus())->toBe(TransferStatusType::PENDING)
        ->and($transactions)->toHaveCount(2)
        ->and($transactions->firstWhere('inventoryLocationId', $this->destination->id))
        ->toMatchArray(['type' => 'incoming', 'quantity' => 4])
        ->and($transactions->firstWhere('inventoryLocationId', $this->origin->id))
        ->toMatchArray(['type' => 'available', 'quantity' => -4]);
});

test('receiving a transfer applies each detail\'s own accepted and rejected quantities', function() {
    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 4],
        ['variant' => $this->tShirtVariant, 'quantity' => 3],
    ]);
    app(Transfers::class)->markAsPending($transfer);

    $details = collect($transfer->getDetails())->keyBy('inventoryItemId');
    $hoodieDetail = $details[$this->hoodieVariant->inventoryItemId];
    $tShirtDetail = $details[$this->tShirtVariant->inventoryItemId];

    app(Transfers::class)->receive($transfer, [
        $hoodieDetail->uid => ['accept' => 3, 'reject' => 1],
        $tShirtDetail->uid => ['accept' => 1],
    ]);

    $savedTransfer = Transfer::find()->id($transfer->id)->one();
    $savedDetails = collect($savedTransfer->getDetails())->keyBy('id');

    expect($savedTransfer->getTransferStatus())->toBe(TransferStatusType::PARTIAL)
        ->and($savedDetails[$hoodieDetail->id])
        ->quantityAccepted->toBe(3)
        ->quantityRejected->toBe(1)
        ->and($savedDetails[$tShirtDetail->id])
        ->quantityAccepted->toBe(1)
        ->quantityRejected->toBe(0)
        ->and(DB::table(Table::INVENTORYTRANSACTIONS)
            ->where('transferId', $transfer->id)
            ->where('inventoryLocationId', $this->destination->id)
            ->where('type', 'incoming')
            ->sum('quantity'))->toEqual(7 - 3 - 1 - 1);
});

test('receiving everything marks the transfer as received', function() {
    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 2],
    ]);
    app(Transfers::class)->markAsPending($transfer);
    $detail = $transfer->getDetails()[0];

    app(Transfers::class)->receive($transfer, [$detail->uid => ['accept' => 2]]);

    expect(Transfer::find()->id($transfer->id)->one()->getTransferStatus())->toBe(TransferStatusType::RECEIVED);
});

test('sort options do not include table attributes that have no column', function() {
    expect(Transfer::sortOptions())->not->toHaveKeys(['originLocation', 'destinationLocation', 'received']);
});

test('only a draft transfer with items offers to be marked as pending in the editor', function() {
    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 1],
    ]);

    $markAsPending = fn(Transfer $transfer) => collect($transfer->actionMenuDescriptors())
        ->firstWhere('behavior.actionUrl', Url::actionUrl('commerce/transfers/mark-as-pending'));

    expect($markAsPending($transfer))
        ->behavior->params->toBe(['transferId' => $transfer->id]);

    app(Transfers::class)->markAsPending($transfer);

    expect($markAsPending(Transfer::find()->id($transfer->id)->one()))->toBeNull();
});

/**
 * @param array<string, mixed> $form
 * @return array<string, mixed>|null
 */
function findFormControl(array $form, string $path): ?array
{
    foreach ($form['nodes'] ?? $form['children'] ?? [] as $node) {
        if (($node['control']['path'] ?? null) === [$path]) {
            return $node['control'];
        }

        if ($control = findFormControl($node, $path)) {
            return $control;
        }
    }

    return null;
}

test('a draft transfer’s editor manages its locations and items with form controls', function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 2],
    ]);

    $form = get($transfer->getCpEditUrl())->assertOk()->inertiaProps('form');
    $details = findFormControl($form, 'details');
    $detail = $transfer->getDetails()[0];

    expect(findFormControl($form, 'originLocationId'))
        ->reactive->toBeTrue()
        ->and(findFormControl($form, 'destinationLocationId'))->not->toBeNull()
        ->and($details)
        ->component->toBe('commerce:transfer-details')
        ->reactive->toBeTrue()
        ->and($form['values']['details'])->toBe([
            $detail->uid => [
                'id' => $detail->id,
                'uid' => $detail->uid,
                'inventoryItemId' => $this->hoodieVariant->inventoryItemId,
                'quantity' => 2,
            ],
        ])
        ->and($details['props']['itemsHtml'])->toHaveKey((string)$this->hoodieVariant->inventoryItemId)
        ->and(collect($details['props']['options'])->pluck('value')->all())
        ->toContain((string)$this->hoodieVariant->inventoryItemId, (string)$this->tShirtVariant->inventoryItemId);
});

test('a transfer that has left draft is shown without form controls', function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 2],
    ]);
    app(Transfers::class)->markAsPending($transfer);

    $form = get($transfer->getCpEditUrl())->assertOk()->inertiaProps('form');

    expect(findFormControl($form, 'originLocationId'))->toBeNull()
        ->and(findFormControl($form, 'details'))->toBeNull();
});

test('changing the origin refreshes the items that can be transferred', function() {
    actingAs(User::find()->admin(true)->one());

    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 2],
    ]);

    $form = postJson(action(UpdateFieldLayoutController::class), [
        'elementType' => Transfer::class,
        'elementId' => $transfer->id,
        'siteId' => $transfer->siteId,
        'originLocationId' => $this->destination->id,
        'destinationLocationId' => $this->origin->id,
    ])->assertOk()->json('form');

    expect($form['values']['originLocationId'])->toBe((string)$this->destination->id)
        ->and(collect(findFormControl($form, 'details')['props']['options'])->where('disabled', false))->toBeEmpty();
});

test('saving a draft transfer from the editor adds, updates and removes its items', function() {
    actingAs(User::find()->admin(true)->one());

    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 2],
        ['variant' => $this->tShirtVariant, 'quantity' => 3],
    ]);
    $hoodieDetail = collect($transfer->getDetails())->firstWhere('inventoryItemId', $this->hoodieVariant->inventoryItemId);
    $newUid = (string)Str::uuid();

    postJson(action([SaveElementController::class, 'store']), [
        'elementType' => Transfer::class,
        'elementId' => $transfer->id,
        'siteId' => $transfer->siteId,
        'originLocationId' => (string)$this->origin->id,
        'destinationLocationId' => (string)$this->destination->id,
        'details' => [
            $hoodieDetail->uid => [
                'id' => $hoodieDetail->id,
                'uid' => $hoodieDetail->uid,
                'inventoryItemId' => $this->hoodieVariant->inventoryItemId,
                'quantity' => '5',
            ],
            $newUid => [
                'id' => null,
                'uid' => $newUid,
                'inventoryItemId' => (string)$this->tShirtVariant->inventoryItemId,
                'quantity' => '1',
            ],
        ],
    ])->assertOk();

    $details = collect(Transfer::find()->id($transfer->id)->one()->getDetails())->keyBy('uid');

    expect($details)->toHaveCount(2)
        ->and($details[$hoodieDetail->uid])
        ->id->toBe($hoodieDetail->id)
        ->quantity->toBe(5)
        ->and($details[$newUid])
        ->inventoryItemId->toBe($this->tShirtVariant->inventoryItemId)
        ->quantity->toBe(1);
});

test('a draft transfer can’t be saved without any items', function() {
    actingAs(User::find()->admin(true)->one());

    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 2],
    ]);

    postJson(action([SaveElementController::class, 'store']), [
        'elementType' => Transfer::class,
        'elementId' => $transfer->id,
        'siteId' => $transfer->siteId,
        'details' => [],
    ])->assertBadRequest()->assertJsonStructure(['errors' => ['details']]);

    expect(Transfer::find()->id($transfer->id)->one()->getDetails())->toHaveCount(1);
});

test('marking a transfer as pending from the editor redirects back to it', function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 1],
    ]);
    $editUrl = $transfer->getCpEditUrl();

    from($editUrl)
        ->post(Url::actionUrl('commerce/transfers/mark-as-pending'), ['transferId' => $transfer->id], ['X-Inertia' => 'true'])
        ->assertRedirect($editUrl);

    expect(Transfer::find()->id($transfer->id)->one()->getTransferStatus())->toBe(TransferStatusType::PENDING);
});

test('only a pending or partially received transfer offers to receive inventory in the editor', function() {
    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 2],
    ]);

    $receive = fn(Transfer $transfer) => collect($transfer->actionMenuDescriptors())
        ->firstWhere('behavior.type', 'formModal');

    expect($receive($transfer))->toBeNull();

    app(Transfers::class)->markAsPending($transfer);
    $transfer = Transfer::find()->id($transfer->id)->one();

    expect($receive($transfer)['behavior'])->toBe([
        'type' => 'formModal',
        'modalUrl' => Url::actionUrl('commerce/transfers/prepare-receive-modal'),
        'actionUrl' => Url::actionUrl('commerce/transfers/receive-transfer'),
        'params' => ['transferId' => $transfer->id],
    ]);

    app(Transfers::class)->receive($transfer, [$transfer->getDetails()[0]->uid => ['accept' => 2]]);

    expect($receive(Transfer::find()->id($transfer->id)->one()))->toBeNull();
});

test('the receive modal lists each item with what has been received so far', function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 4],
    ]);
    app(Transfers::class)->markAsPending($transfer);
    $detail = $transfer->getDetails()[0];
    app(Transfers::class)->receive($transfer, [$detail->uid => ['accept' => 1, 'reject' => 1]]);

    $response = getJson(Url::actionUrl('commerce/transfers/prepare-receive-modal', ['transferId' => $transfer->id]))
        ->assertOk()
        ->assertJsonPath('title', 'Receive Transfer')
        ->assertJsonPath('submitLabel', 'Receive');

    expect(findFormControl($response->json('form'), 'details'))
        ->component->toBe('commerce:transfer-receive')
        ->and(findFormControl($response->json('form'), 'details')['props']['rows'])->toBe([
            [
                'uid' => $detail->uid,
                'label' => 'rad-hood',
                'quantity' => 4,
                'accepted' => 1,
                'rejected' => 1,
                'deletedMessage' => null,
            ],
        ]);
});

test('receiving from the modal applies the posted quantities', function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 4],
    ]);
    app(Transfers::class)->markAsPending($transfer);
    $detail = $transfer->getDetails()[0];

    postJson(Url::actionUrl('commerce/transfers/receive-transfer'), [
        'transferId' => $transfer->id,
        'details' => [$detail->uid => ['accept' => '3', 'reject' => '']],
    ])->assertOk()->assertJsonPath('message', 'Updated');

    $saved = Transfer::find()->id($transfer->id)->one();

    expect($saved->getTransferStatus())->toBe(TransferStatusType::PARTIAL)
        ->and($saved->getDetails()[0])
        ->quantityAccepted->toBe(3)
        ->quantityRejected->toBe(0);
});

test('submitting the receive modal untouched receives nothing', function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 4],
    ]);
    app(Transfers::class)->markAsPending($transfer);

    postJson(Url::actionUrl('commerce/transfers/receive-transfer'), [
        'transferId' => $transfer->id,
        'details' => null,
    ])->assertOk();

    expect(Transfer::find()->id($transfer->id)->one()->getTransferStatus())->toBe(TransferStatusType::PENDING);
});

test('a draft transfer can’t be received', function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 4],
    ]);

    getJson(Url::actionUrl('commerce/transfers/prepare-receive-modal', ['transferId' => $transfer->id]))->assertBadRequest();
    postJson(Url::actionUrl('commerce/transfers/receive-transfer'), ['transferId' => $transfer->id])->assertBadRequest();
});

test('receiving requires being able to save the transfer', function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $transfer = createDraftTransfer($this->origin, $this->destination, [
        ['variant' => $this->hoodieVariant, 'quantity' => 4],
    ]);
    app(Transfers::class)->markAsPending($transfer);
    Gate::before(fn($user, string $ability, array $arguments) => $ability === 'save' && ($arguments[0] ?? null) instanceof Transfer ? false : null);

    getJson(Url::actionUrl('commerce/transfers/prepare-receive-modal', ['transferId' => $transfer->id]))->assertForbidden();
    postJson(Url::actionUrl('commerce/transfers/receive-transfer'), ['transferId' => $transfer->id])->assertForbidden();
});
