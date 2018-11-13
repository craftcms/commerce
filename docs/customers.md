# Customers

Customers are stored in Craft Commerce for the purpose of having a single place to relate orders to.
A customer will always be created during checkout if they are a guest, or if the user is logged in, the customer related to the logged in user will be used.

Logged-in users can only be a single customer in the system. A person may checkout as a guest customer with the same email multiple times, and a new guest customer will be created each time.

After a user is registered and verified, whenever they log in, all guest orders that used the email address belonging to that user are transferred to the logged in user. This means, even if a registered user makes an anonymous/guest order, the next time they log in, the order will appear in their order history, as long as they used the same email address.

