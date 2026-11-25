# Audit performances — G2RD WooCommerce Promotions 1.0.0

**Score :** 38 / 100  
**Benchmarks 100 / 1k / 10k / 50k produits :** **non exécutés** (pas de boutique de charge)  
**Outils manquants ici :** Query Monitor, k6, New Relic, etc.

---

## 1. Constat principal

Le plugin est pensé pour la **correctness** (prix justes, caches invalidés, pages non cachées pendant campagnes) plus que pour le **passage à l’échelle**. Sur une boutique type Drakkar (catalogue dense, labels, variations), plusieurs chemins peuvent devenir coûteux.

---

## 2. Anomalies

### P0-PERF-001 — `on_sale_ids` parcourt le catalogue
- **Fichier :** `includes/Pricing.php` 284-296
- **Comportement :** si des campagnes actives concernent le profil courant, le filtre `pre_transient_wc_products_onsale` :
  1. lit le transient natif ;
  2. pagine `candidates()` par 200 ;
  3. pour chaque produit parent, charge les variations visibles ;
  4. exécute `quote()` ;
  5. fusionne les IDs.
- **Complexité :** O(produits_candidats × variations) **par miss de transient**, potentiellement à chaque invalidation d’epoch.
- **Impact :** TTFB élevé, timeouts, charge MySQL N+1 (`wc_get_product`, taxonomies).
- **Correction :**
  - plafond (ex. abandonner l’enrichissement au-delà de N produits) ;
  - précalcul Action Scheduler → option/transient versionné ;
  - ou documenter « désactiver On sale / ne pas utiliser ce filtre » pour gros catalogues.
- **Tests charge :** pages `/on-sale/`, widgets, blocs « produits en promo ».

### P2-PERF-002 — Invalidation agressive
- **Fichier :** `Repository::invalidate`
- **Effets :** nouvel UUID epoch + `WC_Cache_Helper` product + `rocket_clean_domain()` complet + action `g2rd_promotions_cache_invalidated`.
- **Déclencheurs :** save/delete campagne, settings, boundary start/end, reconcile si signature change.
- **Risque :** tempêtes de purge si nombreuses campagnes ou reconcile bruyant.
- **Correction :** purge ciblée (URLs produits touchés) ; debounce ; ne full-clean Rocket qu’aux transitions start/end.

### P2-PERF-003 — `cache_safe` = no-cache global front
- **Fichier :** `Pricing::cache_control`
- **Effet :** `DONOTCACHEPAGE` sur **toutes** les requêtes front dès qu’une campagne enabled a `end > now` (donc aussi les programmées futures).
- **Impact :** hausse TTFB / CPU PHP pendant toute la durée de planification + diffusion.
- **Trade-off :** volontaire (évite fuite prix). À mesurer vs CDN edge.
- **Piste :** no-cache seulement pages produit/catégorie concernées, ou vary-by-cookie rôle.

### P2-PERF-004 — Chargement de toutes les campagnes
- **Fichier :** `Repository::all()` → `SELECT * … ORDER BY id DESC` sans pagination.
- **Impact :** faible à dizaines de campagnes ; à surveiller à centaines.
- **Correction :** `active()` SQL avec `WHERE enabled=1 AND starts<=? AND ends>?` + index déjà présent `KEY schedule (enabled,starts,ends)`.

### P3-PERF-005 — Cache requête `terms` / `quotes`
- Présent en mémoire d’instance (`$this->terms`, `$this->quotes`) — bon pour une page.
- Invalidé sur save produit / set_object_terms — OK.
- Pas de object cache partagé (Redis) pour quotes cross-request — acceptable v1.

### P3-PERF-006 — Assets admin
- CSS/JS chargés seulement si `page=g2rd-promotions` — **bon**.
- Aucun asset front plugin — **bon**.

### P2-PERF-007 — Stats admin
- Jusqu’à 50 pages × 100 commandes, hydratation objets WC — lourd pour shop_manager.
- À basculer en rapport async.

---

## 3. Caches et planification

| Mécanisme | Support code | Test réel |
|---|---|---|
| Transients WC on-sale | Filtre `pre_transient_wc_products_onsale` | Non |
| Variation prices hash (+ profil/rôle/user) | Oui | Non |
| Epoch `g2rd_promo_cache_epoch` | Oui | Non |
| WP Rocket `rocket_clean_domain` | Si fonction existe | Non |
| Redis Object Cache | Indirect via invalidation WC | Non |
| OPcache | N/A code | — |
| Action Scheduler boundaries | Oui | Non |
| Reconcile 5 min | Oui (AS) / hourly WP-Cron fallback | Non |
| Cron en retard | Dates revalidées à chaque `quote()` | Mock logique OK |

**Point positif important :** même si le cron rate, une campagne expirée n’est pas appliquée car `Rules::active($r, time())` est réévalué au calcul.

---

## 4. Estimations (non mesurées — hypothèses)

Ces chiffres sont des **ordres de grandeur d’analyse**, pas des mesures.

| Scénario | Risque |
|---|---|
| 100 produits, 1 campagne ciblée | Faible |
| 1 000 produits, campagne « tous labels/cats » | Moyen sur on-sale |
| 10 000+ / 50 000 | **Élevé à critique** sur on-sale + tax_query larges |
| 100 campagnes actives | Mémoire `all()` + winner loop : moyen |

---

## 5. Comparaison avec / sans plugin

**Non mesurée.** À faire en recette avec Query Monitor :

1. Accueil, catégorie, fiche, panier, checkout.
2. Avec campagne active vs plugin désactivé.
3. Compter requêtes SQL, temps PHP, pics mémoire.

---

## 6. Recommandations priorisées

1. Réécrire ou borner `on_sale_ids` (bloquant prod catalogue large).
2. Mesurer l’impact `cache_safe` sur TTFB Drakkar ; affiner vary-by-role.
3. SQL `active()` indexé au lieu de filtrer PHP.
4. Debounce / ciblage des purges Rocket.
5. Rapport k6 + QM annexé avant go-live.
