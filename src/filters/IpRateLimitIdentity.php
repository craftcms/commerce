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
 * IP-based rate limit identity for use with RateLimiter.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.11.2
 */
class IpRateLimitIdentity extends BaseObject implements RateLimitInterface
{
    public int $limit;
    public int $window;
    public string $keyPrefix;
    public string $ip;

    /**
     * @inheritdoc
     */
    public function getRateLimit($request, $action): array
    {
        return [$this->limit, $this->window];
    }

    /**
     * @inheritdoc
     */
    public function loadAllowance($request, $action): array
    {
        $key = $this->getCacheKey($action);
        $data = Craft::$app->getCache()->get($key);
        return $data !== false ? $data : [$this->limit, time()];
    }

    /**
     * @inheritdoc
     */
    public function saveAllowance($request, $action, $allowance, $timestamp): void
    {
        $key = $this->getCacheKey($action);
        Craft::$app->getCache()->set($key, [$allowance, $timestamp], $this->window);
    }

    private function getCacheKey(Action $action): string
    {
        return sprintf('%s:%s:%s', $this->keyPrefix, $action->getUniqueId(), $this->ip);
    }
}
