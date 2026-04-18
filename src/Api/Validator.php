<?php
declare(strict_types=1);

namespace RareFolio\Api;

/**
 * Input validators shared across public API endpoints.
 *
 * Philosophy: reject malformed input at the edge so the DB never sees it.
 * Every method returns the normalized value or throws InvalidArgumentException.
 */
final class Validator
{
    /**
     * Accepts either of the two canonical Rarefolio identifier forms:
     *   - qd-silver-XXXXXXX  (site-facing, e.g. qd-silver-0000001)
     *   - RF-XXXX..          (internal rarefolio_token_id)
     *
     * Returns the lowercased/trimmed value.
     */
    public static function cnftId(string $raw): string
    {
        $v = strtolower(trim($raw));
        if ($v === '') {
            throw new \InvalidArgumentException('cnft_id is required');
        }
        if (!preg_match('/^(qd-silver-\d{7}|rf-[a-z0-9_-]{1,24})$/', $v)) {
            throw new \InvalidArgumentException('cnft_id format invalid');
        }
        return $v;
    }

    /**
     * Silver bar serials in the Rarefolio catalog look like "E101837" —
     * one uppercase letter followed by digits. Loosen if you ever introduce
     * a different serial format.
     */
    public static function barSerial(string $raw): string
    {
        $v = strtoupper(trim($raw));
        if ($v === '') {
            throw new \InvalidArgumentException('bar_serial is required');
        }
        if (!preg_match('/^[A-Z][0-9]{5,12}$/', $v)) {
            throw new \InvalidArgumentException('bar_serial format invalid');
        }
        return $v;
    }

    public static function boundedInt(?string $raw, int $min, int $max, int $default): int
    {
        if ($raw === null || $raw === '') {
            return $default;
        }
        if (!ctype_digit($raw)) {
            return $default;
        }
        $v = (int) $raw;
        if ($v < $min) return $min;
        if ($v > $max) return $max;
        return $v;
    }
}
