<?php
/**
 * price_service.php - price normalization, validation, baseline/flagging
 * and the shared ingestion pipeline used by manual entry and bulletin import.
 */
class PriceService
{
    private const MAX_PRICE = 1000000;   // data-derived sanity ceiling per unit
    private const BASELINE_DAYS = 7;
    private const BASELINE_MIN_REPORTS = 3;

    /** Conversion factor: 1 unit of $unitId equals X base units of $productId. */
    public static function factor(int $productId, int $unitId): ?float
    {
        $row = Database::first(
            "SELECT factor_to_base FROM product_units WHERE product_id = ? AND unit_id = ?",
            [$productId, $unitId]
        );
        return $row ? (float)$row['factor_to_base'] : null;
    }

    /** Convert a reported price into the product's base unit. */
    public static function normalize(int $productId, int $unitId, float $price): ?float
    {
        if ($productId <= 0 || $unitId <= 0 || $price < 0) {
            return null;
        }
        $factor = self::factor($productId, $unitId);
        if ($factor === null || $factor <= 0) {
            return null;
        }
        return round($price / $factor, 4);
    }

    /** Most recent normalized market price across all markets for a product. */
    public static function latestMarketPrice(int $productId): ?float
    {
        $value = Database::scalar(
            "SELECT normalized_price FROM price_reports
              WHERE product_id = ? AND deleted_at IS NULL
              ORDER BY date_reported DESC LIMIT 1",
            [$productId]
        );
        return $value === false || $value === null ? null : (float)$value;
    }

    /**
     * Deviation for display/storage with a graceful basis hierarchy:
     *   1. 7-day baseline for product+market (needs >= BASELINE_MIN_REPORTS reports)
     *   2. the market's recent price (any prior report within the last 7 days)
     *   3. the product's average across all markets (excluding this row)
     * Returns ['dev' => ?float, 'basis' => 'baseline'|'recent_price'|'product_avg'|null,
     *          'basis_value' => ?float].
     */
    public static function displayDeviation(int $productId, int $marketId, string $date, float $normalized, int $excludeId = 0, ?array $precomputedBaseline = null): array
    {
        $ts  = strtotime($date);
        $day = $ts !== false ? date('Y-m-d', $ts) : $date;

        $base = $precomputedBaseline ?? self::baseline($productId, $marketId, $day);
        if ($base !== null && $base['baseline'] > 0) {
            return [
                'dev'         => round(($normalized - $base['baseline']) / $base['baseline'] * 100, 2),
                'basis'       => 'baseline',
                'basis_value' => (float)$base['baseline'],
            ];
        }

        $recent = Database::first(
            "SELECT AVG(normalized_price) AS avg_price, COUNT(*) AS n
               FROM price_reports
              WHERE product_id = ? AND market_id = ? AND deleted_at IS NULL
                AND DATE(date_reported) < ?
                AND DATE(date_reported) >= DATE_SUB(?, INTERVAL 7 DAY)",
            [$productId, $marketId, $day, $day]
        );
        if ($recent !== null && (int)$recent['n'] >= 1 && (float)$recent['avg_price'] > 0) {
            $v = round((float)$recent['avg_price'], 4);
            return [
                'dev'         => round(($normalized - $v) / $v * 100, 2),
                'basis'       => 'recent_price',
                'basis_value' => $v,
            ];
        }

        $avg = Database::scalar(
            'SELECT AVG(normalized_price) FROM price_reports
              WHERE product_id = ? AND deleted_at IS NULL' . ($excludeId > 0 ? ' AND id <> ?' : ''),
            $excludeId > 0 ? [$productId, $excludeId] : [$productId]
        );
        if ($avg !== null && $avg !== false && (float)$avg > 0) {
            $v = round((float)$avg, 4);
            return [
                'dev'         => round(($normalized - $v) / $v * 100, 2),
                'basis'       => 'product_avg',
                'basis_value' => $v,
            ];
        }

        return ['dev' => null, 'basis' => null, 'basis_value' => null];
    }

    /**
     * Average normalized price of the previous 7 days for the same
     * product + market. Requires at least 3 earlier reports.
     */
    public static function baseline(int $productId, int $marketId, string $date): ?array
    {
        $row = Database::first(
            "SELECT AVG(normalized_price) AS avg_price, COUNT(*) AS n
               FROM price_reports
              WHERE product_id = ?
                AND market_id = ?
                AND deleted_at IS NULL
                AND DATE(date_reported) <  ?
                AND DATE(date_reported) >= DATE_SUB(?, INTERVAL ? DAY)",
            [$productId, $marketId, $date, $date, self::BASELINE_DAYS]
        );
        if (!$row || (int)$row['n'] < self::BASELINE_MIN_REPORTS) {
            return null;
        }
        return [
            'baseline' => round((float)$row['avg_price'], 4),
            'count'    => (int)$row['n'],
        ];
    }

    /**
     * Validate + store one price report. Returns:
     *   ['ok' => true,  'id' => int, 'normalized' => float, 'flagged' => bool]
     *   ['ok' => false, 'error' => string]
     */
    public static function addReport(array $in): array
    {
        $productId = (int)($in['product_id'] ?? 0);
        $marketId  = (int)($in['market_id'] ?? 0);
        $unitId    = (int)($in['unit_id'] ?? 0);
        $price     = (float)($in['price'] ?? -1);
        $date      = $in['date_reported'] ?? '';
        $source    = $in['source_type'] ?? 'manual';
        $adminId   = !empty($in['reported_by']) ? (int)$in['reported_by'] : null;

        if ($productId <= 0 || $marketId <= 0 || $unitId <= 0) {
            return ['ok' => false, 'error' => 'Product, market and unit are all required.'];
        }
        if ($price < 0) {
            return ['ok' => false, 'error' => 'Price must not be negative.'];
        }
        if ($price > self::MAX_PRICE) {
            return ['ok' => false, 'error' => 'Price is above the allowed maximum.'];
        }
        if (!$date) {
            return ['ok' => false, 'error' => 'A report date is required.'];
        }
        $dt = date('Y-m-d H:i:s', strtotime($date));
        if ($dt === false) {
            return ['ok' => false, 'error' => 'Invalid report date/time.'];
        }

        $normalized = self::normalize($productId, $unitId, $price);
        if ($normalized === null) {
            return ['ok' => false, 'error' => 'That unit does not convert to the product base unit.'];
        }

        // Duplicate guard: same admin, same product, same market, same day, manual.
        if ($source === 'manual' && $adminId) {
            $dup = Database::scalar(
                "SELECT COUNT(*) FROM price_reports
                  WHERE product_id = ? AND market_id = ? AND reported_by = ?
                    AND source_type = 'manual' AND DATE(date_reported) = DATE(?)
                    AND deleted_at IS NULL",
                [$productId, $marketId, $adminId, $dt]
            );
            if ((int)$dup > 0) {
                return ['ok' => false, 'error' => 'A manual report for this product, market and day already exists.'];
            }
        }

        $day   = date('Y-m-d', strtotime($dt));
        $base  = self::baseline($productId, $marketId, $day);
        $dv    = self::displayDeviation($productId, $marketId, $dt, $normalized, 0, $base);
        $dev   = $dv['dev'];
        $flag  = $dev !== null && abs($dev) > UNUSUAL_PRICE_PCT;

        Database::execute(
            "INSERT INTO price_reports
                (product_id, market_id, unit_id, price, normalized_price, source_type,
                 reported_by, baseline_price, deviation_pct, date_reported)
             VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$productId, $marketId, $unitId, $price, $normalized, $source,
             $adminId, $base['baseline'] ?? null, $dev, $dt]
        );
        $id = (int)Database::getConnection()->lastInsertId();

        self::rebuildDay($productId, $marketId, $day);

        return ['ok' => true, 'id' => $id, 'normalized' => $normalized, 'flagged' => $flag];
    }

    /** Regenerate (or remove) the daily aggregate snapshot for one day. */
    public static function rebuildDay(int $productId, int $marketId, string $day): void
    {
        $row = Database::first(
            "SELECT AVG(normalized_price) AS a,
                    MIN(normalized_price) AS mn,
                    MAX(normalized_price) AS mx,
                    COUNT(*) AS n
               FROM price_reports
              WHERE product_id = ? AND market_id = ?
                AND DATE(date_reported) = ? AND deleted_at IS NULL",
            [$productId, $marketId, $day]
        );

        if (!$row || (int)$row['n'] === 0) {
            Database::execute(
                "DELETE FROM price_history
                  WHERE product_id = ? AND market_id = ? AND snapshot_date = ?",
                [$productId, $marketId, $day]
            );
            return;
        }

        Database::execute(
            "INSERT INTO price_history
                (product_id, market_id, snapshot_date, avg_normalized_price, min_normalized_price, max_normalized_price)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                avg_normalized_price = VALUES(avg_normalized_price),
                min_normalized_price = VALUES(min_normalized_price),
                max_normalized_price = VALUES(max_normalized_price)",
            [$productId, $marketId, $day,
             round((float)$row['a'], 4), (float)$row['mn'], (float)$row['mx']]
        );
    }

}
