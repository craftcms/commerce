<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Table as CmsTable;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\Exceptions\OrderBeingEditedException;
use CraftCms\Commerce\Order\Edit\Exceptions\StaleOrderException;
use CraftCms\Commerce\Order\Edit\OrderEditSessions;
use CraftCms\Commerce\Order\Edit\OrderOperations;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\DB;

function sessionsAdmin(string $username = 'editor'): User
{
    $user = new User();
    $user->username = $username;
    $user->email = "$username@crafttest.com";
    $user->admin = true;
    Elements::saveElement($user);

    return $user;
}

function sessionsOperation(array $data): CraftCms\Commerce\Order\Edit\OrderOperation
{
    return app(OrderOperations::class)->operationFromArray($data);
}

function sessionsFreshOrder(Order $order): Order
{
    return Order::find()->id($order->id)->status(null)->one();
}

function sessionsTouchOrder(Order $order): void
{
    DB::table(CmsTable::ELEMENTS)->where('id', $order->id)->update(['dateUpdated' => '2030-01-01 00:00:00']);
}

describe('starting', function() {
    it('starts an empty session based on the order’s current state', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $user = sessionsAdmin();

        $session = app(OrderEditSessions::class)->start($order, $user);

        expect($session->orderId)->toBe($order->id);
        expect($session->userId)->toBe($user->id);
        expect($session->hasChanges())->toBeFalse();
        expect($session->edit->baseDateUpdated->getTimestamp())->toBe($order->dateUpdated->getTimestamp());
    });

    it('returns the user’s existing session', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $user = sessionsAdmin();
        $sessions = app(OrderEditSessions::class);

        $first = $sessions->start($order, $user);
        $sessions->addOperation($first, sessionsFreshOrder($order), sessionsOperation(['type' => 'setReference', 'reference' => 'NEW-REF']));

        $again = $sessions->start($order, $user);

        expect($again->id)->toBe($first->id);
        expect($again->hasChanges())->toBeTrue();
    });

    it('refuses to start a session another user holds', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $sessions = app(OrderEditSessions::class);
        $sessions->start($order, sessionsAdmin('first'));

        $sessions->start($order, sessionsAdmin('second'));
    })->throws(OrderBeingEditedException::class);

    it('refuses to start a session for a cart', function() {
        $fixture = OrdersFixture::seed();
        $cart = new Order();
        $cart->number = bin2hex(random_bytes(16));
        $cart->storeId = $fixture->storeId;
        Elements::saveElement($cart, false);

        app(OrderEditSessions::class)->start($cart, sessionsAdmin());
    })->throws(InvalidArgumentException::class);

    it('lets another user take over, discarding the first user’s changes', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $sessions = app(OrderEditSessions::class);
        $first = $sessions->start($order, sessionsAdmin('first'));
        $sessions->addOperation($first, sessionsFreshOrder($order), sessionsOperation(['type' => 'setReference', 'reference' => 'NEW-REF']));
        $second = sessionsAdmin('second');

        $session = $sessions->takeOver($order, $second);

        expect($session->userId)->toBe($second->id);
        expect($session->hasChanges())->toBeFalse();
        expect(DB::table(Table::ORDER_EDIT_SESSIONS)->where('orderId', $order->id)->count())->toBe(1);
    });
});

describe('changing', function() {
    it('lists a repeated change to the same thing once, with the latest value', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $sessions = app(OrderEditSessions::class);
        $session = $sessions->start($order, sessionsAdmin());

        foreach (['methodA', null, 'methodB'] as $handle) {
            $session = $sessions->addOperation($session, sessionsFreshOrder($order), sessionsOperation(['type' => 'setShippingMethod', 'shippingMethodHandle' => $handle, 'shippingMethodName' => $handle]));
        }
        $session = $sessions->addOperation($session, sessionsFreshOrder($order), sessionsOperation(['type' => 'setReference', 'reference' => 'A']));
        $session = $sessions->addOperation($session, sessionsFreshOrder($order), sessionsOperation(['type' => 'setReference', 'reference' => 'B']));

        $operations = $sessions->getSessionByOrderId($order->id)->edit->operations;

        expect(collect($operations)->map(fn($operation) => $operation::type())->all())->toBe(['setShippingMethod', 'setReference']);
        expect($operations[0]->shippingMethodHandle)->toBe('methodB');
        expect($operations[1]->reference)->toBe('B');
    });

    it('merges changes to the same line item', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $uid = $order->getLineItems()[0]->uid;
        $sessions = app(OrderEditSessions::class);
        $session = $sessions->start($order, sessionsAdmin());

        $session = $sessions->addOperation($session, sessionsFreshOrder($order), sessionsOperation(['type' => 'updateLineItem', 'lineItemUid' => $uid, 'changes' => ['qty' => 3, 'price' => 10]]));
        $sessions->addOperation($session, sessionsFreshOrder($order), sessionsOperation(['type' => 'updateLineItem', 'lineItemUid' => $uid, 'changes' => ['qty' => 4]]));

        $operations = $sessions->getSessionByOrderId($order->id)->edit->operations;

        expect($operations)->toHaveCount(1);
        expect($operations[0]->changes)->toEqual(['qty' => 4, 'price' => 10]);
    });

    it('keeps the custom field values of earlier edits that a later one leaves out', function() {
        $earlier = sessionsOperation(['type' => 'setFieldValues', 'values' => ['notes' => 'First', 'priority' => 'high']]);
        $later = sessionsOperation(['type' => 'setFieldValues', 'values' => ['notes' => 'Second']]);

        expect($later->absorb($earlier)->values)->toBe(['notes' => 'Second', 'priority' => 'high']);
    });

    it('drops a change that puts the order back as it was', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $sessions = app(OrderEditSessions::class);
        $session = $sessions->start($order, sessionsAdmin());

        $session = $sessions->addOperation($session, sessionsFreshOrder($order), sessionsOperation(['type' => 'setReference', 'reference' => 'TEMP']));
        $session = $sessions->addOperation($session, sessionsFreshOrder($order), sessionsOperation(['type' => 'setReference', 'reference' => $order->reference]));

        expect($session->hasChanges())->toBeFalse();
    });

    it('adds and removes operations, keeping the order unsaved', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $sessions = app(OrderEditSessions::class);
        $session = $sessions->start($order, sessionsAdmin());

        $session = $sessions->addOperation($session, sessionsFreshOrder($order), sessionsOperation(['type' => 'setReference', 'reference' => 'NEW-REF']));
        $session = $sessions->addOperation($session, sessionsFreshOrder($order), sessionsOperation([
            'type' => 'addAdjustment', 'adjustmentType' => 'discount', 'name' => 'Goodwill', 'amount' => -5,
        ]));

        expect(collect($sessions->getSessionByOrderId($order->id)->edit->operations)->map(fn($operation) => $operation::type())->all())
            ->toBe(['setReference', 'addAdjustment']);

        $session = $sessions->removeOperation($session, sessionsFreshOrder($order), 0);

        expect(collect($session->edit->operations)->map(fn($operation) => $operation::type())->all())->toBe(['addAdjustment']);
        expect(sessionsFreshOrder($order)->reference)->toBe($order->reference);
        expect($sessions->orderHasChanges($order->id))->toBeTrue();
    });

    it('rejects an operation that doesn’t apply, leaving the session as it was', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $sessions = app(OrderEditSessions::class);
        $session = $sessions->start($order, sessionsAdmin());

        try {
            $sessions->addOperation($session, sessionsFreshOrder($order), sessionsOperation(['type' => 'removeLineItem', 'lineItemUid' => 'missing']));
        } catch (OperationNotApplicableException $exception) {
            expect($exception->operationIndex)->toBe(0);
            expect($sessions->getSessionByOrderId($order->id)->hasChanges())->toBeFalse();

            return;
        }

        test()->fail('Expected the operation to be rejected.');
    });

    it('previews an operation without adding it', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $sessions = app(OrderEditSessions::class);
        $session = $sessions->start($order, sessionsAdmin());
        $preview = sessionsFreshOrder($order);

        $sessions->preview($session, $preview, sessionsOperation(['type' => 'setReference', 'reference' => 'MAYBE']));

        expect($preview->reference)->toBe('MAYBE');
        expect($sessions->getSessionByOrderId($order->id)->hasChanges())->toBeFalse();
    });
});

describe('committing', function() {
    it('saves the edit and ends the session', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $sessions = app(OrderEditSessions::class);
        $session = $sessions->start($order, sessionsAdmin());
        $session = $sessions->addOperation($session, sessionsFreshOrder($order), sessionsOperation(['type' => 'setReference', 'reference' => 'NEW-REF']));

        expect($sessions->commit($session, sessionsFreshOrder($order)))->toBeTrue();
        expect(sessionsFreshOrder($order)->reference)->toBe('NEW-REF');
        expect($sessions->getSessionByOrderId($order->id))->toBeNull();
    });

    it('moves the session on to the order’s new state when the order changed, for the user to review', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $sessions = app(OrderEditSessions::class);
        $session = $sessions->start($order, sessionsAdmin());
        $session = $sessions->addOperation($session, sessionsFreshOrder($order), sessionsOperation(['type' => 'setReference', 'reference' => 'NEW-REF']));
        sessionsTouchOrder($order);
        $changed = sessionsFreshOrder($order);

        try {
            $sessions->commit($session, $changed);
            test()->fail('Expected the commit to be rejected.');
        } catch (StaleOrderException) {
        }

        expect($changed->reference)->toBe('NEW-REF');
        expect(sessionsFreshOrder($order)->reference)->toBe($order->reference);

        $rebased = $sessions->getSessionByOrderId($order->id);
        expect($rebased->edit->baseDateUpdated->getTimestamp())->toBe($changed->dateUpdated->getTimestamp());

        expect($sessions->commit($rebased, sessionsFreshOrder($order)))->toBeTrue();
        expect(sessionsFreshOrder($order)->reference)->toBe('NEW-REF');
    });

    it('flags the operation that no longer applies when the order changed', function() {
        $order = OrdersFixture::seed()->orders['completed-new'];
        $lineItem = $order->getLineItems()[0];
        $sessions = app(OrderEditSessions::class);
        $session = $sessions->start($order, sessionsAdmin());
        $session = $sessions->addOperation($session, sessionsFreshOrder($order), sessionsOperation(['type' => 'setReference', 'reference' => 'NEW-REF']));
        $session = $sessions->addOperation($session, sessionsFreshOrder($order), sessionsOperation(['type' => 'setLineItemNotes', 'lineItemUid' => $lineItem->uid, 'note' => 'Gift', 'privateNote' => '']));
        DB::table(Table::LINEITEMS)->where('uid', $lineItem->uid)->delete();
        sessionsTouchOrder($order);

        try {
            $sessions->commit($session, sessionsFreshOrder($order));
        } catch (OperationNotApplicableException $exception) {
            expect($exception->operationIndex)->toBe(1);
            expect($sessions->getSessionByOrderId($order->id)->edit->baseDateUpdated->getTimestamp())
                ->toBe($session->edit->baseDateUpdated->getTimestamp());

            return;
        }

        test()->fail('Expected the commit to be rejected.');
    });
});

it('purges sessions untouched for 30 days', function() {
    $fixture = OrdersFixture::seed();
    $sessions = app(OrderEditSessions::class);
    $old = $sessions->start($fixture->orders['completed-new'], sessionsAdmin('first'));
    $recent = $sessions->start($fixture->orders['completed-shipped'], sessionsAdmin('second'));
    DB::table(Table::ORDER_EDIT_SESSIONS)->where('id', $old->id)->update(['dateUpdated' => now()->subDays(31)]);

    expect($sessions->purgeStaleSessions())->toBe(1);
    expect($sessions->getSessionByOrderId($old->orderId))->toBeNull();
    expect($sessions->getSessionByOrderId($recent->orderId))->not->toBeNull();
});
