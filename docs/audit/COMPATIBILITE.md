# Compatibilité — G2RD WooCommerce Promotions 1.0.0

**Score compatibilité :** 42 / 100  
**Lecture :** beaucoup d’éléments sont « déclarés » ou « branchés dans le code », très peu sont « prouvés sur une boutique réelle ».

---

## 1. Plateforme

| Composant | Exigence plugin | Statut audit |
|---|---|---|
| PHP | ≥ 8.1 | Syntaxe OK sous PHP 8.4.3 ; WeakMap OK |
| WordPress | ≥ 6.6 | Non testé runtime |
| WooCommerce | requis (`Requires Plugins`) | Non testé runtime |
| HPOS (Custom Order Tables) | `declare_compatibility(..., true)` | Déclaré ; snapshots via CRUD item → conception OK ; **non testé** |
| Multisite | non documenté | Non géré explicitement → **risque** activation réseau |

---

## 2. WooCommerce features

| Feature | Intégration code | Test |
|---|---|---|
| Produits simples | Oui | Mock |
| Variations | Oui (filters + hash) | Mock partiel |
| Produits groupés / externes | Hors `quote` types | Non évalué métier |
| Panier classique | Oui | Mock |
| Checkout classique | Snapshot ligne | Mock |
| Cart/Checkout Blocks + Store API | Hooks coupon errors + snapshot drafts | **Non testé** |
| Coupons | Blocage / option stack | Mock (blocage) |
| Taxes / shipping | Délégués WC | Non testé |
| Emails | Meta item visibles si WC les affiche | Non testé |
| On sale / widgets | `pre_transient_wc_products_onsale` | Code dangereux perf |
| `wc_product_meta_lookup` tri/filtre prix | **Non mis à jour** | Limitation connue — **P1** |
| Schema.org / flux Google | Non traité | Non évalué |

---

## 3. Wholesale / B2B

| Mode | Statut |
|---|---|
| `off` (défaut) | Fail-closed — **recommandé** tant que non validé |
| `meta` (`{role}_wholesale_price`) | Codé + mock OK |
| `filter` adaptateur PHP | Hooks documentés ; **aucun adaptateur Drakkar fourni** |

**Identification extension Wholesale Drakkar :**  
Impossible sur ce poste (pas de `wp-content/plugins` Drakkar complet ; Local Sites vide ; `drakkar-plateforme/site-test` n’est pas une install WP boutique).

**Ne pas activer de campagne Wholesale en production sans adaptateur testé.**

---

## 4. Facturation PDF

| Extension | Détection | Adaptateur | Test |
|---|---|---|---|
| WP Overnight (WooCommerce PDF Invoices) | `wcpdf_get_document` / hooks `wpo_wcpdf_*` | Totaux + résumé opt-in | **Non testé** |
| Autres PDF (mPDF, Factures FR tierces, G2RD Facturation) | — | Inconnu | Repo Facturation **404** → **non testé / non comparable** |

Règle : l’économie G2RD est **informative** ; le total payé reste celui de WooCommerce.

---

## 5. Paiements

| Gateway | Intégration plugin | Test |
|---|---|---|
| PayPal Payments | Aucune (s’appuie sur total WC) | Non |
| WooPayments | Idem | Non |
| Stripe | Idem | Non |
| Express pay (Apple/Google) | Sensible aux prix cache/session | Non |

Le montant gateway **doit** égaler le total commande WC après recalcul. À prouver en **sandbox uniquement**.

---

## 6. Cache / perf stack

| Outil | Support | Remarque |
|---|---|---|
| WP Rocket | `rocket_clean_domain()` | Full purge — lourd |
| Autres page cache | `DONOTCACHEPAGE` + nocache headers | Si respectés |
| CDN / reverse proxy | Non contrôlé | **Point de vigilance Drakkar** |
| Redis Object Cache | Indirect | Hash variations séparé |
| Action Scheduler | Boundaries + reconcile | Préféré à WP-Cron |

---

## 7. Thème / UI G2RD

| Élément | Statut |
|---|---|
| Indépendance thème (assets front absents) | Oui |
| Elessi child Drakkar (copie locale partielle) | Non audité en rendu prix/badges |
| Console native thème FSE | Non implémentée |
| Design admin vs Connector | Proche mode autonome Verre |
| G2RD Facturation UI | Incomparable (accès manquant) |

---

## 8. Matrice de décision go-live Drakkar

| Intégration | Peut-on go-live sans ? |
|---|---|
| Wholesale non configuré | Oui **si aucune campagne B2B** |
| Lookup prix / filtres prix | Non si la boutique expose tri/filtre prix |
| WP Rocket + CDN | Non sans plan de purge / vary |
| PayPal/Stripe sandbox OK | Non |
| Facture PDF modèle réel | Non si factures clients légales |
| L112-1-1 B2C | Non pour campagnes grand public FR |
| Thème badges natifs | À valider visuellement |

---

## 9. Accès manquants (bloquants pour clôturer la compatibilité)

1. Boutique WordPress/WooCommerce de **recette** clonée de Drakkar.
2. Liste exacte des plugins (Wholesale, facturation, cache, paiement).
3. Dépôt `SebG2RD/g2rd-facturation` (privé ou inexistant en public).
4. Accès staging CDN / WP Rocket.

Dès que ces accès sont fournis, reprendre la phase 3 du plan de corrections **sans toucher à la production**.
