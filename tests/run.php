<?php
/** Standalone PHP tests: mocked WP/WC APIs, no database or network. */
namespace {
    if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
    define('ABSPATH',__DIR__.'/');
    $GLOBALS['opts']=[];$GLOBALS['roles']=[];$GLOBALS['logged']=false;$GLOBALS['admin']=false;$GLOBALS['cartobj']=(object)['session'=>null,'cart'=>null];
    $GLOBALS['termmap']=[10=>['product_brand'=>[1],'product_cat'=>[11]],20=>['product_brand'=>[2],'product_cat'=>[12]],30=>['product_brand'=>[1],'product_cat'=>[13]]];
    function get_option($k,$default=false){return $GLOBALS['opts'][$k]??$default;}
    function wp_get_current_user(){return (object)['roles'=>$GLOBALS['roles']];}
    function is_user_logged_in(){return $GLOBALS['logged'];}
    function get_current_user_id(){return $GLOBALS['logged']?123:0;}
    function wp_doing_cron(){return false;}function wp_doing_ajax(){return false;}function is_admin(){return $GLOBALS['admin'];}
    function taxonomy_exists($t){return in_array($t,['product_cat','product_brand'],true);}
    function is_taxonomy_hierarchical($t){return $t==='product_cat';}
    function get_ancestors($id,$tax,$type){return $id===13?[11]:[];}
    function wp_get_object_terms($id,$tax,$args){return $GLOBALS['termmap'][$id][$tax]??[];}
    function is_wp_error($v){return $v instanceof \WP_Error;}
    function get_woocommerce_currency(){return 'EUR';}function wc_get_price_decimals(){return 2;}
    function wc_format_decimal($x,$n){return number_format((float)$x,$n,'.','');}
    function wc_format_localized_decimal($x){return (string)$x;}
    function apply_filters($tag,$value,...$args){
        if($tag==='g2rd_promo_github_release' && array_key_exists('g2rd_release_mock',$GLOBALS)) return $GLOBALS['g2rd_release_mock'];
        return $value;
    }
    function __($s,$d=''){return $s;}
    function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
    function plugin_basename($file){return 'g2rd-woocommerce-promotions/g2rd-woocommerce-promotions.php';}
    function get_transient($k){return $GLOBALS['transients'][$k]??false;}
    function set_transient($k,$v,$t=0){$GLOBALS['transients'][$k]=$v;return true;}
    function delete_transient($k){unset($GLOBALS['transients'][$k]);return true;}
    function wp_remote_get($url,$args=[]){return $GLOBALS['http_response']??new \WP_Error('http','blocked');}
    function wp_remote_retrieve_body($r){return is_array($r)?($r['body']??''):'';}
    function wp_remote_retrieve_response_code($r){return is_array($r)?(int)($r['code']??0):0;}
    class WP_Error {function __construct(public $code='',public $message=''){}}
    function WC(){return $GLOBALS['cartobj'];}
    function wc_get_product($id){return isset($GLOBALS['products'][$id])?clone $GLOBALS['products'][$id]:false;}
    function wc_get_price_excluding_tax($p,$args){return $args['price']*$args['qty'];}
    function wc_get_price_including_tax($p,$args){return $args['price']*$args['qty']*1.2;}
    function wc_price($p,$args=[]){return number_format($p,2).' €';}
    function wp_strip_all_tags($v){return strip_tags($v);}
    function update_option($k,$v,$autoload=null){$GLOBALS['opts'][$k]=$v;return true;}
    function delete_option($k){unset($GLOBALS['opts'][$k]);return true;}
    function wp_generate_uuid4(){return uniqid('',true);}
    function wp_json_encode($v){return json_encode($v);}
    function did_action($h){return 0;}
    function wp_next_scheduled($h,$a=[]){return $GLOBALS['scheduled'][$h]??false;}
    function wp_schedule_single_event($t,$h,$a=[]){$GLOBALS['scheduled'][$h]=$t;return true;}
    function wp_get_post_parent_id($id){return isset($GLOBALS['products'][$id])?$GLOBALS['products'][$id]->parent:0;}
    /** Ignores tax_query: returns every parent product, a superset the engine then filters. */
    class WP_Query {public array $posts;public int $max_num_pages=1;function __construct(array $a){$ids=array_keys(array_filter($GLOBALS['products'],static fn($p)=>$p->type!=='variation'));$this->posts=isset($a['post__in'])?array_values(array_intersect($ids,$a['post__in'])):$ids;}}
    class WC_Coupon {function __construct(public bool $individual){}function get_individual_use(){return $this->individual;}}
    class MockErrors {public array $codes=[];function add($code,$message){$this->codes[]=$code;}}
    class WC_Product {
        public string $price;public array $meta=[];public ?string $other_price=null;public ?int $sale_from=null;public ?int $sale_to=null;
        public function __construct(public int $id,public string $regular='30',public string $sale='',public string $type='simple',public int $parent=0){$this->price=$sale!==''?$sale:$regular;}
        function is_on_sale($context='view'){return $this->sale!=='' && (float)$this->regular>(float)$this->sale && ($this->sale_from===null||$this->sale_from<=time()) && ($this->sale_to===null||$this->sale_to>time());}
        function get_id(){return $this->id;}function get_parent_id(){return $this->parent;}function is_type($t){return in_array($this->type,(array)$t,true);}
        function get_regular_price($context='view'){return $context==='edit'?$this->regular:$GLOBALS['pricing']->regular($this->regular,$this);}
        function get_sale_price($context='view'){return $context==='edit'?$this->sale:$GLOBALS['pricing']->sale($this->sale,$this);}
        function get_price($context='view'){return $context==='edit'?$this->price:$GLOBALS['pricing']->price($this->other_price??$this->price,$this);}
        function set_price($p){$this->price=(string)$p;}function get_meta($key,$single=true,$context='view'){return $this->meta[$key]??'';}
    }
    class WC_Order_Item_Product {
        public array $meta=[];function __construct(public float $qty,public float $subtotal,public float $tax){}
        function get_quantity(){return $this->qty;}function get_subtotal(){return $this->subtotal;}function get_subtotal_tax(){return $this->tax;}
        function update_meta_data($k,$v){$this->meta[$k]=$v;}function get_meta($k,$single=true){return $this->meta[$k]??'';}
    }
    class WC_Order {function __construct(public array $items=[]){ }function get_currency(){return 'EUR';}function get_items($t='line_item'){return $this->items;}}
    class MockCart {public array $coupons=[];function __construct(public array $cart_contents){}function get_cart(){return $this->cart_contents;}function get_coupons(){return $this->coupons;}}
}
namespace G2RD\Promotions {
    if(!defined('G2RD_PROMO_VERSION')) define('G2RD_PROMO_VERSION','1.1.1');
    if(!defined('G2RD_PROMO_FILE')) define('G2RD_PROMO_FILE',__DIR__.'/../g2rd-woocommerce-promotions.php');
    require_once __DIR__.'/../includes/Rules.php';require_once __DIR__.'/../includes/Repository.php';require_once __DIR__.'/../includes/Pricing.php';require_once __DIR__.'/../includes/Orders.php';require_once __DIR__.'/../includes/Updater.php';
    $tests=0;$fail=0;$GLOBALS['transients']=[];
    function expect($actual,$expected,string $label):void {global $tests,$fail;$tests++;if($actual!==$expected){$fail++;echo "FAIL $label: ".var_export($actual,true)." expected ".var_export($expected,true)."\n";}else echo "PASS $label\n";}
    /** One rule of a campaign. */
    function r(array $o=[]):array{return array_merge(['type'=>'percent','value'=>20,'brands'=>[],'categories'=>[],'products'=>[],'priority'=>0],$o);}
    /** A campaign; by default a single percentage rule on label 1 × category 11. */
    function rule(int $id=1,float $rate=20,array $overrides=[]):array{return array_merge(['id'=>$id,'name'=>'Test '.$id,'enabled'=>true,'start'=>time()-3600,'end'=>time()+3600,'profiles'=>['guest','retail'],'on_sale'=>'exclude','rules'=>[r(['value'=>$rate,'brands'=>[1],'categories'=>[11]])],'exclude_products'=>[],'exclude_categories'=>[],'exclude_brands'=>[],'brand_taxonomy'=>'product_brand'],$overrides);}
    $repo=new Repository();$rr=new \ReflectionProperty(Repository::class,'rows');$rs=new \ReflectionProperty(Repository::class,'settings');
    $set=function(array $rules,array $settings=[]) use($repo,$rr,$rs){$rr->setValue($repo,$rules);$rs->setValue($repo,null);$GLOBALS['opts']['g2rd_promo_settings']=$settings;$GLOBALS['pricing']=new Pricing($repo);};
    $set([rule()]);$p=new \WC_Product(10);$other=new \WC_Product(20);$child=new \WC_Product(30);$variation=new \WC_Product(101,'30','','variation',10);
    $GLOBALS['products']=[10=>$p,20=>$other,30=>$child,101=>$variation];
    $engine=$GLOBALS['pricing'];
    expect($engine->context()['profile'],'guest','visitor context');
    expect($engine->quote($p)['price'],24.0,'guest discount');
    expect($p->get_price(),'24.00','price getter string');
    expect($p->get_regular_price(),'30.00','regular getter');
    expect($p->get_sale_price(),'24.00','sale getter');
    expect($engine->on_sale(false,$p),true,'native badge state');
    expect($p->get_price('edit'),'30','raw price unchanged');
    expect($engine->quote($other),null,'wrong label and category');
    expect($engine->quote($child)['price'],24.0,'sub-category inherited');
    expect($engine->quote($variation)['price'],24.0,'variation inherits parent taxonomy');
    $sale=new \WC_Product(10,'30','25');expect($engine->quote($sale),null,'native sale protected');expect($sale->get_price(),'25','native sale price unchanged');
    $external=clone $p;$external->other_price='27';expect($external->get_price(),'27','external override respected');expect($external->get_sale_price(),'','no false sale for external override');
    $set([rule(1,20),rule(2,30)]);expect($GLOBALS['pricing']->quote($p)['id'],2,'largest discount wins');
    $set([rule(4,30),rule(2,30)]);expect($GLOBALS['pricing']->quote($p)['id'],2,'stable tie breaker');
    $set([rule(1,20,['exclude_products'=>[10]])]);expect($GLOBALS['pricing']->quote($variation),null,'parent exclusion covers variation');
    $set([rule()],['exclude_categories'=>[11]]);expect($GLOBALS['pricing']->quote($child),null,'global exclusion inherited');
    $set([rule(1,20,['start'=>time()+60,'end'=>time()+120])]);expect($GLOBALS['pricing']->quote($p),null,'future rule not applied');
    $set([rule(1,20,['start'=>time()-60,'end'=>time()])]);expect($GLOBALS['pricing']->quote($p),null,'end exclusive');
    $set([rule(1,20,['enabled'=>false])]);expect($GLOBALS['pricing']->quote($p),null,'disabled rule');
    $set([rule(1,20,['brand_taxonomy'=>'other_tax'])]);expect($GLOBALS['pricing']->quote($p),null,'taxonomy mismatch fails closed');
    $GLOBALS['logged']=true;$GLOBALS['roles']=['customer'];$set([rule()]);expect($GLOBALS['pricing']->context()['profile'],'retail','retail user');expect($GLOBALS['pricing']->quote($p)['price'],24.0,'retail discount');
    $GLOBALS['roles']=['wholesale_customer'];expect($GLOBALS['pricing']->context()['profile'],'wholesale','wholesale user');expect($GLOBALS['pricing']->quote($p),null,'public offer not applied to wholesale');
    $set([rule(1,10,['profiles'=>['wholesale']])]);expect($GLOBALS['pricing']->quote($p),null,'wholesale disabled until configured');
    $set([rule(1,10,['profiles'=>['wholesale']])],['wholesale_mode'=>'meta']);expect($GLOBALS['pricing']->quote($p),null,'missing wholesale price fails closed');
    $p->meta['wholesale_customer_wholesale_price']='18';expect($GLOBALS['pricing']->quote($p)['price'],16.2,'discount based on wholesale rate');expect($p->get_price(),'16.20','wholesale view getter');expect($p->get_regular_price(),'18.00','wholesale regular price');
    $p->meta['wholesale_customer_sale_price']='17';$set([rule(1,10,['profiles'=>['wholesale']])],['wholesale_mode'=>'meta','wholesale_sale_key'=>'{role}_sale_price']);expect($GLOBALS['pricing']->quote($p),null,'existing wholesale sale protected');
    $GLOBALS['roles']=['other_b2b'];expect($GLOBALS['pricing']->context()['profile'],'wholesale','unconfigured B2B role protected');expect($GLOBALS['pricing']->quote($p),null,'unknown B2B role no discount');
    $GLOBALS['logged']=false;$GLOBALS['roles']=[];$set([rule()]);$engine=$GLOBALS['pricing'];$GLOBALS['admin']=true;expect($p->get_price(),'30','admin editing price unchanged');$GLOBALS['admin']=false;
    $cart=new \MockCart(['a'=>['data'=>clone $p,'quantity'=>2,'product_id'=>10,'variation_id'=>0]]);$GLOBALS['cartobj']->cart=$cart;
    $engine->reset_cart($cart);$engine->apply_cart($cart);expect($cart->cart_contents['a']['data']->get_price(),'24.00','cart discount');expect($cart->cart_contents['a']['g2rd_promotion']['rate'],20.0,'cart metadata');
    $engine->reset_cart($cart);$engine->apply_cart($cart);expect($cart->cart_contents['a']['data']->get_price(),'24.00','cart recalculation no double discount');
    $individual=new \WC_Coupon(true);$stackable=new \WC_Coupon(false);
    expect($engine->coupon_valid(true,$individual),false,'individual-use coupon refused next to promo items');
    expect($engine->coupon_valid(true,$stackable),true,'stackable coupon (e.g. refund) stays valid');
    $errors=new \MockErrors();$cart->coupons=['refund'=>$stackable];$engine->store_coupon_errors($errors,$cart);expect($errors->codes,[],'no checkout error for stackable coupon');
    $cart->coupons=['vip'=>$individual];$engine->store_coupon_errors($errors,$cart);expect($errors->codes,['g2rd_promo_coupon_conflict'],'checkout error for individual-use coupon');$cart->coupons=[];
    $orders=new Orders($engine);$item=new \WC_Order_Item_Product(2,48,9.6);$order=new \WC_Order([$item]);$orders->classic_line($item,'a',$cart->cart_contents['a'],$order);
    expect($item->meta[Orders::META]['saving_net'],12.0,'order net savings');expect($item->meta[Orders::META]['saving_gross'],14.4,'order gross savings');expect($item->meta[Orders::META]['price'],24.0,'order price snapshot');
    $totals=$orders->totals(['order_total'=>['label'=>'Total','value'=>'57.60']],$order,'incl');expect($totals['order_total']['value'],'57.60','no second discount on total');expect(isset($totals['g2rd_promotion_savings']),true,'informational savings row');
    $rr->setValue($repo,[]);$engine->reset_cart($cart);$engine->apply_cart($cart);expect($cart->cart_contents['a']['data']->get_price(),'30','expired rule restores price');expect(isset($cart->cart_contents['a']['g2rd_promotion']),false,'expired rule clears cart metadata');expect(Orders::savings($order,true),14.4,'historical order savings preserved');
    expect(Rules::price(29.99,r(['value'=>30]),2),20.99,'currency rounding');expect(Rules::price(10,r(['value'=>100]),2),0.0,'100 percent');

    // --- Black Friday example of the brief: the most specific rule wins, not the biggest discount ---
    $GLOBALS['termmap']+=[40=>['product_brand'=>[5],'product_cat'=>[11]],41=>['product_brand'=>[7],'product_cat'=>[14]],42=>['product_brand'=>[7],'product_cat'=>[11]],43=>['product_brand'=>[],'product_cat'=>[11]],44=>['product_brand'=>[9],'product_cat'=>[12]]];
    $bf=[r(['value'=>40]),r(['value'=>5,'brands'=>[5,6]]),r(['value'=>30,'brands'=>[7]]),r(['value'=>10,'brands'=>[7],'categories'=>[14]])];
    $set([rule(1,0,['rules'=>$bf])]);
    foreach([40=>28.5,41=>27.0,42=>21.0,43=>18.0,44=>18.0] as $id=>$price) expect($GLOBALS['pricing']->quote(new \WC_Product($id))['price'],$price,"black friday product $id");
    $bf[0]['priority']=1;$set([rule(1,0,['rules'=>$bf])]);expect($GLOBALS['pricing']->quote(new \WC_Product(40))['rule'],0,'manual priority overrides specificity');
    $set([rule(1,0,['rules'=>[r(['value'=>40]),r(['type'=>'fixed','value'=>5,'products'=>[42]])]])]);$q=$GLOBALS['pricing']->quote(new \WC_Product(42));expect([$q['price'],$q['rate']],[5.0,83.33],'specific product rule with fixed price');
    $set([rule(1,0,['rules'=>[r(['type'=>'amount','value'=>5])]])]);expect($GLOBALS['pricing']->quote(new \WC_Product(40))['price'],25.0,'fixed amount off the regular price');
    $set([rule(1,0,['rules'=>[r(['type'=>'fixed','value'=>35])]])]);expect($GLOBALS['pricing']->quote(new \WC_Product(40)),null,'fixed price never raises a price');

    // --- Products already on sale ---
    $sale=new \WC_Product(10,'30','25');
    $set([rule(1,20,['on_sale'=>'include'])]);expect($GLOBALS['pricing']->quote($sale)['price'],24.0,'include mode computed from regular price');expect($sale->get_price(),'24.00','include mode replaces native sale price');
    $set([rule(1,0,['on_sale'=>'include','rules'=>[r(['type'=>'fixed','value'=>26,'brands'=>[1]])]])]);expect($sale->get_price(),'26.00','include mode applies even above the old sale price');
    $future=new \WC_Product(10,'30','25');$future->sale_from=time()+86400;$future->price='30';$set([rule()]);expect($GLOBALS['pricing']->quote($future)['price'],24.0,'scheduled native sale not started: campaign applies');
    $expired=new \WC_Product(10,'30','25');$expired->sale_to=time()-60;$expired->price='30';expect($GLOBALS['pricing']->quote($expired)['price'],24.0,'expired native sale: campaign applies');

    // --- Campaigns saved by 1.0.x ---
    $legacy=(new \ReflectionMethod(Repository::class,'decode'))->invoke(null,['id'=>'3','name'=>'Old','rate'=>'15.000','enabled'=>'1','starts'=>'1','ends'=>'2','updated'=>'0','conditions'=>json_encode(['profiles'=>['guest'],'brands'=>[1],'categories'=>[],'coupons'=>true,'brand_taxonomy'=>'product_brand'])]);
    expect([$legacy['rules'][0]['type'],$legacy['rules'][0]['value'],$legacy['rules'][0]['brands'],$legacy['on_sale']],['percent',15.0,[1],'exclude'],'1.0.x campaign read as one percentage rule');

    // --- Background on-sale index (section Discount) ---
    $GLOBALS['opts']['g2rd_promo_cache_epoch']='e1';$GLOBALS['transients']['wc_products_onsale']=[99];$set([rule()]);$engine=$GLOBALS['pricing'];
    expect($engine->on_sale_ids(false),false,'stale index falls back to the native list');expect(isset($GLOBALS['scheduled'][Pricing::BUILD]),true,'stale index queues a rebuild');
    $engine->build_sale_index();$ids=$engine->on_sale_ids(false);
    expect([in_array(10,$ids,true),in_array(30,$ids,true),in_array(99,$ids,true),in_array(20,$ids,true)],[true,true,true,false],'index lists promo and native products only');
    $GLOBALS['opts']['g2rd_promo_cache_epoch']='e2';$set([rule()]);expect($GLOBALS['pricing']->on_sale_ids(false),false,'index invalidated by a campaign change');expect(Rules::parse_date('2026-03-29T02:30',new \DateTimeZone('Europe/Paris')),null,'nonexistent DST hour rejected');expect(Rules::parse_date('2026-02-30T12:00',new \DateTimeZone('Europe/Paris')),null,'invalid date rejected');

    // --- Updater (GitHub Releases, sans réseau) ---
    $norm=Updater::normalize(['tag_name'=>'v1.2.3','html_url'=>'https://example.test/r','body'=>'notes','assets'=>[['name'=>'g2rd-woocommerce-promotions-1.2.3.zip','browser_download_url'=>'https://example.test/p.zip']]]);
    expect($norm['version']??null,'1.2.3','updater strips v prefix');
    expect($norm['package']??null,'https://example.test/p.zip','updater picks release zip asset');
    expect(Updater::normalize(['tag_name'=>'v1.2.3','assets'=>[['name'=>'source.zip','browser_download_url'=>'https://example.test/src.zip']]])['package']??null,'','updater ignores non-plugin zip');
    expect(Updater::normalize(['tag_name'=>'nightly']),null,'updater rejects non-semver tag');

    $GLOBALS['transients']=[];
    $GLOBALS['g2rd_release_mock']=['tag_name'=>'v9.9.9','html_url'=>'https://example.test/r','body'=>'x','assets'=>[['name'=>'g2rd-woocommerce-promotions-9.9.9.zip','browser_download_url'=>'https://example.test/u.zip']]];
    $upd=Updater::check(false,['Version'=>'1.0.1'],'g2rd-woocommerce-promotions/g2rd-woocommerce-promotions.php',[]);
    expect(is_array($upd)&&($upd['version']??'')==='9.9.9',true,'updater offers newer release');
    expect($upd['package']??null,'https://example.test/u.zip','updater package url');

    $GLOBALS['transients']=[];
    $upd_same=Updater::check(false,['Version'=>'9.9.9'],'g2rd-woocommerce-promotions/g2rd-woocommerce-promotions.php',[]);
    expect($upd_same,false,'updater silent when already current');

    $GLOBALS['transients']=[];
    $GLOBALS['g2rd_release_mock']=['tag_name'=>'v2.0.0','html_url'=>'https://example.test/r','body'=>'','assets'=>[]];
    expect(Updater::check(false,['Version'=>'1.0.1'],'g2rd-woocommerce-promotions/g2rd-woocommerce-promotions.php',[]),false,'updater silent without zip asset');

    unset($GLOBALS['g2rd_release_mock']);$GLOBALS['transients']=[];$GLOBALS['http_response']=new \WP_Error('http','fail');
    expect(Updater::latest_release(),null,'updater fails closed on API error');
    expect(Updater::check(false,['Version'=>'1.0.1'],'other-plugin/other.php',[]),false,'updater ignores other plugins');

    echo "\n$tests tests, $fail failures.\n";exit($fail?1:0);
}
