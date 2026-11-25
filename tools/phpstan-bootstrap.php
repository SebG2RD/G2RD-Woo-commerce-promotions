<?php
/**
 * Stubs minimaux pour PHPStan hors installation WordPress/WooCommerce.
 * Ne sont jamais chargés en production.
 */
if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}
if (!defined('DAY_IN_SECONDS')) {
	define('DAY_IN_SECONDS', 86400);
}
if (!defined('G2RD_PROMO_VERSION')) {
	define('G2RD_PROMO_VERSION', '0.0.0');
}
if (!defined('G2RD_PROMO_FILE')) {
	define('G2RD_PROMO_FILE', dirname(__DIR__) . '/g2rd-woocommerce-promotions.php');
}
if (!defined('WP_CLI')) {
	define('WP_CLI', false);
}
if (!defined('REST_REQUEST')) {
	define('REST_REQUEST', false);
}

if (!function_exists('WC')) {
	function WC() {
		return (object) [
			'cart' => null,
			'session' => null,
		];
	}
}
if (!function_exists('wc_get_product')) {
	function wc_get_product($id) {
		return false;
	}
}
if (!function_exists('wc_get_price_decimals')) {
	function wc_get_price_decimals() {
		return 2;
	}
}
if (!function_exists('wc_format_decimal')) {
	function wc_format_decimal($number, $dp = false, $trim_zeros = false) {
		return (string) $number;
	}
}
if (!function_exists('wc_format_localized_decimal')) {
	function wc_format_localized_decimal($value) {
		return (string) $value;
	}
}
if (!function_exists('wc_format_localized_price')) {
	function wc_format_localized_price($price) {
		return (string) $price;
	}
}
if (!function_exists('wc_format_sale_price')) {
	function wc_format_sale_price($regular, $sale) {
		return '';
	}
}
if (!function_exists('wc_get_price_to_display')) {
	function wc_get_price_to_display($product, $args = []) {
		return 0.0;
	}
}
if (!function_exists('wc_get_price_excluding_tax')) {
	function wc_get_price_excluding_tax($product, $args = []) {
		return 0.0;
	}
}
if (!function_exists('wc_get_price_including_tax')) {
	function wc_get_price_including_tax($product, $args = []) {
		return 0.0;
	}
}
if (!function_exists('wc_price')) {
	function wc_price($price, $args = []) {
		return '';
	}
}
if (!function_exists('wc_add_notice')) {
	function wc_add_notice($message, $notice_type = 'success', $data = []) {}
}
if (!function_exists('wc_has_notice')) {
	function wc_has_notice($message, $notice_type = 'success') {
		return false;
	}
}
if (!function_exists('wc_get_orders')) {
	function wc_get_orders($args = []) {
		return [];
	}
}
if (!function_exists('get_woocommerce_currency')) {
	function get_woocommerce_currency() {
		return 'EUR';
	}
}
if (!function_exists('get_woocommerce_currency_symbol')) {
	function get_woocommerce_currency_symbol($currency = '') {
		return '€';
	}
}
if (!function_exists('as_enqueue_async_action')) {
	function as_enqueue_async_action($hook, $args = [], $group = '', $unique = false) {
		return 0;
	}
}
if (!class_exists('WC_Coupon', false)) {
	class WC_Coupon {
		public function get_individual_use($context = 'view') { return false; }
	}
}
if (!function_exists('as_unschedule_all_actions')) {
	function as_unschedule_all_actions($hook, $args = [], $group = '') {}
}
if (!function_exists('as_schedule_single_action')) {
	function as_schedule_single_action($timestamp, $hook, $args = [], $group = '', $unique = false) {
		return 0;
	}
}
if (!function_exists('as_has_scheduled_action')) {
	function as_has_scheduled_action($hook, $args = null, $group = '') {
		return false;
	}
}
if (!function_exists('as_schedule_recurring_action')) {
	function as_schedule_recurring_action($timestamp, $interval, $hook, $args = [], $group = '', $unique = false) {
		return 0;
	}
}
if (!function_exists('rocket_clean_domain')) {
	function rocket_clean_domain() {}
}
if (!function_exists('wcpdf_get_document')) {
	function wcpdf_get_document($type, $order) {
		return false;
	}
}

if (!class_exists('WC_Product', false)) {
	class WC_Product {
		public function get_id() { return 0; }
		public function get_parent_id() { return 0; }
		public function is_type($type) { return false; }
		public function get_regular_price($context = 'view') { return ''; }
		public function get_sale_price($context = 'view') { return ''; }
		public function get_price($context = 'view') { return ''; }
		public function set_price($price) {}
		public function get_meta($key, $single = true, $context = 'view') { return ''; }
		public function get_status() { return 'publish'; }
		public function get_name() { return ''; }
		public function get_visible_children() { return []; }
		public function get_price_suffix($price = '', $qty = 1) { return ''; }
		public function is_on_sale($context = 'view') { return false; }
	}
}
if (!class_exists('WC_Order', false)) {
	class WC_Order {
		public function get_currency() { return 'EUR'; }
		public function get_items($type = 'line_item') { return []; }
		public function get_prices_include_tax() { return false; }
		public function has_status($status) { return false; }
		public function get_qty_refunded_for_item($item_id) { return 0; }
		public function get_total_refunded_for_item($item_id) { return 0; }
		public function get_id() { return 0; }
	}
}
if (!class_exists('WC_Order_Item_Product', false)) {
	class WC_Order_Item_Product {
		public function get_quantity() { return 0; }
		public function get_subtotal() { return 0; }
		public function get_subtotal_tax() { return 0; }
		public function get_total() { return 0; }
		public function get_product_id() { return 0; }
		public function get_variation_id() { return 0; }
		public function get_id() { return 0; }
		public function get_meta($key, $single = true) { return ''; }
		public function update_meta_data($key, $value) {}
		public function delete_meta_data($key) {}
		public function save() {}
	}
}
if (!class_exists('WC_Cache_Helper', false)) {
	class WC_Cache_Helper {
		public static function get_transient_version($group, $refresh = false) { return ''; }
	}
}
if (!class_exists('WC_Data_Store', false)) {
	class WC_Data_Store {
		public static function load($name) {
			return new self();
		}
		public function get_on_sale_products() {
			return [];
		}
	}
}
if (!class_exists('WP_Query', false)) {
	class WP_Query {
		/** @var array<int,int|string> */
		public $posts = [];
		public $found_posts = 0;
		public $max_num_pages = 0;
	}
}
