# WooCommerce-Request-a-Quote-with-Hourly-Billing
This WordPress plugin extends WooCommerce to enable products to be billed on an hourly basis and integrates a request-a-quote system for such products. With this plugin, store owners can:

Add a checkbox to mark products as billed hourly.
Specify the hourly rate for products.
Replace the default "Add to Cart" button with a "Request a Quote" button for hourly products.
Allow customers to enter custom details, such as the number of hours or additional notes, before requesting a quote.
Manage quote requests and custom fields through a dedicated settings page.

Features
Hourly Billing: Configure products to be billed hourly with custom hourly rates.
Dynamic Pricing: Calculate pricing based on the number of hours entered by customers.
Request a Quote: Replace the "Add to Cart" button with a "Request a Quote" button for hourly billed products.
Custom Fields: Add and manage custom fields to collect additional information from customers.
Admin Management: Manage settings and custom fields through a user-friendly admin interface.
Integration with WooCommerce Cart: Properly handle hourly rates and custom inputs in the cart and checkout process.

Installation
Download the plugin files.
Upload the plugin folder to the /wp-content/plugins/ directory of your WordPress installation.
Activate the plugin through the Plugins menu in WordPress.
Configure products for hourly billing in the product settings.

Usage

Product Setup
Navigate to the product edit page in WooCommerce.
Check the "Hourly Rate" option to enable hourly billing for the product.
Enter the hourly rate in the provided field.

Request a Quote Workflow
On the product page, customers can enter the number of hours and other custom details.
Clicking the "Request a Quote" button adds the product and details to the cart.
The quote details appear in the order summary during checkout.

Custom Fields Management
To manage custom fields:
Go to WooCommerce > Quote Settings.
Add, edit, or remove custom fields using the interface provided.

Technical Details
Hooks & Filters Used: Utilizes WooCommerce hooks like woocommerce_product_options_general_product_data, woocommerce_process_product_meta, woocommerce_before_calculate_totals, etc.
AJAX Integration: Includes custom AJAX handlers for adding products with custom data.
Frontend Customization: Includes JavaScript for dynamic field toggling and request-a-quote form handling.

Screenshots
Product settings page with hourly rate options.
Quote form on the product page.
Quote details in the cart and order summary.

Contributing
We welcome contributions to this plugin. Please fork the repository, make your changes, and submit a pull request.

License
This plugin is licensed under the GPL-3.0 License.
