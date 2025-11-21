# B2Brouter for WooCommerce

Generate and send electronic invoices from WooCommerce orders using B2Brouter's eDocExchange service.

## Description

B2Brouter for WooCommerce is a WordPress plugin that integrates your WooCommerce store with B2Brouter's electronic invoicing platform. Automatically generate compliant electronic invoices for your orders and send them to your customers through B2Brouter.

### Features

- **Automatic or Manual Invoice Generation**: Choose to generate invoices automatically when orders are completed, or manually from the order admin panel
- **TIN/VAT Number Field**: Automatic TIN/VAT number collection at checkout for both classic and block-based checkout
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

### For End Users (Recommended)

**Download the pre-built release ZIP** (includes all dependencies):

1. Download `b2brouter-woocommerce-X.X.X.zip` from the releases page
2. In WordPress Admin, go to **Plugins → Add New → Upload Plugin**
3. Choose the downloaded ZIP file
4. Click **Install Now** → **Activate Plugin**
5. Follow the welcome screen to configure your B2Brouter API key

**No Composer required!** The release ZIP includes all dependencies.

### For Developers

If you're developing or contributing to the plugin:

```bash
# Clone repository
git clone https://github.com/B2Brouter/b2brouter-woocommerce.git
cd b2brouter-woocommerce

# Install dependencies via Composer
composer install

# Link to WordPress plugins directory
ln -s $(pwd) /path/to/wordpress/wp-content/plugins/b2brouter-woocommerce
```

**Note:** The `vendor/` directory is gitignored for development. For distribution, use the build script:

```bash
./build-release.sh
# Creates: dist/b2brouter-woocommerce-X.X.X.zip (ready for distribution)
```

See [DISTRIBUTION.md](DISTRIBUTION.md) for complete release instructions.

## Configuration

### 1. Get Your API Key

1. Visit [B2Brouter](https://app.b2brouter.net)
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
2. Or visit [B2Brouter Account](https://app.b2brouter.net) directly

## TIN/VAT Number Collection

The plugin automatically adds a TIN/VAT Number field to your WooCommerce checkout to collect tax identification numbers from customers.

### Supported Checkout Types

- **Block Checkout** (WooCommerce 8.6+): Field appears in the contact information section
- **Classic Checkout** (Shortcode-based): Field appears in the billing section after company name

### Field Details

- **Label**: "Tax ID / VAT Number"
- **Type**: Text field (optional)
- **Location**: Contact section (block checkout) or Billing section (classic checkout)
- **Storage**: Saved as `_billing_tin` order meta
- **Admin**: Visible in order edit screen under billing information

### How It Works

1. Customer enters their TIN/VAT number during checkout
2. Value is saved to the order as `_billing_tin` meta
3. TIN is displayed in the admin order view
4. TIN is automatically included in B2Brouter invoices

**Note**: The field is optional by default. Customers can complete checkout without entering a TIN/VAT number.

## Invoice Data

The plugin automatically includes the following data in invoices:

- Customer name and email
- Billing address (street, city, postal code, country)
- Company name (if provided)
- **Tax ID / VAT Number** (collected at checkout via the TIN field)
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

### B2Brouter PHP SDK Integration

The plugin uses the official [B2Brouter PHP SDK](https://github.com/B2Brouter/b2brouter-php) (v0.9.0+) for all API interactions.

**SDK Information:**
- **Packagist:** https://packagist.org/packages/b2brouter/b2brouter-php
- **GitHub:** https://github.com/B2Brouter/b2brouter-php
- **Version:** ^0.9.0
- **License:** MIT

**Environment Configuration:**
- **Default:** Staging (`https://api-staging.b2brouter.net`)
- **Production:** `https://api.b2brouter.net` (configurable in settings)

Key methods used:

```php
// Initialize client with environment-specific API base URL
$api_base = $this->settings->get_api_base_url(); // Returns staging or production URL
$client = new \B2BRouter\B2BRouterClient($api_key, ['api_base' => $api_base]);

// Create invoice
$invoice = $client->invoices->create($account_id, ['invoice' => $invoice_data]);

// Send invoice
$client->invoices->send($invoice['id']);

// Validate API key and get accounts
$accounts = $client->accounts->all(['limit' => 1]);
```

## Support

- **Documentation**: [B2Brouter API Documentation](https://developer.b2brouter.net)
- **B2Brouter**: [B2Brouter](https://app.b2brouter.net)
- **GitHub Issues**: [Report Issues](https://github.com/jtorrents/b2b-woocommerce/issues)

## License

This library is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Credits

- Developed by [B2Brouter](https://b2brouter.net)
- Uses the [B2Brouter PHP SDK](https://github.com/jtorrents/b2b-php)
- Built for [WooCommerce](https://woocommerce.com)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
