<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\gql\checkout\CheckoutSchema;
use craft\commerce\models\Checkout as CheckoutModel;
use craft\commerce\Plugin;
use craft\elements\User;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use yii\base\Component;

/**
 * Checkout service.
 *
 * Powers the hosted checkout. It issues short-lived tokens that are bound to a single cart (and, for signed-in
 * customers, to that customer), and executes GraphQL operations against a schema that can only touch that cart.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Checkout extends Component
{
    /**
     * @var int How long, in seconds, a checkout token remains valid for.
     */
    public int $tokenDuration = 7200;

    private ?Schema $_schema = null;

    /**
     * Returns a signed token that grants access to the checkout GraphQL API for the given cart.
     *
     * @param Order $cart
     * @param User|null $user The signed-in customer, if there is one. Grants access to their address book.
     * @param CheckoutModel|null $checkout The checkout configuration the customer is using
     * @return string
     */
    public function createToken(Order $cart, ?User $user = null, ?CheckoutModel $checkout = null): string
    {
        $payload = Json::encode([
            'n' => $cart->number,
            'u' => $user?->id,
            'c' => $checkout?->uid,
            'e' => time() + $this->tokenDuration,
        ]);

        return Craft::$app->getSecurity()->hashData(base64_encode($payload));
    }

    /**
     * Validates a token, and returns the cart number and user ID it is bound to.
     *
     * @param string $token
     * @return array{number: string, userId: int|null, checkoutUid: string|null}|null `null` if the token is invalid or expired.
     */
    public function parseToken(string $token): ?array
    {
        $data = Craft::$app->getSecurity()->validateData($token);

        if ($data === false) {
            return null;
        }

        $payload = Json::decodeIfJson(base64_decode($data, true) ?: '');

        if (!is_array($payload) || empty($payload['n']) || ($payload['e'] ?? 0) < time()) {
            return null;
        }

        return [
            'number' => (string)$payload['n'],
            'userId' => isset($payload['u']) ? (int)$payload['u'] : null,
            'checkoutUid' => $payload['c'] ?? null,
        ];
    }

    /**
     * Returns the URL of the hosted checkout.
     *
     * ```twig
     * <a href="{{ craft.commerce.checkout.url({ redirect: 'shop/thanks' }) }}">Checkout</a>
     * ```
     *
     * @param array $params Query string params, such as a `redirect` URL to send the customer to after payment.
     * @return string
     */
    public function getUrl(array $params = []): string
    {
        return UrlHelper::siteUrl('commerce/checkout', $params);
    }

    /**
     * Returns the URL of the checkout GraphQL endpoint.
     *
     * @return string
     */
    public function getGraphqlUrl(): string
    {
        return UrlHelper::actionUrl('commerce/checkout/graphql');
    }

    /**
     * Returns the checkout GraphQL schema.
     *
     * @return Schema
     */
    public function getSchema(): Schema
    {
        return $this->_schema ??= CheckoutSchema::create();
    }

    /**
     * Executes a GraphQL operation on behalf of the cart and user the token is bound to.
     *
     * @param string $token
     * @param string $query
     * @param array|null $variables
     * @param string|null $operationName
     * @return array The GraphQL result, ready to be sent as JSON.
     */
    public function execute(string $token, string $query, ?array $variables = null, ?string $operationName = null): array
    {
        $context = $this->parseToken($token);

        if ($context === null) {
            return ['errors' => [['message' => 'The checkout session has expired.', 'extensions' => ['code' => 'EXPIRED']]]];
        }

        $plugin = Plugin::getInstance();
        $context['user'] = $context['userId'] ? User::find()->id($context['userId'])->one() : null;
        $context['plugin'] = $plugin;

        $cart = $plugin->getOrders()->getOrderByNumber($context['number']);
        $context['checkout'] = $cart
            ? ($context['checkoutUid'] ? $plugin->getCheckouts()->getCheckoutByUid($context['checkoutUid']) : null)
                ?? $plugin->getCheckouts()->getCheckoutForStore($cart->getStore())
            : null;

        $debug = Craft::$app->getConfig()->getGeneral()->devMode ? DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE : DebugFlag::NONE;

        return GraphQL::executeQuery($this->getSchema(), $query, null, $context, $variables, $operationName)
            ->setErrorsHandler(function(array $errors, callable $formatter) {
                foreach ($errors as $error) {
                    // Client-safe errors are expected (a bad quantity, an expired token); anything else is a bug
                    if (!$error->isClientSafe()) {
                        Craft::error($error->getPrevious() ?? $error, __METHOD__);
                    }
                }

                return array_map($formatter, $errors);
            })
            ->toArray($debug);
    }
}
