<?php
namespace G2RD\Promotions;

/**
 * Vérifie les GitHub Releases et affiche la mise à jour WordPress native.
 *
 * Branche : filtre update_plugins_github.com (Update URI du plugin).
 * Le ZIP attendu est l’asset de release g2rd-woocommerce-promotions-{version}.zip
 * (pas l’archive source du dépôt).
 */
final class Updater {
    private const REPO = 'SebG2RD/G2RD-Woo-commerce-promotions';
    private const SLUG = 'g2rd-woocommerce-promotions';
    private const TRANSIENT = 'g2rd_promo_github_release';
    /** 6 heures — limite le débit vers l’API GitHub. */
    private const CACHE_TTL = 21600;
    /** 15 minutes après un échec réseau. */
    private const FAIL_TTL = 900;

    public static function register(): void {
        add_filter('update_plugins_github.com', [self::class, 'check'], 10, 4);
        add_filter('plugins_api', [self::class, 'info'], 10, 3);
        add_action('delete_site_transient_update_plugins', [self::class, 'clear_cache']);
    }

    /** Invalide le cache local (aligné sur le cache de mises à jour WP). */
    public static function clear_cache(): void {
        delete_transient(self::TRANSIENT);
    }

    /**
     * @param array|false $update
     * @param array       $plugin_data
     * @param string      $plugin_file
     * @param string[]    $locales
     * @return array|false
     */
    public static function check($update, array $plugin_data, string $plugin_file, $locales = []) {
        if ($plugin_file !== plugin_basename(G2RD_PROMO_FILE)) {
            return $update;
        }
        // Une autre source a déjà fourni une réponse pour ce plugin.
        if (!empty($update)) {
            return $update;
        }

        $release = self::latest_release();
        if (!$release) {
            return false;
        }

        $remote = $release['version'];
        $current = $plugin_data['Version'] ?? G2RD_PROMO_VERSION;
        if (!version_compare($remote, (string) $current, '>')) {
            return false;
        }
        if ($release['package'] === '') {
            return false;
        }

        return [
            'id'            => 'https://github.com/'.self::REPO,
            'slug'          => self::SLUG,
            'version'       => $remote,
            'url'           => $release['url'],
            'package'       => $release['package'],
            'tested'        => $plugin_data['Tested up to'] ?? '',
            'requires'      => $plugin_data['RequiresWP'] ?? ($plugin_data['Requires at least'] ?? ''),
            'requires_php'  => $plugin_data['RequiresPHP'] ?? '8.1',
        ];
    }

    /**
     * Fiche « Voir les détails » dans Extensions.
     *
     * @param false|object|array $result
     * @param string             $action
     * @param object             $args
     * @return false|object|array
     */
    public static function info($result, string $action, $args) {
        if ($action !== 'plugin_information' || !is_object($args) || ($args->slug ?? '') !== self::SLUG) {
            return $result;
        }

        $release = self::latest_release();
        if (!$release) {
            return $result;
        }

        $body = $release['body'] !== '' ? $release['body'] : 'Voir la page de release GitHub.';
        // GitHub envoie du Markdown : on affiche en texte échappé (pas de rendu MD complet).
        $changelog = '<pre style="white-space:pre-wrap;font-family:inherit">'.esc_html($body).'</pre>';

        return (object) [
            'name'          => 'G2RD WooCommerce Promotions',
            'slug'          => self::SLUG,
            'version'       => $release['version'],
            'author'        => '<a href="https://g2rd.fr/">G2RD Agence Web</a>',
            'homepage'      => $release['url'],
            'requires'      => '6.6',
            'requires_php'  => '8.1',
            'download_link' => $release['package'],
            'sections'      => [
                'description' => 'Campagnes promotionnelles par label, catégorie et profil client.',
                'changelog'   => $changelog,
            ],
        ];
    }

    /**
     * Dernière release normalisée (version sans « v », URL, ZIP, notes).
     *
     * @return array{version:string,url:string,package:string,body:string}|null
     */
    public static function latest_release(): ?array {
        $cached = get_transient(self::TRANSIENT);
        if (is_array($cached) && !empty($cached['_miss'])) {
            return null;
        }
        if (is_array($cached) && isset($cached['version'], $cached['package'])) {
            return $cached;
        }

        // Injection de tests / overrides (pas d’appel réseau si un tableau est fourni).
        $override = apply_filters('g2rd_promo_github_release', null);
        if (is_array($override)) {
            $normalized = self::normalize($override);
            if ($normalized) {
                set_transient(self::TRANSIENT, $normalized, self::CACHE_TTL);
                return $normalized;
            }
            return null;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/'.self::REPO.'/releases/latest',
            [
                'timeout'    => 12,
                'headers'    => [
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'G2RD-WooCommerce-Promotions/'.G2RD_PROMO_VERSION,
                ],
            ]
        );

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            // Court cache d’échec pour éviter de marteler l’API.
            set_transient(self::TRANSIENT, ['_miss' => 1], self::FAIL_TTL);
            return null;
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            return null;
        }

        $normalized = self::normalize($data);
        if (!$normalized) {
            return null;
        }

        set_transient(self::TRANSIENT, $normalized, self::CACHE_TTL);
        return $normalized;
    }

    /**
     * Transforme la payload GitHub (ou un mock) en structure interne.
     *
     * @param array $data
     * @return array{version:string,url:string,package:string,body:string}|null
     */
    public static function normalize(array $data): ?array {
        $tag = (string) ($data['tag_name'] ?? $data['version'] ?? '');
        $version = ltrim($tag, 'vV');
        if ($version === '' || !preg_match('/^\d+\.\d+\.\d+/', $version)) {
            return null;
        }

        $package = (string) ($data['package'] ?? '');
        if ($package === '' && !empty($data['assets']) && is_array($data['assets'])) {
            $wanted = self::SLUG.'-'.$version.'.zip';
            foreach ($data['assets'] as $asset) {
                if (!is_array($asset)) {
                    continue;
                }
                $name = (string) ($asset['name'] ?? '');
                $url = (string) ($asset['browser_download_url'] ?? '');
                if ($name === $wanted && $url !== '') {
                    $package = $url;
                    break;
                }
            }
        }

        return [
            'version' => $version,
            'url'     => (string) ($data['html_url'] ?? $data['url'] ?? 'https://github.com/'.self::REPO.'/releases'),
            'package' => $package,
            'body'    => (string) ($data['body'] ?? ''),
        ];
    }
}
