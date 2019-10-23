# Customers

Customers are stored in Craft Commerce for the purpose of having a single place to relate orders to.

A customer will always be created during checkout if they are a guest, or if the user is logged in, the customer related to the logged in user will be used.

Logged-in users can only be a single customer in the system. A person may checkout as a guest customer with the same email multiple times, and a new guest customer will be created each time.

After a user is registered and verified, whenever they log in, all guest orders that used the email address belonging to that user are transferred to the logged in user. This means, even if a registered user makes an anonymous/guest order, the next time they log in, the order will appear in their order history, as long as they used the same email address.

## Customer Info

If a user account has a Craft Commerce customer record associated with it, a “Customer Info” tab will be added to their account page in the Control Panel.

This tab contains the following information:

- **Orders** – A list of previous orders for the customer.
- **Active Carts** – A list of active carts for the customer based on the [Commerce::$activeCartDuration](configuration.md#activecartduration).
- **Inactive Carts** – A list of inactive carts for the customer [Commerce::$activeCartDuration](configuration.md#activecartduration).
- **Addresses** – A list of the customer's addresses with the ability to edit or delete.
- **Subscriptions** – A list of the customer's subscriptions.
