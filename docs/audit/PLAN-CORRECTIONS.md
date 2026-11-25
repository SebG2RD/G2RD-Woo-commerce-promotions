# Plan de corrections — G2RD WooCommerce Promotions 1.0.0

**Statut :** en attente de validation avant toute modification du code source  
**Principe :** corrections par lots cohérents, commits distincts, tests de non-régression à chaque lot  
**Interdit :** affaiblir ou supprimer un test pour le faire passer

---

## Phase 1 — Vulnérabilités critiques et risques financiers

| ID | Action | Fichiers | Risque régression | Tests |
|---|---|---|---|---|
| P0-PERF-001 / SEC-002 | Réécrire `on_sale_ids` : plafond +/ou précalcul AS + option désactivation | `Pricing.php`, éventuellement `Repository.php`, `Admin.php` (réglage) | Pages « On sale » incomplètes si plafond | Mock + bench 1k/10k + QM |
| P1-CALC-001 | Corriger détection promo native avec dates WC | `Pricing.php`, `tests/run.php` | Produits autrefois exclus peuvent entrer en campagne | Mocks sale future/active/expired |
| P1-CACHE-001 / SEC-001 | Alerte admin si Wholesale on + `cache_safe` off ; doc CDN ; vary cookies | `Admin.php`, `Pricing.php`, `docs/RECETTE.md` | Commerçant forcé de garder no-cache | Recette 2 navigateurs |

**Critère de sortie phase 1 :** plus aucun P0 ; P1 calcul + cache traités ou contournement documenté accepté par le métier.

---

## Phase 2 — Anomalies fonctionnelles

| ID | Action | Fichiers | Tests |
|---|---|---|---|
| P2-FUNC coupons autorisés | Couvrir `coupons=true` dans tests | `tests/run.php` | Coupon valide si flag |
| P2-FUNC 10 recalculs | Étendre idempotence panier ×10 | `tests/run.php` | Prix stable |
| P3 recherche campagnes | Pagination SQL + filtres serveur | `Admin.php`, `Repository.php` | UI manuelle |
| Produits exclus UX | Sélecteur AJAX nom/SKU | `Admin.php`, `admin.js` | A11y clavier |

---

## Phase 3 — Compatibilité WooCommerce, Wholesale, paiement, facturation

| ID | Action | Dépendances | Tests |
|---|---|---|---|
| P1-COMPAT-002 | Identifier plugin Wholesale Drakkar sur **recette** ; écrire adaptateur mini-plugin | filtre `g2rd_promotions_wholesale_*` | Matrice B2B RECETTE |
| P1-COMPAT-001 | Stratégie lookup prix : désactiver widgets prix **ou** table/index dédié **ou** doc blocage go-live | à définir | Tri/filtre catégorie |
| Paiements | Sandbox PayPal / WooPayments / Stripe | boutique recette | Montant gateway = total WC |
| Factures | Valider WP Overnight + modèle réel ; activer `g2rd_promotions_pdf_summary` si besoin | Orders.php inchangé si possible | PDF + email |
| Blocks / Store API | Parcours checkout blocks guest + wholesale | | Meta snapshot présentes |
| HPOS | Commandes CRUD | | Meta item lisibles |

**Accès manquant signalé :** repo `g2rd-facturation` 404 ; pas de copie WP complète Drakkar sur ce poste.

---

## Phase 4 — Performances et cache

| ID | Action | Fichiers |
|---|---|---|
| P2-PERF-002 | Debounce / purge Rocket ciblée | `Repository.php` |
| P2-PERF-003 | Affiner `cache_safe` (vary-by-role, pages concernées) | `Pricing.php` |
| P2-PERF-004 | `active()` SQL indexé | `Repository.php` |
| P2-PERF-007 | Stats/export async Action Scheduler | `Admin.php` |
| Bench | Script QM/k6 annexé dans `docs/audit/` | recette |

---

## Phase 5 — UX/UI et accessibilité

| ID | Action |
|---|---|
| P2-UX-001 | Alignement toggles/champs 44 px / tokens Connector |
| Dirty form | Warning quitter éditeur |
| P3-UX-003 | Modale suppression accessible |
| axe-core | Corriger findings admin |
| Mode console thème | Optionnel, hors scope critique |

**Contrainte :** ne pas modifier Connector, Facturation, ni thème parent G2RD/Elessi.

---

## Phase 6 — Conformité réglementaire

| ID | Action |
|---|---|
| P1-CONF-001 | Conception + implémentation historique prix 30 jours **ou** mode « pas de % affiché » + process manuel validé juridiquement |
| Journal de preuves | Export prix de référence par campagne |
| B2B | Clarifier affichage (pas de faux % grand public sur tarif pro) |

Sans phase 6 validée, **pas de campagnes B2C publiques** recommandées en France.

---

## Phase 7 — Documentation et CI/CD

| Action | Détail |
|---|---|
| Initialiser Git | Si souhaité par le propriétaire du dépôt |
| `composer.json` dev | phpcs, phpcompatibility, phpunit |
| GitHub Actions | lint PHP, `tests/run.php`, phpcs, gitleaks, build ZIP |
| Dependabot | si deps ajoutées |
| `.distignore` / contrôle ZIP | exclure `docs/audit` optionnel, tests, etc. |
| `languages/g2rd-woocommerce-promotions.pot` | i18n |
| README technique | mettre à jour post-correctifs |
| Releases | uniquement après validation explicite |

Workflow proposé (permissions minimales `contents: read` + `security-events` si CodeQL) : voir section CI dans ce plan — **ne pas publier de release sans validation**.

### Esquisse workflow CI

```yaml
# Proposition — à créer seulement après validation
name: ci
on: [push, pull_request]
permissions:
  contents: read
jobs:
  php:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.1' }
      - run: find . -name '*.php' -print0 | xargs -0 -n1 php -l
      - run: php tests/run.php
```

---

## Ordre des commits suggéré (après validation)

1. `fix: respect WooCommerce sale schedules in native sale detection`
2. `fix: bound on-sale product ID enrichment for large catalogs`
3. `fix: harden wholesale cache warnings and settings`
4. `test: extend cart idempotence and coupon stack cases`
5. `perf: query active campaigns via schedule index`
6. `feat: …` lots compat / UX / conformité séparés
7. `ci: add lint and standalone test workflow`

---

## Critères de mise en production (rappel)

Le plugin ne passe en prod Drakkar que si **tous** les points de la section 22 du brief sont verts, notamment :

- plus de P0/P1 sécurité ou calcul ouverts ;
- pas de fuite Wholesale ;
- pas de double remise ;
- cohérences panier/commande/paiement/facture prouvées en recette ;
- conformité annonces B2C tranchée ;
- non-régression verte.

**En l’état audit : critères non satisfaits → Non prêt pour la production.**
