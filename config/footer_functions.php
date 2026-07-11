<?php
/**
 * Obtém o conteúdo de uma secção do rodapé com fallback.
 */
function getFooterText($seccao, $fallback = '') {
    global $conn;
    
    if (!$conn) {
        return $fallback;
    }

    $stmt = $conn->prepare("SELECT conteudo FROM footer_config WHERE seccao = ? LIMIT 1");
    if (!$stmt) return $fallback;
    
    $stmt->bind_param("s", $seccao);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['conteudo'];
    }
    
    $stmt->close();
    return $fallback;
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
        $config['dias'][$idx]['inicio'] = preg_match('/^\d{2}:\d{2}$/', (string) ($diaGuardado['inicio'] ?? '')) ? $diaGuardado['inicio'] : '';
        $config['dias'][$idx]['fim'] = preg_match('/^\d{2}:\d{2}$/', (string) ($diaGuardado['fim'] ?? '')) ? $diaGuardado['fim'] : '';
        $config['dias'][$idx]['texto'] = trim((string) ($diaGuardado['texto'] ?? ''));
    }

    $config['nota'] = trim((string) ($decoded['nota'] ?? ''));
    return $config;
}

function formatarHoraHorario($hora) {
    if (!preg_match('/^(\d{2}):(\d{2})$/', (string) $hora, $m)) {
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
    $raw = getFooterText('horario_funcionamento', '');
    $decoded = json_decode(trim((string) $raw), true);

    if (!is_array($decoded) || (($decoded['tipo'] ?? '') !== 'horario_semana')) {
        $texto = trim((string) $raw);
        if ($texto === '') {
            $texto = $fallback;
        }
        return '<p class="horario-texto-simples">' . $texto . '</p>';
    }

    $config = normalizarHorarioFuncionamento($raw);
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
