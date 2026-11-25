<?php
/**
 * Remove tags <p>, </p> e <br> legadas guardadas na BD em textos simples.
 */
if (!function_exists('limparTextoConfigLegado')) {
    function limparTextoConfigLegado($texto): string {
        $str = (string)$texto;
        $trim = trim($str);
        if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
            return $str;
        }
        $str = preg_replace('/&lt;(\/?(?:p|br)\b[^&]*?)&gt;/i', '<$1>', $str);
        $str = preg_replace('#</p>\s*<p\b[^>]*>#i', "\n", $str);
        $str = preg_replace('#<br\s*/?>#i', "\n", $str);
        $str = preg_replace('#</?p\b[^>]*>#i', '', $str);
        return trim($str);
    }
}

/**
 * Obtém o conteúdo de uma secção do rodapé com fallback.
 */
function getFooterText($seccao, $fallback = '') {
    global $conn;
    static $cache = null;
    
    if (!$conn) {
        return limparTextoConfigLegado($fallback);
    }

    if ($cache === null) {
        $cache = [];
        try {
            $res = $conn->query("SELECT seccao, conteudo FROM footer_config");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $cache[$row['seccao']] = limparTextoConfigLegado($row['conteudo']);
                }
            }
        } catch (Throwable $e) {
            if (function_exists('log_sql')) {
                log_sql('Erro ao carregar footer_config: ' . $e->getMessage(), 'footer_functions.php');
            }
        }
    }

    if (array_key_exists($seccao, $cache)) {
        return $cache[$seccao];
    }

    return limparTextoConfigLegado($fallback);
}

function getHorarioSemanaBase() {
    return [
        ['key' => 'segunda', 'label' => 'Segunda-feira', 'fechado' => false, 'inicio' => '', 'fim' => '', 'texto' => ''],
        ['key' => 'terca', 'label' => 'Terça-feira', 'fechado' => false, 'inicio' => '', 'fim' => '', 'texto' => ''],
        ['key' => 'quarta', 'label' => 'Quarta-feira', 'fechado' => false, 'inicio' => '', 'fim' => '', 'texto' => ''],
        ['key' => 'quinta', 'label' => 'Quinta-feira', 'fechado' => false, 'inicio' => '', 'fim' => '', 'texto' => ''],
        ['key' => 'sexta', 'label' => 'Sexta-feira', 'fechado' => false, 'inicio' => '', 'fim' => '', 'texto' => ''],
        ['key' => 'sabado', 'label' => 'Sábado', 'fechado' => false, 'inicio' => '', 'fim' => '', 'texto' => ''],
        ['key' => 'domingo', 'label' => 'Domingo', 'fechado' => false, 'inicio' => '', 'fim' => '', 'texto' => ''],
    ];
}

function normalizarHorarioFuncionamento($raw = '') {
    $raw = trim((string) $raw);
    $base = getHorarioSemanaBase();
    $config = [
        'tipo' => 'horario_semana',
        'versao' => 1,
        'nota' => '',
        'dias' => $base,
    ];

    if ($raw === '') {
        return $config;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || (($decoded['tipo'] ?? '') !== 'horario_semana')) {
        $config['nota'] = strip_tags($raw);
        return $config;
    }

    $diasPorKey = [];
    foreach (($decoded['dias'] ?? []) as $dia) {
        if (is_array($dia) && isset($dia['key'])) {
            $diasPorKey[$dia['key']] = $dia;
        }
    }

    foreach ($config['dias'] as $idx => $diaBase) {
        $diaGuardado = $diasPorKey[$diaBase['key']] ?? [];
        $config['dias'][$idx]['fechado'] = !empty($diaGuardado['fechado']);
        $config['dias'][$idx]['inicio'] = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) ($diaGuardado['inicio'] ?? '')) ? $diaGuardado['inicio'] : '';
        $config['dias'][$idx]['fim'] = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) ($diaGuardado['fim'] ?? '')) ? $diaGuardado['fim'] : '';
        $config['dias'][$idx]['texto'] = trim((string) ($diaGuardado['texto'] ?? ''));
    }

    $config['nota'] = trim((string) ($decoded['nota'] ?? ''));
    return $config;
}

function formatarHoraHorario($hora) {
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', (string) $hora, $m)) {
        return '';
    }

    return ((int) $m[1]) . 'h' . $m[2];
}

function textoHorarioDia(array $dia) {
    if (!empty($dia['fechado'])) {
        return 'Encerrado';
    }

    $texto = trim((string) ($dia['texto'] ?? ''));
    if ($texto !== '') {
        return $texto;
    }

    $inicio = formatarHoraHorario($dia['inicio'] ?? '');
    $fim = formatarHoraHorario($dia['fim'] ?? '');

    if ($inicio !== '' && $fim !== '') {
        return $inicio . ' às ' . $fim;
    }

    if ($inicio !== '') {
        return 'A partir das ' . $inicio;
    }

    if ($fim !== '') {
        return 'Até às ' . $fim;
    }

    return 'Por consulta';
}

function renderHorarioFuncionamento($fallback = 'Por consulta (temporário)') {
    $raw = trim((string) getFooterText('horario_funcionamento', ''));
    $decoded = $raw !== '' ? json_decode($raw, true) : null;

    if (!is_array($decoded) || (($decoded['tipo'] ?? '') !== 'horario_semana')) {
        $texto = $raw !== '' ? $raw : $fallback;
        return '<p class="horario-texto-simples">' . nl2br(htmlspecialchars($texto, ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    $base = getHorarioSemanaBase();
    $diasPorKey = [];
    foreach (($decoded['dias'] ?? []) as $dia) {
        if (is_array($dia) && isset($dia['key'])) {
            $diasPorKey[$dia['key']] = $dia;
        }
    }
    $config = ['dias' => $base, 'nota' => trim((string) ($decoded['nota'] ?? ''))];
    foreach ($config['dias'] as $idx => $diaBase) {
        $diaGuardado = $diasPorKey[$diaBase['key']] ?? [];
        $config['dias'][$idx]['fechado'] = !empty($diaGuardado['fechado']);
        $config['dias'][$idx]['inicio'] = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) ($diaGuardado['inicio'] ?? '')) ? $diaGuardado['inicio'] : '';
        $config['dias'][$idx]['fim'] = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) ($diaGuardado['fim'] ?? '')) ? $diaGuardado['fim'] : '';
        $config['dias'][$idx]['texto'] = trim((string) ($diaGuardado['texto'] ?? ''));
    }
    $mapaHoje = [
        1 => 'segunda',
        2 => 'terca',
        3 => 'quarta',
        4 => 'quinta',
        5 => 'sexta',
        6 => 'sabado',
        7 => 'domingo',
    ];
    $hoje = $mapaHoje[(int) date('N')] ?? '';
    $html = '<div class="horario-semanal" role="list">';

    foreach ($config['dias'] as $dia) {
        $classes = ['horario-dia'];
        if (!empty($dia['fechado'])) {
            $classes[] = 'is-closed';
        }
        if (($dia['key'] ?? '') === $hoje) {
            $classes[] = 'is-today';
        }

        $html .= '<div class="' . implode(' ', $classes) . '" role="listitem">';
        $html .= '<span class="horario-dia-nome">' . htmlspecialchars($dia['label'], ENT_QUOTES, 'UTF-8');
        if (($dia['key'] ?? '') === $hoje) {
            $html .= '<span class="horario-hoje">Hoje</span>';
        }
        $html .= '</span>';
        $html .= '<span class="horario-dia-horas">' . htmlspecialchars(textoHorarioDia($dia), ENT_QUOTES, 'UTF-8') . '</span>';
        $html .= '</div>';
    }

    $html .= '</div>';

    if (($config['nota'] ?? '') !== '') {
        $html .= '<p class="horario-nota">' . htmlspecialchars($config['nota'], ENT_QUOTES, 'UTF-8') . '</p>';
    }

    return $html;
}
