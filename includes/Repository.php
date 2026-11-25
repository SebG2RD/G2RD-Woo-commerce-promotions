<?php
namespace G2RD\Promotions;
if (!defined('ABSPATH')) { exit; }
final class Repository {
    private ?array $rows = null;
    private ?array $settings = null;
    public static function table(): string { global $wpdb; return $wpdb->prefix.'g2rd_promotions'; }
    public static function logs_table(): string { global $wpdb; return $wpdb->prefix.'g2rd_promotion_logs'; }
    public static function install(): void {
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $collate = $wpdb->get_charset_collate(); $table = self::table(); $logs = self::logs_table();
        dbDelta("CREATE TABLE $table (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            enabled tinyint unsigned NOT NULL DEFAULT 0,
            rate decimal(7,3) NOT NULL,
            starts bigint unsigned NOT NULL,
            ends bigint unsigned NOT NULL,
            conditions longtext NOT NULL,
            created bigint unsigned NOT NULL,
            updated bigint unsigned NOT NULL,
            PRIMARY KEY  (id),
            KEY schedule (enabled,starts,ends)
        ) $collate;");
        dbDelta("CREATE TABLE $logs (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint unsigned NOT NULL DEFAULT 0,
            actor bigint unsigned NOT NULL DEFAULT 0,
            event varchar(40) NOT NULL,
            detail text NOT NULL,
            created bigint unsigned NOT NULL,
            PRIMARY KEY  (id),
            KEY campaign_id (campaign_id)
        ) $collate;");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table))) === $table && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($logs))) === $logs) update_option('g2rd_promo_schema','1',false);
        self::invalidate();
    }
    public function settings(): array {
        if ($this->settings !== null) return $this->settings;
        $defaults = ['brand_taxonomy'=>'product_brand','wholesale_roles'=>['wholesale_customer'],'wholesale_mode'=>'off','wholesale_key'=>'{role}_wholesale_price','wholesale_sale_key'=>'','exclude_products'=>[], 'exclude_categories'=>[], 'exclude_brands'=>[], 'cache_safe'=>true];
        $s = get_option('g2rd_promo_settings', []);
        return $this->settings = array_merge($defaults, is_array($s)?$s:[]);
    }
    public function all(): array {
        if ($this->rows !== null) return $this->rows;
        global $wpdb;
        // %i = identifiant de table (WordPress 6.2+ / exigence Plugin Check).
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY id DESC', self::table()), ARRAY_A) ?: [];
        return $this->rows = array_map([self::class,'decode'],$rows);
    }
    public function get(int $id): ?array { foreach($this->all() as $r) if ($r['id']===$id) return $r; return null; }
    public function active(?int $now=null): array { $now ??= time(); return array_values(array_filter($this->all(), static fn($r)=>Rules::active($r,$now))); }
    public function upcoming(): bool { foreach ($this->all() as $r) if ($r['enabled'] && $r['end']>time()) return true; return false; }
    private static function decode(array $r): array {
        $c = json_decode($r['conditions'], true); $c = is_array($c)?$c:[];
        return array_merge(['profiles'=>[], 'brands'=>[], 'categories'=>[], 'exclude_products'=>[], 'exclude_categories'=>[], 'exclude_brands'=>[], 'coupons'=>false, 'brand_taxonomy'=>'product_brand'], $c, ['id'=>(int)$r['id'],'name'=>$r['name'],'rate'=>(float)$r['rate'],'enabled'=>(bool)$r['enabled'],'start'=>(int)$r['starts'],'end'=>(int)$r['ends'],'updated'=>(int)$r['updated']]);
    }
    public function save(array $r): int {
        global $wpdb;
        $c = array_intersect_key($r,array_flip(['profiles','brands','categories','exclude_products','exclude_categories','exclude_brands','coupons','brand_taxonomy']));
        $data=['name'=>$r['name'],'rate'=>$r['rate'],'enabled'=>(int)$r['enabled'],'starts'=>$r['start'],'ends'=>$r['end'],'conditions'=>wp_json_encode($c),'updated'=>time()];
        $id=(int)($r['id']??0);
        if ($id) { if (!$this->get($id) || $wpdb->update(self::table(),$data,['id'=>$id])===false) throw new \RuntimeException('Enregistrement impossible.'); }
        else { $data['created']=time(); if ($wpdb->insert(self::table(),$data)===false) throw new \RuntimeException('Enregistrement impossible.'); $id=(int)$wpdb->insert_id; }
        $this->rows=null; self::log($id,'save',$r['name']); self::reschedule($id,$r); self::invalidate(); return $id;
    }
    public function delete(int $id): void {
        global $wpdb; $r=$this->get($id); if (!$r) throw new \RuntimeException('Campagne introuvable.');
        if ($wpdb->delete(self::table(),['id'=>$id])===false) throw new \RuntimeException('Suppression impossible.');
        $this->rows=null; self::reschedule($id,null); self::log($id,'delete',$r['name']); self::invalidate();
    }
    public static function log(int $id,string $event,string $detail=''): void { global $wpdb; $wpdb->insert(self::logs_table(),['campaign_id'=>$id,'actor'=>get_current_user_id(),'event'=>$event,'detail'=>$detail,'created'=>time()]); }
    public function logs(): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY id DESC LIMIT %d', self::logs_table(), 200), ARRAY_A) ?: [];
    }
    public static function reschedule(int $id, ?array $r): void {
        foreach (['start','end'] as $k) {
            $args=[$id,$k];
            if (function_exists('as_unschedule_all_actions')) as_unschedule_all_actions('g2rd_promo_boundary',$args,'g2rd-promotions');
            wp_clear_scheduled_hook('g2rd_promo_boundary',$args);
            if ($r && $r['enabled'] && $r[$k]>time()) {
                if (function_exists('as_schedule_single_action') && did_action('action_scheduler_init')) as_schedule_single_action($r[$k],'g2rd_promo_boundary',$args,'g2rd-promotions',true);
                else wp_schedule_single_event($r[$k],'g2rd_promo_boundary',$args);
            }
        }
    }
    public static function invalidate($id=0,$event=''): void {
        update_option('g2rd_promo_cache_epoch',wp_generate_uuid4(),false);
        if (class_exists('WC_Cache_Helper')) \WC_Cache_Helper::get_transient_version('product',true);
        if (function_exists('rocket_clean_domain')) rocket_clean_domain();
        if ($id && is_numeric($id)) self::log((int)$id,'boundary',sanitize_key($event));
        do_action('g2rd_promotions_cache_invalidated');
    }
    public static function signature(): string {
        $repo=new self(); return hash('sha256',wp_json_encode(array_column($repo->active(),'id')));
    }
    public static function reconcile(): void {
        $signature=self::signature(); if (get_option('g2rd_promo_active_signature')!==$signature) {update_option('g2rd_promo_active_signature',$signature,false); self::invalidate();}
    }
    public static function schedule_reconcile(): void {
        if (function_exists('as_has_scheduled_action') && did_action('action_scheduler_init')) {
            if (!as_has_scheduled_action('g2rd_promo_reconcile',[],'g2rd-promotions')) as_schedule_recurring_action(time()+300,300,'g2rd_promo_reconcile',[],'g2rd-promotions',true);
        } elseif (!wp_next_scheduled('g2rd_promo_reconcile')) wp_schedule_event(time()+60,'hourly','g2rd_promo_reconcile');
    }
    public static function deactivate(): void {
        $repo=new self(); foreach($repo->all() as $r) self::reschedule($r['id'],null);
        if (function_exists('as_unschedule_all_actions')) as_unschedule_all_actions('g2rd_promo_reconcile',[],'g2rd-promotions');
        wp_clear_scheduled_hook('g2rd_promo_reconcile'); self::invalidate();
    }
}
