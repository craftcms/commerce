<?php

declare(strict_types=1);

use craft\commerce\events\EmailEvent;
use craft\commerce\events\PdfEvent;
use craft\commerce\events\PdfRenderEvent;
use craft\commerce\events\PdfRenderOptionsEvent;
use craft\commerce\services\Emails as LegacyEmails;
use craft\commerce\services\Pdfs as LegacyPdfs;
use CraftCms\Commerce\Email\Data\Email;
use CraftCms\Commerce\Email\Events\EmailSaved;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Pdf\Data\Pdf;
use CraftCms\Commerce\Pdf\Events\PdfRendering;
use CraftCms\Commerce\Pdf\Events\PdfRenderOptionsResolving;
use CraftCms\Commerce\Pdf\Events\PdfSaved;
use Dompdf\Options;
use yii\base\Event as YiiEvent;

afterEach(function() {
    YiiEvent::offAll();
});

test('legacy listeners receive email and PDF saves', function() {
    $email = new Email();
    $pdf = new Pdf();
    $received = [];

    YiiEvent::on(LegacyEmails::class, LegacyEmails::EVENT_AFTER_SAVE_EMAIL, function(EmailEvent $event) use (&$received) {
        $received[] = [$event->email, $event->isNew];
    });
    YiiEvent::on(LegacyPdfs::class, LegacyPdfs::EVENT_AFTER_SAVE_PDF, function(PdfEvent $event) use (&$received) {
        $received[] = [$event->pdf, $event->isNew];
    });

    event(new EmailSaved($email, true));
    event(new PdfSaved($pdf, false));

    expect($received)->toBe([[$email, true], [$pdf, false]]);
});

test('legacy listeners can change what a PDF renders', function() {
    YiiEvent::on(LegacyPdfs::class, LegacyPdfs::EVENT_BEFORE_RENDER_PDF, function(PdfRenderEvent $event) {
        $event->template = 'custom/pdf';
        $event->variables['extra'] = true;
        $event->pdf = 'rendered';
    });

    event($event = new PdfRendering(new Order(), 'receipt', 'shop/pdf', []));

    expect($event->template)->toBe('custom/pdf')
        ->and($event->variables)->toBe(['extra' => true])
        ->and($event->pdf)->toBe('rendered');
});

test('legacy listeners can change the PDF render options', function() {
    $options = new Options();

    YiiEvent::on(LegacyPdfs::class, LegacyPdfs::EVENT_MODIFY_RENDER_OPTIONS, function(PdfRenderOptionsEvent $event) use ($options) {
        $event->options = $options;
    });

    event($event = new PdfRenderOptionsResolving(new Options()));

    expect($event->options)->toBe($options);
});
