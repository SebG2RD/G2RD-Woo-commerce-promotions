# G2RD WooCommerce Promotions

Campagnes promotionnelles WooCommerce par label, catégorie et profil client (visiteur, particulier, Wholesale).

- **Version :** 1.1.0 (règles multiples, priorités, coupons, index promo en arrière-plan)
- **PHP :** ≥ 8.1 · **WordPress :** ≥ 6.6 · **WooCommerce :** requis
- **Éditeur :** [G2RD Agence Web](https://g2rd.fr/)

## Documentation

- Guide de recette : [`docs/RECETTE.md`](docs/RECETTE.md)
- Audit : [`docs/audit/`](docs/audit/)
- CI/CD : [`docs/CI.md`](docs/CI.md)

## Développement local

```bash
composer install
composer run ci          # tests + sécurité PHPCS + PHPStan + JS
composer run test
composer run lint:security
```

## CI/CD (modèle G2RD)

Workflows GitHub Actions alignés sur Facturation / Connector / Geo :

- tests multi-PHP, SAST sécurité, PHPCompatibility, Plugin Check (+ WooCommerce)
- gitleaks (secrets), zizmor (workflows), Dependabot
- release ZIP signée par empreinte SHA-256 + SBOM

Secret requis pour les releases auto : `GH_PAT` (voir `docs/CI.md`).
