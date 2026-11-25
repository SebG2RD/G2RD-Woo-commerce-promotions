<?php
namespace G2RD\Promotions;
if (!defined('ABSPATH')) { exit; }
final class Admin {
    private Repository $repo; private Pricing $pricing;
    private const CAP='manage_woocommerce';
    private const SLUG='g2rd-promotions';
    public function __construct(Repository $repo,Pricing $pricing) {$this->repo=$repo;$this->pricing=$pricing;}
    public function register(): void {
        add_action('admin_menu',[$this,'menu']); add_action('admin_enqueue_scripts',[$this,'assets']);
        add_filter('admin_body_class',[$this,'body_class']);
        add_action('admin_post_g2rd_promo_save',[$this,'save']); add_action('admin_post_g2rd_promo_action',[$this,'action']);
        add_action('admin_post_g2rd_promo_settings',[$this,'save_settings']); add_action('admin_post_g2rd_promo_export',[$this,'export']);
        add_action('wp_ajax_g2rd_promo_preview',[$this,'preview']);
    }
    public function menu(): void {add_submenu_page('woocommerce','G2RD Promotions','G2RD Promotions',self::CAP,self::SLUG,[$this,'render']);}
    /** Marque le body pour le fond Verre (comme Facturation / Geo). */
    public function body_class(string $classes): string {
        if(isset($_GET['page']) && $_GET['page']===self::SLUG) $classes.=' g2rd-promo-screen';
        return $classes;
    }
    public function assets($hook): void {
        if(!isset($_GET['page']) || $_GET['page']!==self::SLUG) return;
        wp_enqueue_style('g2rd-promotions',plugins_url('assets/admin.css',G2RD_PROMO_FILE),[],G2RD_PROMO_VERSION);
        wp_enqueue_script('g2rd-promotions',plugins_url('assets/admin.js',G2RD_PROMO_FILE),[],G2RD_PROMO_VERSION,true);
        wp_localize_script('g2rd-promotions','G2RDPromo',[
            'ajax'=>admin_url('admin-ajax.php'),
            'nonce'=>wp_create_nonce('g2rd_promo_preview'),
            'working'=>'Calcul en cours…',
            'error'=>'Impossible de charger la prévisualisation.',
            'confirm'=>'Confirmer la suppression de cette campagne ?',
            'tabs'=>['overview','campaigns','calendar','stats','history','settings','diagnostic','help'],
            'defaultTab'=>'overview',
        ]);
    }
    private function guard(): void {if(!current_user_can(self::CAP)) wp_die(esc_html__('Accès refusé.','g2rd-woocommerce-promotions'),'', ['response'=>403]);}
    private function url(array $q=[]): string {return add_query_arg(array_merge(['page'=>self::SLUG],$q),admin_url('admin.php'));}
    private function redirect(array $q=[],string $msg='',bool $error=false): void {
        if($msg!=='') set_transient('g2rd_promo_notice_'.get_current_user_id(),['msg'=>$msg,'error'=>$error],60);
        // ?tab= sert de pont serveur → le JS convertit en #onglet (sans reload ensuite).
        wp_safe_redirect($this->url($q));exit;
    }
    private function ids($raw): array { if(is_string($raw)) $raw=preg_split('/[\s,;]+/',$raw,-1,PREG_SPLIT_NO_EMPTY); if(!is_array($raw)) return []; return array_values(array_unique(array_filter(array_map('absint',array_filter($raw,'is_scalar'))))); }
    private function term_ids($raw,string $tax): array { $ids=$this->ids($raw); return array_values(array_filter($ids,static fn($id)=>taxonomy_exists($tax)&&term_exists($id,$tax))); }
    private function input(array $p,int $id=0): array {
        $s=$this->repo->settings(); $tax=$s['brand_taxonomy'];
        $name=sanitize_text_field(is_string($p['name']??null)?$p['name']:'');
        $start=Rules::parse_date(is_string($p['start']??null)?$p['start']:'',wp_timezone()); $end=Rules::parse_date(is_string($p['end']??null)?$p['end']:'',wp_timezone());
        $profiles=array_values(array_intersect(['guest','retail','wholesale'],is_array($p['profiles']??null)?array_filter($p['profiles'],'is_string'):[]));
        if($name==='' || strlen($name)>190) throw new \InvalidArgumentException('Renseigne un nom de campagne (190 octets maximum).');
        if($start===null || $end===null || $end<=$start) throw new \InvalidArgumentException('La fin doit être postérieure au début. Vérifie les dates et les heures.');
        if(!$profiles) throw new \InvalidArgumentException('Sélectionne au moins un profil client.');
        if(in_array('wholesale',$profiles,true) && $s['wholesale_mode']==='off' && !empty($p['enabled'])) throw new \InvalidArgumentException('Configure les tarifs Wholesale avant de programmer une campagne professionnelle.');
        $rules=[];
        foreach(is_array($p['rules']??null)?$p['rules']:[] as $raw) {
            if(!is_array($raw)) continue;
            $n=count($rules)+1;
            $type=in_array($raw['type']??null,Rules::TYPES,true)?$raw['type']:'percent';
            $value=filter_var($raw['value']??null,FILTER_VALIDATE_FLOAT);
            if($value===false || $value<=0 || round($value,3)!=$value || ($type==='percent' && $value>100)) throw new \InvalidArgumentException(sprintf('Règle %d : valeur invalide (pourcentage entre 0,001 et 100, ou montant positif, 3 décimales maximum).',(int)$n));
            $brands=$this->term_ids($raw['brands']??[],$tax); $cats=$this->term_ids($raw['categories']??[],'product_cat');
            if(count($brands)!==count($this->ids($raw['brands']??[])) || count($cats)!==count($this->ids($raw['categories']??[]))) throw new \InvalidArgumentException(sprintf('Règle %d : une sélection de labels ou catégories est invalide.',(int)$n));
            $products=$this->ids($raw['products']??[]);
            foreach($products as $pid) if(!in_array(get_post_type($pid),['product','product_variation'],true)) throw new \InvalidArgumentException(sprintf('Règle %d : l’article #%d n’existe pas.',(int)$n,(int)$pid));
            $priority=absint($raw['priority']??0);
            if($priority>99) throw new \InvalidArgumentException(sprintf('Règle %d : priorité entre 1 et 99.',(int)$n));
            // Specific products replace the label/category selection.
            $rules[]=['type'=>$type,'value'=>(float)$value,'brands'=>$products?[]:$brands,'categories'=>$products?[]:$cats,'products'=>$products,'priority'=>$priority];
        }
        if(!$rules) throw new \InvalidArgumentException('Ajoute au moins une règle.');
        return ['id'=>$id,'name'=>$name,'start'=>$start,'end'=>$end,'profiles'=>$profiles,'on_sale'=>($p['on_sale']??'')==='include'?'include':'exclude','rules'=>$rules,'exclude_products'=>$this->ids($p['exclude_products']??[]),'exclude_categories'=>$this->term_ids($p['exclude_categories']??[],'product_cat'),'exclude_brands'=>$this->term_ids($p['exclude_brands']??[],$tax),'brand_taxonomy'=>$tax,'enabled'=>!empty($p['enabled'])];
    }
    public function save(): void {
        $this->guard();check_admin_referer('g2rd_promo_save'); $p=wp_unslash($_POST); $id=absint($p['id']??0);
        try {$r=$this->input($p,$id);$id=$this->repo->save($r);$this->redirect(['tab'=>'campaigns'],'Campagne enregistrée.');} catch(\Throwable $e){$this->redirect(['tab'=>'edit','id'=>$id],$e->getMessage(),true);}
    }
    public function action(): void {
        $this->guard();check_admin_referer('g2rd_promo_action');$id=absint($_POST['id']??0);$op=sanitize_key($_POST['op']??'');
        try {$r=$this->repo->get($id);if(!$r) throw new \RuntimeException('Campagne introuvable.');
            if($op==='delete') $this->repo->delete($id);
            elseif($op==='duplicate') {$r['id']=0;$r['enabled']=false;$r['name']=wp_html_excerpt($r['name'],150,'').' (copie)';$id=$this->repo->save($r);$this->redirect(['tab'=>'edit','id'=>$id],'Campagne dupliquée en brouillon.');}
            elseif($op==='toggle') {if(!$r['enabled'] && ($r['end']<=time() || $r['brand_taxonomy']!==$this->repo->settings()['brand_taxonomy'] || in_array('wholesale',$r['profiles'],true)&&$this->repo->settings()['wholesale_mode']==='off')) throw new \RuntimeException('Modifie les dates, la taxonomie ou la configuration Wholesale avant activation.');$r['enabled']=!$r['enabled'];$this->repo->save($r);}
            else throw new \RuntimeException('Action inconnue.');
            $this->redirect(['tab'=>'campaigns'],'Action enregistrée.');
        } catch(\Throwable $e){$this->redirect(['tab'=>'campaigns'],$e->getMessage(),true);}
    }
    public function save_settings(): void {
        $this->guard();check_admin_referer('g2rd_promo_settings'); $p=wp_unslash($_POST);$tax=sanitize_key($p['brand_taxonomy']??'');
        $valid_tax=get_object_taxonomies('product'); if(!in_array($tax,$valid_tax,true) || in_array($tax,['product_cat','product_type','product_visibility'],true)) {$this->redirect(['tab'=>'settings'],'Taxonomie invalide.',true);}
        $roles=array_keys(wp_roles()->roles);$selected=array_values(array_intersect($roles,is_array($p['wholesale_roles']??null)?array_filter($p['wholesale_roles'],'is_string'):[]));
        $mode=sanitize_key($p['wholesale_mode']??'off');if(!in_array($mode,['off','meta','filter'],true)) $mode='off';
        $key=sanitize_text_field($p['wholesale_key']??'');$sale_key=sanitize_text_field($p['wholesale_sale_key']??'');
        if($mode!=='off' && (!$selected || ($mode==='meta' && !$key))) $this->redirect(['tab'=>'settings'],'Indique les rôles et la source des tarifs Wholesale.',true);
        $old=$this->repo->settings();
        $s=['brand_taxonomy'=>$tax,'wholesale_roles'=>$selected,'wholesale_mode'=>$mode,'wholesale_key'=>$key,'wholesale_sale_key'=>$sale_key,'exclude_products'=>$this->ids($p['exclude_products']??[]),'exclude_categories'=>$this->term_ids($p['exclude_categories']??[],'product_cat'),'exclude_brands'=>$tax===$old['brand_taxonomy']?$this->term_ids($p['exclude_brands']??[],$tax):[],'cache_safe'=>!empty($p['cache_safe'])];
        update_option('g2rd_promo_settings',$s,false);Repository::log(0,'settings','Paramètres modifiés');Repository::invalidate();$this->redirect(['tab'=>'settings'],'Paramètres enregistrés.');
    }
    private function post_start(string $action): void {echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="g2rd-promo-form"><input type="hidden" name="action" value="'.esc_attr($action).'">';wp_nonce_field($action);}
    /** Balises SVG autorisées pour wp_kses (icônes hardcodées). */
    private function svg_allowed(): array {
        return [
            'svg'=>['width'=>true,'height'=>true,'viewBox'=>true,'fill'=>true,'stroke'=>true,'stroke-width'=>true,'stroke-linecap'=>true,'stroke-linejoin'=>true,'aria-hidden'=>true],
            'path'=>['d'=>true],'circle'=>['cx'=>true,'cy'=>true,'r'=>true],
            'rect'=>['x'=>true,'y'=>true,'width'=>true,'height'=>true,'rx'=>true],
        ];
    }
    private function icon(string $name): string {
        $paths=['tag'=>'<path d="M20 13 13 20 4 11V4h7z"/><circle cx="8" cy="8" r="1"/>','calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18"/>','users'=>'<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2m1-15a3 3 0 0 1 0 6m1 3a5 5 0 0 1 4 4v2"/>','shield'=>'<path d="M12 3 5 6v6c0 5 7 9 7 9s7-4 7-9V6z"/><path d="m9 12 2 2 4-4"/>','box'=>'<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 9h16m-11 4h6"/>','eye'=>'<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>','chart'=>'<path d="M4 3v17h17M8 15v-4m5 4V6m5 9v-7"/>'];
        $html='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['tag']).'</svg>';
        return wp_kses($html,$this->svg_allowed());
    }
    private function section(string $title,string $desc='',string $icon='tag'): void {echo '<section class="g2rd-promo-section"><header class="g2rd-promo-section-head"><span class="g2rd-promo-icon">'.wp_kses($this->icon($icon),$this->svg_allowed()).'</span><div><h2>'.esc_html($title).'</h2>';if($desc) echo '<p>'.esc_html($desc).'</p>';echo '</div></header>';}
    private function terms(string $name,string $tax,array $selected,string $label): void {
        $terms=taxonomy_exists($tax)?get_terms(['taxonomy'=>$tax,'hide_empty'=>false,'orderby'=>'name']):[];if(is_wp_error($terms)) $terms=[];
        echo '<div class="g2rd-promo-field"><label for="gp-'.esc_attr($name).'">'.esc_html($label).'</label>';
        echo '<input type="search" class="g2rd-promo-search" placeholder="Rechercher…" aria-label="'.esc_attr('Rechercher : '.$label).'" data-target="gp-'.esc_attr($name).'"><select multiple size="6" id="gp-'.esc_attr($name).'" name="'.esc_attr($name).'[]">';
        foreach($terms as $t) echo '<option value="'.(int)$t->term_id.'" '.selected(in_array((int)$t->term_id,$selected,true),true,false).'>'.esc_html($t->name.' ('.$t->count.')').'</option>';
        echo '</select><small>Ctrl / Cmd pour sélectionner plusieurs éléments.</small></div>';
    }
    /** @param array<string,string|bool|int|float> $attrs Attributs HTML additionnels (échappés à l’affichage). */
    private function field(string $name,string $label,$value,string $type='text',array $attrs=[]): void {
        echo '<div class="g2rd-promo-field"><label for="gp-'.esc_attr($name).'">'.esc_html($label).'</label><input id="gp-'.esc_attr($name).'" name="'.esc_attr($name).'" type="'.esc_attr($type).'" value="'.esc_attr((string)$value).'"';
        foreach($attrs as $attr=>$val){
            if($val===true){echo ' '.esc_attr((string)$attr);continue;}
            if($val===false)continue;
            echo ' '.esc_attr((string)$attr).'="'.esc_attr((string)$val).'"';
        }
        echo '></div>';
    }
    private function toggle(string $name,string $label,string $desc,bool $checked,bool $locked=false): void {echo '<label class="g2rd-promo-setting"><span class="g2rd-promo-icon">'.wp_kses($this->icon('shield'),$this->svg_allowed()).'</span><span><strong>'.esc_html($label).'</strong><small>'.esc_html($desc).'</small></span><input type="checkbox" class="g2rd-promo-toggle" name="'.esc_attr($name).'" value="1" '.checked($checked,true,false).' '.disabled($locked,true,false).'></label>';}
    public function render(): void {
        $this->guard();
        $tab=sanitize_key(wp_unslash($_GET['tab']??'overview'));
        $tabs=['overview'=>'Vue d’ensemble','campaigns'=>'Campagnes','calendar'=>'Calendrier','stats'=>'Statistiques','history'=>'Historique','settings'=>'Paramètres','diagnostic'=>'Diagnostic','help'=>'Documentation'];
        $is_edit=($tab==='edit');
        $all=$this->repo->all();
        $counts=['active'=>0,'scheduled'=>0,'draft'=>0,'expired'=>0];
        foreach($all as $r) $counts[Rules::status($r,time())]++;

        echo '<hr class="wp-header-end">';
        echo '<div class="wrap g2rd-promo g2rd-app g2rd-app--php" id="g2rd-promo-root" data-initial-tab="'.esc_attr($is_edit?'campaigns':(isset($tabs[$tab])?$tab:'overview')).'">';
        echo '<div class="g2rd-promo-band" aria-hidden="true"></div><div class="g2rd-promo-inner">';
        echo '<header class="g2rd-promo-topbar"><div class="g2rd-promo-topbar__brand"><span class="g2rd-promo-topbar__mark" aria-hidden="true">'.wp_kses($this->icon('tag'),$this->svg_allowed()).'</span><div><p class="g2rd-promo-topbar__eyebrow">G2RD · WooCommerce</p><h1 class="g2rd-promo-topbar__title">Promotions</h1></div></div>';
        echo '<div class="g2rd-promo-topbar__actions"><span class="g2rd-promo-chip g2rd-promo-chip--mono">v'.esc_html(G2RD_PROMO_VERSION).'</span>';
        echo '<a class="button button-primary g2rd-promo-cta" href="'.esc_url($this->url(['tab'=>'edit'])).'">+ Nouvelle campagne</a></div></header>';

        if(!$is_edit){
            echo '<div class="g2rd-promo-tiles" aria-label="Indicateurs">';
            foreach(['active'=>['Actives','Campagnes en cours'],'scheduled'=>['Programmées','À venir'],'draft'=>['Brouillons','Désactivées'],'expired'=>['Terminées','Expirées']] as $key=>$meta){
                echo '<div class="g2rd-promo-tile"><small>'.esc_html($meta[0]).'</small><b class="is-mono">'.(int)$counts[$key].'</b><span>'.esc_html($meta[1]).'</span></div>';
            }
            echo '</div>';
        }

        echo '<nav class="g2rd-promo-nav" aria-label="Navigation G2RD Promotions"'.($is_edit?' hidden':'').'>';
        foreach($tabs as $key=>$label){
            echo '<a class="g2rd-promo-nav__link" href="#'.esc_attr($key).'" data-tab="'.esc_attr($key).'">'.esc_html($label).'</a>';
        }
        echo '</nav>';

        $notice=get_transient('g2rd_promo_notice_'.get_current_user_id());
        if(is_array($notice)){delete_transient('g2rd_promo_notice_'.get_current_user_id());echo '<div class="notice inline notice-'.($notice['error']?'error':'success').'"><p>'.esc_html($notice['msg']).'</p></div>';}

        echo '<div class="g2rd-promo-body">';
        if($is_edit){
            echo '<div class="g2rd-promo-panel is-active" data-panel="edit" role="tabpanel">';
            $this->editor(absint($_GET['id']??0));
            echo '</div>';
        } else {
            // Tous les onglets rendus une fois : le JS bascule sans recharger la page.
            $panels=['overview'=>[$this,'overview'],'campaigns'=>[$this,'campaigns'],'calendar'=>[$this,'calendar'],'stats'=>[$this,'stats'],'history'=>[$this,'history'],'settings'=>[$this,'settings'],'diagnostic'=>[$this,'diagnostic'],'help'=>[$this,'help']];
            foreach($panels as $key=>$cb){
                echo '<div class="g2rd-promo-panel" data-panel="'.esc_attr($key).'" role="tabpanel" hidden>';
                $cb();
                echo '</div>';
            }
        }
        echo '</div>';
        echo '<footer class="g2rd-promo-footer">G2RD Agence Web · <a href="https://g2rd.fr/" target="_blank" rel="noopener noreferrer">g2rd.fr</a> · v'.esc_html(G2RD_PROMO_VERSION).'</footer>';
        echo '</div></div>';
    }
    private function overview(): void {
        $this->section('Vue d’ensemble','Campagnes récentes et état des intégrations.','tag');
        echo '<p class="g2rd-promo-hint">Les indicateurs du bandeau se mettent à jour à chaque chargement de la page. Change d’onglet sans recharger.</p></section>';
        $this->campaigns(true);
        $this->diagnostic(true);
    }
    private function status(array $r): string {$key=Rules::status($r,time());$labels=['active'=>'Active','scheduled'=>'Programmée','draft'=>'Désactivée / brouillon','expired'=>'Terminée'];return wp_kses_post('<span class="g2rd-promo-chip status-'.esc_attr($key).'">'.esc_html($labels[$key]).'</span>');}
    private function date(int $stamp): string {return wp_date('d/m/Y H:i',$stamp);}
    private function discount(array $rule): string {
        $v=wc_format_localized_decimal($rule['value']); $cur=html_entity_decode(get_woocommerce_currency_symbol(),ENT_QUOTES,'UTF-8');
        return match($rule['type']){'amount'=>'−'.$v.' '.$cur,'fixed'=>'prix fixe '.$v.' '.$cur,default=>'−'.$v.' %'};
    }
    private function rule_summary(array $rule,string $tax): string {
        if($rule['products']) $scope='Articles #'.implode(', #',$rule['products']);
        elseif(!$rule['brands'] && !$rule['categories']) $scope='Tous les articles (règle générale)';
        else $scope=($rule['brands']?$this->term_names($rule['brands'],$tax):'Tous les labels').' × '.($rule['categories']?$this->term_names($rule['categories'],'product_cat'):'toutes catégories');
        return $scope.' : '.$this->discount($rule).($rule['priority']?' (priorité '.$rule['priority'].')':'');
    }
    private function rules_cell(array $c): string {
        $lines=array_map(fn($rule)=>esc_html($this->rule_summary($rule,$c['brand_taxonomy'])),array_slice($c['rules'],0,3));
        if(count($c['rules'])>3) $lines[]=esc_html(sprintf('+ %d autres règles',count($c['rules'])-3));
        return implode('<br>',$lines).($c['on_sale']==='include'?'<small>Inclut les articles déjà en promotion</small>':'');
    }
    private function term_names(array $ids,string $tax): string {if(!$ids)return 'Tous';$names=[];foreach($ids as $id){$t=get_term($id,$tax);$names[]=($t&&!is_wp_error($t))?$t->name:'#'.$id;}return implode(', ',$names);}
    private function profiles(array $p): string {return implode(', ',array_map(static fn($x)=>['guest'=>'Visiteurs','retail'=>'Particuliers','wholesale'=>'Wholesale'][$x]??$x,$p));}
    private function campaigns(bool $overview=false): void {
        $this->section($overview?'Campagnes récentes':'Toutes les campagnes','Une règle par réduction. Les promotions existantes restent prioritaires.');
        $rows=$this->repo->all();if($overview)$rows=array_slice($rows,0,5);
        if(!$rows){echo '<div class="g2rd-promo-empty"><p>Aucune campagne pour le moment.</p><a class="button button-primary" href="'.esc_url($this->url(['tab'=>'edit'])).'">Nouvelle campagne</a></div></section>';return;}
        if(!$overview)echo '<div class="g2rd-promo-toolbar"><input type="search" id="g2rd-campaign-search" placeholder="Rechercher une campagne…" aria-label="Rechercher une campagne"><select id="g2rd-campaign-status" aria-label="Filtrer par statut"><option value="">Tous les statuts</option><option value="active">Actives</option><option value="scheduled">Programmées</option><option value="draft">Désactivées</option><option value="expired">Terminées</option></select></div>';
        echo '<div class="g2rd-promo-table-wrap"><table class="g2rd-promo-table"'.($overview?'':' id="'.esc_attr('g2rd-campaign-table').'"').'><thead><tr><th>Campagne / clientèle</th><th>Règles</th><th>Période</th><th>Statut</th><th>Actions</th></tr></thead><tbody>';
        foreach($rows as $r){echo '<tr data-status="'.esc_attr(Rules::status($r,time())).'"><td><a class="g2rd-promo-name" href="'.esc_url($this->url(['tab'=>'edit','id'=>$r['id']])).'">'.esc_html($r['name']).'</a><small>'.esc_html($this->profiles($r['profiles'])).'</small></td><td>'.wp_kses_post($this->rules_cell($r)).'</td><td>'.esc_html($this->date($r['start'])).'<small>'.esc_html($this->date($r['end'])).'</small></td><td>'.wp_kses_post($this->status($r)).'</td><td><div class="g2rd-promo-actions"><a class="button" href="'.esc_url($this->url(['tab'=>'edit','id'=>$r['id']])).'">Modifier</a>';
            foreach(['toggle'=>$r['enabled']?'Désactiver':'Activer','duplicate'=>'Dupliquer','delete'=>'Supprimer'] as $op=>$label){$this->post_start('g2rd_promo_action');echo '<input type="hidden" name="id" value="'.(int)$r['id'].'"><input type="hidden" name="op" value="'.esc_attr($op).'"><button class="button '.($op==='delete'?'g2rd-promo-danger':'').'" type="submit" '.($op==='delete'?'data-confirm="1"':'').'>'.esc_html($label).'</button></form>';}
            echo '</div></td></tr>';}
        echo '</tbody></table></div></section>';
    }
    private function editor(int $id): void {
        $s=$this->repo->settings();$r=$id?$this->repo->get($id):null;if($id&&!$r){echo '<p>Campagne introuvable.</p>';return;}
        $r??=['id'=>0,'name'=>'','start'=>time(),'end'=>time()+7*DAY_IN_SECONDS,'enabled'=>false,'profiles'=>['guest','retail'],'on_sale'=>'exclude','rules'=>[self::RULE],'exclude_products'=>[],'exclude_categories'=>[],'exclude_brands'=>[],'brand_taxonomy'=>$s['brand_taxonomy']];
        $tax=$s['brand_taxonomy'];
        if($r['brand_taxonomy']!==$tax){echo '<div class="notice notice-warning inline"><p>Taxonomie modifiée. Sélectionne à nouveau les labels avant enregistrement.</p></div>';$r['rules']=array_map(static fn($rule)=>['brands'=>[]]+$rule,$r['rules']);$r['exclude_brands']=[];}
        $this->post_start('g2rd_promo_save');echo '<input type="hidden" name="id" value="'.(int)$r['id'].'"><div class="g2rd-promo-editor"> <div class="g2rd-promo-stack">';
        $this->section('Informations générales','Identifie cette opération commerciale.');$this->field('name','Nom de la campagne',$r['name'],'text',['required'=>true,'maxlength'=>190]);echo '</section>';
        $this->section('Clientèle ciblée','Les visiteurs bénéficient des offres publiques sans compte.','users');foreach(['guest'=>'Visiteurs non connectés','retail'=>'Particuliers connectés','wholesale'=>'Clients Wholesale'] as $key=>$label) echo '<label class="g2rd-promo-check"><input type="checkbox" name="profiles[]" value="'.esc_attr($key).'" '.checked(in_array($key,$r['profiles'],true),true,false).'> '.esc_html($label).'</label>';if($s['wholesale_mode']==='off')echo '<p class="g2rd-promo-hint">Wholesale : configure la source des tarifs professionnels dans Paramètres.</p>';echo '</section>';
        $this->section('Règles de remise','Une seule règle par article, jamais cumulée. Ordre automatique : articles spécifiques, puis label + catégorie, puis label ou catégorie, puis règle générale (tout vide). Une priorité manuelle (1 = la plus haute) passe avant cet ordre. Remises calculées sur le prix normal ; sous-catégories incluses.','box');
        echo '<div id="g2rd-promo-rules">';foreach($r['rules'] as $i=>$rule)$this->rule_fields((string)$i,$rule,$tax);echo '</div><template id="g2rd-promo-rule-template">';$this->rule_fields('__i__',self::RULE,$tax);echo '</template><button type="button" class="button" id="g2rd-promo-add-rule">+ Ajouter une règle</button></section>';
        $this->section('Planification','La fin est exclusive. Fuseau horaire WordPress : '.wp_timezone_string(),'calendar');echo '<div class="g2rd-promo-fields">';$this->field('start','Début',wp_date('Y-m-d\TH:i',$r['start']),'datetime-local',['required'=>true]);$this->field('end','Fin',wp_date('Y-m-d\TH:i',$r['end']),'datetime-local',['required'=>true]);echo '</div>';$this->toggle('enabled','Campagne activée','Le moteur respecte les dates même si le cron est en retard.',$r['enabled']);echo '</section>';
        $this->section('Articles déjà en promotion','Articles ayant un prix promo WooCommerce actif (dates comprises).','tag');
        foreach(['exclude'=>'Exclure : ils gardent leur prix promo actuel, quelle que soit la règle.','include'=>'Inclure : le prix de la campagne remplace temporairement leur prix promo, rétabli à la fin.'] as $key=>$label) echo '<label class="g2rd-promo-check"><input type="radio" name="on_sale" value="'.esc_attr($key).'" '.checked($r['on_sale'],$key,false).'> '.esc_html($label).'</label>';
        echo '</section>';
        $this->section('Exclusions et protections','Les exclusions globales restent prioritaires.','shield');$this->toggle('stack','Ne pas cumuler les campagnes','Une seule remise par article. Entre campagnes simultanées, le prix le plus bas est retenu.',true,true);echo '<p class="g2rd-promo-hint">Codes promo : ceux marqués « Utilisation individuelle uniquement » sont refusés si le panier contient un article en promotion. Les autres restent utilisables.</p><div class="g2rd-promo-fields">';$this->terms('exclude_brands',$tax,$r['exclude_brands'],'Labels exclus');$this->terms('exclude_categories','product_cat',$r['exclude_categories'],'Catégories exclues');echo '</div>';$this->field('exclude_products','Identifiants produits / variations exclus (séparés par des virgules)',implode(', ',$r['exclude_products']));echo '</section></div><aside class="g2rd-promo-stack">';
        $this->section('Prévisualisation','Simulation serveur de cette campagne seule.','eye');echo '<div class="g2rd-promo-toolbar"><button type="button" class="button" id="g2rd-promo-preview">Calculer les prix</button><button type="button" class="button" id="g2rd-promo-count">Compter les articles par règle</button></div><div id="g2rd-promo-preview-result" aria-live="polite"></div></section>';
        $this->section('Publication','Vérifie les prix et les exclusions avant activation.');echo '<p>'.wp_kses_post($this->status($r)).'</p><button class="button button-primary g2rd-promo-full" type="submit">Enregistrer la campagne</button><a class="button g2rd-promo-full" href="'.esc_url($this->url(['tab'=>'campaigns'])).'">Retour aux campagnes</a></section></aside></div></form>';
        // Lien retour : ?tab=campaigns → le JS bascule en #campaigns après reload POST.
    }
    private const RULE=['type'=>'percent','value'=>20,'brands'=>[],'categories'=>[],'products'=>[],'priority'=>0];
    private function rule_fields(string $i,array $rule,string $tax): void {
        $n='rules['.$i.']';
        echo '<fieldset class="g2rd-promo-rule"><legend>Règle <span class="g2rd-promo-rule-num">'.esc_html(is_numeric($i)?(string)((int)$i+1):'').'</span></legend><div class="g2rd-promo-fields"><div class="g2rd-promo-field"><label for="gp-'.esc_attr($n).'[type]">Type de remise</label><select id="gp-'.esc_attr($n).'[type]" name="'.esc_attr($n).'[type]">';
        foreach(['percent'=>'Pourcentage (−40 %)','amount'=>'Montant déduit (−5 €)','fixed'=>'Prix fixe (5 €)'] as $key=>$label) echo '<option value="'.esc_attr($key).'" '.selected($rule['type'],$key,false).'>'.esc_html($label).'</option>';
        echo '</select></div>';$this->field($n.'[value]','Valeur',$rule['value'],'number',['min'=>'0.001','step'=>'0.001','required'=>true]);echo '</div><div class="g2rd-promo-fields">';
        $this->terms($n.'[brands]',$tax,$rule['brands'],'Labels (aucun = tous)');$this->terms($n.'[categories]','product_cat',$rule['categories'],'Catégories (aucune = toutes)');echo '</div><div class="g2rd-promo-fields">';
        $this->field($n.'[products]','Articles spécifiques (IDs, remplacent labels et catégories)',implode(', ',$rule['products']));
        $this->field($n.'[priority]','Priorité manuelle (1 à 99, vide = automatique)',$rule['priority']?:'','number',['min'=>'1','max'=>'99','step'=>'1']);
        echo '</div><button type="button" class="button g2rd-promo-danger g2rd-promo-rule-remove">Supprimer la règle</button></fieldset>';
    }
    public function preview(): void {
        $this->guard();check_ajax_referer('g2rd_promo_preview','nonce');
        try{$p=wp_unslash($_POST);$p['enabled']=1;$c=$this->input($p,absint($p['id']??0));$c['id']=$c['id']?:PHP_INT_MAX;$c['start']=time()-1;$c['end']=time()+60;
            $count_only=($p['preview_mode']??'')==='count';$size=$count_only?100:20;
            $ids=$this->pricing->candidate_ids([$c]);$pages=max(1,(int)ceil(count($ids)/$size));$page=min($pages,max(1,absint($p['preview_page']??1)));
            $contexts=[];foreach($c['profiles'] as $profile)foreach($profile==='wholesale'?$this->repo->settings()['wholesale_roles']:[''] as $role)$contexts[]=['profile'=>$profile,'role'=>$role];
            $data=[];$matched=0;$counts=array_fill(0,count($c['rules']),0);
            foreach(array_slice($ids,($page-1)*$size,$size) as $id){$product=wc_get_product($id);if(!$product)continue;foreach($this->pricing->leaves($product) as $leaf){foreach($contexts as $n=>$ctx){$q=$this->pricing->quote($leaf,$ctx,[$c]);if(!$q)continue;$matched++;
                // Per-rule counts use the first targeted profile, so each article counts once.
                if($n===0)$counts[$q['rule']]++;
                if(!$count_only && count($data)<60)$data[]=['product'=>$leaf->get_name(),'profile'=>$this->profiles([$ctx['profile']]).($ctx['role']?' / '.$ctx['role']:''),'rule'=>$q['rule']+1,'base'=>wc_format_localized_price($q['base']),'price'=>wc_format_localized_price($q['price']),'currency'=>get_woocommerce_currency()];}}}
            wp_send_json_success(['rows'=>$data,'matched'=>$matched,'counts'=>$counts,'candidates'=>count($ids),'page'=>$page,'pages'=>$pages]);
        }catch(\Throwable $e){wp_send_json_error(['message'=>$e->getMessage()],400);}
    }
    private function settings(): void {
        $s=$this->repo->settings();$this->post_start('g2rd_promo_settings');$this->section('Labels / marques','Sélectionne la taxonomie réellement utilisée dans la boutique.');echo '<div class="g2rd-promo-field"><label for="gp-tax">Taxonomie</label><select id="gp-tax" name="brand_taxonomy">';foreach(get_object_taxonomies('product','objects') as $tax){if(in_array($tax->name,['product_cat','product_type','product_visibility'],true))continue;echo '<option value="'.esc_attr($tax->name).'" '.selected($tax->name,$s['brand_taxonomy'],false).'>'.esc_html($tax->label.' ('.$tax->name.')').'</option>';}echo '</select><small>Un changement désactive les règles de l’ancienne taxonomie jusqu’à modification.</small></div></section>';
        $this->section('Tarifs Wholesale','Sécurité : aucun tarif professionnel supposé.','users');echo '<div class="g2rd-promo-fields"><div class="g2rd-promo-field"><label for="gp-roles">Rôles Wholesale</label><select multiple size="6" id="gp-roles" name="wholesale_roles[]">';foreach(wp_roles()->roles as $key=>$role)echo '<option value="'.esc_attr($key).'" '.selected(in_array($key,$s['wholesale_roles'],true),true,false).'>'.esc_html(translate_user_role($role['name']).' ('.$key.')').'</option>';echo '</select></div><div class="g2rd-promo-field"><label for="gp-mode">Source du tarif</label><select id="gp-mode" name="wholesale_mode">';foreach(['off'=>'Désactivée (sécurité)','meta'=>'Métadonnée produit par rôle','filter'=>'Adaptateur PHP (filtre)'] as $key=>$label)echo '<option value="'.esc_attr($key).'" '.selected($key,$s['wholesale_mode'],false).'>'.esc_html($label).'</option>';echo '</select></div></div>';$this->field('wholesale_key','Clé de prix professionnel ({role} remplacé par le rôle)',$s['wholesale_key']);$this->field('wholesale_sale_key','Clé du prix promotionnel professionnel (facultatif)',$s['wholesale_sale_key']);echo '<p class="g2rd-promo-hint">Exemple à vérifier : {role}_wholesale_price. Les remises quantitatives ou par groupe nécessitent un adaptateur. Teste le tarif catalogue et panier avant activation.</p></section>';
        $this->section('Exclusions globales','Appliquées à toutes les campagnes.','shield');echo '<div class="g2rd-promo-fields">';$this->terms('exclude_brands',$s['brand_taxonomy'],$s['exclude_brands'],'Labels exclus');$this->terms('exclude_categories','product_cat',$s['exclude_categories'],'Catégories exclues (ex. Firm Price)');echo '</div>';$this->field('exclude_products','Identifiants produits exclus',implode(', ',$s['exclude_products']));echo '</section>';
        $this->section('Cache et performances','Précaution pour les tarifs dynamiques.');$this->toggle('cache_safe','Mode cache sécurisé','Empêche la mise en cache des pages publiques pendant qu’une campagne est active. Les caches (WP Rocket) sont purgés au début et à la fin de chaque campagne.',$s['cache_safe']);echo '</section><button class="button button-primary" type="submit">Enregistrer les paramètres</button></form>';
    }
    private function calendar(): void {
        $this->section('Calendrier des campagnes','Périodes dans le fuseau horaire du site.','calendar');$rows=$this->repo->all();usort($rows,static fn($a,$b)=>$a['start']<=>$b['start']);if(!$rows)echo '<p>Aucune campagne.</p>';foreach($rows as $r)echo '<div class="g2rd-promo-calendar-row"><span class="g2rd-promo-icon">'.wp_kses($this->icon('calendar'),$this->svg_allowed()).'</span><div><a class="g2rd-promo-name" href="'.esc_url($this->url(['tab'=>'edit','id'=>$r['id']])).'">'.esc_html($r['name']).'</a><small>'.esc_html($this->date($r['start']).' → '.$this->date($r['end'])).'</small></div>'.wp_kses_post($this->status($r)).'</div>';echo '</section>';
    }
    private function history(): void {
        $this->section('Journal des opérations','Les 200 dernières opérations de gestion et de planification.');echo '<div class="g2rd-promo-table-wrap"><table class="g2rd-promo-table"><thead><tr><th>Date</th><th>Campagne</th><th>Événement</th><th>Utilisateur</th><th>Détail</th></tr></thead><tbody>';foreach($this->repo->logs() as $r){$user=$r['actor']?get_userdata($r['actor']):null;echo '<tr><td>'.esc_html($this->date((int)$r['created'])).'</td><td>'.(int)$r['campaign_id'].'</td><td>'.esc_html($r['event']).'</td><td>'.esc_html($user?$user->display_name:'Système').'</td><td>'.esc_html($r['detail']).'</td></tr>';}echo '</tbody></table></div></section>';
    }
    private function diagnostic(bool $short=false): void {
        $s=$this->repo->settings();$checks=[['WooCommerce',class_exists('WooCommerce')?'Détecté':'Absent',class_exists('WooCommerce')],['Taxonomie des labels',taxonomy_exists($s['brand_taxonomy'])?$s['brand_taxonomy']:'À configurer',taxonomy_exists($s['brand_taxonomy'])],['Tarifs Wholesale',$s['wholesale_mode']==='off'?'Non configurés':'Configurés, à tester',false],['Planification',function_exists('as_schedule_single_action')?'Action Scheduler disponible':'WP-Cron',function_exists('as_schedule_single_action')],['WP Rocket',function_exists('rocket_clean_domain')?'Purge disponible':'Non détecté',function_exists('rocket_clean_domain')],['Facturation PDF',function_exists('wcpdf_get_document')?'WP Overnight détecté, à valider':'Métadonnées WooCommerce standard',false],['Cache sécurisé',$s['cache_safe']?'Activé':'Désactivé',$s['cache_safe']]];
        $this->section('État des intégrations','Détection technique, pas un test de bout en bout.','shield');foreach($checks as [$label,$value,$ok])echo '<div class="g2rd-promo-calendar-row"><span class="g2rd-promo-dot '.($ok?'is-ok':'').'"></span><strong>'.esc_html($label).'</strong><span class="g2rd-promo-push">'.esc_html($value).'</span></div>';echo '</section>';
    }
    private function stats_data(string $from,string $to): array {
        $a=Rules::parse_date($from.'T00:00',wp_timezone());$b=Rules::parse_date($to.'T00:00',wp_timezone());if($a===null||$b===null||$b<$a||$b-$a>366*DAY_IN_SECONDS)throw new \InvalidArgumentException('Période invalide (366 jours maximum).');$end=(new \DateTimeImmutable($to,wp_timezone()))->modify('+1 day')->getTimestamp()-1;
        $data=[];$page=1;$orders_seen=0;$truncated=false;
        do{$result=wc_get_orders(['type'=>'shop_order','status'=>['processing','completed','refunded'],'date_created'=>$a.'...'.$end,'limit'=>100,'page'=>$page,'paginate'=>true,'return'=>'objects','orderby'=>'date','order'=>'DESC']);foreach($result->orders as $order){$orders_seen++;foreach($order->get_items('line_item') as $item){$q=$item->get_meta(Orders::META,true);if(!is_array($q))continue;$key=$q['id'].'|'.$q['profile'].'|'.$order->get_currency();if(!isset($data[$key]))$data[$key]=['campaign'=>$q['name'],'id'=>$q['id'],'profile'=>$q['profile'],'currency'=>$order->get_currency(),'sales'=>0.0,'savings'=>0.0,'qty'=>0.0,'orders'=>[]];$qty=(float)$item->get_quantity();$remaining=max(0,$qty+min(0,(float)$order->get_qty_refunded_for_item($item->get_id())));$net=max(0,(float)$item->get_total()-abs((float)$order->get_total_refunded_for_item($item->get_id())));$data[$key]['sales']+=$net;$data[$key]['savings']+=(float)$q['saving_net']*($qty>0?$remaining/$qty:0);$data[$key]['qty']+=$remaining;$data[$key]['orders'][$order->get_id()]=true;}}$page++;if($page>50 && $page<=$result->max_num_pages){$truncated=true;break;}}while($page<=$result->max_num_pages);
        return ['rows'=>array_values($data),'truncated'=>$truncated,'scanned'=>$orders_seen];
    }
    private function stats(): void {
        $from=sanitize_text_field(wp_unslash($_GET['from']??wp_date('Y-m-d',time()-30*DAY_IN_SECONDS)));$to=sanitize_text_field(wp_unslash($_GET['to']??wp_date('Y-m-d')));
        $this->section('Statistiques commerciales','Commandes traitées, terminées et remboursées. Montants HT par devise, hors livraison.','chart');echo '<form method="get" class="g2rd-promo-toolbar" action="'.esc_url(admin_url('admin.php')).'"><input type="hidden" name="page" value="g2rd-promotions"><input type="hidden" name="tab" value="stats"><label>Du <input type="date" name="from" value="'.esc_attr($from).'"></label><label> au <input type="date" name="to" value="'.esc_attr($to).'"></label><button class="button">Filtrer</button></form>';
        try{$report=$this->stats_data($from,$to);if($report['truncated'])echo '<p class="g2rd-promo-hint">Rapport partiel : limite de 5 000 commandes. Réduis la période.</p>';echo '<p>'.(int)$report['scanned'].' commandes analysées.</p><div class="g2rd-promo-table-wrap"><table class="g2rd-promo-table"><thead><tr><th>Campagne</th><th>Profil</th><th>CA HT net</th><th>Remises HT (estimées après retours)</th><th>Quantité nette</th><th>Commandes</th></tr></thead><tbody>';foreach($report['rows'] as $r)echo '<tr><td>'.esc_html($r['campaign']).'</td><td>'.esc_html($this->profiles([$r['profile']])).'</td><td>'.wp_kses_post(wc_price($r['sales'],['currency'=>$r['currency']])).'</td><td>'.wp_kses_post(wc_price($r['savings'],['currency'=>$r['currency']])).'</td><td>'.esc_html((string)$r['qty']).'</td><td>'.count($r['orders']).'</td></tr>';echo '</tbody></table></div>';$this->post_start('g2rd_promo_export');echo '<input type="hidden" name="from" value="'.esc_attr($from).'"><input type="hidden" name="to" value="'.esc_attr($to).'"><button class="button">Exporter CSV</button></form>';}catch(\Throwable $e){echo '<p>'.esc_html($e->getMessage()).'</p>';}echo '</section>';
    }
    public function export(): void {
        $this->guard();check_admin_referer('g2rd_promo_export');
        try{$data=$this->stats_data(sanitize_text_field(wp_unslash($_POST['from']??'')),sanitize_text_field(wp_unslash($_POST['to']??'')));}
        catch(\Throwable $e){wp_die(esc_html($e->getMessage()));}
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="g2rd-promotions.csv"');
        // BOM + lignes CSV sans fwrite/fclose (Plugin Check / WP_Filesystem).
        echo "\xEF\xBB\xBF";
        $this->csv_echo(['Campagne','ID','Profil','Devise','CA HT','Remises HT estimees','Quantites','Commandes','Rapport partiel']);
        foreach($data['rows'] as $r){
            $name=(string)$r['campaign'];
            if(preg_match('/^[=+\-@\s]/u',$name))$name="'".$name;
            $this->csv_echo([$name,$r['id'],$r['profile'],$r['currency'],round((float)$r['sales'],2),round((float)$r['savings'],2),$r['qty'],count($r['orders']),$data['truncated']?'oui':'non']);
        }
        exit;
    }
    /** Écrit une ligne CSV échappée vers la sortie (compatible Plugin Check). */
    private function csv_echo(array $fields): void {
        $out=[];
        foreach($fields as $field){
            $value=(string)$field;
            if(str_contains($value,'"')||str_contains($value,';')||str_contains($value,"\n")||str_contains($value,"\r")){
                $value='"'.str_replace('"','""',$value).'"';
            }
            $out[]=$value;
        }
        echo implode(';',$out)."\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV binaire texte contrôlé
    }
    private function help(): void {
        $this->section('Guide de prise en main','G2RD WooCommerce Promotions v'.G2RD_PROMO_VERSION);echo '<ol><li>Dans Paramètres, sélectionne la taxonomie de labels et les exclusions globales.</li><li>Configure les rôles Wholesale et vérifie les clés de prix avec le plugin B2B.</li><li>Ajoute une campagne et ses règles : pourcentage, montant déduit ou prix fixe, par labels, catégories ou articles. Une règle sans label ni catégorie couvre tous les autres articles, y compris ceux sans label.</li><li>Choisis d’exclure ou d’inclure les articles déjà en promotion, puis les dates.</li><li>Calcule la prévisualisation et compte les articles par règle. Compare les prix de plusieurs produits sur une boutique de test.</li><li>Active la campagne. Vérifie panier, paiement, commandes, badges et facture.</li></ol><p>Les campagnes dynamiques ne modifient jamais le prix de référence enregistré dans les fiches produits. Les commandes conservent leur propre historique de remise.</p><p><strong>À valider :</strong> moteur Wholesale, thème, facture PDF, règles de cache, tri et filtre de prix. L’historique légal des prix sur 30 jours n’est pas fourni dans cette version.</p><h3>Changelog 1.1.0</h3><p>Plusieurs règles par campagne (pourcentage, montant déduit, prix fixe), priorités, articles déjà en promotion exclus ou inclus, coupons à usage individuel refusés, liste des promotions calculée en arrière-plan.</p><h3>Changelog 1.0.3</h3><p>Version complète pour déploiement (updater + design Verre).</p><h3>Changelog 1.0.2</h3><p>Design Verre G2RD et onglets sans rechargement.</p><h3>Changelog 1.0.1</h3><p>Notifications de mise à jour via GitHub Releases.</p><h3>Changelog 1.0.0</h3><p>Campagnes planifiées, profils clients, prix dynamiques, prévisualisation, historique des commandes, statistiques et diagnostic.</p></section>';
    }
}
