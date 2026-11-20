<?php
require_once __DIR__ . '/../../includes/ui/ui.php';

/**
 * Componente: Modal de Edição Rápida (wrapper compatível -> ui_quick_edit_modal)
 */
function renderQuickEditModal($id = 'qe-modal', $title = 'Edição Rápida') {
    echo ui_quick_edit_modal((string)$id, (string)$title);
}
