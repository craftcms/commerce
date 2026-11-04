<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Heading;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Ui\UiResolver;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Helpers\Locale;
use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Order\Edit\OrderEditGuard;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Exceptions\FulfillmentException;
use CraftCms\Commerce\Order\OrderFulfillment;
use CraftCms\Commerce\Order\Orders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * The actions on the order edit screen that save straight away and aren't edits to the order
 * itself: sending an email, fulfilling stock and sharing a cart.
 *
 * A completed order that has an edit session with changes waiting refuses all of them. See
 * {@see OrderEditGuard}.
 *
 * @since 6.0.0
 */
class OrderActionsController
{
    use RespondsWithFlash;

    public function __construct(
        private readonly OrderEditGuard $guard,
        private readonly OrderFulfillment $fulfillment,
        private readonly UiResolver $uiResolver,
    ) {
    }

    /**
     * The UI of the form that picks an email to send, for `openUiModal()`.
     */
    public function emailForm(Request $request): JsonResponse
    {
        $order = $this->order($request, completed: true);

        $options = app(Emails::class)->getAllEnabledEmails($order->storeId)
            ->map(fn($email) => ['label' => $email->name, 'value' => (string)$email->id])
            ->values()
            ->all();

        abort_if($options === [], 422, t('No emails are enabled for this store.', category: 'commerce'));

        $ui = Ui::make([
            Field::make(t('Email', category: 'commerce'), Choice::make('emailId')->options($options)->withoutPlaceholder())->required(),
        ]);

        return new JsonResponse([
            'ui' => $this->uiResolver->resolve($ui, new UiContext(values: ['emailId' => $options[0]['value']])),
            'title' => t('Send Email', category: 'commerce'),
            'submitLabel' => t('Send', category: 'commerce'),
        ]);
    }

    /**
     * Sends one of the store's emails for the order.
     */
    public function sendEmail(Request $request): JsonResponse
    {
        $order = $this->order($request, completed: true);
        $data = $request->validate(['emailId' => ['required', 'integer']]);

        $email = app(Emails::class)->getEmailById((int)$data['emailId'], $order->storeId);

        if (!$email || !$email->enabled) {
            return new JsonResponse([
                'message' => t('Can not find enabled email.', category: 'commerce'),
                'errors' => ['emailId' => [t('Can not find enabled email.', category: 'commerce')]],
            ], 422);
        }

        $originalLanguage = \Craft::$app->language;
        $originalFormattingLocale = \Craft::$app->formattingLocale;
        Locale::switchAppLanguage($email->getRenderLanguage($order));

        $error = '';

        try {
            $sent = app(Emails::class)->sendEmail($email, $order, null, $order->toArray(), $error);
        } catch (Throwable $exception) {
            report($exception);
            $sent = false;
        } finally {
            Locale::switchAppLanguage($originalLanguage, $originalFormattingLocale->id);
        }

        if (!$sent) {
            return new JsonResponse(['message' => $error ?: t('Could not send email', category: 'commerce')], 422);
        }

        return $this->asJsonSuccess(t('Email sent.', category: 'commerce'));
    }

    /**
     * The UI of the fulfilment form, for `openUiModal()`: a quantity for each line item's stock at each
     * location. A negative quantity returns fulfilled stock to committed.
     */
    public function fulfillmentForm(Request $request): JsonResponse
    {
        $order = $this->order($request, completed: true);
        $levels = $this->fulfillment->levels($order);

        abort_if($levels->isEmpty(), 422, t('This order has no committed stock to fulfil.', category: 'commerce'));

        $nodes = [];
        $values = [];

        foreach ($levels->groupBy('inventoryLocationId') as $locationId => $locationLevels) {
            $nodes[] = Heading::make("location-$locationId", $locationLevels->first()->getInventoryLocation()->name)->level(3);

            foreach ($locationLevels as $level) {
                $key = OrderFulfillment::levelKey($level);

                $nodes[] = Field::make(
                    $level->getLineItem()->getDescription(),
                    Text::make(['fulfillment', $key])
                        ->inputType('number')
                        ->min(-$level->fulfilledQuantity)
                        ->max(max($level->outstandingCommittedQuantity, 0))
                        ->step(1)
                        ->size(6),
                )->instructions(t('{fulfilled} of {committed} fulfilled', [
                    'fulfilled' => $level->fulfilledQuantity,
                    'committed' => $level->committedQuantity,
                ], category: 'commerce'));

                $values['fulfillment'][$key] = 0;
            }
        }

        return new JsonResponse([
            'ui' => $this->uiResolver->resolve(Ui::make($nodes), new UiContext(values: $values)),
            'title' => t('Fulfil', category: 'commerce'),
            'submitLabel' => t('Update', category: 'commerce'),
        ]);
    }

    /**
     * Moves committed stock to fulfilled, by the quantities the fulfilment form submitted.
     */
    public function fulfill(Request $request): JsonResponse
    {
        $order = $this->order($request, completed: true);
        $quantities = $request->input('fulfillment', []);
        $fulfillments = [];

        foreach ($this->fulfillment->levels($order) as $level) {
            $quantity = (int)($quantities[OrderFulfillment::levelKey($level)] ?? 0);

            if ($quantity !== 0) {
                $fulfillments[] = [
                    'inventoryLocationId' => $level->inventoryLocationId,
                    'inventoryItemId' => $level->inventoryItemId,
                    'lineItemId' => $level->lineItemId,
                    'quantity' => $quantity,
                ];
            }
        }

        try {
            $this->fulfillment->fulfill($fulfillments, $order);
        } catch (FulfillmentException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        }

        return $this->asJsonSuccess(t('Updated committed stock successfully.', category: 'commerce'));
    }

    /**
     * The link that loads a cart for its customer.
     */
    public function shareCart(Request $request): JsonResponse
    {
        $cart = $this->order($request, completed: false);

        return new JsonResponse(['url' => app(Carts::class)->getLoadCartUrl($cart)]);
    }

    private function order(Request $request, bool $completed): Order
    {
        $request->validate(['orderId' => ['required', 'integer']]);

        $order = app(Orders::class)->getOrderById((int)$request->input('orderId'));
        abort_if(!$order, 404, t('Can not find order.', category: 'commerce'));
        abort_unless($order->isCompleted === $completed, 400, $completed
            ? t('Carts can’t be changed this way.', category: 'commerce')
            : t('The order is already completed.', category: 'commerce'));

        $user = $this->user();
        abort_unless($order->canView($user), 403, 'User not authorized to view this order.');

        $reason = $this->guard->blockReason($order, $user->id);
        abort_if($reason !== null, 423, (string)$reason);

        return $order;
    }

    private function user(): User
    {
        $user = currentUserElement();
        abort_if(!$user, 403);

        return $user;
    }
}
