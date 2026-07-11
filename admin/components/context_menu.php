<?php
/**
 * Componente: Menu de Contexto (Right-click)
 * Centraliza a estrutura do menu suspenso.
 */
function renderContextMenu($items = [], $id = 'ctx-menu') {
    ?>
    <div class="ctx-menu" id="<?php echo htmlspecialchars($id); ?>">
        <?php foreach ($items as $item): ?>
            <?php if ($item === 'separator'): ?>
                <div class="ctx-sep"></div>
            <?php else: ?>
                <a href="<?php echo $item['href'] ?? '#'; ?>" 
                   id="<?php echo $item['id'] ?? ''; ?>" 
                   class="ctx-item <?php echo $item['class'] ?? ''; ?>">
                    <?php if (isset($item['icon'])): ?>
                        <?php echo $item['icon']; ?>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($item['label']); ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php
}
?>
