<?php
// config/rate_limit.php — Rate limiting leve por IP + ação via ficheiros temporários (sem alterações na BD)

if (!function_exists('rate_limit_client_ip')) {
    function rate_limit_client_ip(): string
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}

if (!function_exists('rate_limit_allow')) {
    /**
     * Verifica e regista uma tentativa numa janela deslizante.
     * Devolve true se permitido, false se excedeu o limite.
     */
    function rate_limit_allow(string $action, int $maxRequests, int $windowSeconds, ?string $identifier = null): bool
    {
        $id = $identifier ?? rate_limit_client_ip();
        $dir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'toptop_rl';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return true; // fail-open se o SO não permitir temp dir
        }

        $file = $dir . DIRECTORY_SEPARATOR . hash('sha256', $action . '|' . $id) . '.json';
        $fp = @fopen($file, 'c+b');
        if ($fp === false) {
            return true;
        }

        $allowed = true;
        try {
            if (flock($fp, LOCK_EX)) {
                $raw = stream_get_contents($fp);
                $now = time();
                $cutoff = $now - $windowSeconds;
                $timestamps = [];

                if (is_string($raw) && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $ts) {
                            if (is_int($ts) && $ts > $cutoff) {
                                $timestamps[] = $ts;
                            }
                        }
                    }
                }

                if (count($timestamps) >= $maxRequests) {
                    $allowed = false;
                } else {
                    $timestamps[] = $now;
                }

                rewind($fp);
                ftruncate($fp, 0);
                fwrite($fp, json_encode($timestamps));
                fflush($fp);
                flock($fp, LOCK_UN);
            }
        } finally {
            fclose($fp);
        }

        return $allowed;
    }
}
