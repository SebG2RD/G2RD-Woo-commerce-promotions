<?php
namespace G2RD\Promotions;
if (!defined('ABSPATH')) { exit; }
/** Pure selection logic. Dates are UTC timestamps; intervals are [start,end). */
final class Rules {
    public const TYPES = ['percent', 'amount', 'fixed'];
    public static function active(array $c, int $now): bool {
        return !empty($c['enabled']) && $c['start'] <= $now && $now < $c['end'];
    }
    public static function status(array $c, int $now): string {
        if (empty($c['enabled'])) return 'draft';
        if ($now < $c['start']) return 'scheduled';
        return $now >= $c['end'] ? 'expired' : 'active';
    }
    /** Campaign-level filters: targeted profile and campaign exclusions. */
    public static function excluded(array $c, array $p): bool {
        if (!in_array($p['profile'], $c['profiles'], true)) return true;
        return array_intersect($c['exclude_products'], $p['ids']) || array_intersect($c['exclude_categories'], $p['categories']) || array_intersect($c['exclude_brands'], $p['brands']);
    }
    /** Specific products ignore labels and categories; an empty selection means all. */
    public static function matches(array $rule, array $p): bool {
        if ($rule['products']) return (bool)array_intersect($rule['products'], $p['ids']);
        if ($rule['brands'] && !array_intersect($rule['brands'], $p['brands'])) return false;
        return !$rule['categories'] || array_intersect($rule['categories'], $p['categories']);
    }
    /** 1 specific products, 2 label × category, 3 label or category, 4 general rule. */
    public static function level(array $rule): int {
        if ($rule['products']) return 1;
        if ($rule['brands'] && $rule['categories']) return 2;
        return $rule['brands'] || $rule['categories'] ? 3 : 4;
    }
    public static function uses_brands(array $c): bool {
        return $c['exclude_brands'] || array_filter(array_column($c['rules'], 'brands'));
    }
    /** Index of the single rule that applies: manual priority (1 = first), then specificity, then list order. */
    public static function winner(array $c, array $p): ?int {
        if (self::excluded($c, $p)) return null;
        $best = null; $rank = PHP_INT_MAX;
        foreach ($c['rules'] as $i => $r) {
            if (!self::matches($r, $p)) continue;
            $k = $r['priority'] > 0 ? (int)$r['priority'] : 100 + self::level($r);
            if ($k < $rank) { $best = $i; $rank = $k; }
        }
        return $best;
    }
    /** Discounts are always computed from the base (regular) price. */
    public static function price(float $base, array $rule, int $precision): float {
        $v = (float)$rule['value'];
        $price = match ($rule['type']) { 'amount' => $base - $v, 'fixed' => $v, default => $base * (1 - $v / 100) };
        return round(max(0, $price), $precision, PHP_ROUND_HALF_UP);
    }
    public static function parse_date(string $input, \DateTimeZone $zone): ?int {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $input, $zone);
        if (!$d || $d->format('Y-m-d\TH:i') !== $input) return null;
        return $d->getTimestamp();
    }
}
