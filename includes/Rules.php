<?php
namespace G2RD\Promotions;
if (!defined('ABSPATH')) { exit; }
/** Pure selection logic. Dates are UTC timestamps; intervals are [start,end). */
final class Rules {
    public static function active(array $r, int $now): bool {
        return !empty($r['enabled']) && $r['start'] <= $now && $now < $r['end'];
    }
    public static function status(array $r, int $now): string {
        if (empty($r['enabled'])) return 'draft';
        if ($now < $r['start']) return 'scheduled';
        return $now >= $r['end'] ? 'expired' : 'active';
    }
    public static function matches(array $r, array $p): bool {
        if (!in_array($p['profile'], $r['profiles'], true)) return false;
        if (array_intersect($r['exclude_products'], $p['ids'])) return false;
        if (array_intersect($r['exclude_categories'], $p['categories'])) return false;
        if (array_intersect($r['exclude_brands'], $p['brands'])) return false;
        if ($r['brands'] && !array_intersect($r['brands'], $p['brands'])) return false;
        if ($r['categories'] && !array_intersect($r['categories'], $p['categories'])) return false;
        return true;
    }
    public static function winner(array $rules, array $product, int $now): ?array {
        $best = null;
        foreach ($rules as $r) {
            if (!self::active($r,$now) || !self::matches($r,$product)) continue;
            if ($best === null || $r['rate'] > $best['rate'] || ($r['rate'] == $best['rate'] && $r['id'] < $best['id'])) $best = $r;
        }
        return $best;
    }
    public static function price(float $base, float $rate, int $precision): float {
        return round(max(0, $base * (1 - $rate / 100)), $precision, PHP_ROUND_HALF_UP);
    }
    public static function parse_date(string $input, \DateTimeZone $zone): ?int {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $input, $zone);
        if (!$d || $d->format('Y-m-d\TH:i') !== $input) return null;
        return $d->getTimestamp();
    }
}
