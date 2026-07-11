<?php
/**
 * Componente: Modal de Edição Rápida
 * Centraliza o shell do modal para evitar repetição de HTML.
 */
function renderQuickEditModal($id = 'qe-modal', $title = 'Edição Rápida') {
    ?>
    <div class="qe-modal" id="<?php echo htmlspecialchars($id); ?>">
        <div class="qe-card">
            <div class="qe-hd">
                <h3><?php echo htmlspecialchars($title); ?></h3>
                <button type="button" class="btn-close-unified qe-close" title="Fechar">&times;</button>
            </div>
            <form class="qe-form">
                <div class="qe-body">
                    <!-- Conteúdo dinâmico injetado via JS -->
                </div>
                <div class="qe-btns">
                    <button type="button" class="qe-btn qe-btn-cancel">Cancelar</button>
                    <button type="submit" class="qe-btn qe-btn-save">Guardar Alterações</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}
?>
