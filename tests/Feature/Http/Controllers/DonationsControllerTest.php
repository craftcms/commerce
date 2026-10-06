<?php

declare(strict_types=1);

use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Purchasable\Elements\Donation;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
});

function donationFormFields(array $form): array
{
    return collect($form['nodes'])
        ->where('component', 'craft:field')
        ->mapWithKeys(fn(array $node) => [$node['props']['label'] => !($node['hidden'] ?? $node['props']['hidden'] ?? false)])
        ->all();
}

it('renders the donation settings page, creating the donation', function() {
    get(Url::cpUrl('commerce/donations'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('Form', false)
            ->where('title', 'Donation Settings')
            ->where('crumbs.0.label', 'Commerce')
            ->where('submit.url', Url::actionUrl('commerce/donations/save'))
            ->where('refreshUrl', Url::actionUrl('commerce/donations/render-form'))
            ->where('form.values.sku', 'DONATION-CC5')
            ->where('form.values.availableForPurchase', false)
            ->has('form.values.enabled')
        );

    expect(Donation::find()->status(null)->count())->toBe(1);
});

it('requires the manage donation settings permission', function() {
    Gate::before(fn($user, $ability) => $ability === 'commerce-manageDonationSettings' ? false : null);

    get(Url::cpUrl('commerce/donations'))->assertForbidden();
    postJson(Url::actionUrl('commerce/donations/save'), ['enabled' => true])->assertForbidden();
});

it('shows the fields that apply to the lightswitches’ state', function(array $values, array $visible) {
    get(Url::cpUrl('commerce/donations'))->assertOk();

    $form = postJson(Url::actionUrl('commerce/donations/render-form'), [
        'values' => $values,
        'scope' => [],
    ])->assertOk()->json('form');

    expect(donationFormFields($form))->toBe($visible)
        // A field that's revealed keeps the donation's own value.
        ->and($form['values']['sku'])->toBe('DONATION-CC5');
})->with([
    'disabled' => [
        ['enabled' => false],
        ['Enabled' => true, 'Available for purchase?' => false, 'SKU' => false],
    ],
    'enabled' => [
        ['enabled' => true, 'availableForPurchase' => false],
        ['Enabled' => true, 'Available for purchase?' => true, 'SKU' => false],
    ],
    'available for purchase' => [
        ['enabled' => true, 'availableForPurchase' => true],
        ['Enabled' => true, 'Available for purchase?' => true, 'SKU' => true],
    ],
]);

it('saves the donation settings', function() {
    get(Url::cpUrl('commerce/donations'))->assertOk();

    postJson(Url::actionUrl('commerce/donations/save'), [
        'enabled' => true,
        'availableForPurchase' => true,
        'sku' => 'GIVE-1',
    ])->assertOk()->assertJsonPath('message', 'Donation settings saved.');

    $donation = Donation::find()->status(null)->one();

    expect($donation->enabled)->toBeTrue()
        ->and($donation->availableForPurchase)->toBeTrue()
        ->and($donation->sku)->toBe('GIVE-1');
});

it('redirects back to the donation settings after saving from the page', function() {
    get(Url::cpUrl('commerce/donations'))->assertOk();

    $cpTrigger = Cms::config()->cpTrigger;
    $actionTrigger = Cms::config()->actionTrigger;

    post("/$cpTrigger/$actionTrigger/commerce/donations/save", [
        'enabled' => true,
        'availableForPurchase' => true,
        'sku' => 'GIVE-1',
        'redirect' => encrypt('commerce/donations'),
    ])->assertRedirect(Url::cpUrl('commerce/donations'));

    expect(Donation::find()->status(null)->one()->sku)->toBe('GIVE-1');
});

it('returns to the donation settings when saving and continuing to edit', function() {
    get(Url::cpUrl('commerce/donations'))->assertOk();

    $cpTrigger = Cms::config()->cpTrigger;
    $actionTrigger = Cms::config()->actionTrigger;

    // Saving without leaving the screen posts no redirect.
    post("/$cpTrigger/$actionTrigger/commerce/donations/save", [
        'enabled' => true,
        'availableForPurchase' => true,
        'sku' => 'GIVE-2',
    ], ['referer' => Url::cpUrl('commerce/donations')])->assertRedirect(Url::cpUrl('commerce/donations'));

    expect(Donation::find()->status(null)->one()->sku)->toBe('GIVE-2');
});

it('keeps the values of fields that weren’t shown', function() {
    get(Url::cpUrl('commerce/donations'))->assertOk();

    postJson(Url::actionUrl('commerce/donations/save'), [
        'enabled' => true,
        'availableForPurchase' => true,
        'sku' => 'GIVE-1',
    ])->assertOk();

    // A disabled donation only posts the Enabled lightswitch.
    postJson(Url::actionUrl('commerce/donations/save'), ['enabled' => false])->assertOk();

    $donation = Donation::find()->status(null)->one();

    expect($donation->enabled)->toBeFalse()
        ->and($donation->availableForPurchase)->toBeTrue()
        ->and($donation->sku)->toBe('GIVE-1');
});

it('rejects an empty SKU', function() {
    get(Url::cpUrl('commerce/donations'))->assertOk();

    postJson(Url::actionUrl('commerce/donations/save'), [
        'enabled' => true,
        'availableForPurchase' => true,
        'sku' => '',
    ])->assertStatus(400)->assertJsonStructure(['errors' => ['sku']]);

    expect(Donation::find()->status(null)->one()->sku)->toBe('DONATION-CC5');
});
