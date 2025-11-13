<?php
/**
 * Invoice Generator - Integrates with B2Brouter PHP SDK
 *
 * @package B2Brouter\WooCommerce
 * @since 1.0.0
 */

namespace B2Brouter\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Invoice Generator class
 *
 * Handles invoice generation and API communication with B2Brouter
 *
 * @since 1.0.0
 */
class Invoice_Generator {

    /**
     * Settings instance
     *
     * @since 1.0.0
     * @var Settings
     */
    private $settings;

    /**
     * B2Brouter API client
     *
     * @since 1.0.0
     * @var \B2BRouter\Client\B2BRouterClient|null
     */
    private $client = null;

    /**
     * Constructor
     *
     * @since 1.0.0
     * @param Settings $settings Settings instance
     */
    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    /**
     * Get B2Brouter client instance
     *
     * @since 1.0.0
     * @return \B2BRouter\Client\B2BRouterClient The B2Brouter API client
     * @throws \Exception If API key is not configured or SDK is not found
     */
    private function get_client() {
        if (null !== $this->client) {
            return $this->client;
        }

        $api_key = $this->settings->get_api_key();

        if (empty($api_key)) {
            throw new \Exception(__('API key not configured', 'b2brouter-woocommerce'));
        }

        if (!class_exists('B2BRouter\B2BRouterClient')) {
            throw new \Exception(__('B2Brouter PHP SDK not found', 'b2brouter-woocommerce'));
        }

        $this->client = new \B2BRouter\B2BRouterClient($api_key);

        return $this->client;
    }

    /**
     * Generate invoice from WooCommerce order
     *
     * @since 1.0.0
     * @param int $order_id The WooCommerce order ID
     * @return array{success: bool, invoice_id?: string, invoice_number?: string, message: string} Generation result
     */
    public function generate_invoice($order_id) {
        try {
            // Get order
            $order = wc_get_order($order_id);

            if (!$order) {
                throw new \Exception(__('Order not found', 'b2brouter-woocommerce'));
            }

            // Check if invoice already generated
            if ($order->get_meta('_b2brouter_invoice_id')) {
                throw new \Exception(__('Invoice already generated for this order', 'b2brouter-woocommerce'));
            }

            // Get client
            $client = $this->get_client();

            // Get account ID
            $account_id = $this->settings->get_account_id();

            if (empty($account_id)) {
                throw new \Exception(__('Account ID not configured. Please validate your API key.', 'b2brouter-woocommerce'));
            }

            // Prepare invoice data
            $invoice_data = $this->prepare_invoice_data($order);

            // Create invoice via B2Brouter API
            $invoice = $client->invoices->create($account_id, array('invoice' => $invoice_data));

            // Send invoice
            $client->invoices->send($invoice['id']);

            // Store invoice ID in order meta
            $order->add_meta_data('_b2brouter_invoice_id', $invoice['id'], true);
            $order->add_meta_data('_b2brouter_invoice_number', $invoice['number'] ?? '', true);
            $order->add_meta_data('_b2brouter_invoice_date', current_time('mysql'), true);
            $order->save();

            // Add order note
            $order->add_order_note(
                sprintf(
                    __('B2Brouter invoice generated successfully. Invoice ID: %s', 'b2brouter-woocommerce'),
                    $invoice['id']
                )
            );

            // Increment transaction counter
            $this->settings->increment_transaction_count();

            return array(
                'success' => true,
                'invoice_id' => $invoice['id'],
                'invoice_number' => $invoice['number'] ?? '',
                'message' => __('Invoice generated successfully', 'b2brouter-woocommerce')
            );

        } catch (\Exception $e) {
            // Log error
            error_log('B2Brouter Invoice Generation Error: ' . $e->getMessage());

            // Add order note with error
            if ($order) {
                $order->add_order_note(
                    sprintf(
                        __('B2Brouter invoice generation failed: %s', 'b2brouter-woocommerce'),
                        $e->getMessage()
                    )
                );
            }

            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Prepare invoice data from WooCommerce order
     *
     * @since 1.0.0
     * @param \WC_Order $order The WooCommerce order
     * @return array The invoice data array for B2Brouter API
     */
    private function prepare_invoice_data($order) {
        // Get billing details
        $billing_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if (empty($billing_name)) {
            $billing_name = $order->get_billing_company();
        }

        // Prepare contact (customer) data
        $contact = array(
            'name' => $billing_name,
            'email' => $order->get_billing_email(),
            'country' => $order->get_billing_country(),
            'address' => $order->get_billing_address_1(),
            'city' => $order->get_billing_city(),
            'postalcode' => $order->get_billing_postcode(),
        );

        // Add address line 2 if present
        if ($order->get_billing_address_2()) {
            $contact['address'] .= ', ' . $order->get_billing_address_2();
        }

        // Add VAT/TIN number if available
        $vat_number = $order->get_meta('_billing_vat_number');
        if (!empty($vat_number)) {
            $contact['tin_value'] = $vat_number;
        }

        // Prepare line items
        $invoice_lines = array();

        foreach ($order->get_items() as $item) {
            $line = array(
                'description' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => (float) $order->get_item_subtotal($item, false, false),
            );

            // Add taxes if present
            $tax_rate = $this->get_item_tax_rate($item, $order);
            if ($tax_rate > 0) {
                $line['taxes_attributes'] = array(
                    array(
                        'name' => 'IVA',
                        'category' => 'S',  // Standard rate
                        'percent' => $tax_rate,
                    )
                );
            }

            $invoice_lines[] = $line;
        }

        // Add shipping as line item if exists
        if ($order->get_shipping_total() > 0) {
            $shipping_line = array(
                'description' => __('Shipping', 'b2brouter-woocommerce'),
                'quantity' => 1,
                'price' => (float) $order->get_shipping_total(),
            );

            $shipping_tax_rate = $this->get_shipping_tax_rate($order);
            if ($shipping_tax_rate > 0) {
                $shipping_line['taxes_attributes'] = array(
                    array(
                        'name' => 'IVA',
                        'category' => 'S',
                        'percent' => $shipping_tax_rate,
                    )
                );
            }

            $invoice_lines[] = $shipping_line;
        }

        // Generate invoice number based on order
        $invoice_number = 'INV-' . $order->get_billing_country() . '-' . date('Y') . '-' . str_pad($order->get_id(), 5, '0', STR_PAD_LEFT);

        // Prepare invoice data
        $invoice_data = array(
            'number' => $invoice_number,
            'date' => current_time('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime(current_time('Y-m-d') . ' +30 days')),
            'currency' => $order->get_currency(),
            'language' => substr(get_locale(), 0, 2),  // Get language from WordPress locale (e.g., 'es' from 'es_ES')
            'contact' => $contact,
            'invoice_lines_attributes' => $invoice_lines,
            'extra_info' => sprintf(
                __('WooCommerce Order #%s', 'b2brouter-woocommerce'),
                $order->get_order_number()
            ),
        );

        return $invoice_data;
    }

    /**
     * Get tax rate for order item
     *
     * @since 1.0.0
     * @param \WC_Order_Item_Product $item The order item
     * @param \WC_Order $order The order
     * @return float The tax rate percentage
     */
    private function get_item_tax_rate($item, $order) {
        $taxes = $item->get_taxes();

        if (empty($taxes['total'])) {
            return 0;
        }

        $tax_total = array_sum($taxes['total']);
        $item_total = $item->get_total();

        if ($item_total > 0) {
            return round(($tax_total / $item_total) * 100, 2);
        }

        return 0;
    }

    /**
     * Get tax rate for shipping
     *
     * @since 1.0.0
     * @param \WC_Order $order The order
     * @return float The shipping tax rate percentage
     */
    private function get_shipping_tax_rate($order) {
        $shipping_total = $order->get_shipping_total();
        $shipping_tax = $order->get_shipping_tax();

        if ($shipping_total > 0 && $shipping_tax > 0) {
            return round(($shipping_tax / $shipping_total) * 100, 2);
        }

        return 0;
    }

    /**
     * Check if order has invoice
     *
     * @since 1.0.0
     * @param int $order_id The order ID
     * @return bool True if order has invoice, false otherwise
     */
    public function has_invoice($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        return !empty($order->get_meta('_b2brouter_invoice_id'));
    }

    /**
     * Get invoice ID for order
     *
     * @since 1.0.0
     * @param int $order_id The order ID
     * @return string|null The invoice ID or null if not found
     */
    public function get_invoice_id($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }
        return $order->get_meta('_b2brouter_invoice_id');
    }
}
