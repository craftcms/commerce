<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\User\Elements\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

/**
 * Finds and creates the customers an order can be given, for the order edit screen's customer field.
 *
 * @since 6.0.0
 */
class OrderCustomersController
{
    private const int MIN_QUERY_LENGTH = 2;

    private const int LIMIT = 10;

    /**
     * Searches the users the current user may view, with the user search index so anything a developer
     * adds to it can be searched.
     */
    public function search(Request $request): JsonResponse
    {
        $viewer = $this->viewer();
        $query = trim((string)$request->query('query', ''));
        $canCreate = Gate::forUser($viewer)->allows('save', new User());

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return new JsonResponse(['customers' => [], 'canCreate' => $canCreate]);
        }

        $customers = User::find()
            ->status(null)
            ->search($this->searchTerms($query))
            ->limit(self::LIMIT * 3)
            ->collect()
            ->filter(fn(User $user) => Gate::forUser($viewer)->allows('view', $user))
            ->take(self::LIMIT)
            ->map(fn(User $user) => $this->customerData($user))
            ->values()
            ->all();

        return new JsonResponse(['customers' => $customers, 'canCreate' => $canCreate]);
    }

    /**
     * Gets the customer with an email address, creating one if there isn't one.
     */
    public function create(Request $request): JsonResponse
    {
        abort_unless(Gate::forUser($this->viewer())->allows('save', new User()), 403, t('You can’t create users.', category: 'commerce'));

        $data = $request->validate(['email' => ['required', 'email']]);

        try {
            $user = Users::ensureUserByEmail($data['email']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['email' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return new JsonResponse(['message' => $exception->getMessage()], 422);
        }

        return new JsonResponse([
            'message' => t('Customer created.', category: 'commerce'),
            'customer' => $this->customerData($user),
        ]);
    }

    /**
     * Matches the start of each word, so results appear as someone types, unless the query already uses
     * the search syntax.
     */
    private function searchTerms(string $query): string
    {
        if (preg_match('/[*:"]/', $query)) {
            return $query;
        }

        return implode(' ', array_map(fn(string $word) => $word . '*', preg_split('/\s+/', $query) ?: []));
    }

    /**
     * @return array{id: int, name: string|null, email: string|null}
     */
    private function customerData(User $user): array
    {
        return [
            'id' => (int)$user->id,
            'name' => $user->getFriendlyName(),
            'email' => $user->email,
        ];
    }

    private function viewer(): User
    {
        return currentUserElement() ?? abort(403);
    }
}
