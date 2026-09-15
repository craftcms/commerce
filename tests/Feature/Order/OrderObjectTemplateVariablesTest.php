<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\renderSandboxedObjectTemplate;

test('date filter on a datetime attribute does not throw when rendering an object template', function() {
    $order = new Order();
    $order->dateOrdered = new DateTime('2026-03-16 12:16:00');

    $fileName = renderSandboxedObjectTemplate(
        'Invoice-{{ dateOrdered|date(\'Y-m-d\') }}',
        $order,
        $order->getObjectTemplateVariables(),
    );

    expect($fileName)->toBe('Invoice-2026-03-16');
});
