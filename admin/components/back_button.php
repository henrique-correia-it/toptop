<?php
require_once __DIR__ . '/../../includes/ui/ui.php';

/**
 * Componente: Botão de Voltar com Seta (wrapper compatível -> ui_back_button)
 */
function renderBackButton($url = '/admin', $title = 'Voltar ao Painel') {
    echo ui_back_button((string)$url, (string)$title);
}
