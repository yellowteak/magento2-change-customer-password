# YellowTeak_ChangeCustomerPassword module
Magento 2 module that makes it possible to change the customer password from the admin.  This can be useful for customer support purposes or when a customer has forgotten their password and needs assistance.

This module adds a new tab to the customer edit page in the Magento admin panel, allowing administrators to change the customer's password directly from there.

## Installation details
1. Run `composer require yellowteak/magento2-change-customer-password`
2. Run `php bin/magento module:enable YellowTeak_ChangeCustomerPassword`

## Configuration
Before the new tab will be visible in the customer edit page, you need to enable the module in the configuration settings. To do this, follow these steps:
1. Log in to the Magento admin panel.
2. Navigate to `Stores > Configuration > YellowTeak > Change Customer Password`.
3. Set `Enabled` to `Yes`.
4. Click `Save Config`.
5. Flush the cache by navigating to `System > Cache Management` and clicking `Flush Magento Cache`.

## Usage
Once the module is enabled, you can change a customer's password by following these steps:
1. Log in to the Magento admin panel.
2. Navigate to `Customers > All Customers`.
3. Click on the customer whose password you want to change.
4. Click on the `Change Password` tab.
5. Enter the new password in the `New Password` and `Password Confirmation` field.
6. For security reasons, you will also need to enter your own admin password in the `Your Password` field to confirm the change.
7. Click `Save` to update the customer's password.

![Screenshot](https://raw.githubusercontent.com/yellowteak/magento2-change-customer-password/master/docs/screenshot-1.png)

