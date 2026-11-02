<?php

declare(strict_types=1);

use CraftCms\Aliases\Aliases;
use CraftCms\Cms\Email\Data\EmailSettings;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Cms\View\TemplateRoots;
use CraftCms\Commerce\Email\Data\Email;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Email\Events\MailSending;
use CraftCms\Commerce\Email\Events\MailSent;
use CraftCms\Commerce\Email\Mailables\OrderEmail;
use CraftCms\Commerce\Email\Models\Email as EmailRecord;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

beforeEach(function() {
    $templatesPath = dirname(__DIR__, 2) . '/Support/templates';
    Aliases::set('@templates', $templatesPath);
    app(TemplateRoots::class)->register(TemplateMode::Site, '', $templatesPath);

    Mail::fake();

    $this->fixture = OrdersFixture::seed();
    $this->order = $this->fixture->orders['completed-new'];

    $this->email = new Email();
    $this->email->storeId = $this->fixture->storeId;
    $this->email->name = 'Order Confirmation';
    $this->email->subject = 'Order Confirmation';
    $this->email->templatePath = 'emails/order-confirmation';
    $this->email->recipientType = EmailRecord::TYPE_CUSTOM;
    $this->email->setTo('shop@example.com');
    $this->email->setSenderAddress('orders@example.com');
    $this->email->setSenderName('Example Orders');
    $this->email->replyTo = 'support@example.com';

    if (!app(Emails::class)->saveEmail($this->email)) {
        throw new RuntimeException('Could not save email: ' . json_encode($this->email->errors()->all()));
    }
});

test('order emails are sent as Laravel mailables', function() {
    $error = '';
    $sent = app(Emails::class)->sendEmail($this->email, $this->order, error: $error);

    expect($error)->toBe('')->and($sent)->toBeTrue();

    Mail::assertSent(OrderEmail::class, fn(OrderEmail $mail) => $mail->hasTo('shop@example.com')
        && $mail->hasFrom('orders@example.com', 'Example Orders')
        && $mail->hasReplyTo('support@example.com')
        && $mail->hasSubject('Order Confirmation'));

    Mail::sent(OrderEmail::class)->first()->assertSeeInHtml('<h1>Order Confirmation ' . $this->order->shortNumber . '</h1>', false);
});

test('MailSending listeners can change the mailable, and MailSent listeners receive it', function() {
    $sentMailable = null;

    Event::listen(MailSending::class, function(MailSending $event) {
        $event->mailable->subject('Changed');
        $event->mailable->bcc('audit@example.com');
    });
    Event::listen(MailSent::class, function(MailSent $event) use (&$sentMailable) {
        $sentMailable = $event->mailable;
    });

    app(Emails::class)->sendEmail($this->email, $this->order);

    Mail::assertSent(OrderEmail::class, fn(OrderEmail $mail) => $mail->hasSubject('Changed') && $mail->hasBcc('audit@example.com'));
    expect($sentMailable)->toBeInstanceOf(OrderEmail::class);
});

test('MailSending listeners can cancel an order email', function() {
    Event::listen(MailSending::class, fn(MailSending $event) => $event->isValid = false);

    expect(app(Emails::class)->sendEmail($this->email, $this->order))->toBeTrue();

    Mail::assertNothingSent();
});

test('order emails fail without a recipient', function() {
    $this->email->setTo('');
    $error = '';

    expect(app(Emails::class)->sendEmail($this->email, $this->order, error: $error))->toBeFalse()
        ->and($error)->toContain('No email address found');

    Mail::assertNothingSent();
});

test('an order email keeps its own sender and reply-to over the site email settings', function(bool $hasOwnSender) {
    $settings = EmailSettings::fromProjectConfig()->resolveForSite(null);
    $mailable = new OrderEmail();

    if ($hasOwnSender) {
        $mailable->from('orders@example.com', 'Example Orders')->replyTo('support@example.com');
    }

    (fn() => $this->prepareMailableForDelivery())->call($mailable);

    if ($hasOwnSender) {
        expect($mailable->from)->toBe([['name' => 'Example Orders', 'address' => 'orders@example.com']])
            ->and($mailable->replyTo)->toBe([['name' => null, 'address' => 'support@example.com']]);
    } else {
        expect(array_column($mailable->from, 'address'))->toBe(array_filter([$settings->fromEmail]));
    }
})->with([
    'own sender' => [true],
    'site sender' => [false],
]);

test('order emails include a plain text body when the email has a plain text template', function() {
    $this->email->plainTextTemplatePath = 'emails/order-confirmation-plain';

    app(Emails::class)->sendEmail($this->email, $this->order);

    Mail::sent(OrderEmail::class)->first()->assertSeeInText('Order Confirmation ' . $this->order->shortNumber . ' (plain text)');
});
