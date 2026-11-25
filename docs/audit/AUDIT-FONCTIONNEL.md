# Audit fonctionnel — G2RD WooCommerce Promotions 1.0.0

**Score fonctionnalités :** 66 / 100  
**Score fiabilité calculs :** 62 / 100  

---

## 1. Moteur de règles (`Rules.php`)

| Règle métier | Implémentation | Preuve |
|---|---|---|
| Intervalle `[start, end)` | `Rules::active` | Mock PASS (fin exclusive) |
| Matching profil | `in_array` profiles | Mock PASS |
| Labels OU entre eux, ET avec catégories | `matches()` | Mock PASS + doc |
| Exclusions produit / cat / brand | intersections | Mock PASS |
| Meilleure remise, puis plus petit ID | `winner()` | Mock PASS |
| Arrondi HALF_UP, plancher 0 | `price()` | Mock PASS (29,99→20,99 ; 100 %) |
| Parse date fuseau + rejet DST impossible | `parse_date` | Mock PASS |

**Statut :** logique pure **solide**.

---

## 2. Campagnes (CRUD admin)

| Fonction | Statut | Notes |
|---|---|---|
| Création / édition | Présent | Validation stricte (`input`) |
| Brouillon (`enabled=false`) | Présent | |
| Activation / désactivation | Présent | Refuse activation si fin passée, taxo changée, Wholesale off |
| Duplication | Présent | Copie en brouillon |
| Suppression | Présent | Confirm JS + nonce |
| Programmation auto | Présent | Action Scheduler / WP-Cron bornes + reconcile 5 min |
| Fuseau WP | Présent | Stockage timestamp UTC, saisie `wp_timezone` |
| Campagnes simultanées | Présent | Non-cumul |
| Historique | Présent | 200 dernières ops |
| Recherche / filtres | Partiel | Filtre **JS** sur table déjà chargée, pas de pagination SQL |
| Prévisualisation | Présent | AJAX, 20 parents/page, max 60 lignes affichées |
| Export CSV | Présent | Stats, pas export de définition de campagnes |
| Statistiques | Partiel | Estimation remboursements ; max 5 000 commandes |
| Calendrier | Partiel | Liste chronologique, pas de vue graphique |

---

## 3. Sélection produits

| Cas | Statut |
|---|---|
| Taxonomie labels configurable | Présent (`product_brand` défaut) |
| Catégories + descendants | Présent (`get_ancestors` + `include_children` candidats) |
| Intersection label ET catégorie | Présent |
| Produits simples / variations | Présent (`quote` limite à simple+variation) |
| Variables (fourchette) | Partiel via filtres `variation_prices_*` — **non testé WC réel** |
| Nouveaux produits pendant campagne | Éligibilité à la volée (pas de snapshot produit) — OK conception |
| Produits importés / reclassés | Invalidation terms + quote dynamique — OK conception |
| Produits supprimés | Naturellement hors catalogue |
| Rupture / virtuel / téléchargeable | Pas d’exclusion automatique (comportement à valider métier) |
| Exclusions individuelles / label / cat | Présent (campagne + global) |

**Exemple Drakkar (label × catégorie) :** couvert par la logique `matches` + tests d’intersection.  
**Non prouvé** sur données réelles Drakkar / Black Gangrene.

---

## 4. Protection des promotions existantes

### Comportement voulu (doc + code)

- Promo WooCommerce individuelle prioritaire.
- Promo Wholesale détectée (meta/filtre) prioritaire.
- Overrides prix tiers : `compatible_input` refuse d’écraser.
- Meilleure campagne G2RD seule (pas de cumul).
- Prix `edit` / admin / cron / REST hors store non altérés (`storefront()`).

### Anomalie P1-CALC-001 — dates de promo natives ignorées

- **Fichier :** `Pricing.php` lignes 84-87 `has_native_sale()`
- **Problème :** lit `get_sale_price('edit')` et compare à regular **sans** vérifier `_sale_price_dates_from/to` ni `is_on_sale()` natif.
- **Effets possibles :**
  1. Produit avec promo **future** encore stockée → G2RD **bloqué** trop tôt (manque à gagner).
  2. Promo **expirée** dont la meta sale reste → G2RD **bloqué** à tort.
- **Impact financier :** remises G2RD non appliquées alors qu’elles devraient l’être (ou inversement selon nettoyage meta WC).
- **Correction recommandée :** s’appuyer sur l’état « réellement en promo maintenant » (dates WC) tout en restant fail-safe pour les promos actives/programmées selon la règle métier écrite.
- **Tests à ajouter :** sale future, sale en cours, sale expirée avec meta résiduelle.

### Autres points

| Sujet | Statut |
|---|---|
| Coupons | Présent (blocage panier entier si campagne non stackable) |
| Extensions pricing dynamiques | Tentative de coexistence via `compatible_input` — **non testé** avec plugins tiers |
| Campagnes G2RD concurrentes | Mock PASS |

---

## 5. Profils clients

| Profil | Détection | Remise | Preuve |
|---|---|---|---|
| Visiteur | `!is_user_logged_in` → `guest` | campagnes `guest` | Mock PASS |
| Particulier | rôle non wholesale → `retail` | campagnes `retail` | Mock PASS |
| Wholesale configuré | rôles settings + meta/filtre | base B2B | Mock PASS (18→16,20) |
| Wholesale non configuré | mode `off` / meta absente | **aucune** remise B2B | Mock PASS |
| Rôle contenant wholesale/b2b non mappé | profil wholesale forcé, base null | fail-closed | Mock PASS |
| Multi-rôles Wholesale | ordre de `wholesale_roles` | Présent | Non testé multi |
| Admin en back-office | `storefront()` false | prix bruts | Mock PASS |
| Admin AJAX front whitelist | fragments / checkout… | Présent | Non testé |

**Recalcul login/logout :** `cart_loaded_from_session` + `calculate_totals` — conception OK, runtime **non évalué**.

---

## 6. Affichage boutique

| Point | Statut |
|---|---|
| Prix barrés / sale via getters WC | Conception native (bonne pour badges thème) |
| `price_html` Wholesale forcé en sale format | Présent |
| Catalogue / catégories / labels | Via getters — **non testé thème Elessi** |
| Blocs Gutenberg / AJAX recherche | Non testé |
| Mini-panier / wishlist | Non testé |
| On sale | Branché mais perf dangereuse (voir perf) |
| Schema.org / flux externes | **Non évalué** |
| Duplication badges | Non observée dans le plugin (pas de badge CSS front custom) — à valider avec thème |

---

## 7. Panier → commande → facture

| Étape | Code | Preuve |
|---|---|---|
| Recalcul panier idempotent | `reset` + `apply` + clone ligne | Mock PASS |
| Notice si promo change | `wc_add_notice` | Présent code |
| Classic checkout snapshot | `classic_line` | Mock PASS |
| Blocks / Store API snapshot | `blocks_order` drafts only | Code present, **non testé** |
| Historique commande figé | meta `_g2rd_promotion_snapshot` | Mock PASS après expiration règle |
| Totaux informatifs (pas double déduction) | `totals` / `cart_savings` | Mock PASS |
| PDF WP Overnight | hooks | Détection seule |
| Paiements / HPOS | déclaration HPOS ; pas d’appel gateway | **Non testé** |
| Expiration pendant tunnel | recalcul avant total | Conception OK, **non testé** |

---

## 8. Matrice fonctionnelle demandée — statut d’audit

| Scénario | Attendu | Résultat audit |
|---|---|---|
| 100 € −20 % | 80 € | Mock PASS (30→24 équivalent) |
| Promo native 70 € | 70 € | Mock PASS (30/25) |
| Concurrentes 10 % et 30 % | 70 € | Mock PASS (winner 30 %) |
| Expirée / future / non éligible / exclu | prix plein | Mock PASS |
| Visiteur −20 % | OK | Mock PASS |
| Wholesale non éligible | tarif pro | Mock PASS |
| Wholesale 60 € −10 % | 54 € | Mock équivalent 18→16,2 PASS |
| 10 recalculs panier | stable | 2 recalculs PASS ; 10 **non rejoués** |
| Manipulation navigateur | rejet serveur | Conception OK ; HTTP **non testé** |
| Accès anonyme settings | refus | Code 403 ; HTTP **non testé** |
| Suppression campagne après commande | facture inchangée | Mock PASS snapshot |
| Désactivation plugin | tarifs habituels | Conception (filtres absents) ; **non testé install** |

---

## 9. Verdict fonctionnel

Le **cœur métier unitaire** est crédible et prudent.  
La **homologation boutique** (Drakkar : Wholesale, thème, blocs, paiements, factures) est **absente**.  
Corriger P1-CALC-001 avant toute campagne réelle.
