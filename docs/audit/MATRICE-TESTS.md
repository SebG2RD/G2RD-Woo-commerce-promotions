# Matrice de tests — G2RD WooCommerce Promotions 1.0.0

## 1. État des tests existants

| Élément | Valeur |
|---|---|
| Fichier | `tests/run.php` |
| Nature | CLI autonome, **mocks** WP/WC (pas de base, pas de Woo réel) |
| Exécution audit | `php tests/run.php` → **51 tests, 0 failure** (24/11/2026, PHP 8.4.3) |
| Appels `expect(` | 36 lignes (plusieurs asserts par ligne) |
| PHPUnit / wp-phpunit | Absents |
| E2E Playwright | Absent |
| CI GitHub Actions | Absent |

**Conclusion :** les tests valident la **logique pure** et des scénarios panier/commande **simulés**. Ils ne valident **pas** l’intégration WordPress/WooCommerce, ni la sécurité HTTP, ni les perfs.

---

## 2. Couverture des 51 asserts (regroupée)

| Famille | Couvert mock | Manques notables |
|---|---|---|
| Contexte guest/retail/wholesale | Oui | Multi-rôles, shop_manager storefront |
| Remise + getters price/regular/sale | Oui | Taxes display HTML réel |
| Intersection taxo / sous-cat / variation | Oui | Marques WC natives multi-plugins |
| Promo native protégée | Oui | **Dates from/to** |
| Override externe | Oui | Plugins pricing réels |
| Winner / tie-break | Oui | |
| Exclusions | Oui | |
| Dates future/fin exclusive/disabled | Oui | Changement heure réel WP |
| Wholesale meta / fail-closed | Oui | Plugin Wholesale Drakkar |
| Admin storefront off | Oui | AJAX whitelist |
| Panier reset/apply/idempotence | Partiel (2×) | 10×, blocs, session |
| Coupons non stackables | Oui | Coupon autorisé `coupons=true` |
| Snapshot order + totals | Oui | HPOS, refunds, Store API |
| Arrondi / 100 % / DST parse | Oui | Devises multi, Aelia/WPML |

---

## 3. Matrice minimale exigée — statut

| Scénario | Attendu | Mock | Intégration WP | E2E | Sécurité |
|---|---|---|---|---|---|
| 100 € −20 % → 80 € | OK | PASS (équiv.) | À faire | À faire | — |
| Promo existante 70 € | 70 € | PASS | À faire | À faire | — |
| Concurrentes 10 % / 30 % | 70 € | PASS | À faire | — | — |
| Campagne expirée | plein tarif | PASS | À faire | À faire | — |
| Programmée futur | plein tarif | PASS | À faire | À faire | — |
| Non éligible / exclu | plein tarif | PASS | À faire | — | — |
| Visiteur −20 % | 80 € | PASS | À faire | À faire | — |
| Wholesale non éligible | tarif pro | PASS | À faire | À faire | — |
| Wholesale 60 € −10 % → 54 € | OK | PASS (équiv.) | À faire | À faire | — |
| 10 recalculs panier | stable | Partiel (2) | À faire | — | — |
| Prix modifié navigateur | rejet/recalcul | — | À faire | À faire | À faire |
| Accès anonyme settings | refus | — | À faire | — | À faire |
| Suppression campagne post-commande | facture OK | PASS snapshot | À faire | À faire | — |
| Désactivation plugin | tarifs habituels | — | À faire | À faire | — |

---

## 4. Stratégie de tests à compléter

### 4.1 Unitaires (étendre `tests/run.php` ou PHPUnit)

- `has_native_sale` avec dates from/to (après correctif).
- 10× `reset_cart`/`apply_cart`.
- Campagne `coupons=true` laisse le coupon valide.
- `Rules::matches` matrice label×catégorie type Drakkar / Black Gangrene.
- Rate 0.001 / 99.999 / rejet 100.0001 (admin).

### 4.2 Intégration WP/WC (wp-env ou Local)

- Activation tables + option schema.
- Produit simple + variable + stock + virtuel.
- Checkout classique + Checkout blocks + Store API.
- HPOS on/off.
- Emails commande.
- Refund partiel / total + stats.

### 4.3 Sécurité

- Requêtes `admin-post` sans nonce → refus.
- Utilisateur `customer` → 403.
- Tentative IDOR campagne (si un jour endpoints par auteur).
- Store API : altération line items prix.

### 4.4 Performance

- k6 ou script WP-CLI : 1k / 10k produits.
- Query Monitor sur on-sale, catégorie, fiche, panier.

### 4.5 E2E / accessibilité

- Playwright : parcours visiteur + wholesale.
- axe-core sur écrans admin G2RD Promotions.

### 4.6 Non-régression anomalies

Chaque ID `P0-*` / `P1-*` doit avoir au moins un test automatisé **échouant avant correctif** puis passant après — **ne jamais supprimer l’assert**.

---

## 5. Règle qualité

- Syntaxe `php -l` ≠ test fonctionnel.
- Mock PASS ≠ production ready.
- Aucun test « réussi » sans exécution effective (cette règle a été respectée pour les 51 mocks).
