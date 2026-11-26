<?php
/**
 * Global functions (no namespace): WP Overnight Premium Templates calls a
 * "Custom function" block by its plain name.
 */
if (!defined('ABSPATH')) { exit; }

/**
 * Invoice "Total Savings" row. In WooCommerce > PDF Invoices > Customizer, add a
 * "Custom function" block to the Total Rows with this function name.
 *
 * @param object $document WPO document; its public $order holds the WC_Order.
 */
function g2rd_promotions_total_savings($document): array {
    return \G2RD\Promotions\Orders::pdf_rows(is_object($document) ? ($document->order ?? null) : null);
}
