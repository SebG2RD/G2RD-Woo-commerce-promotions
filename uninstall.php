<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
// Retention is intentional: campaign history and order metadata are business records.
// To explicitly delete plugin configuration, define G2RD_PROMOTIONS_DELETE_DATA=true.
if (!defined('G2RD_PROMOTIONS_DELETE_DATA') || !G2RD_PROMOTIONS_DELETE_DATA) return;
global $wpdb;
$wpdb->query('DROP TABLE IF EXISTS '.$wpdb->prefix.'g2rd_promotions');
$wpdb->query('DROP TABLE IF EXISTS '.$wpdb->prefix.'g2rd_promotion_logs');
foreach(['g2rd_promo_schema','g2rd_promo_settings','g2rd_promo_cache_epoch','g2rd_promo_active_signature','g2rd_promo_sale_index','g2rd_promo_sale_build','g2rd_promo_sale_rev'] as $key) delete_option($key);
// Historical order items and amounts are never removed.
