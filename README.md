# B2Brouter for WooCommerce

Generate and send electronic invoices from WooCommerce orders using B2Brouter's eDocExchange service.

## Description

B2Brouter for WooCommerce is a WordPress plugin that integrates your WooCommerce store with B2Brouter's electronic invoicing platform. Automatically generate compliant electronic invoices for your orders and send them to your customers through B2Brouter.

### Features

- **Automatic or Manual Invoice Generation**: Choose to generate invoices automatically when orders are completed, or manually from the order admin panel
- **API Key Authentication**: Secure integration with B2Brouter using API keys
- **Transaction Counter**: Track the total number of invoices generated
- **Admin Bar Counter**: Quick view of invoice count directly in the WordPress admin bar
- **Bulk Invoice Generation**: Generate invoices for multiple orders at once
- **Order Integration**: View invoice status and details directly in WooCommerce orders
- **B2Brouter PHP SDK**: Built on the official B2Brouter PHP SDK for reliable integration

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- Composer (for dependency management)
- Active B2Brouter eDocExchange subscription

## Installation

### Manual Installation

1. Download the plugin files
2. Upload the `b2brouter-woocommerce` folder to `/wp-content/plugins/`
3. Run `composer install` in the plugin directory to install dependencies
4. Activate the plugin through the 'Plugins' menu in WordPress
5. Follow the welcome screen instructions to configure your API key

### Via Composer

```bash
composer require b2brouter/woocommerce-plugin
```

## Configuration

### 1. Get Your API Key

1. Visit [B2Brouter eDocExchange](https://b2brouter.com/edocexchange)
2. If you're a new user, complete the registration process
3. If you're an existing user, log in to your account
4. Activate your eDocExchange subscription
5. Copy your API key

### 2. Configure the Plugin

1. Go to **B2Brouter** → **Settings** in WordPress admin
2. Paste your API key in the **API Key** field
3. Click **Validate Key** to verify the connection
4. Choose your **Invoice Generation Mode**:
   - **Automatic**: Invoices are generated automatically when orders are completed
   - **Manual**: Invoices are generated manually using a button in the order admin
5. Click **Save Settings**

## Usage

### Automatic Mode

When automatic mode is enabled:

1. Customer completes a purchase
2. Order status changes to "Completed"
3. Invoice is automatically generated and sent via B2Brouter
4. Order note is added with invoice details

### Manual Mode

When manual mode is enabled:

1. Go to **WooCommerce** → **Orders**
2. Click on an order to view details
3. In the **B2Brouter Invoice** meta box, click **Generate Invoice**
4. Invoice is created and sent via B2Brouter
5. Meta box updates to show invoice details

### Bulk Invoice Generation

1. Go to **WooCommerce** → **Orders**
2. Select multiple orders using checkboxes
3. From **Bulk Actions** dropdown, select **Generate B2Brouter Invoices**
4. Click **Apply**
5. Invoices are generated for all selected orders

### View Invoice Status

- **Orders List**: A checkmark icon appears in the **Invoice** column for orders with invoices
- **Order Details**: View full invoice details in the **B2Brouter Invoice** meta box
- **Admin Bar**: View total invoice count in the WordPress admin bar

## Advanced Configuration

Advanced settings like transports, formats, taxes, and compliance rules are configured in your B2Brouter account, not in the WordPress plugin.

To access advanced settings:

1. Click **Access B2Brouter Account Settings** in the plugin settings page
2. Or visit [B2Brouter Account](https://b2brouter.com/account) directly

## Invoice Data

The plugin automatically includes the following data in invoices:

- Customer name and email
- Billing address (street, city, postal code, country)
- Company name (if provided)
- VAT number (if available in order meta as `_billing_vat_number`)
- Order line items with quantities, prices, and tax rates
- Shipping costs (if applicable)
- Order currency
- WooCommerce order ID and order number (in metadata)

## Troubleshooting

### Invoice Generation Fails

- Verify your API key is valid using the **Validate Key** button
- Check that your eDocExchange subscription is active
- Review order notes for specific error messages
- Ensure all required customer information is present in the order

### API Key Validation Fails

- Check that you copied the complete API key
- Verify your eDocExchange subscription is active
- Ensure your WordPress site can connect to B2Brouter servers (no firewall blocking)

### Missing Invoice in Order

- Check that automatic mode is enabled (if expecting automatic generation)
- Verify the order status is "Completed"
- Check if an invoice already exists (invoices can only be generated once per order)
- Review order notes for any error messages

### Composer Dependencies Not Found

```bash
cd /path/to/wp-content/plugins/b2brouter-woocommerce
composer install
```

## Development

### File Structure

```
b2brouter-woocommerce/
├── assets/
│   ├── css/
│   │   └── admin.css           # Admin styles
│   └── js/
│       └── admin.js            # Admin JavaScript
├── includes/
│   ├── class-b2brouter-settings.php        # Settings handler
│   ├── class-b2brouter-admin.php           # Admin interface
│   ├── class-b2brouter-invoice-generator.php  # Invoice generation logic
│   └── class-b2brouter-order-handler.php   # WooCommerce order integration
├── vendor/                     # Composer dependencies (not in repo)
├── b2brouter-woocommerce.php   # Main plugin file
├── composer.json               # Composer configuration
└── README.md                   # This file
```

### Hooks and Filters

The plugin provides hooks for developers:

#### Actions

- `woocommerce_order_status_completed` - Automatic invoice generation
- `add_meta_boxes` - Invoice meta box registration
- `admin_bar_menu` - Admin bar counter

#### Filters

- `manage_edit-shop_order_columns` - Add invoice column to orders list
- `bulk_actions-edit-shop_order` - Add bulk action for invoice generation
- `plugin_action_links_{basename}` - Add settings link to plugins page

### B2B PHP SDK Integration

The plugin uses the [B2Brouter PHP SDK](https://github.com/jtorrents/b2b-php) for all API interactions.

Key methods used:

```php
// Initialize client
$client = new \B2BRouter\Client\B2BRouterClient($api_key);

// Create invoice
$invoice = $client->invoices->create($invoice_data);

// Send invoice
$client->invoices->send($invoice['id']);

// List invoices (for validation)
$invoices = $client->invoices->all(['limit' => 1]);
```

## Support

- **Documentation**: [B2Brouter Documentation](https://b2brouter.com/docs)
- **Support**: [B2Brouter Support](https://b2brouter.com/support)
- **GitHub Issues**: [Report Issues](https://github.com/jtorrents/b2b-woocommerce/issues)

## Changelog

### 1.0.0 - 2025-01-XX

- Initial release
- Automatic and manual invoice generation
- API key authentication
- Transaction counter
- Admin bar integration
- Bulk invoice generation
- WooCommerce orders integration
- B2Brouter PHP SDK integration

## License

This plugin is licensed under the GPL v2 or later.

## Credits

- Developed by [B2Brouter](https://b2brouter.com)
- Uses the [B2Brouter PHP SDK](https://github.com/jtorrents/b2b-php)
- Built for [WooCommerce](https://woocommerce.com)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
