<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\filters;


use Craft;
use yii\base\Action;
use yii\base\BaseObject;
use yii\filters\RateLimitInterface;

/**
 * Cart Number based rate limit filter.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.11.0
 */
class CartNumberRateLimit extends BaseObject implements RateLimitInterface
{
    /**
     * @var int Maximum number of allowed requests within the specified window.
     */
    public int $limit;

    /**
     * @var int The size of the window in seconds for which the limit applies.
     */
    public int $window;

    /**
     * @var string The IP address to rate limit
     */
    public string $ip;

    /**
     * @inerhitdoc
     */
    public function getRateLimit($request, $action): array
    {
        return [$this->limit, $this->window];
    }

    /**
     * @inerhitdoc
     */
    public function loadAllowance($request, $action): array
    {
        $key = $this->getCacheKey($action);
        $data = Craft::$app->getCache()->get($key);

        return $data !== false ? $data : [$this->limit, time()];
    }

    /**
     * @inerhitdoc
     */
    public function saveAllowance($request, $action, $allowance, $timestamp): void
    {
        $key = $this->getCacheKey($action);
        Craft::$app->getCache()->set($key, [$allowance, $timestamp], $this->window);
    }

    /**
     * @param Action $action
     * @return string
     */
    private function getCacheKey(Action $action): string
    {
        return sprintf('cart-number-rate-limit:%s:%s', $action->getUniqueId(), $this->ip);
    }
}
