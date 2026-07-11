<?php
// includes/ShippingService.php

const DEFAULT_FREE_SHIPPING_THRESHOLD_PT_MAINLAND = 50.0;

if (!function_exists('normalize_country_code')) {
    function normalize_country_code(?string $countryCode, string $fallback = 'PT'): string
    {
        $countryCode = strtoupper(trim((string)$countryCode));
        return preg_match('/^[A-Z]{2,5}$/', $countryCode) ? $countryCode : $fallback;
    }
}

if (!function_exists('get_shipping_rates')) {
    function get_shipping_rates(mysqli $conn): array
    {
        $rates = [];
        $result = $conn->query(
            "SELECT country_code, min_weight, max_weight, price
             FROM shipping_rates
             ORDER BY (country_code = 'PT') DESC, country_code ASC, min_weight ASC"
        );

        if (!$result) {
            return $rates;
        }

        while ($row = $result->fetch_assoc()) {
            $rates[$row['country_code']][] = [
                'min' => (float)$row['min_weight'],
                'max' => (float)$row['max_weight'],
                'preco' => (float)$row['price'],
            ];
        }

        return $rates;
    }
}

if (!function_exists('get_shipping_rates_for_country')) {
    function get_shipping_rates_for_country(mysqli $conn, ?string $countryCode, string $fallback = 'PT'): array
    {
        $countryCode = normalize_country_code($countryCode, $fallback);
        $rates = [];

        $stmt = $conn->prepare(
            "SELECT min_weight, max_weight, price
             FROM shipping_rates
             WHERE country_code = ?
             ORDER BY min_weight ASC"
        );

        if (!$stmt) {
            return $rates;
        }

        foreach ([$countryCode, $fallback] as $code) {
            $stmt->bind_param('s', $code);
            $stmt->execute();
            $result = $stmt->get_result();

            $rates = [];
            while ($row = $result->fetch_assoc()) {
                $rates[] = [
                    'min' => (float)$row['min_weight'],
                    'max' => (float)$row['max_weight'],
                    'preco' => (float)$row['price'],
                ];
            }

            if (!empty($rates) || $code === $fallback) {
                break;
            }
        }

        $stmt->close();
        return $rates;
    }
}

if (!function_exists('calculate_shipping_from_rates')) {
    function calculate_shipping_from_rates(int $weightGrams, array $rates): float
    {
        foreach ($rates as $rate) {
            $min = (float)($rate['min'] ?? 0);
            $max = (float)($rate['max'] ?? 0);

            if ($weightGrams >= $min && ($max == 0 || $weightGrams < $max)) {
                return (float)($rate['preco'] ?? 0);
            }
        }

        return empty($rates) ? 0.0 : (float)(end($rates)['preco'] ?? 0);
    }
}

if (!function_exists('get_free_shipping_threshold')) {
    function get_free_shipping_threshold(mysqli $conn): float
    {
        $rawValue = function_exists('getLojaConfig')
            ? getLojaConfig('portes_gratis_minimo_pt_continental', (string)DEFAULT_FREE_SHIPPING_THRESHOLD_PT_MAINLAND)
            : DEFAULT_FREE_SHIPPING_THRESHOLD_PT_MAINLAND;

        if (!is_numeric($rawValue)) {
            return DEFAULT_FREE_SHIPPING_THRESHOLD_PT_MAINLAND;
        }

        $threshold = round((float)$rawValue, 2);
        return $threshold > 0 ? $threshold : DEFAULT_FREE_SHIPPING_THRESHOLD_PT_MAINLAND;
    }
}

if (!function_exists('is_portugal_continental')) {
    function is_portugal_continental(?string $countryCode, ?string $postalCode): bool
    {
        if (normalize_country_code($countryCode) !== 'PT') {
            return false;
        }

        if (!preg_match('/^\s*(\d{4})-\d{3}\s*$/', (string)$postalCode, $matches)) {
            return false;
        }

        $postalPrefix = (int)$matches[1];
        return $postalPrefix >= 1000 && $postalPrefix <= 8999;
    }
}

if (!function_exists('qualifies_for_free_shipping')) {
    function qualifies_for_free_shipping(
        float $orderSubtotal,
        ?string $countryCode,
        ?string $postalCode,
        float $threshold
    ): bool {
        return $threshold > 0
            && $orderSubtotal >= $threshold
            && is_portugal_continental($countryCode, $postalCode);
    }
}

if (!function_exists('calculate_shipping')) {
    function calculate_shipping(
        int $weightGrams,
        mysqli $conn,
        ?string $countryCode = 'PT',
        float $orderSubtotal = 0.0,
        ?string $postalCode = null
    ): float {
        if (qualifies_for_free_shipping(
            $orderSubtotal,
            $countryCode,
            $postalCode,
            get_free_shipping_threshold($conn)
        )) {
            return 0.0;
        }

        return calculate_shipping_from_rates(
            $weightGrams,
            get_shipping_rates_for_country($conn, $countryCode)
        );
    }
}
