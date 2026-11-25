# Audit global — G2RD WooCommerce Promotions 1.0.0

**Date :** 24 novembre 2026  
**Auditeur :** analyse statique du code source + exécution des tests autonomes + contrôles PHPCS  
**Périmètre :** dépôt local `g2rd-woocommerce-promotions` (8 fichiers PHP, ~867 lignes)  
**Code source modifié pendant l’audit :** non  
**Verdict :** **Non prêt pour la production**

---

## 1. Résumé exécutif

Le plugin est une première version de **recette** cohérente : moteur de règles clair, protection des promotions natives, séparation des profils (visiteur / particulier / Wholesale), métadonnées de commande, interface admin inspirée du design « Verre » G2RD Connector.

En revanche, **aucun test d’intégration WordPress/WooCommerce réel** n’a pu être exécuté ici. Les 51 tests existants passent, mais sur des API simulées. Plusieurs risques bloquants pour Drakkar restent ouverts : conformité prix de référence 30 jours absente, performances du filtre « en promo », Wholesale non branché sur l’extension réelle, tables de recherche WooCommerce non adaptées aux prix dynamiques, absence de dépôt Git / CI.

**Ne pas déployer sur https://drakkar666.com tant que les phases 1 à 3 du plan de corrections ne sont pas validées sur une boutique de recette isolée.**

---

## 2. Méthode de notation (sur 100)

Chaque score part de **100**, puis des points sont retirés selon des critères mesurables.  
Si la preuve d’exécution manque, la note est plafonnée et/ou marquée **non évalué** sur la sous-partie concernée.

| Critère | Déduction typique |
|---|---|
| Anomalie P0 confirmée | −25 |
| Anomalie P1 confirmée | −12 |
| Anomalie P2 | −5 |
| Anomalie P3 | −2 |
| Domaine sans test réel alors qu’il est critique | plafond 55 et mention « non évalué » |

---

## 3. Scores

| Domaine | Score | Commentaire court |
|---|---:|---|
| Sécurité | **74** | Capacités + nonces bien posés ; pas de SQLi/XSS évidente. Pas de scan dynamique (ZAP). Fuite Wholesale possible si cache mal réglé. |
| Qualité du code | **48** | Architecture claire, mais style ultra-compact hors WordPress Coding Standards (centaines d’erreurs PHPCS). |
| Fonctionnalités | **66** | Cœur campagnes / prix / commandes présent. Manquent conformité 30 j, lookup prix, mode console thème, EN. |
| Fiabilité des calculs | **62** | 51/51 mocks OK (idempotence, non-cumul, Wholesale fail-closed). Bug probable des soldes programmées WC. Intégrations réelles **non évaluées**. |
| Performances | **38** | `on_sale_ids` peut parcourir tout le catalogue ; `cache_safe` désactive le cache page globale ; pas de bench 10k/50k. |
| Compatibilité | **42** | HPOS déclaré. Wholesale / PayPal / Stripe / factures / thème Drakkar **non testés**. Repo Facturation inaccessible (404). |
| UX/UI | **73** | Aligné Connector (cartes 16px, accent WP, icônes 1,75). Calendrier graphique et mode console absents. |
| Accessibilité | **58** | Labels, `aria-live`, focus-visible présents. Pas de audit axe / WCAG instrumenté → partiellement **non évalué**. |
| Maintenabilité | **64** | Petit volume, namespaces, responsabilités séparées. `Admin.php` monolithique ; pas d’autoload Composer ni CI. |
| Conformité | **22** | L.112-1-1 (prix le plus bas 30 jours) **non implémenté**. Risque réglementaire B2C majeur. |

**Moyenne indicative (non pondérée) : 54,7 / 100.**

---

## 4. Cartographie technique

### 4.1 Arborescence réelle

```
g2rd-woocommerce-promotions/
├── g2rd-woocommerce-promotions.php   # bootstrap
├── uninstall.php
├── readme.txt / CHANGELOG.md / LICENSE
├── assets/admin.css + admin.js
├── docs/RECETTE.md (+ docs/audit/*)
├── includes/
│   ├── Rules.php        # logique pure dates / matching / prix
│   ├── Repository.php   # SQL, cron, cache
│   ├── Pricing.php      # moteur WooCommerce
│   ├── Orders.php       # snapshots commande / PDF
│   └── Admin.php        # UI + AJAX + stats + CSV
└── tests/run.php        # 51 assertions simulées
```

### 4.2 Cycle de vie

| Étape | Comportement observé |
|---|---|
| Activation | `Repository::install()` + `dbDelta` tables `g2rd_promotions` / `g2rd_promotion_logs` |
| Runtime | Si schéma ≠ `1` → moteur **désactivé** (fail-closed) |
| Désactivation | Annule cron / Action Scheduler, invalide caches, **conserve** données |
| Désinstallation | Conserve tout sauf si `G2RD_PROMOTIONS_DELETE_DATA=true` |
| Multisite | Non traité explicitement |

### 4.3 Hooks majeurs

- Prix : `woocommerce_product(_variation)_get_{price|regular_price|sale_price}` (prio 9999)
- Variations hash, `is_on_sale`, `get_price_html`
- Panier : `before_calculate_totals` (−1000 reset / 99999 apply), session refresh
- Coupons : `coupon_is_valid`, `check_cart_items`, Store API errors
- Commandes : checkout classique + Store API draft
- Facture : totaux Woo + hooks WP Overnight
- Cache : `template_redirect` DONOTCACHEPAGE, purge Rocket, epoch UUID
- Admin : `admin_post_*`, `wp_ajax_g2rd_promo_preview`

**Aucun endpoint REST custom.** Surface d’attaque API limitée au Store API WooCommerce déjà existant.

### 4.4 État Git / dépendances

- **Pas de dépôt `.git`** dans le dossier audité.
- Pas de `composer.json`, `package.json`, `.github/workflows`, fichiers de traduction.
- Dépendance runtime : WooCommerce uniquement.
- Référence Connector : accessible (public). Référence Facturation : **404 GitHub**.

---

## 5. Preuves d’exécution obtenues

| Contrôle | Résultat |
|---|---|
| `php -l` sur 7 fichiers PHP | OK |
| `php tests/run.php` | **51 tests, 0 failure** |
| `node --check assets/admin.js` | OK |
| PHPCS WordPress-Core | Nombreuses erreurs de style (espaces, tableaux courts, structures inline) |
| PHPCompatibilityWP 8.1+ | 1 alerte `break` (faux positif probable sur `foreach` compact) |
| Installation WP/WC locale Drakkar | **Absente** (pas de `wp-content/plugins` exploitable) |
| OWASP ZAP / k6 / axe / Query Monitor | **Non exécutés** (environnement manquant) |

---

## 6. Fonctionnalités — synthèse

### Validées en logique unitaire (mocks)

- Remise %, non-cumul (meilleure offre), tie-break ID
- Intersection label **ET** catégorie, héritage sous-catégories
- Exclusion produit parent → variations
- Campagne future / expirée / désactivée
- Protection promo WooCommerce native (prix sale < regular)
- Wholesale fail-closed sans config / sans meta
- Exemple 30 € −30 % → 21 € ; Wholesale 18 € −10 % → 16,20 €
- Idempotence panier (2 recalculs)
- Snapshot commande + ligne « économie » informative (pas de double soustraction)
- Dates invalides / heure DST inexistante rejetées

### Partielles

- Wholesale (adaptateur à écrire et tester)
- Factures PDF (hooks présents, modèle réel non validé)
- Cache (purge Rocket oui ; CDN/Redis non maîtrisés)
- Store API / blocs (hooks présents, **non testés**)
- Stats / CSV (limite 5 000 commandes, estimation remboursements)
- UX admin (solide mais sans console thème native)

### Absentes

- Historique légal des prix 30 jours (L.112-1-1)
- Réindexation / contournement `wc_product_meta_lookup`
- Traduction anglaise complète
- CI/CD, packaging ZIP contrôlé
- Tests d’intégration / E2E / charge / accessibilité instrumentés
- Calendrier graphique, graphiques avancés

---

## 7. Anomalies prioritaires (vue globale)

| ID | Sévérité | Titre |
|---|---|---|
| P0-PERF-001 | P0 | Parcours catalogue complet via `on_sale_ids` |
| P1-CALC-001 | P1 | `has_native_sale()` ignore les dates de promo WC |
| P1-CONF-001 | P1 | Conformité prix de référence 30 jours absente |
| P1-COMPAT-001 | P1 | Tri / filtre prix lookup incohérents avec prix dynamiques |
| P1-COMPAT-002 | P1 | Wholesale Drakkar non identifié / non branché |
| P1-CACHE-001 | P1 | Risque de fuite de prix si `cache_safe` off ou CDN non purgé |
| P2-PERF-002 | P2 | Purge Rocket + invalidation fréquente |
| P2-QA-001 | P2 | Couverture limitée aux mocks |
| P2-ARCH-001 | P2 | Hors standards WPCS / pas de CI |
| P3-I18N-001 | P3 | Dossier `languages` absent |
| P3-UX-001 | P3 | Pagination campagnes côté client uniquement |

Détails dans les rapports thématiques.

---

## 8. Limitations de l’environnement d’audit

1. Aucune boutique WordPress/WooCommerce de recette démarrable depuis ce poste pour le plugin.
2. Pas d’accès au dépôt `SebG2RD/g2rd-facturation` (404).
3. Pas d’identification de l’extension Wholesale réellement installée sur Drakkar.
4. Aucune transaction sandbox PayPal/Stripe/WooPayments.
5. Aucun test de charge 10k/50k produits.
6. Dépôt Git absent → pas d’historique de commits ni de workflows Actions à auditer *in situ*.

---

## 9. Verdict argumenté

**Non prêt pour la production.**

Justification :

1. Risque financier et réglementaire B2C (affichage de pourcentages sans prix de référence légal).
2. Risque de performance majeur sur les listes « en promo » / catalogues larges.
3. Intégrations critiques Drakkar (Wholesale, paiement, facture, thème, cache CDN) **non prouvées**.
4. Bug logique probable sur les promotions WooCommerce datées.
5. Les 51 tests verts ne remplacent pas une homologation sur une copie de boutique.

Le plugin est cependant une **base sérieuse de recette** : fail-closed Wholesale, protection des overrides externes, snapshots de commande, documentation `RECETTE.md` honnête sur ses limites.

**Prochaine étape attendue :** validation du `PLAN-CORRECTIONS.md`, puis corrections par lots (sans toucher la production).
