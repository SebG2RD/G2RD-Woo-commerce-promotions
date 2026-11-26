<?php
namespace G2RD\Promotions;
if (!defined('ABSPATH')) { exit; }
final class Orders {
    public const META='_g2rd_promotion_snapshot';
    private Pricing $pricing;
    public function __construct(Pricing $pricing) { $this->pricing=$pricing; }
    public function register(): void {
        add_action('woocommerce_checkout_create_order_line_item',[$this,'classic_line'],20,4);
        add_action('woocommerce_store_api_checkout_update_order_from_request',[$this,'blocks_order'],100,2);
        add_filter('woocommerce_get_order_item_totals',[$this,'totals'],30,3);
        add_filter('woocommerce_order_item_display_meta_key',[$this,'display_key'],10,2);
        add_action('wpo_wcpdf_after_order_details',[$this,'pdf_summary'],20,2);
        add_filter('wpo_wcpdf_raw_order_totals',[$this,'pdf_totals'],20,2);
    }
    public function classic_line($item,$key,$values,$order): void {
        if($item instanceof \WC_Order_Item_Product) $item->update_meta_data('_g2rd_cart_key',$key);
        $this->snapshot($item,$values,$order);
    }
    private function snapshot($item,array $line,$order): void {
        if (empty($line['g2rd_promotion']) || !$item instanceof \WC_Order_Item_Product) return;
        $q=$line['g2rd_promotion']; $p=$line['data']??null; $qty=(float)$item->get_quantity();
        if(!$p instanceof \WC_Product || $qty<=0 || abs((float)$p->get_price()-(float)$q['price'])>0.00001) return;
        $base_net=(float)wc_get_price_excluding_tax($p,['qty'=>$qty,'price'=>$q['base']]);
        $base_gross=(float)wc_get_price_including_tax($p,['qty'=>$qty,'price'=>$q['base']]);
        $net=(float)$item->get_subtotal(); $gross=$net+(float)$item->get_subtotal_tax();
        $precision=wc_get_price_decimals();
        $q['quantity']=$qty; $q['base_net']=round($base_net,$precision); $q['base_gross']=round($base_gross,$precision);
        $q['saving_net']=round(max(0,$base_net-$net),$precision); $q['saving_gross']=round(max(0,$base_gross-$gross),$precision);
        $q['currency']=$order->get_currency()?:get_woocommerce_currency();
        $item->update_meta_data(self::META,$q);
        // Stable public key used by generic PDF plugins, emails and order details.
        $item->update_meta_data('Promotion G2RD',sprintf('%s (−%s %%)',$q['name'],wc_format_localized_decimal($q['rate'])));
        $item->update_meta_data('Économie G2RD HT',html_entity_decode(wp_strip_all_tags(wc_price($q['saving_net'],['currency'=>$q['currency']])),ENT_QUOTES,'UTF-8'));
    }
    public function blocks_order($order,$request): void {
        if(!$order instanceof \WC_Order || !WC()->cart) return;
        // Drafts may be recalculated many times. Never rewrite completed order history.
        if(!$order->has_status('checkout-draft')) return;
        $cart=WC()->cart->get_cart(); $used=[];
        foreach($order->get_items('line_item') as $item) {
            $saved_key=$item->get_meta('_g2rd_cart_key',true);$match=null;
            if(is_string($saved_key) && isset($cart[$saved_key])) $match=$saved_key;
            else {
                // Older integrations: only match an unambiguous cart line.
                $candidates=[];$product_id=$item->get_variation_id()?:$item->get_product_id();
                foreach($cart as $key=>$line) {
                    if(isset($used[$key])) continue;
                    $id=(int)(($line['variation_id']??0)?:($line['product_id']??0));
                    if($id!==$product_id || (float)$line['quantity']!==(float)$item->get_quantity()) continue;
                    if(abs((float)($line['line_subtotal']??-1)-(float)$item->get_subtotal())>0.005) continue;
                    if(abs((float)($line['line_total']??-1)-(float)$item->get_total())>0.005) continue;
                    $candidates[]=$key;
                }
                if(count($candidates)===1) $match=$candidates[0];
            }
            if($match===null || isset($used[$match])) continue;
            $used[$match]=true;
            $item->delete_meta_data(self::META);$item->delete_meta_data('Promotion G2RD');$item->delete_meta_data('Économie G2RD HT');
            $item->update_meta_data('_g2rd_cart_key',$match);
            $this->snapshot($item,$cart[$match],$order);$item->save();
        }
    }

    /** Stored keys stay stable (old orders included); only the label shown on pages, emails and PDFs changes. */
    public function display_key($key,$meta) {
        $labels=['Promotion G2RD'=>__('Promotion','g2rd-woocommerce-promotions'),'Économie G2RD HT'=>__('Savings (excl. VAT)','g2rd-woocommerce-promotions')];
        return is_object($meta) && isset($meta->key) && isset($labels[$meta->key]) ? $labels[$meta->key] : $key;
    }
    /** Rows for the WP Overnight Premium Templates "Custom function" block (see includes/functions.php). */
    public static function pdf_rows($order): array {
        if(!$order instanceof \WC_Order) return [];
        $sum=self::savings($order,get_option('woocommerce_tax_display_cart','excl')==='incl');
        return $sum>0?[['label'=>__('Total Savings','g2rd-woocommerce-promotions'),'value'=>wc_price($sum,['currency'=>$order->get_currency()])]]:[];
    }
    public static function savings($order,bool $incl): float {
        $sum=0.0; foreach($order->get_items('line_item') as $item) { $q=$item->get_meta(self::META,true); if(is_array($q)) $sum+=(float)($q[$incl?'saving_gross':'saving_net']??0); } return $sum;
    }
    public function totals($rows,$order,$tax_display) {
        $sum=self::savings($order,$tax_display==='incl'); if($sum<=0) return $rows;
        $out=[]; foreach($rows as $key=>$row) {
            if($key==='order_total') $out['g2rd_promotion_savings']=['label'=>__('Total Savings:','g2rd-woocommerce-promotions'),'value'=>wc_price($sum,['currency'=>$order->get_currency()])];
            $out[$key]=$row;
        } return $out;
    }
    public function pdf_totals($rows,$order) {
        if(!$order instanceof \WC_Order || isset($rows['g2rd_promotion_savings'])) return $rows;
        return $this->totals($rows,$order,get_option('woocommerce_tax_display_cart','excl'));
    }
    public function pdf_summary($type,$order): void {
        if($type!=='invoice' || !$order instanceof \WC_Order) return;
        // The item metadata is rendered by default. Add an informational total only
        // if the invoice template does not use our Woo totals row (opt-in).
        if(!apply_filters('g2rd_promotions_pdf_summary',false,$order)) return;
        $sum=self::savings($order,$order->get_prices_include_tax()); if($sum>0) echo '<p>'.esc_html__('Total Savings:','g2rd-woocommerce-promotions').' '.wp_kses_post(wc_price($sum,['currency'=>$order->get_currency()])).'</p>';
    }
}
