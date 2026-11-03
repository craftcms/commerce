<?php

declare(strict_types=1);

use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Pdf\Data\Pdf;
use CraftCms\Commerce\Pdf\Pdfs;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Foundation\AliasLoader;
use Symfony\Component\DomCrawler\Crawler;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function() {
    prioritizeCommerceRoutes();

    // A Craft project registers the CMS's facade aliases, which the control panel layout uses.
    $cmsComposer = json_decode((string)file_get_contents(dirname(__DIR__, 4) . '/vendor/craftcms/cms/composer.json'), true);
    foreach ($cmsComposer['extra']['laravel']['aliases'] ?? [] as $alias => $class) {
        AliasLoader::getInstance()->alias($alias, $class);
    }
    config(['mail.default' => 'array']);

    $this->fixture = OrdersFixture::seed();
    $this->order = $this->fixture->orders['completed-new'];
});

/** @return array<string, string> */
function emailChallengeFormValues(string $html): array
{
    $values = [];
    foreach ((new Crawler($html))->filter('form input[type=hidden]') as $input) {
        $values[$input->getAttribute('name')] = $input->getAttribute('value');
    }

    return $values;
}

test('the PDF download challenge form sends a new download link', function() {
    $pdf = new Pdf([
        'storeId' => $this->order->storeId,
        'name' => 'Order PDF',
        'handle' => 'orderPdf',
        'templatePath' => 'shop/pdf/order',
        'isDefault' => true,
        'enabled' => true,
    ]);
    expect(app(Pdfs::class)->savePdf($pdf))->toBeTrue();

    $html = get(Url::actionUrl('commerce/downloads/email-challenge', ['number' => $this->order->number]))
        ->assertOk()
        ->assertSee($this->order->getMaskedEmail())
        ->getContent();

    $values = emailChallengeFormValues($html);

    expect($values)->toHaveKey('orderNumberHash');

    post(Url::actionUrl('commerce/downloads/pdf-challenge'), $values)
        ->assertRedirect(Url::actionUrl('commerce/downloads/pdf-sent', ['hash' => $values['orderNumberHash']]));
});

test('the cart recovery challenge form sends a cart recovery link', function() {
    $cart = new Order();
    $cart->number = bin2hex(random_bytes(16));
    $cart->storeId = $this->order->storeId;
    $cart->setCustomerId($this->order->getCustomerId());
    expect(Elements::saveElement($cart, false))->toBeTrue();

    $html = get(Url::actionUrl('commerce/cart/email-challenge', ['number' => $cart->number]))
        ->assertOk()
        ->assertSee($cart->getMaskedEmail())
        ->getContent();

    $values = emailChallengeFormValues($html);

    post(Url::actionUrl('commerce/cart/cart-challenge'), $values)
        ->assertRedirect(Url::actionUrl('commerce/cart/cart-sent', ['hash' => $values['cartNumberHash']]));
});

test('the challenge pages show the control panel logo and any error', function() {
    Cms::config()->cpLogoUrl = 'https://example.com/logo.svg';
    session()->flash('error', 'Something went wrong.');

    $html = get(Url::actionUrl('commerce/downloads/email-challenge', ['number' => $this->order->number]))
        ->assertOk()
        ->getContent();

    $crawler = new Crawler($html);

    expect($crawler->filter('#login-logo')->attr('src'))->toBe('https://example.com/logo.svg')
        ->and($crawler->filter('#login-logo')->attr('alt'))->toBe(Cms::systemName())
        ->and($crawler->filter('.error')->text())->toBe('Something went wrong.');
});

test('masked emails keep the first half of the name', function(string $email, string $masked) {
    $maskEmail = new ReflectionMethod(Order::class, '_maskEmail');

    expect($maskEmail->invoke(new Order(), $email))->toBe($masked);
})->with([
    'odd length name' => ['admin@example.com', 'ad***@example.com'],
    'even length name' => ['customer@example.com', 'cust***@example.com'],
    'long name' => ['averyveryverylongname@example.com', 'averyveryv***@example.com'],
    'short name' => ['ab@example.com', '***@example.com'],
]);
