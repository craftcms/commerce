<?php

declare(strict_types=1);

use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Facades\Drafts;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
    app(Plugin::class)->edition = Plugin::EDITION_ENTERPRISE;

    $this->indexPath = '/' . Cms::config()->cpTrigger . '/commerce/inventory/transfers';
});

it('renders the transfers index page', function() {
    get($this->indexPath)
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('commerce::inventory/transfers/Index', false)
            ->where('elementType', Transfer::class)
            ->where('indexUrl', Url::cpUrl('commerce/inventory/transfers'))
            ->where('source.key', '*')
            ->has('sourceNavItems', 2)
            ->where('sourceNavItems.0.label', 'All Transfers')
            ->where('sourceNavItems.0.href', Url::cpUrl('commerce/inventory/transfers'))
            ->where('sourceNavItems.0.selected', true)
            ->where('sourceNavItems.1.label', 'Transfer Status')
            ->where('sourceNavItems.1.group', true)
            ->has('sourceNavItems.1.subnav', 4)
            ->where('sourceNavItems.1.subnav.1.label', 'Pending')
            ->where('sourceNavItems.1.subnav.1.href', Url::cpUrl('commerce/inventory/transfers', ['source' => 'pending']))
            ->where('sourceNavItems.1.subnav.1.selected', false)
            ->where('newTransferLabel', 'New transfer')
            ->where('newTransferUrl', Url::actionUrl('commerce/transfers/create'))
            ->where('crumbs.0.label', 'Commerce')
            ->has('data')
            ->has('sources')
        );
});

it('selects a transfer status source', function() {
    get($this->indexPath . '?source=pending')
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('source.key', 'pending')
            ->where('title', 'Pending')
            ->where('sourceNavItems.0.selected', false)
            ->where('sourceNavItems.1.subnav.0.selected', false)
            ->where('sourceNavItems.1.subnav.1.selected', true)
            ->where('sourceNavItems.1.subnav.2.selected', false)
            ->where('sourceNavItems.1.subnav.3.selected', false)
        );
});

it('requires the manage inventory transfers permission', function() {
    Gate::before(fn($user, $ability) => $ability === 'commerce-manageInventoryTransfers' ? false : null);

    get($this->indexPath)->assertForbidden();
});

it('leaves out the new transfer button when a transfer can’t be saved', function() {
    Gate::before(fn($user, string $ability, array $arguments) => $ability === 'save' && ($arguments[0] ?? null) instanceof Transfer ? false : null);

    get($this->indexPath)
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page->where('newTransferUrl', null));
});

it('renders the transfer edit page', function() {
    $transfer = new Transfer();
    $transfer->originLocationId = app(InventoryLocations::class)->getAllInventoryLocations()->first()->id;
    Elements::saveElement($transfer, runValidation: false);

    get($this->indexPath . '/' . $transfer->id)
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('elements/Edit', false)
            ->where('elementType', Transfer::class)
            ->where('elementId', $transfer->id)
            ->where('saveUrl', Url::actionUrl('elements/save'))
            ->where('canAutosave', false)
            ->where('crumbs.0.label', 'Commerce')
            ->where('crumbs.1.label', 'Transfers')
            ->has('ui')
        );
});

it('offers to create a new transfer draft', function() {
    $user = User::find()->admin(true)->one();
    $draft = new Transfer();
    Drafts::saveElementAsDraft($draft, $user->id, markAsSaved: false);

    get($this->indexPath . '/' . $draft->id . '?draftId=' . $draft->draftId)
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('draftId', $draft->draftId)
            ->where('editorActions.primary.label', 'Create transfer')
        );
});
