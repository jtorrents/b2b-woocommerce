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

        // Create client with environment setting
        $options = array('api_base' => $this->settings->get_api_base_url());
        $this->client = new \B2BRouter\B2BRouterClient($api_key, $options);

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

            // Auto-save PDF if enabled
            if ($this->settings->get_auto_save_pdf()) {
                // Wait a moment for B2Brouter to process the invoice
                sleep(2);

                // Try to download and save PDF
                $pdf_result = $this->save_invoice_pdf($order_id, false);

                if ($pdf_result['success']) {
                    $order->add_order_note(
                        __('Invoice PDF automatically downloaded and cached locally', 'b2brouter-woocommerce')
                    );
                }
            }

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

    /**
     * Download invoice as PDF from B2Brouter API
     *
     * @since 1.0.0
     * @param string $invoice_id The B2Brouter invoice ID
     * @return array{success: bool, pdf_data?: string, filename?: string, message: string}
     */
    public function download_invoice_pdf($invoice_id) {
        try {
            if (empty($invoice_id)) {
                throw new \Exception(__('Invoice ID is required', 'b2brouter-woocommerce'));
            }

            // Get B2Brouter client
            $client = $this->get_client();

            // Download PDF using SDK v0.9.1
            $pdf_data = $client->invoices->downloadPdf($invoice_id);

            // Validate PDF data
            if (empty($pdf_data)) {
                throw new \Exception(__('PDF data is empty', 'b2brouter-woocommerce'));
            }

            // Generate filename
            $filename = sanitize_file_name("invoice-{$invoice_id}.pdf");

            return array(
                'success' => true,
                'pdf_data' => $pdf_data,
                'filename' => $filename,
                'message' => __('PDF downloaded successfully', 'b2brouter-woocommerce')
            );

        } catch (\B2BRouter\Exception\ResourceNotFoundException $e) {
            error_log('B2Brouter PDF Download - Invoice not found: ' . $invoice_id);
            return array(
                'success' => false,
                'message' => __('Invoice not found', 'b2brouter-woocommerce')
            );

        } catch (\B2BRouter\Exception\AuthenticationException $e) {
            error_log('B2Brouter PDF Download - Authentication failed: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('API authentication failed. Please check your API key.', 'b2brouter-woocommerce')
            );

        } catch (\B2BRouter\Exception\PermissionException $e) {
            error_log('B2Brouter PDF Download - Permission denied: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('You do not have permission to download this invoice.', 'b2brouter-woocommerce')
            );

        } catch (\B2BRouter\Exception\ApiErrorException $e) {
            error_log('B2Brouter PDF Download - API error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => sprintf(
                    __('API error: %s', 'b2brouter-woocommerce'),
                    $e->getMessage()
                )
            );

        } catch (\Exception $e) {
            error_log('B2Brouter PDF Download - Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Save invoice PDF to local storage
     *
     * @since 1.0.0
     * @param int $order_id The WooCommerce order ID
     * @param bool $force_download Force new download even if file exists
     * @return array{success: bool, file_path?: string, file_url?: string, message: string}
     */
    public function save_invoice_pdf($order_id, $force_download = false) {
        try {
            // Get order
            $order = wc_get_order($order_id);

            if (!$order) {
                throw new \Exception(__('Order not found', 'b2brouter-woocommerce'));
            }

            // Get invoice ID
            $invoice_id = $order->get_meta('_b2brouter_invoice_id');

            if (empty($invoice_id)) {
                throw new \Exception(__('No invoice found for this order', 'b2brouter-woocommerce'));
            }

            // Check if PDF already exists and we're not forcing download
            $existing_path = $order->get_meta('_b2brouter_invoice_pdf_path');
            if (!$force_download && !empty($existing_path) && file_exists($existing_path)) {
                $upload_dir = wp_upload_dir();
                $filename = basename($existing_path);
                $file_url = $upload_dir['baseurl'] . '/b2brouter-invoices/' . $filename;

                return array(
                    'success' => true,
                    'file_path' => $existing_path,
                    'file_url' => $file_url,
                    'message' => __('Using existing PDF file', 'b2brouter-woocommerce'),
                    'cached' => true
                );
            }

            // Download PDF from API
            $result = $this->download_invoice_pdf($invoice_id);

            if (!$result['success']) {
                throw new \Exception($result['message']);
            }

            // Create storage directory
            $storage_path = $this->settings->get_pdf_storage_path();

            if (!file_exists($storage_path)) {
                if (!wp_mkdir_p($storage_path)) {
                    throw new \Exception(__('Failed to create PDF storage directory', 'b2brouter-woocommerce'));
                }

                // Add security files
                $this->secure_pdf_directory($storage_path);
            }

            // Generate unique filename
            $filename = sanitize_file_name("invoice-order-{$order_id}-{$invoice_id}.pdf");
            $file_path = $storage_path . '/' . $filename;

            // Save PDF file
            $bytes_written = file_put_contents($file_path, $result['pdf_data']);

            if ($bytes_written === false) {
                throw new \Exception(__('Failed to save PDF file', 'b2brouter-woocommerce'));
            }

            // Store metadata in order
            $order->update_meta_data('_b2brouter_invoice_pdf_path', $file_path);
            $order->update_meta_data('_b2brouter_invoice_pdf_filename', $filename);
            $order->update_meta_data('_b2brouter_invoice_pdf_size', $bytes_written);
            $order->update_meta_data('_b2brouter_invoice_pdf_date', current_time('mysql'));
            $order->save();

            // Generate URL (note: direct access blocked by .htaccess)
            $upload_dir = wp_upload_dir();
            $file_url = $upload_dir['baseurl'] . '/b2brouter-invoices/' . $filename;

            return array(
                'success' => true,
                'file_path' => $file_path,
                'file_url' => $file_url,
                'file_size' => $bytes_written,
                'message' => __('PDF saved successfully', 'b2brouter-woocommerce')
            );

        } catch (\Exception $e) {
            error_log('B2Brouter Save PDF Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Secure PDF storage directory
     *
     * @since 1.0.0
     * @param string $directory_path Directory to secure
     * @return void
     */
    private function secure_pdf_directory($directory_path) {
        // Add .htaccess to prevent direct access
        $htaccess_path = $directory_path . '/.htaccess';
        $htaccess_content = "# B2Brouter Invoice PDFs - Access Denied\n";
        $htaccess_content .= "Options -Indexes\n";
        $htaccess_content .= "<Files *.pdf>\n";
        $htaccess_content .= "    Require all denied\n";
        $htaccess_content .= "</Files>\n";

        file_put_contents($htaccess_path, $htaccess_content);

        // Add index.php to prevent directory listing
        $index_path = $directory_path . '/index.php';
        file_put_contents($index_path, "<?php\n// Silence is golden.");
    }

    /**
     * Stream invoice PDF directly to browser
     *
     * @since 1.0.0
     * @param int $order_id The WooCommerce order ID
     * @param bool $download Force download vs inline display
     * @return void Outputs PDF and exits
     */
    public function stream_invoice_pdf($order_id, $download = false) {
        try {
            // Get order
            $order = wc_get_order($order_id);

            if (!$order) {
                wp_die(
                    esc_html__('Order not found', 'b2brouter-woocommerce'),
                    esc_html__('Error', 'b2brouter-woocommerce'),
                    array('response' => 404)
                );
            }

            // Check permissions
            if (!$this->can_access_invoice($order)) {
                wp_die(
                    esc_html__('You do not have permission to access this invoice.', 'b2brouter-woocommerce'),
                    esc_html__('Permission Denied', 'b2brouter-woocommerce'),
                    array('response' => 403)
                );
            }

            // Get invoice ID
            $invoice_id = $order->get_meta('_b2brouter_invoice_id');

            if (empty($invoice_id)) {
                wp_die(
                    esc_html__('No invoice found for this order', 'b2brouter-woocommerce'),
                    esc_html__('Error', 'b2brouter-woocommerce'),
                    array('response' => 404)
                );
            }

            // Check if PDF exists locally
            $pdf_path = $order->get_meta('_b2brouter_invoice_pdf_path');

            if (!empty($pdf_path) && file_exists($pdf_path)) {
                // Use cached PDF
                $pdf_data = file_get_contents($pdf_path);
                $filename = basename($pdf_path);
            } else {
                // Download and save PDF (this will cache it)
                $save_result = $this->save_invoice_pdf($order_id, false);

                if (!$save_result['success']) {
                    wp_die(
                        esc_html($save_result['message']),
                        esc_html__('Error', 'b2brouter-woocommerce'),
                        array('response' => 500)
                    );
                }

                // Read the saved PDF file
                $pdf_data = file_get_contents($save_result['file_path']);
                $filename = basename($save_result['file_path']);
            }

            // Clear any previous output
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Set headers
            header('Content-Type: application/pdf');
            header('Content-Length: ' . strlen($pdf_data));

            if ($download) {
                header('Content-Disposition: attachment; filename="' . $filename . '"');
            } else {
                header('Content-Disposition: inline; filename="' . $filename . '"');
            }

            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            header('Expires: 0');

            // Output PDF
            echo $pdf_data;
            exit;

        } catch (\Exception $e) {
            error_log('B2Brouter Stream PDF Error: ' . $e->getMessage());
            wp_die(
                esc_html($e->getMessage()),
                esc_html__('Error', 'b2brouter-woocommerce'),
                array('response' => 500)
            );
        }
    }

    /**
     * Check if current user can access invoice for order
     *
     * @since 1.0.0
     * @param \WC_Order $order The order
     * @return bool True if user has access
     */
    private function can_access_invoice($order) {
        // Admins can always access
        if (current_user_can('manage_woocommerce')) {
            return true;
        }

        // Check if current user is the order customer
        $user_id = get_current_user_id();
        if ($user_id > 0 && (int) $order->get_customer_id() === $user_id) {
            return true;
        }

        // Check for guest access with order key
        if (isset($_GET['key']) && $order->get_order_key() === $_GET['key']) {
            return true;
        }

        return false;
    }

    /**
     * Delete stored PDF for an order
     *
     * @since 1.0.0
     * @param int $order_id The order ID
     * @return bool True on success
     */
    public function delete_invoice_pdf($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return false;
        }

        $pdf_path = $order->get_meta('_b2brouter_invoice_pdf_path');

        if (!empty($pdf_path) && file_exists($pdf_path)) {
            if (unlink($pdf_path)) {
                // Clear metadata
                $order->delete_meta_data('_b2brouter_invoice_pdf_path');
                $order->delete_meta_data('_b2brouter_invoice_pdf_filename');
                $order->delete_meta_data('_b2brouter_invoice_pdf_size');
                $order->delete_meta_data('_b2brouter_invoice_pdf_date');
                $order->save();

                return true;
            }
        }

        return false;
    }
}
