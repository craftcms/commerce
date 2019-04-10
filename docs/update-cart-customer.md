# Update Cart Customer

A cart always has an associated customer. That customer can be a guest (no associated user), or a customer that is not a guest 
and is associated with a registered user.

When a user logs in, the current customer switches to the customer associated to the logged in user.

The only information stored about a customer is the email address, and their related address book addresses, as well as their 
primary shipping and billing address IDs.

As guest customer the address book is pretty useless as the next time they visit the site 
all addresses in their address book will be lost. If they register on checkout the address book will survive. 

## Updating the email address on an order

If a customer is currently a guest, the email address of the customer is updated by updating the cart’s email. This can be done with 
the same controller action as updating anything else on the cart.

Example:

```twig
<form method="POST">
<input type="hidden" name="action" value="commerce/cart/update-cart">
{{ redirectInput('shop/cart') }}
{{ csrfInput() }}

<input type="text"
       name="email"
       class="{% if cart.getFirstError('email') %}has-error{% endif %}"
       value="{{ cart.email }}"
       placeholder="{{ "your@email.com"|t }}">
       
<input type="submit" value="Update Cart Email"/>
<form>
```
If a customer is a logged in user, you can not update the email address of the order, it is always set as the email 
address of the user.

## Checking if a guest customer has a user account

When guiding users through the checkout, it is a good idea to collect the email address from them early, this would 
enable the checking if they have an account with your site already:

```twig
{% if craft.users.email(cart.email).one() %}You are already a user, please log in.{% endif %}
```
Once a user logs in, the cart's customer will switch to be the registered user’s customer record.

## Registering a guest customer as a user

If the customer is a guest, and they do not have an account, you can always show them a standard [Craft CMS registration 
form](/v3/dev/examples/user-registration-form.html) during checkout.

If you would like to allow the customer to register on checkout, you can update the order and flag it to register the user on 
order completion. This can be done with the same controller action as updating anything else on the cart.

Example:

 ```twig
<form method="POST">
 <input type="hidden" name="action" value="commerce/cart/update-cart">
 {{ redirectInput('shop/cart') }}
 {{ csrfInput() }}
 <label for="registerUserOnOrderComplete">
  <input type="checkbox" id="registerUserOnOrderComplete" name="registerUserOnOrderComplete" value="1" /> Register me for a user account
 </label>
 <input type="submit" value="Update Cart"/>
<form>
 ```
 
 Alternatively, as in our example templates, you can set this flag on the `commerce/payments/pay` controller action form.
 
 Example:
 
```twig
<input type="hidden" name="action" value="commerce/payments/pay">
<!-- payment form...-->
<label for="registerUserOnOrderComplete">
 <input type="checkbox" id="registerUserOnOrderComplete" name="registerUserOnOrderComplete" value="1" /> Register me for a user account
</label>
```

With the `registerUserOnOrderComplete` flag set to `true` on the order, the following will happen when the order is marked as complete:

1. Check if a user already exists, and if so, do nothing and not continue with the steps below.
2. Create a user with the same email address as the order.
3. Set the customer record to relate to that new user. This means all addresses in the guest customers address book will become the new users.
4. An account activation email will be sent, that will allow them to set their password.