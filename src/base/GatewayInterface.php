<?php

namespace craft\commerce\base;

use craft\base\SavableComponentInterface;
use craft\commerce\elements\Order;
use Omnipay\Common\Message\RequestInterface;

/**
 * GatewayInterface defines the common interface to be implemented by gateway classes.
 *
 * A class implementing this interface should also use [[SavableComponentTrait]] and [[GatewayTrait]].
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
interface GatewayInterface extends SavableComponentInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Create an item bag based on the order.
     *
     * @param Order $order
     *
     * @return ItemBag|null
     */
    public function createItemBag(Order $order);

    /**
     * Create a purchase request.
     *
     * @param array $parameters Request parameters
     *
     * @return RequestInterface
     */
    public function purchase(array $parameters): RequestInterface;

    /**
     * Create an authorize request.
     *
     * @param array $parameters Request parameters
     *
     * @return RequestInterface
     */
    public function authorize(array $parameters): RequestInterface;

    /**
     * Create a refund request.
     *
     * @param array $parameters Request parameters
     *
     * @return RequestInterface
     */
    public function refund(array $parameters): RequestInterface;

    /**
     * Create a capture request.
     *
     * @param array $parameters Request parameters
     *
     * @return RequestInterface
     */
    public function capture(array $parameters): RequestInterface;

    /**
     * Return true if gateway supports purchase requests.
     *
     * @return bool
     */
    public function supportsPurchase(): bool;

    /**
     * Return true if gateway supports authorize requests.
     *
     * @return bool
     */
    public function supportsAuthorize(): bool;

    /**
     * Return true if gateway supports refund requests.
     *
     * @return bool
     */
    public function supportsRefund(): bool;

    /**
     * Return true if gateway supports capture requests.
     *
     * @return bool
     */
    public function supportsCapture(): bool;
}
