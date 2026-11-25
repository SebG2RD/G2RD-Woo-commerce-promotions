# CI/CD — G2RD WooCommerce Promotions

Pipeline aligné sur les dépôts G2RD (Facturation, Connector, WP Job Manager Geo, Theme).

## Workflows

| Fichier | Rôle | Bloquant |
|---|---|---|
| `ci.yml` | Tests PHP 8.1–8.4, sécurité PHPCS, PHPStan, syntaxe JS | Oui (sauf style WP) |
| `phpcs-security.yml` | WordPress (info) + Security SAST + PHPCompatibility | Sécurité + compat |
| `plugin-check.yml` | Plugin Check + activation avec WooCommerce | Erreurs (warnings ignorés) |
| `secrets.yml` | gitleaks (historique complet, binaire hashé) | Oui |
| `zizmor.yml` | Analyse des workflows GitHub | Oui |
| `auto-tag.yml` | Commit `version: x.y.z` → tag `vX.Y.Z` | — |
| `release.yml` | ZIP + activation WP/WC + release + SHA-256 | Oui |
| `sbom.yml` | SBOM CycloneDX sur la release | Oui |
| `dependabot.yml` | Composer (dev) + GitHub Actions, cooldown 7 j | — |

## Secrets GitHub à configurer

| Secret | Obligatoire | Usage |
|---|---|---|
| `GH_PAT` | Pour les releases auto | PAT Contents:write, limité à ce dépôt (auto-tag) |

Sans `GH_PAT`, un commit `version:` échoue **seulement** si le tag `vX.Y.Z` n’existe pas encore (le `GITHUB_TOKEN` ne peut pas re-déclencher `release.yml`). Si le tag a déjà été poussé à la main, Auto-tag réussit sans secret.

## Commandes locales

```bash
composer install
composer run test          # 51 asserts mocks
composer run lint:security # SAST OWASP
composer run analyse       # PHPStan
composer run js:check
composer run ci
bash tools/verify-release-version.sh 1.0.0
php tools/build-zip.php
bash tools/verify-plugin-zip.sh g2rd-woocommerce-promotions.zip
```

## Publication d’une version

1. Aligner Version / `G2RD_PROMO_VERSION` / Stable tag / Changelog sur `x.y.z`
2. Commit sur `main` avec le sujet exact : `version: x.y.z`
3. `auto-tag.yml` crée `vX.Y.Z` → `release.yml` construit et publie le ZIP

## Écarts volontaires vs Facturation

- Pas de signature Ed25519 ni webhook g2rd.fr (pas d’updater intégré v1)
- Style WordPress Coding Standards en **informatif** (code compact actuel)
- Aucune dépendance Composer runtime (WooCommerce fournit Action Scheduler)
