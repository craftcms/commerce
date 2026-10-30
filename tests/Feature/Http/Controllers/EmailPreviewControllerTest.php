<?php

declare(strict_types=1);

use CraftCms\Aliases\Aliases;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Cms\View\TemplateRoots;
use CraftCms\Commerce\Email\Data\Email;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    // Point site template rendering at a directory containing a minimal
    // `emails/order-confirmation.twig`, since the real one only ships with a project's own
    // site templates, not with Commerce itself.
    $templatesPath = dirname(__DIR__, 3) . '/Support/templates';
    Aliases::set('@templates', $templatesPath);
    app(TemplateRoots::class)->register(TemplateMode::Site, '', $templatesPath);
});

/** @return array{fixture: OrdersFixture, email: Email} */
function seedOrderConfirmationEmail(): array
{
    $fixture = OrdersFixture::seed();

    $email = new Email();
    $email->storeId = $fixture->storeId;
    $email->name = 'Order Confirmation';
    $email->subject = 'Order Confirmation';
    $email->templatePath = 'emails/order-confirmation';
    if (!app(Emails::class)->saveEmail($email)) {
        throw new RuntimeException('Could not save email: ' . json_encode($email->errors()->all()));
    }

    return compact('fixture', 'email');
}

it('renders the email template for a specific order', function() {
    ['fixture' => $fixture, 'email' => $email] = seedOrderConfirmationEmail();
    $order = $fixture->orders['completed-new'];

    $response = get(Url::actionUrl('commerce/email-preview/render', [
        'email' => $email->id . ':' . $email->storeId,
        'number' => $order->number,
    ]));

    $response->assertOk();
    expect($response->getContent())
        ->toContain('<title>Order Confirmation</title>')
        ->toContain('<h1>Order Confirmation ' . $order->shortNumber . '</h1>');
});

it('renders the email template for a random completed order when no order number is given', function() {
    ['email' => $email] = seedOrderConfirmationEmail();

    $response = get(Url::actionUrl('commerce/email-preview/render', [
        'email' => $email->id . ':' . $email->storeId,
    ]));

    $response->assertOk();
    expect($response->getContent())->toContain('<title>Order Confirmation</title>');
    expect(preg_match('/<h1>Order Confirmation [0-9a-zA-Z]{7}<\/h1>/', $response->getContent()))->toBe(1);
});
