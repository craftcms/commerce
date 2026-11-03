<?php

declare(strict_types=1);

use CraftCms\Aliases\Aliases;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Facades\Template;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Cms\View\TemplateRoots;
use CraftCms\Commerce\Email\Data\Email;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Email\Jobs\SendEmailJob;
use CraftCms\Commerce\Order\Data\OrderHistory;
use CraftCms\Commerce\Order\Events\OrderStatusEmailsResolving;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Pdf\Data\Pdf;
use CraftCms\Commerce\Pdf\Pdfs;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function() {
    $templatesPath = dirname(__DIR__, 2) . '/Support/templates';
    Aliases::set('@templates', $templatesPath);
    app(TemplateRoots::class)->register(TemplateMode::Site, '', $templatesPath);

    $this->fixture = OrdersFixture::seed();
    $this->order = $this->fixture->orders['completed-new'];
    $this->originalLanguage = app()->getLocale();

    $this->email = new Email();
    $this->email->storeId = $this->fixture->storeId;
    $this->email->name = 'German email';
    $this->email->subject = 'Bestellung';
    $this->email->templatePath = 'locale/number';
    $this->email->language = 'de';
    if (!app(Emails::class)->saveEmail($this->email)) {
        throw new RuntimeException('Could not save email: ' . json_encode($this->email->errors()->all()));
    }
});

test('the email preview renders in the email’s language', function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $response = get(Url::actionUrl('commerce/email-preview/render', [
        'email' => $this->email->id . ':' . $this->email->storeId,
        'number' => $this->order->number,
    ]))->assertOk();

    expect($response->getContent())->toContain('<p>1.234,5</p>')
        ->and(app()->getLocale())->toBe($this->originalLanguage);
});

test('order PDFs render in the PDF’s language', function() {
    $pdf = new Pdf([
        'storeId' => $this->fixture->storeId,
        'name' => 'German PDF',
        'handle' => 'germanPdf',
        'templatePath' => 'locale/number',
        'language' => 'de',
    ]);

    $renderedWith = null;
    Template::partialMock()
        ->shouldReceive('renderTemplate')
        ->andReturnUsing(function() use (&$renderedWith) {
            $renderedWith = [app()->getLocale(), I18N::getFormattingLocale()->id];

            return '<p>PDF</p>';
        });

    app(Pdfs::class)->renderPdfForOrder($this->order, pdf: $pdf);

    expect($renderedWith)->toBe(['de', 'de'])
        ->and(app()->getLocale())->toBe($this->originalLanguage);
});

test('order status emails are queued with order data formatted in the email’s language', function() {
    Queue::fake();
    Event::listen(OrderStatusEmailsResolving::class, fn(OrderStatusEmailsResolving $event) => $event->emails = [$this->email]);

    $orderHistory = new OrderHistory();
    $orderHistory->id = 1;

    app(OrderStatuses::class)->statusChangeHandler($this->order, $orderHistory);

    Queue::assertPushed(SendEmailJob::class, fn(SendEmailJob $job) => $job->commerceEmailId === $this->email->id
        && $job->orderData['totalPriceAsCurrency'] === I18N::withLocale('de', null, fn() => $this->order->totalPriceAsCurrency));

    expect(app()->getLocale())->toBe($this->originalLanguage)
        ->and(I18N::withLocale('de', null, fn() => $this->order->totalPriceAsCurrency))->not->toBe($this->order->totalPriceAsCurrency);
});
