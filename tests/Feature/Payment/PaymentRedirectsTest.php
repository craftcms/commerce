<?php

declare(strict_types=1);

use CraftCms\Aliases\Aliases;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Cms\View\TemplateRoots;
use CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface;
use CraftCms\Commerce\Payment\Payments;
use CraftCms\Commerce\Plugin;
use Symfony\Component\HttpFoundation\Response;

function redirectingGatewayResponse(string $method): RequestResponseInterface
{
    return new class($method) implements RequestResponseInterface {
        public function __construct(private string $method)
        {
        }

        public function isSuccessful(): bool
        {
            return false;
        }

        public function isProcessing(): bool
        {
            return false;
        }

        public function isRedirect(): bool
        {
            return true;
        }

        public function getRedirectMethod(): string
        {
            return $this->method;
        }

        public function getRedirectData(): array
        {
            return ['token' => 'abc"123', 'amount' => '10.00'];
        }

        public function getRedirectUrl(): string
        {
            return 'https://gateway.example.com/pay?x=1&y=2';
        }

        public function getTransactionReference(): string
        {
            return 'ref';
        }

        public function getCode(): string
        {
            return '';
        }

        public function getData(): mixed
        {
            return null;
        }

        public function getMessage(): string
        {
            return '';
        }
    };
}

/** @return array{0: Response|null, 1: string|null, 2: array|null} */
function handleGatewayRedirect(RequestResponseInterface $response): array
{
    $redirect = null;
    $redirectData = null;
    $redirectResponse = (function() use ($response, &$redirect, &$redirectData) {
        return $this->handleRedirect($response, $redirect, $redirectData);
    })->call(app(Payments::class));

    return [$redirectResponse, $redirect, $redirectData];
}

test('GET gateway redirects are returned as a redirect URL', function() {
    [$redirectResponse, $redirect, $redirectData] = handleGatewayRedirect(redirectingGatewayResponse('GET'));

    expect($redirectResponse)->toBeNull()
        ->and($redirect)->toBe('https://gateway.example.com/pay?x=1&y=2')
        ->and($redirectData)->toBe(['token' => 'abc"123', 'amount' => '10.00']);
});

test('POST gateway redirects return a page that submits the redirect data to the gateway', function() {
    [$redirectResponse, $redirect] = handleGatewayRedirect(redirectingGatewayResponse('POST'));

    expect($redirectResponse)->toBeInstanceOf(Response::class)
        ->and($redirect)->toBe('https://gateway.example.com/pay?x=1&y=2')
        ->and($redirectResponse->getContent())
        ->toContain('<form action="https://gateway.example.com/pay?x=1&amp;y=2" method="post">')
        ->toContain('<input type="hidden" name="token" value="abc&quot;123" />')
        ->toContain('<input type="hidden" name="amount" value="10.00" />')
        ->toContain('document.forms[0].submit()');
});

test('POST gateway redirects render the gatewayPostRedirectTemplate when it is set', function() {
    $templatesPath = dirname(__DIR__, 2) . '/Support/templates';
    Aliases::set('@templates', $templatesPath);
    app(TemplateRoots::class)->register(TemplateMode::Site, '', $templatesPath);
    Plugin::getInstance()->getSettings()->gatewayPostRedirectTemplate = '_commerce/post-redirect';

    [$redirectResponse] = handleGatewayRedirect(redirectingGatewayResponse('POST'));

    expect($redirectResponse->getContent())
        ->toContain('<form action="https://gateway.example.com/pay?x=1&amp;y=2" method="post" id="custom-redirect">')
        ->toContain('<input type="hidden" name="token" value="abc&quot;123" />');
});
