<?php
// config/formatters.php

if (!function_exists('format_money')) {
    function format_money($valor, string $simbolo = '€', string $posicao = 'prefix'): string
    {
        $formatado = number_format((float)$valor, 2, ',', '.');
        return $posicao === 'suffix' ? $formatado . $simbolo : $simbolo . $formatado;
    }
}

if (!function_exists('format_bytes')) {
    function format_bytes($bytes, int $decimais = 2, string $bytesLabel = 'bytes'): string
    {
        $bytes = (float)$bytes;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, $decimais) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, $decimais) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, $decimais) . ' KB';
        }
        return (int)$bytes . ' ' . $bytesLabel;
    }
}

if (!function_exists('format_time_ago')) {
    function format_time_ago(string $datetime, string $style = 'long'): string
    {
        $timezone = new DateTimeZone('Europe/Lisbon');
        $agora = new DateTime('now', $timezone);
        $data = new DateTime($datetime, $timezone);
        $diff = $agora->getTimestamp() - $data->getTimestamp();

        if ($style === 'short') {
            if ($diff < 60) return 'Agora mesmo';
            if ($diff < 3600) return 'Há ' . floor($diff / 60) . ' min';
            if ($diff < 86400) return 'Há ' . floor($diff / 3600) . 'h';
            if ($diff < 604800) return 'Há ' . floor($diff / 86400) . 'd';
            return date('d/m/Y H:i', strtotime($datetime));
        }

        if ($diff < 60) return 'Agora mesmo';
        $minutos = floor($diff / 60);
        if ($minutos < 60) return $minutos == 1 ? 'Há 1 min' : 'Há ' . $minutos . ' min atrás';
        $horas = floor($minutos / 60);
        if ($horas < 24) return $horas == 1 ? 'Há 1 hora' : 'Há ' . $horas . ' horas atrás';
        $dias = floor($horas / 24);
        if ($dias == 1) return 'Ontem';
        return 'Há ' . $dias . ' dias atrás';
    }
}
