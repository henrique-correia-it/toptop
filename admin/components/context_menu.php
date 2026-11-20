<?php
require_once __DIR__ . '/../../includes/ui/ui.php';

/**
 * Componente: Menu de Contexto (wrapper compatível -> ui_context_menu)
 */
function renderContextMenu($items = [], $id = 'ctx-menu') {
    echo ui_context_menu(is_array($items) ? $items : [], (string)$id);
}
