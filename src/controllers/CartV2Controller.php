<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\base\Element;
use craft\commerce\elements\Order;
use craft\commerce\models\cart\AddPurchasablesToCartForm;
use craft\commerce\models\cart\AddPurchasableToCartForm;
use craft\commerce\models\cart\UpdateEmailForm;
use craft\commerce\models\cart\UpdateLineItemsForm;
use craft\commerce\Plugin;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\Response;

/**
 * Class Cart Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CartV2Controller extends BaseFrontEndController
{
    /**
     * @var Order The cart element
     */
    protected Order $_cart;

    /**
     * @var string the name of the cart variable
     */
    protected string $_cartVariable;

    /**
     * @var User|null
     */
    protected ?User $_currentUser = null;

    /**
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        $this->_cartVariable = Plugin::getInstance()->getSettings()->cartVariable;
        $this->_currentUser = Craft::$app->getUser()->getIdentity();

        parent::init();
    }

    /**
     * @return Response|null
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function actionUpdateEmail(): ?Response
    {
        /** @var UpdateEmailForm $updateEmailForm */
        $updateEmailForm = Craft::createObject([
            'class' => UpdateEmailForm::class,
        ]);

        if (!$updateEmailForm->load($this->request->post()) || !Plugin::getInstance()->getCarts()->updateCartFromForm($updateEmailForm)) {
            return $this->asFailure(
                Craft::t('commerce', 'Error updating email.'),
                compact('updateEmailForm'),
                compact('updateEmailForm'),
            );
        }

        return $this->_returnCart(
            cart: $updateEmailForm->getOrder(),
            successMessage: $updateEmailForm->getSuccessMessage(),
            failMessage: $updateEmailForm->getFailMessage()
        );
    }

    public function actionAddToCart(): ?Response
    {
        $cart = Plugin::getInstance()->getCarts()->getCart();

        $postKeys = array_keys($this->request->post());

        /** @var AddPurchasablesToCartForm $addPurchasablesToCartForm */
        $addPurchasablesToCartForm = Craft::createObject([
            'class' => AddPurchasablesToCartForm::class,
        ]);
        /** @var AddPurchasableToCartForm $addPurchasableToCartForm */
        $addPurchasableToCartForm = Craft::createObject([
            'class' => AddPurchasableToCartForm::class,
        ]);

        $forms = [
            $addPurchasablesToCartForm,
            $addPurchasableToCartForm,
        ];

        foreach ($forms as $form) {
            if (in_array($form->formName(), $postKeys, true)) {
                $form->setOrder($cart);
                if (!$form->load($this->request->post()) || !Plugin::getInstance()->getCarts()->updateCartFromForm($form)) {
                    return $this->asFailure(
                        Craft::t('commerce', 'Error adding to cart.'),
                        [$form->formName() => $form],
                        [$form->formName() => $form],
                    );
                }

                $cart = $form->getCart();
            }
        }

        // TODO figure out a multiple form return
        return $this->_returnCart(
            cart: $cart,
            successMessage: $addPurchasablesToCartForm->getSuccessMessage(),
            failMessage: $addPurchasablesToCartForm->getFailMessage()
        );
    }

    public function actionUpdateLineItems(): ?Response
    {
        $updateLineItemsForm = Craft::createObject([
            'class' => UpdateLineItemsForm::class,
        ]);

        if (!$updateLineItemsForm->load($this->request->post()) || !Plugin::getInstance()->getCarts()->updateCartFromForm($updateLineItemsForm)) {
            return $this->asFailure(
                Craft::t('commerce', 'Error updating line items.'),
                compact('updateLineItemsForm'),
                compact('updateLineItemsForm'),
            );
        }

        return $this->_returnCart(
            cart: $updateLineItemsForm->getOrder(),
            successMessage: $updateLineItemsForm->getSuccessMessage(),
            failMessage: $updateLineItemsForm->getFailMessage()
        );
    }

    /**
     * @param Order $cart
     * @param string $successMessage
     * @param string $failMessage
     * @return Response
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    private function _returnCart(Order $cart, string $successMessage, string $failMessage): Response
    {
        // TODO what to do with validate custom fields

        $updateCartSearchIndexes = Plugin::getInstance()->getSettings()->updateCartSearchIndexes;

        // Do not clear errors, as errors could be added to the cart before _returnCart is called.
        if (!$cart->validate($cart->activeAttributes(), false) || !Craft::$app->getElements()->saveElement($cart, false, false, $updateCartSearchIndexes)) {
            return $this->asModelFailure(
                $cart,
                $failMessage,
                'cart',
                [
                    $this->_cartVariable => $this->cartArray($cart),
                ],
                [
                    $this->_cartVariable => $cart,
                ]
            );
        }

        return $this->asModelSuccess(
            $cart,
            $successMessage,
            'cart',
            [
                $this->_cartVariable => $this->cartArray($cart),
            ]
        );
    }
}
