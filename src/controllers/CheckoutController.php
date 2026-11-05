<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\Plugin;
use craft\commerce\web\assets\checkout\CheckoutAsset;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\web\View;
use yii\web\Response;

/**
 * Class Checkout Controller
 *
 * Serves the hosted checkout, and the GraphQL endpoint it talks to.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class CheckoutController extends BaseFrontEndController
{
    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = ['index', 'graphql'];

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // The GraphQL endpoint authenticates with its own token rather than the session
        if ($action->id === 'graphql') {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    /**
     * Renders the hosted checkout for the current cart.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $plugin = Plugin::getInstance();
        $cart = $plugin->getCarts()->getCart();
        $user = Craft::$app->getUser()->getIdentity();

        // A link can name the checkout to use; otherwise it’s the store’s default
        $checkout = $plugin->getCheckouts()->getCheckoutForStore($cart->getStore(), $this->request->getParam('checkout'));

        // Where to send the customer after paying: the checkout’s return URL wins, then the link’s own `redirect`
        $returnUrl = $checkout->getReturnUrlForOrder($cart);
        if (!$returnUrl && ($redirect = $this->request->getParam('redirect'))) {
            $returnUrl = $this->getValidatedReturnUrl($redirect);
            if ($returnUrl) {
                // Land on the order, like the example templates’ own checkout does
                $returnUrl = UrlHelper::urlWithParams($returnUrl, ['number' => $cart->number, 'success' => 'true']);
            }
        }

        // Remember which checkout this cart is being paid through, so its rules apply when the payment is made
        Craft::$app->getSession()->set('commerce:checkout:' . $cart->number, $checkout->uid);

        $config = [
            'graphqlUrl' => $plugin->getCheckout()->getGraphqlUrl(),
            'token' => $plugin->getCheckout()->createToken($cart, $user, $checkout),
            'payUrl' => UrlHelper::actionUrl('commerce/payments/pay'),
            'completePaymentUrl' => UrlHelper::actionUrl('commerce/payments/complete-payment'),
            'csrf' => [
                'name' => $this->request->csrfParam,
                'token' => $this->request->getCsrfToken(),
            ],
            'cartNumber' => $cart->number,
            'isGuest' => $user === null,
            'siteName' => Craft::$app->getSites()->getCurrentSite()->getName(),
            'homeUrl' => $checkout->getContinueShoppingUrlForSite(),
            'returnUrl' => $returnUrl,
            'returnUrlHash' => $returnUrl ? Craft::$app->getSecurity()->hashData($returnUrl) : null,
            'loginUrl' => Craft::$app->getUser()->loginUrl,
            'theme' => [
                'brandColor' => $checkout->brandColor,
                'colorScheme' => $checkout->colorScheme,
                'cornerStyle' => $checkout->cornerStyle,
                'logoUrl' => $checkout->logoUrl,
            ],
        ];

        $bundle = $this->getView()->registerAssetBundle(CheckoutAsset::class);

        return $this->renderTemplate('commerce/_checkout/index', [
            'config' => Json::htmlEncode($config),
            'siteName' => $config['siteName'],
            'jsUrl' => $bundle->baseUrl . '/checkout.js',
            'cssUrl' => $bundle->baseUrl . '/checkout.css',
        ], View::TEMPLATE_MODE_CP);
    }

    /**
     * Executes a checkout GraphQL operation.
     *
     * @return Response
     */
    public function actionGraphql(): Response
    {
        $this->requirePostRequest();

        $token = $this->request->getBearerToken() ?? '';
        $body = $this->request->getBodyParams();
        $query = (string)($body['query'] ?? '');
        $variables = isset($body['variables']) && is_array($body['variables']) ? $body['variables'] : null;
        $operationName = isset($body['operationName']) && is_string($body['operationName']) ? $body['operationName'] : null;

        $result = Plugin::getInstance()->getCheckout()->execute($token, $query, $variables, $operationName);

        $this->response->getHeaders()->set('Cache-Control', 'no-store');

        return $this->asJson($result);
    }

    /**
     * Only allows return URLs on the current host, so the checkout can’t be used as an open redirect.
     */
    private function getValidatedReturnUrl(string $url): ?string
    {
        if (!UrlHelper::isFullUrl($url)) {
            return UrlHelper::siteUrl($url);
        }

        return UrlHelper::hostInfo($url) === $this->request->getHostInfo() ? $url : null;
    }
}
