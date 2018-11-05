# Subscription Queries

You can fetch subscriptions in your templates or PHP code using **subscription queries**.

::: code
```twig
{# Create a new subscription query #}
{% set mySubscriptionQuery = craft.subscriptions() %}
```
```php
// Create a new subscription query
$mySubscriptionQuery = \craft\commerce\elements\Subscription::find();
```
:::

Once you’ve created a subscription query, you can set [parameters](#parameters) on it to narrow down the results, and then [execute it](https://docs.craftcms.com/v3/dev/element-queries/#executing-element-queries) by calling `.all()`. An array of [Subscription](api:craft\commerce\elements\Subscription) objects will be returned.

::: tip
See [Introduction to Element Queries](https://docs.craftcms.com/v3/dev/element-queries/) in the Craft docs to learn about how element queries work.
:::

## Example

We can display all of the current user’s subscriptions by doing the following:

1. Create a subscription query with `craft.subscriptions()`.
2. Set the [user](#user) parameter on it.
3. Fetch the subscriptions with `.all()`.
4. Loop through the subscriptions using a [for](https://twig.symfony.com/doc/2.x/tags/for.html) tag to output their HTML.

```twig
{# Make sure someone is logged in #}
{% requireLogin %}

{# Create a subscription query with the 'user' parameter #}
{% set mySubscriptionQuery = craft.subscriptions()
    .user(currentUser) %}

{# Fetch the subscriptions #}
{% set subscriptions = mySubscriptionQuery.all() %}

{# Display the subscriptions #}
{% for subscription in subscriptions %}
    <article>
        <h1><a href="{{ subscription.url }}">{{ subscription.title }}</a></h1>
        {{ subscription.summary }}
        <a href="{{ subscription.url }}">Learn more</a>
    </article>
{% endfor %}
```

## Parameters

Subscription queries support the following parameters:

<!-- BEGIN PARAMS -->

### `anyStatus`

Clears out the [status](#status) and [enabledForSite()](https://docs.craftcms.com/api/v3/craft-elements-db-elementquery.html#method-enabledforsite) parameters.





::: code
```twig
{# Fetch all subscriptions, regardless of status #}
{% set subscriptions = craft.subscriptions()
    .anyStatus()
    .all() %}
```

```php
// Fetch all subscriptions, regardless of status
$subscriptions = \craft\commerce\elements\Subscription::find()
    ->anyStatus()
    ->all();
```
:::


### `asArray`

Causes the query to return matching subscriptions as arrays of data, rather than `\craft\commerce\elements\Subscription` objects.





::: code
```twig
{# Fetch subscriptions as arrays #}
{% set subscriptions = craft.subscriptions()
    .asArray()
    .all() %}
```

```php
// Fetch subscriptions as arrays
$subscriptions = \craft\commerce\elements\Subscription::find()
    ->asArray()
    ->all();
```
:::


### `dateCanceled`

Narrows the query results based on the subscriptions’ cancellation date.

Possible values include:

| Value | Fetches subscriptions…
| - | -
| `'>= 2018-04-01'` | that were canceled on or after 2018-04-01.
| `'< 2018-05-01'` | that were canceled before 2018-05-01
| `['and', '>= 2018-04-04', '< 2018-05-01']` | that were canceled between 2018-04-01 and 2018-05-01.



::: code
```twig
{# Fetch subscriptions that were canceled recently #}
{% set aWeekAgo = date('7 days ago')|atom %}

{% set subscriptions = craft.subscriptions()
    .dateCanceled(">= #{aWeekAgo}")
    .all() %}
```

```php
// Fetch subscriptions that were canceled recently
$aWeekAgo = new \DateTime('7 days ago')->format(\DateTime::ATOM);

$subscriptions = \craft\commerce\elements\Subscription::find()
    ->dateCanceled(">= {$aWeekAgo}")
    ->all();
```
:::


### `dateCreated`

Narrows the query results based on the subscriptions’ creation dates.



Possible values include:

| Value | Fetches subscriptions…
| - | -
| `'>= 2018-04-01'` | that were created on or after 2018-04-01.
| `'< 2018-05-01'` | that were created before 2018-05-01
| `['and', '>= 2018-04-04', '< 2018-05-01']` | that were created between 2018-04-01 and 2018-05-01.



::: code
```twig
{# Fetch subscriptions created last month #}
{% set start = date('first day of last month')|atom %}
{% set end = date('first day of this month')|atom %}

{% set subscriptions = craft.subscriptions()
    .dateCreated(['and', ">= #{start}", "< #{end}"])
    .all() %}
```

```php
// Fetch subscriptions created last month
$start = new \DateTime('first day of next month')->format(\DateTime::ATOM);
$end = new \DateTime('first day of this month')->format(\DateTime::ATOM);

$subscriptions = \craft\commerce\elements\Subscription::find()
    ->dateCreated(['and', ">= {$start}", "< {$end}"])
    ->all();
```
:::


### `dateExpired`

Narrows the query results based on the subscriptions’ expiration date.

Possible values include:

| Value | Fetches subscriptions…
| - | -
| `'>= 2018-04-01'` | that expired on or after 2018-04-01.
| `'< 2018-05-01'` | that expired before 2018-05-01
| `['and', '>= 2018-04-04', '< 2018-05-01']` | that expired between 2018-04-01 and 2018-05-01.



::: code
```twig
{# Fetch subscriptions that expired recently #}
{% set aWeekAgo = date('7 days ago')|atom %}

{% set subscriptions = craft.subscriptions()
    .dateExpired(">= #{aWeekAgo}")
    .all() %}
```

```php
// Fetch subscriptions that expired recently
$aWeekAgo = new \DateTime('7 days ago')->format(\DateTime::ATOM);

$subscriptions = \craft\commerce\elements\Subscription::find()
    ->dateExpired(">= {$aWeekAgo}")
    ->all();
```
:::


### `dateUpdated`

Narrows the query results based on the subscriptions’ last-updated dates.



Possible values include:

| Value | Fetches subscriptions…
| - | -
| `'>= 2018-04-01'` | that were updated on or after 2018-04-01.
| `'< 2018-05-01'` | that were updated before 2018-05-01
| `['and', '>= 2018-04-04', '< 2018-05-01']` | that were updated between 2018-04-01 and 2018-05-01.



::: code
```twig
{# Fetch subscriptions updated in the last week #}
{% set lastWeek = date('1 week ago')|atom %}

{% set subscriptions = craft.subscriptions()
    .dateUpdated(">= #{lastWeek}")
    .all() %}
```

```php
// Fetch subscriptions updated in the last week
$lastWeek = new \DateTime('1 week ago')->format(\DateTime::ATOM);

$subscriptions = \craft\commerce\elements\Subscription::find()
    ->dateUpdated(">= {$lastWeek}")
    ->all();
```
:::


### `fixedOrder`

Causes the query results to be returned in the order specified by [id](#id).





::: code
```twig
{# Fetch subscriptions in a specific order #}
{% set subscriptions = craft.subscriptions()
    .id([1, 2, 3, 4, 5])
    .fixedOrder()
    .all() %}
```

```php
// Fetch subscriptions in a specific order
$subscriptions = \craft\commerce\elements\Subscription::find()
    ->id([1, 2, 3, 4, 5])
    ->fixedOrder()
    ->all();
```
:::


### `gatewayId`

Narrows the query results based on the gateway, per its ID.

Possible values include:

| Value | Fetches subscriptions…
| - | -
| `1` | with a gateway with an ID of 1.
| `'not 1'` | not with a gateway with an ID of 1.
| `[1, 2]` | with a gateway with an ID of 1 or 2.
| `['not', 1, 2]` | not with a gateway with an ID of 1 or 2.




### `id`

Narrows the query results based on the subscriptions’ IDs.



Possible values include:

| Value | Fetches subscriptions…
| - | -
| `1` | with an ID of 1.
| `'not 1'` | not with an ID of 1.
| `[1, 2]` | with an ID of 1 or 2.
| `['not', 1, 2]` | not with an ID of 1 or 2.



::: code
```twig
{# Fetch the subscription by its ID #}
{% set subscription = craft.subscriptions()
    .id(1)
    .one() %}
```

```php
// Fetch the subscription by its ID
$subscription = \craft\commerce\elements\Subscription::find()
    ->id(1)
    ->one();
```
:::



::: tip
This can be combined with [fixedOrder](#fixedorder) if you want the results to be returned in a specific order.
:::


### `inReverse`

Causes the query results to be returned in reverse order.





::: code
```twig
{# Fetch subscriptions in reverse #}
{% set subscriptions = craft.subscriptions()
    .inReverse()
    .all() %}
```

```php
// Fetch subscriptions in reverse
$subscriptions = \craft\commerce\elements\Subscription::find()
    ->inReverse()
    ->all();
```
:::


### `isCanceled`

Narrows the query results to only subscriptions that are canceled.



::: code
```twig
{# Fetch canceled subscriptions #}
{% set subscriptions = {twig-function}
    .isCanceled()
    .all() %}
```

```php
// Fetch canceled subscriptions
$subscriptions = \craft\commerce\elements\Subscription::find()
    ->isCanceled()
    ->all();
```
:::


### `isExpired`

Narrows the query results to only subscriptions that have expired.



::: code
```twig
{# Fetch expired subscriptions #}
{% set subscriptions = {twig-function}
    .isExpired()
    .all() %}
```

```php
// Fetch expired subscriptions
$subscriptions = \craft\commerce\elements\Subscription::find()
    ->isExpired()
    ->all();
```
:::


### `limit`

Determines the number of subscriptions that should be returned.



::: code
```twig
{# Fetch up to 10 subscriptions  #}
{% set subscriptions = craft.subscriptions()
    .limit(10)
    .all() %}
```

```php
// Fetch up to 10 subscriptions
$subscriptions = \craft\commerce\elements\Subscription::find()
    ->limit(10)
    ->all();
```
:::


### `nextPaymentDate`

Narrows the query results based on the subscriptions’ next payment dates.

Possible values include:

| Value | Fetches subscriptions…
| - | -
| `'>= 2018-04-01'` | with a next payment on or after 2018-04-01.
| `'< 2018-05-01'` | with a next payment before 2018-05-01
| `['and', '>= 2018-04-04', '< 2018-05-01']` | with a next payment between 2018-04-01 and 2018-05-01.



::: code
```twig
{# Fetch subscriptions with a payment due soon #}
{% set aWeekFromNow = date('+7 days')|atom %}

{% set subscriptions = craft.subscriptions()
    .nextPaymentDate("< #{aWeekFromNow}")
    .all() %}
```

```php
// Fetch subscriptions with a payment due soon
$aWeekFromNow = new \DateTime('+7 days')->format(\DateTime::ATOM);

$subscriptions = \craft\commerce\elements\Subscription::find()
    ->nextPaymentDate("< {$aWeekFromNow}")
    ->all();
```
:::


### `offset`

Determines how many subscriptions should be skipped in the results.



::: code
```twig
{# Fetch all subscriptions except for the first 3 #}
{% set subscriptions = craft.subscriptions()
    .offset(3)
    .all() %}
```

```php
// Fetch all subscriptions except for the first 3
$subscriptions = \craft\commerce\elements\Subscription::find()
    ->offset(3)
    ->all();
```
:::


### `onTrial`

Narrows the query results to only subscriptions that are on trial.



::: code
```twig
{# Fetch trialed subscriptions #}
{% set subscriptions = {twig-function}
    .onTrial()
    .all() %}
```

```php
// Fetch trialed subscriptions
$subscriptions = \craft\commerce\elements\Subscription::find()
    ->isPaid()
    ->all();
```
:::


### `orderBy`

Determines the order that the subscriptions should be returned in.



::: code
```twig
{# Fetch all subscriptions in order of date created #}
{% set subscriptions = craft.subscriptions()
    .orderBy('elements.dateCreated asc')
    .all() %}
```

```php
// Fetch all subscriptions in order of date created
$subscriptions = \craft\commerce\elements\Subscription::find()
    ->orderBy('elements.dateCreated asc')
    ->all();
```
:::


### `orderId`

Narrows the query results based on the order, per its ID.

Possible values include:

| Value | Fetches subscriptions…
| - | -
| `1` | with an order with an ID of 1.
| `'not 1'` | not with an order with an ID of 1.
| `[1, 2]` | with an order with an ID of 1 or 2.
| `['not', 1, 2]` | not with an order with an ID of 1 or 2.




### `plan`

Narrows the query results based on the subscription plan.

Possible values include:

| Value | Fetches subscriptions…
| - | -
| `'foo'` | for a plan with a handle of `foo`.
| `['foo', 'bar']` | for plans with a handle of `foo` or `bar`.
| a [Plan](api:craft\commerce\base\Plan) object | for a plan represented by the object.



::: code
```twig
{# Fetch Supporter plan subscriptions #}
{% set subscriptions = craft.subscriptions()
    .plan('supporter')
    .all() %}
```

```php
// Fetch Supporter plan subscriptions
$subscriptions = \craft\commerce\elements\Subscription::find()
    ->plan('supporter')
    ->all();
```
:::


### `planId`

Narrows the query results based on the subscription plans’ IDs.

Possible values include:

| Value | Fetches subscriptions…
| - | -
| `1` | for a plan with an ID of 1.
| `[1, 2]` | for plans with an ID of 1 or 2.
| `['not', 1, 2]` | for plans not with an ID of 1 or 2.




### `reference`

Narrows the query results based on the reference.






### `relatedTo`

Narrows the query results to only subscriptions that are related to certain other elements.



See [Relations](https://docs.craftcms.com/v3/relations.html) for a full explanation of how to work with this parameter.



::: code
```twig
{# Fetch all subscriptions that are related to myCategory #}
{% set subscriptions = craft.subscriptions()
    .relatedTo(myCategory)
    .all() %}
```

```php
// Fetch all subscriptions that are related to $myCategory
$subscriptions = \craft\commerce\elements\Subscription::find()
    ->relatedTo($myCategory)
    ->all();
```
:::


### `search`

Narrows the query results to only subscriptions that match a search query.



See [Searching](https://docs.craftcms.com/v3/searching.html) for a full explanation of how to work with this parameter.



::: code
```twig
{# Get the search query from the 'q' query string param #}
{% set searchQuery = craft.request.getQueryParam('q') %}

{# Fetch all subscriptions that match the search query #}
{% set subscriptions = craft.subscriptions()
    .search(searchQuery)
    .all() %}
```

```php
// Get the search query from the 'q' query string param
$searchQuery = \Craft::$app->request->getQueryParam('q');

// Fetch all subscriptions that match the search query
$subscriptions = \craft\commerce\elements\Subscription::find()
    ->search($searchQuery)
    ->all();
```
:::


### `status`

Narrows the query results based on the subscriptions’ statuses.

Possible values include:

| Value | Fetches subscriptions…
| - | -
| `'active'` _(default)_ | that are active.
| `'expired'` | that have expired.



::: code
```twig
{# Fetch expired subscriptions #}
{% set subscriptions = {twig-function}
    .status('expired')
    .all() %}
```

```php
// Fetch expired subscriptions
$subscriptions = \craft\commerce\elements\Subscription::find()
    ->status('expired')
    ->all();
```
:::


### `trialDays`

Narrows the query results based on the number of trial days.






### `uid`

Narrows the query results based on the subscriptions’ UIDs.





::: code
```twig
{# Fetch the subscription by its UID #}
{% set subscription = craft.subscriptions()
    .uid('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
    .one() %}
```

```php
// Fetch the subscription by its UID
$subscription = \craft\commerce\elements\Subscription::find()
    ->uid('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
    ->one();
```
:::


### `user`

Narrows the query results based on the subscriptions’ user accounts.

Possible values include:

| Value | Fetches subscriptions…
| - | -
| `'foo'` | for a user account with a username of `foo`
| `['foo', 'bar']` | for user accounts with a username of `foo` or `bar`.
| a [User](https://docs.craftcms.com/api/v3/craft-elements-user.html) object | for a user account represented by the object.



::: code
```twig
{# Fetch the current user's subscriptions #}
{% set subscriptions = craft.subscriptions()
    .user(currentUser)
    .all() %}
```

```php
// Fetch the current user's subscriptions
$user = Craft::$app->user->getIdentity();
$subscriptions = \craft\commerce\elements\Subscription::find()
    ->user($user)
    ->all();
```
:::


### `userId`

Narrows the query results based on the subscriptions’ user accounts’ IDs.

Possible values include:

| Value | Fetches subscriptions…
| - | -
| `1` | for a user account with an ID of 1.
| `[1, 2]` | for user accounts with an ID of 1 or 2.
| `['not', 1, 2]` | for user accounts not with an ID of 1 or 2.



::: code
```twig
{# Fetch the current user's subscriptions #}
{% set subscriptions = craft.subscriptions()
    .userId(currentUser.id)
    .all() %}
```

```php
// Fetch the current user's subscriptions
$user = Craft::$app->user->getIdentity();
$subscriptions = \craft\commerce\elements\Subscription::find()
    ->userId($user->id)
    ->all();
```
:::


### `with`

Causes the query to return matching subscriptions eager-loaded with related elements.



See [Eager-Loading Elements](https://docs.craftcms.com/v3/dev/eager-loading-elements.html) for a full explanation of how to work with this parameter.



::: code
```twig
{# Fetch subscriptions eager-loaded with the "Related" field’s relations #}
{% set subscriptions = craft.subscriptions()
    .with(['related'])
    .all() %}
```

```php
// Fetch subscriptions eager-loaded with the "Related" field’s relations
$subscriptions = \craft\commerce\elements\Subscription::find()
    ->with(['related'])
    ->all();
```
:::



<!-- END PARAMS -->
