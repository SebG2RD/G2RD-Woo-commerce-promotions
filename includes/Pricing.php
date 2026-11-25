<?php
namespace G2RD\Promotions;
if (!defined('ABSPATH')) { exit; }
final class Pricing {
    public const BUILD='g2rd_promo_build_sale_index';
    private const INDEX='g2rd_promo_sale_index';
    private const BUILD_STATE='g2rd_promo_sale_build';
    private const REV='g2rd_promo_sale_rev';
    private const BATCH=200;
    private Repository $repo;
    private \WeakMap $cart_prices;
    private array $terms=[];
    private array $quotes=[];
    private bool $reading_sale_ids=false;
    private ?array $sale_ids=null;
    private bool $checking_input=false;
    private array $previous_quotes=[];
    private bool $refreshing_cart=false;
    public function __construct(Repository $repo) { $this->repo=$repo; $this->cart_prices=new \WeakMap(); }
    public function register(): void {
        foreach (['woocommerce_product_get_', 'woocommerce_product_variation_get_'] as $prefix) {
            add_filter($prefix.'price',[$this,'price'],9999,2);
            add_filter($prefix.'regular_price',[$this,'regular'],9999,2);
            add_filter($prefix.'sale_price',[$this,'sale'],9999,2);
        }
        add_filter('woocommerce_variation_prices_price',[$this,'price'],9999,2);
        add_filter('woocommerce_variation_prices_regular_price',[$this,'regular'],9999,2);
        add_filter('woocommerce_variation_prices_sale_price',[$this,'sale'],9999,2);
        add_filter('woocommerce_get_variation_prices_hash',[$this,'variation_hash'],9999,3);
        add_filter('woocommerce_product_is_on_sale',[$this,'on_sale'],9999,2);
        add_filter('woocommerce_get_price_html',[$this,'price_html'],9999,2);
        add_action('woocommerce_cart_loaded_from_session',[$this,'refresh_cart'],20);
        add_action('woocommerce_before_calculate_totals',[$this,'reset_cart'],-1000);
        add_action('woocommerce_before_calculate_totals',[$this,'apply_cart'],99999);
        add_filter('woocommerce_get_item_data',[$this,'cart_display'],10,2);
        add_filter('woocommerce_coupon_is_valid',[$this,'coupon_valid'],9999,2);
        add_action('woocommerce_check_cart_items',[$this,'check_coupons']);
        add_action('woocommerce_store_api_cart_errors',[$this,'store_coupon_errors'],10,2);
        add_action('woocommerce_cart_totals_before_order_total',[$this,'cart_savings']);
        add_action('woocommerce_review_order_before_order_total',[$this,'cart_savings']);
        add_filter('pre_transient_wc_products_onsale',[$this,'on_sale_ids'],9999);
        add_action('template_redirect',[$this,'cache_control'],0);
        add_action('woocommerce_after_product_object_save',[$this,'product_changed']);
        add_action('set_object_terms',[$this,'terms_changed'],10,6);
        add_action(self::BUILD,[$this,'build_sale_index']);
        add_action('g2rd_promotions_cache_invalidated',fn()=>$this->request_sale_index());
    }
    /** Storefront only: never alter editing, management REST, cron or CLI prices. */
    public function storefront(): bool {
        if (defined('WP_CLI') && WP_CLI || wp_doing_cron()) return false;
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $route=(string)($GLOBALS['wp']->query_vars['rest_route']??'');
            $uri=isset($_SERVER['REQUEST_URI'])?wp_unslash($_SERVER['REQUEST_URI']):'';
            return str_starts_with($route,'/wc/store/') || (bool)preg_match('~(?:/wp-json|/index\.php/wp-json)/wc/store/|[?&]rest_route=(?:%2F|/)wc(?:%2F|/)store(?:%2F|/)~i',$uri);
        }
        if (is_admin()) {
            if(!wp_doing_ajax()) return false;
            // Do not change the prices of order edits or other privileged admin AJAX.
            if(current_user_can('manage_woocommerce')) {
                $action=isset($_REQUEST['action'])&&is_string($_REQUEST['action'])?sanitize_key(wp_unslash($_REQUEST['action'])):'';
                $allowed=['woocommerce_get_refreshed_fragments','woocommerce_add_to_cart','woocommerce_checkout','woocommerce_get_variation','woocommerce_update_order_review','woocommerce_apply_coupon','woocommerce_remove_coupon'];
                if(!apply_filters('g2rd_promotions_frontend_ajax',in_array($action,$allowed,true),$action)) return false;
            }
        }
        return true;
    }
    public function context(): array {
        $s=$this->repo->settings(); $roles=(array)wp_get_current_user()->roles;
        if (!is_user_logged_in()) return ['profile'=>'guest','role'=>''];
        // Configuration order is authoritative if a user has several wholesale roles.
        foreach($s['wholesale_roles'] as $role) if(in_array($role,$roles,true)) return ['profile'=>'wholesale','role'=>$role];
        // Fail closed for common unconfigured wholesale roles.
        foreach($roles as $role) if(preg_match('/wholesale|b2b/i',$role)) return ['profile'=>'wholesale','role'=>$role];
        return ['profile'=>'retail','role'=>''];
    }
    private function product_terms(int $id,string $tax): array {
        $key=$id.':'.$tax;
        if(isset($this->terms[$key])) return $this->terms[$key];
        if(!taxonomy_exists($tax)) return [];
        $ids=wp_get_object_terms($id,$tax,['fields'=>'ids']);
        if(is_wp_error($ids)) return [];
        $result=array_map('intval',$ids);
        if(is_taxonomy_hierarchical($tax)) foreach($result as $i) $result=array_merge($result,array_map('intval',get_ancestors($i,$tax,'taxonomy')));
        return $this->terms[$key]=array_values(array_unique($result));
    }
    public function facts(\WC_Product $p,array $context,string $tax): array {
        $parent=$p->get_parent_id(); $id=$parent?:$p->get_id();
        return ['profile'=>$context['profile'],'ids'=>array_values(array_unique([$id,$p->get_id()])), 'categories'=>$this->product_terms($id,'product_cat'),'brands'=>$this->product_terms($id,$tax)];
    }
    public function base(\WC_Product $p,array $context): ?float {
        $s=$this->repo->settings();
        if($context['profile']!=='wholesale') {
            $base=$p->get_regular_price('edit');
            return $base!=='' && is_numeric($base) && (float)$base>0 ? (float)$base : null;
        }
        if (!in_array($context['role'],$s['wholesale_roles'],true)) return null;
        $base=null;
        if($s['wholesale_mode']==='meta' && $s['wholesale_key']!=='') {
            $key=str_replace('{role}',$context['role'],$s['wholesale_key']);
            $base=$p->get_meta($key,true,'edit');
        }
        if($s['wholesale_mode']!=='off') $base=apply_filters('g2rd_promotions_wholesale_base_price',$base,$p,$context['role']);
        return $base!=='' && is_numeric($base) && (float)$base>0 ? (float)$base : null;
    }
    private function wholesale_sale(\WC_Product $p,array $context): bool {
        if($context['profile']!=='wholesale') return false;
        $s=$this->repo->settings(); $has=false;
        if($s['wholesale_sale_key']!=='') {
            $v=$p->get_meta(str_replace('{role}',$context['role'],$s['wholesale_sale_key']),true,'edit');
            $has=$v!=='' && is_numeric($v);
        }
        return (bool)apply_filters('g2rd_promotions_has_wholesale_sale',$has,$p,$context['role']);
    }
    /** Authoritative quote; no calls to view getters, so no recursive filters. */
    public function quote(\WC_Product $p,?array $context=null,?array $campaigns=null,?int $now=null): ?array {
        $context ??= $this->context(); $now ??= time();
        $campaigns ??= $this->repo->active($now);
        if (!$campaigns || !$p->is_type(['simple','variation']) || $this->wholesale_sale($p,$context)) return null;
        $s=$this->repo->settings(); $facts=$this->facts($p,$context,$s['brand_taxonomy']);
        if(array_intersect($s['exclude_products'],$facts['ids']) || array_intersect($s['exclude_categories'],$facts['categories']) || array_intersect($s['exclude_brands'],$facts['brands'])) return null;
        // WooCommerce's own test, sale dates included; the edit context runs no filters.
        $native=$p->is_on_sale('edit');
        $best=null; $winner=null; $base=null; $decimals=wc_get_price_decimals();
        foreach($campaigns as $c) {
            if(!Rules::active($c,$now) || ($native && $c['on_sale']!=='include')) continue;
            // Do not reinterpret stored term IDs after a taxonomy switch.
            if($c['brand_taxonomy']!==$s['brand_taxonomy'] || (!taxonomy_exists($c['brand_taxonomy']) && Rules::uses_brands($c))) continue;
            $i=Rules::winner($c,$facts); if($i===null) continue;
            $base ??= $this->base($p,$context); if($base===null) return null;
            $price=Rules::price($base,$c['rules'][$i],$decimals);
            // Campaigns never stack: the lowest price wins, then the oldest campaign.
            if($price<$base && (!$best || $price<$best['price'] || ($price==$best['price'] && $c['id']<$best['id']))) { $best=['id'=>$c['id'],'name'=>$c['name'],'rule'=>$i,'price'=>$price]; $winner=$c; }
        }
        if(!$best || !apply_filters('g2rd_promotions_product_eligible',true,$p,$context,$winner)) return null;
        return $best+['profile'=>$context['profile'],'role'=>$context['role'],'base'=>$base,'rate'=>round(($base-$best['price'])/$base*100,2),'replaces_sale'=>$native,'currency'=>get_woocommerce_currency()];
    }
    private function same($a,$b): bool { return is_numeric($a) && is_numeric($b) && abs((float)$a-(float)$b)<0.00001; }
    private function compatible_input($input,\WC_Product $p,array $q): bool {
        // A third-party discount or price override is not silently replaced.
        return $this->same($input,$q['base'])
            || (!empty($q['replaces_sale']) && $this->same($input,$p->get_sale_price('edit')))
            || ($q['profile']==='wholesale' && $this->same($input,$p->get_regular_price('edit')));
    }
    private function raw_view_quote(\WC_Product $p): ?array {
        if($this->checking_input || !$this->storefront()) return null;
        if(isset($this->cart_prices[$p])) return $this->cart_prices[$p]['quote'];
        $c=$this->context(); $key=$p->get_id().':'.$c['profile'].':'.$c['role'].':'.get_woocommerce_currency().':'.$p->get_regular_price('edit').':'.$p->get_sale_price('edit').':'.implode(',',array_column($this->repo->active(),'id'));
        if(!array_key_exists($key,$this->quotes)) $this->quotes[$key]=$this->quote($p,$c);
        return $this->quotes[$key];
    }
    private function view_quote(\WC_Product $p): ?array {
        $q=$this->raw_view_quote($p);
        if(!$q || isset($this->cart_prices[$p])) return $q;
        // Read the effective price without our own filters to protect external pricing.
        $this->checking_input=true;
        try { $input=$p->get_price(); } finally { $this->checking_input=false; }
        return $this->compatible_input($input,$p,$q) ? $q : null;
    }
    public function price($value,$p) {
        if($this->checking_input || !$p instanceof \WC_Product || !$this->storefront()) return $value;
        if(isset($this->cart_prices[$p])) return wc_format_decimal($this->cart_prices[$p]['quote']['price'],wc_get_price_decimals());
        $q=$this->raw_view_quote($p);
        return $q && $this->compatible_input($value,$p,$q) ? wc_format_decimal($q['price'],wc_get_price_decimals()) : $value;
    }
    public function regular($value,$p) {
        if(!$p instanceof \WC_Product) return $value;
        $q=$this->view_quote($p);
        return $q ? wc_format_decimal($q['base'],wc_get_price_decimals()) : $value;
    }
    public function sale($value,$p) {
        if(!$p instanceof \WC_Product) return $value;
        $q=$this->view_quote($p);
        return $q ? wc_format_decimal($q['price'],wc_get_price_decimals()) : $value;
    }
    public function on_sale($value,$p) { return $value || ($p instanceof \WC_Product && $this->view_quote($p)!==null); }
    public function price_html($html,$p) {
        if(!$p instanceof \WC_Product || !$this->storefront()) return $html;
        $q=$this->view_quote($p);
        if(!$q || !$this->same($p->get_price(),$q['price'])) return $html;
        if($q['profile']!=='wholesale') return $html;
        return wc_format_sale_price(wc_get_price_to_display($p,['price'=>$q['base']]),wc_get_price_to_display($p,['price'=>$q['price']])).$p->get_price_suffix($q['price']);
    }
    public function variation_hash($hash,$p,$display) {
        if(!$this->storefront()) return $hash;
        $c=$this->context(); $hash['g2rd']=[get_option('g2rd_promo_cache_epoch',''),array_column($this->repo->active(),'id'),$c['profile'],$c['role'],get_woocommerce_currency()];
        // User-specific adapters may produce different values for the same role.
        if($c['profile']==='wholesale') $hash['g2rd_user']=get_current_user_id();
        return $hash;
    }
    public function refresh_cart($cart): void {
        if($this->refreshing_cart || !$this->storefront()) return;
        $lines=$cart->get_cart();if(!$lines) return;
        $needs_refresh=(bool)$this->repo->active();
        foreach ($lines as $line) {
            if (!empty($line['g2rd_promotion'])) {
                $needs_refresh = true;
                break;
            }
        }
        if(!$needs_refresh) return;
        $this->refreshing_cart=true;
        try {$cart->calculate_totals();} finally {$this->refreshing_cart=false;}
    }
    public function reset_cart($cart): void {
        $this->previous_quotes=[];
        foreach($cart->get_cart() as $key=>$line) {
            $p=$line['data']??null; if(!$p instanceof \WC_Product) continue;
            if(isset($this->cart_prices[$p])) { $previous=$this->cart_prices[$p]; if($this->same($p->get_price('edit'),$previous['quote']['price'])) $p->set_price($previous['original']); unset($this->cart_prices[$p]); }
            if(!empty($line['g2rd_promotion'])) $this->previous_quotes[$key]=$line['g2rd_promotion'];
            unset($cart->cart_contents[$key]['g2rd_promotion']);
        }
    }
    public function apply_cart($cart): void {
        if(!$this->storefront()) return;
        foreach($cart->get_cart() as $key=>$line) {
            $p=$line['data']??null; if(!$p instanceof \WC_Product) continue;
            // Fresh product avoids discounting a previously mutated cart price.
            $fresh=wc_get_product($p->get_id()); if(!$fresh) continue;
            $q=$this->quote($fresh); if(!$q) continue;
            $original=$p->get_price('edit');
            if(!$this->compatible_input($original,$fresh,$q)) continue;
            // Also respect view-level pricing filters that run before ours.
            if(!$this->same($p->get_price(),$q['price'])) continue;
            // Clone so separate cart lines never share a modified product object.
            $p=clone $p; $p->set_price(wc_format_decimal($q['price'],wc_get_price_decimals()));
            $this->cart_prices[$p]=['original'=>$original,'quote'=>$q];
            $cart->cart_contents[$key]['data']=$p;
            $cart->cart_contents[$key]['g2rd_promotion']=$q;
        }
        if(function_exists('wc_add_notice') && function_exists('wc_has_notice') && WC()->session) {
            foreach($this->previous_quotes as $key=>$old) {
                $new=$cart->cart_contents[$key]['g2rd_promotion']??null;
                if(!$new || !$this->same($new['price'],$old['price']??null)) {
                    $notice=__('Une promotion de ton panier a changé. Les prix ont été actualisés.','g2rd-woocommerce-promotions');
                    if(!wc_has_notice($notice,'notice')) wc_add_notice($notice,'notice');
                    break;
                }
            }
        }
    }
    public function cart_display($data,$line) {
        if(!empty($line['g2rd_promotion'])) { $q=$line['g2rd_promotion']; $data[]=['key'=>__('Promotion','g2rd-woocommerce-promotions'),'value'=>sprintf('%s (−%s %%)',esc_html($q['name']),wc_format_localized_decimal($q['rate']))]; }
        return $data;
    }
    private function has_promo($cart): bool {
        foreach($cart->get_cart() as $line) {
            $p=$line['data']??null;
            if($p instanceof \WC_Product && $this->view_quote($p)) return true;
        }
        return false;
    }
    /** Only "Individual use only" coupons conflict with campaigns; stackable ones (e.g. refund coupons) stay valid. */
    private function blocked_coupons($cart): bool {
        foreach($cart->get_coupons() as $coupon) if($coupon instanceof \WC_Coupon && $coupon->get_individual_use()) return $this->has_promo($cart);
        return false;
    }
    public function coupon_valid($valid,$coupon) {
        if(!$valid || !$coupon instanceof \WC_Coupon || !$coupon->get_individual_use() || !$this->storefront() || !WC()->cart) return $valid;
        return !$this->has_promo(WC()->cart);
    }
    public function check_coupons(): void {
        if(!$this->storefront() || !WC()->cart || !$this->blocked_coupons(WC()->cart)) return;
        $message=__('Un code promo à usage individuel ne se cumule pas avec les articles en promotion de ton panier. Retire le code pour continuer.','g2rd-woocommerce-promotions');
        if(!wc_has_notice($message,'error')) wc_add_notice($message,'error');
    }
    public function store_coupon_errors($errors,$cart): void {
        if($cart && $this->blocked_coupons($cart)) $errors->add('g2rd_promo_coupon_conflict',__('Retire le code promo à usage individuel : il ne se cumule pas avec les articles en promotion.','g2rd-woocommerce-promotions'));
    }
    public function cart_savings(): void {
        if(!WC()->cart) return;
        $cart=WC()->cart; $incl=$cart->display_prices_including_tax();$saving=0;
        foreach($cart->get_cart() as $line) {
            $q=$line['g2rd_promotion']??null;$p=$line['data']??null;
            if(!$q || !$p instanceof \WC_Product) continue;
            $args=['qty'=>$line['quantity'],'price'=>$q['base']];
            $base=$incl?wc_get_price_including_tax($p,$args):wc_get_price_excluding_tax($p,$args);
            $net=(float)($line['line_subtotal']??0)+($incl?(float)($line['line_subtotal_tax']??0):0);
            $saving+=max(0,$base-$net);
        }
        if($saving>0) echo '<tr class="g2rd-promotion-savings"><th>'.esc_html__('Économie G2RD (déjà déduite)','g2rd-woocommerce-promotions').'</th><td>'.wp_kses_post(wc_price($saving)).'</td></tr>';
    }
    /** Published parent product IDs that may match: OR between rules, AND between label and category. */
    public function candidate_ids(array $campaigns): array {
        $s=$this->repo->settings(); $union=['relation'=>'OR']; $products=[];
        foreach($campaigns as $c) {
            if($c['brand_taxonomy']!==$s['brand_taxonomy']) continue;
            foreach($c['rules'] as $r) {
                if($r['products']) { foreach($r['products'] as $id) $products[]=wp_get_post_parent_id($id)?:$id; continue; }
                $group=['relation'=>'AND'];
                if($r['brands']) {if(!taxonomy_exists($c['brand_taxonomy'])) continue; $group[]=['taxonomy'=>$c['brand_taxonomy'],'field'=>'term_id','terms'=>$r['brands'],'include_children'=>true];}
                if($r['categories']) $group[]=['taxonomy'=>'product_cat','field'=>'term_id','terms'=>$r['categories'],'include_children'=>true];
                if(count($group)===1) return $this->query_ids([]);
                $union[]=$group;
            }
        }
        $ids=[];
        if($products) $ids=$this->query_ids(['post__in'=>$products]);
        if(count($union)>1) $ids=array_merge($ids,$this->query_ids(['tax_query'=>$union]));
        $ids=array_values(array_unique($ids)); sort($ids);
        return $ids;
    }
    private function query_ids(array $extra): array {
        // Pas de suppress_filters : interdit par Plugin Check / VIP ; le flag
        // g2rd_promo_candidates permet aux intégrations de reconnaître cette requête.
        $args=['post_type'=>'product','post_status'=>'publish','fields'=>'ids','posts_per_page'=>500,'orderby'=>'ID','order'=>'ASC','ignore_sticky_posts'=>true,'g2rd_promo_candidates'=>true]+$extra;
        $ids=[]; $page=1;
        do { $query=new \WP_Query($args+['paged'=>$page++]); $ids=array_merge($ids,array_map('intval',$query->posts)); } while($page<=$query->max_num_pages);
        return $ids;
    }
    public function leaves(\WC_Product $p): array {
        if(!$p->is_type('variable')) return [$p];
        $out=[]; foreach($p->get_visible_children() as $id) { $v=wc_get_product($id); if($v && $v->get_status()==='publish') $out[]=$v; } return $out;
    }
    /** Native on-sale list + the background index. Never scans the catalogue during a visit. */
    public function on_sale_ids($pre) {
        if($this->reading_sale_ids || $pre!==false || !$this->storefront()) return $pre;
        if($this->sale_ids!==null) return $this->sale_ids;
        $ctx=$this->context();
        if(!array_filter($this->repo->active(),static fn($c)=>in_array($ctx['profile'],$c['profiles'],true))) return $pre;
        $index=get_option(self::INDEX);
        // Stale index (campaign boundary, edit): WooCommerce's native list until the rebuild lands.
        if(!is_array($index) || ($index['sig']??'')!==$this->index_signature()) { $this->request_sale_index(); return $pre; }
        $this->reading_sale_ids=true;
        try {
            $native=get_transient('wc_products_onsale');
            if($native===false) { $rows=\WC_Data_Store::load('product')->get_on_sale_products(); $native=wp_parse_id_list(array_merge(wp_list_pluck($rows,'id'),array_diff(wp_list_pluck($rows,'parent_id'),[0]))); }
        } finally { $this->reading_sale_ids=false; }
        return $this->sale_ids=array_values(array_unique(array_map('intval',array_merge($native,$index['ids'][$ctx['profile'].':'.$ctx['role']]??[]))));
    }
    private function index_signature(): string {
        return hash('sha256',get_option('g2rd_promo_cache_epoch','').wp_json_encode(array_column($this->repo->active(),'id')));
    }
    /** Queue a rebuild; $products_changed restarts a running build so edited products are recomputed. */
    public function request_sale_index(bool $products_changed=false): void {
        if($products_changed) update_option(self::REV,wp_generate_uuid4(),false);
        $this->enqueue_build(true);
    }
    private function enqueue_build(bool $unique): void {
        if(function_exists('as_enqueue_async_action') && did_action('action_scheduler_init')) as_enqueue_async_action(self::BUILD,[],'g2rd-promotions',$unique);
        elseif(!$unique || !wp_next_scheduled(self::BUILD)) wp_schedule_single_event(time(),self::BUILD);
    }
    /** Background job: one batch of parent products per run, then re-queues itself. */
    public function build_sale_index(): void {
        $sig=$this->index_signature(); $rev=(string)get_option(self::REV,''); $campaigns=$this->repo->active();
        $build=get_option(self::BUILD_STATE);
        if(!is_array($build) || $build['sig']!==$sig || $build['rev']!==$rev) $build=['sig'=>$sig,'rev'=>$rev,'offset'=>0,'ids'=>[]];
        $candidates=$campaigns?$this->candidate_ids($campaigns):[];
        $contexts=$this->index_contexts($campaigns);
        foreach(array_slice($candidates,$build['offset'],self::BATCH) as $id) {
            $p=wc_get_product($id); if(!$p) continue;
            foreach($this->leaves($p) as $v) foreach($contexts as $key=>$ctx) {
                $q=$this->quote($v,$ctx,$campaigns);
                // Raw price: storefront-only price filters do not run in the background.
                if($q && $this->compatible_input($v->get_price('edit'),$v,$q)) { $build['ids'][$key][]=$id; $build['ids'][$key][]=$v->get_id(); }
            }
        }
        $build['offset']+=self::BATCH; $this->terms=[];
        if($build['offset']<count($candidates)) { update_option(self::BUILD_STATE,$build,false); $this->enqueue_build(false); return; }
        update_option(self::INDEX,['sig'=>$sig,'ids'=>array_map(static fn($ids)=>array_values(array_unique($ids)),$build['ids'])],false);
        delete_option(self::BUILD_STATE);
    }
    /** shortcut: wholesale contexts are per role, so user-specific wholesale adapters are not reflected; upgrade if such an adapter is used. */
    private function index_contexts(array $campaigns): array {
        $out=[];
        foreach($campaigns as $c) foreach($c['profiles'] as $profile) {
            foreach($profile==='wholesale'?$this->repo->settings()['wholesale_roles']:[''] as $role) $out[$profile.':'.$role]=['profile'=>$profile,'role'=>$role];
        }
        return $out;
    }
    public function cache_control(): void {
        // Boundaries purge page caches (Repository::invalidate), so caching is only stopped while a campaign runs.
        if(!$this->repo->settings()['cache_safe'] || !$this->repo->active()) return;
        // Conservative: promotional products may be embedded anywhere (including home).
        if(!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE',true);
        nocache_headers();
    }
    public function product_changed($p): void { if($this->repo->upcoming()) { $this->quotes=[]; $this->sale_ids=null; $this->terms=[]; if($this->repo->active()) $this->request_sale_index(true); } }
    public function terms_changed($id,$terms,$tt_ids,$taxonomy,$append,$old): void { if(in_array($taxonomy,['product_cat',$this->repo->settings()['brand_taxonomy']],true)) { $this->terms=[];$this->quotes=[];$this->sale_ids=null; if($this->repo->active()) $this->request_sale_index(true); } }
}
