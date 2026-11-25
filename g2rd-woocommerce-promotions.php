<?php
/**
 * Plugin Name: G2RD WooCommerce Promotions
 * Description: Campagnes promotionnelles par label, catégorie et profil client, intégrées aux prix WooCommerce.
 * Version: 1.0.3
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: G2RD Agence Web
 * Author URI: https://g2rd.fr/
 * Text Domain: g2rd-woocommerce-promotions
 * License: GPL-2.0-or-later
 * Update URI: https://github.com/SebG2RD/G2RD-Woo-commerce-promotions
 */
namespace G2RD\Promotions;
if (!defined('ABSPATH')) { exit; }
define('G2RD_PROMO_VERSION', '1.0.3');
define('G2RD_PROMO_FILE', __FILE__);
require_once __DIR__.'/includes/Rules.php';
require_once __DIR__.'/includes/Repository.php';
require_once __DIR__.'/includes/Pricing.php';
require_once __DIR__.'/includes/Orders.php';
require_once __DIR__.'/includes/Admin.php';
require_once __DIR__.'/includes/Updater.php';
register_activation_hook(__FILE__, [Repository::class, 'install']);
register_deactivation_hook(__FILE__, [Repository::class, 'deactivate']);
// Updater hors du gate WooCommerce : les notifications de MAJ restent actives même si WC est absent.
Updater::register();
add_action('before_woocommerce_init', static function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
add_action('plugins_loaded', static function () {
    load_plugin_textdomain('g2rd-woocommerce-promotions', false, dirname(plugin_basename(__FILE__)).'/languages');
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', static function () { if (current_user_can('activate_plugins')) echo '<div class="notice notice-error"><p>G2RD Promotions : WooCommerce est requis.</p></div>'; });
        return;
    }
    if (get_option('g2rd_promo_schema') !== '1') Repository::install();
    if (get_option('g2rd_promo_schema') !== '1') {
        add_action('admin_notices', static function () { if (current_user_can('manage_woocommerce')) echo '<div class="notice notice-error"><p>G2RD Promotions : tables non disponibles. Moteur désactivé par sécurité.</p></div>'; });
        return;
    }
    $repo = new Repository();
    $pricing = new Pricing($repo);
    $orders = new Orders($pricing);
    $pricing->register(); $orders->register();
    (new Admin($repo, $pricing))->register();
    add_action('g2rd_promo_boundary', [Repository::class, 'invalidate']);
    add_action('g2rd_promo_reconcile', [Repository::class, 'reconcile']);
    add_action('init', [Repository::class, 'schedule_reconcile']);
    add_filter('plugin_action_links_'.plugin_basename(__FILE__), static function ($links) {
        if (current_user_can('manage_woocommerce')) array_unshift($links, '<a href="'.esc_url(admin_url('admin.php?page=g2rd-promotions')).'">Promotions</a>');
        return $links;
    });
});
