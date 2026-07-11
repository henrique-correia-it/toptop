<?php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || !in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor'])) {
    header("Location: /admin/admin.php");
    exit;
}

include '../templates/header.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../config/database.php';

$res_templates = $conn->query("SELECT * FROM email_templates ORDER BY group_name DESC, id ASC");
$templates_db = $res_templates->fetch_all(MYSQLI_ASSOC);

$templates_info = [];
$templates_data = [];
$grupos = [];

foreach ($templates_db as $tpl) {
    $key = $tpl['template_key'];
    $templates_info[$key] = [
        'nome'     => $tpl['template_name'],
        'gatilho'  => $tpl['trigger_type'],
        'descricao'=> $tpl['description'],
    ];
    $templates_data[$key] = [
        'assunto' => $tpl['subject'],
        'corpo'   => $tpl['body']
    ];

    $group_name = $tpl['group_name'];
    if (!isset($grupos[$group_name])) {
        $grupos[$group_name] = [];
    }
    $grupos[$group_name][] = $key;
}
?>

<main class="dashboard-container animate-entry">

<!-- Bloquear scroll automático no refresh -->
<script>
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }
    window.scrollTo(0, 0);
</script>
    <div class="admin-page-header">
        <div class="header-title-container">
            <?php renderBackButton('/admin', 'Voltar ao Painel'); ?>
            <h2>Gestor de Templates de Email</h2>
        </div>
    </div>

    <div class="admin-container" style="max-width: 1200px; margin: 0 auto;">

        <div class="gestor-emails-grid">

        <div class="coluna-placeholders">
            <p class="placeholder-global-titulo">Placeholders</p>
            <div class="form-card">
                <div class="form-card-body" style="font-size: 0.9rem;">
                    <p class="placeholder-seccao-titulo">Encomendas</p>
                    <ul>
                        <li><code title="Copiar">{nome_cliente}</code></li>
                        <li><code title="Copiar">{id_encomenda}</code></li>
                        <li><code title="Copiar">{subtotal_produtos}</code></li>
                        <li><code title="Copiar">{portes_envio}</code></li>
                        <li><code title="Copiar">{total_final}</code></li>
                        <li><code title="Copiar">{lista_produtos}</code></li>
                        <li><code title="Copiar">{metodo_pagamento}</code></li>
                        <li><code title="Copiar">{link_acompanhamento}</code></li>
                        <li><code title="Copiar">{codigo_tracking}</code></li>
                    </ul>
                    <p class="placeholder-seccao-titulo">Notificações da Loja</p>
                    <ul>
                        <li><code title="Copiar">{id_encomenda}</code></li>
                        <li><code title="Copiar">{nome_cliente}</code></li>
                        <li><code title="Copiar">{email_cliente}</code></li>
                        <li><code title="Copiar">{total_final}</code></li>
                        <li><code title="Copiar">{metodo_pagamento}</code></li>
                        <li><code title="Copiar">{nome_remetente}</code></li>
                        <li><code title="Copiar">{email_remetente}</code></li>
                        <li><code title="Copiar">{mensagem}</code></li>
                        <li><code title="Copiar">{link_admin}</code></li>
                    </ul>
                    <p class="placeholder-seccao-titulo">Autenticação</p>
                    <ul>
                        <li><code title="Copiar">{nome_cliente}</code></li>
                        <li><code title="Copiar">{nome_admin}</code></li>
                        <li><code title="Copiar">{link_recuperacao}</code></li>
                        <li><code title="Copiar">{link_verificacao}</code></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="coluna-templates">
            <?php foreach ($grupos as $titulo_grupo => $chaves): ?>
                <p class="template-grupo-titulo"><?php echo htmlspecialchars($titulo_grupo); ?></p>

                <?php foreach ($chaves as $key):
                    $info = $templates_info[$key];
                ?>
                <div class="template-accordion-item"
                     data-template-key="<?php echo $key; ?>"
                     data-nome="<?php echo htmlspecialchars($info['nome'], ENT_QUOTES); ?>"
                     data-descricao="<?php echo htmlspecialchars($info['descricao'], ENT_QUOTES); ?>">
                    <div class="template-accordion-header">
                        <div class="template-accordion-header-title">
                            <svg class="template-accordion-toggle-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <h3><?php echo htmlspecialchars($info['nome']); ?></h3>
                        </div>
                        <div class="template-meta-strip">
                            <span>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <?php echo htmlspecialchars($info['gatilho']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="template-accordion-content">
                        <div class="template-accordion-inner">
                            <p class="template-descricao-inline"><?php echo htmlspecialchars($info['descricao']); ?></p>
                            <form class="form-template-email" data-template-key="<?php echo $key; ?>">
                                <div class="form-group">
                                    <label for="assunto_<?php echo $key; ?>">Assunto do Email:</label>
                                    <input type="text" id="assunto_<?php echo $key; ?>" value="<?php echo htmlspecialchars($templates_data[$key]['assunto']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="corpo_<?php echo $key; ?>">Corpo do Email:</label>
                                    <textarea id="corpo_<?php echo $key; ?>" rows="8" class="auto-resize-textarea" required><?php echo htmlspecialchars($templates_data[$key]['corpo']); ?></textarea>
                                </div>
                                <div class="form-actions" style="justify-content: flex-end;">
                                    <button type="submit" class="button add-btn">Guardar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>

        </div>
    </div>
</main>

<!-- Context Menu -->
<?php 
renderContextMenu([
    [
        'href' => '#', 
        'id' => 'ctx-editar-template', 
        'label' => 'Editar Nome e Descrição', 
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>'
    ]
], 'ctx-menu-template'); 
?>

<!-- Modal Editar Nome/Descrição -->
<?php renderQuickEditModal('modal-editar-template', 'Editar Template'); ?>

<template id="tpl-modal-body">
    <input type="hidden" id="modal-template-key">
    <div class="qe-body">
        <div class="qe-f">
            <label>Nome</label>
            <input type="text" id="modal-template-nome" class="qe-in" required>
        </div>
        <div class="qe-f">
            <label>Descrição</label>
            <textarea id="modal-template-descricao" class="qe-in" rows="3"></textarea>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- ACORDEÃO ---
    document.querySelectorAll('.template-accordion-item').forEach(item => {
        const header  = item.querySelector('.template-accordion-header');
        header.addEventListener('click', () => {
            const isAtivo = item.classList.contains('ativo');
            document.querySelectorAll('.template-accordion-item').forEach(other => {
                if (other !== item) {
                    other.classList.remove('ativo');
                    other.querySelector('.template-accordion-content').style.maxHeight = null;
                }
            });

            if (!isAtivo) {
                item.classList.add('ativo');
                const content = item.querySelector('.template-accordion-content');
                content.style.maxHeight = content.scrollHeight + "px";
                setTimeout(() => {
                    content.querySelectorAll('textarea').forEach(ta => {
                        ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px';
                    });
                    content.style.maxHeight = content.scrollHeight + "px";
                }, 100);
            } else {
                item.classList.remove('ativo');
                item.querySelector('.template-accordion-content').style.maxHeight = null;
            }
        });
    });

    // --- CONTEXT MENU ---
    document.addEventListener('admin:contextmenu', function(e) {
        const { row, menu, originalEvent } = e.detail;
        
        // Só mostra o menu se clicou no h3 (nome) ou na descrição
        if (!originalEvent.target.closest('h3, .template-descricao-inline')) {
            menu.style.display = 'none'; 
            return;
        }

        menu.querySelector('#ctx-editar-template').onclick = () => {
            const modal = document.getElementById('modal-editar-template');
            const form = modal.querySelector('form');
            form.innerHTML = document.getElementById('tpl-modal-body').innerHTML + form.querySelector('.qe-btns').outerHTML;
            
            modal.querySelector('#modal-template-key').value       = row.dataset.templateKey;
            modal.querySelector('#modal-template-nome').value      = row.dataset.nome;
            modal.querySelector('#modal-template-descricao').value = row.dataset.descricao;
            modal.style.display = 'flex';

            // Re-bind submit since we replaced innerHTML
            form.onsubmit = submeterEdicaoTemplate;
        };
    });

    function submeterEdicaoTemplate(e) {
        e.preventDefault();
        const form = e.target;
        const key = form.querySelector('#modal-template-key').value;
        const nome = form.querySelector('#modal-template-nome').value;
        const descricao = form.querySelector('#modal-template-descricao').value;
        
        const fd = new FormData();
        fd.append('template_key', key); fd.append('nome', nome); fd.append('descricao', descricao);
        fd.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

        fetch('ajax_salvar_template.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(data => {
                if (data.sucesso) {
                    const item = document.querySelector(`[data-template-key="${key}"]`);
                    item.dataset.nome = nome; item.dataset.descricao = descricao;
                    item.querySelector('h3').textContent = nome;
                    item.querySelector('.template-descricao-inline').textContent = descricao;
                    document.getElementById('modal-editar-template').style.display = 'none';
                    mostrarPopup('Template atualizado!', 'sucesso');
                }
            });
    }

    // --- GUARDAR ASSUNTO/CORPO ---
    document.querySelectorAll('.form-template-email').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const key = this.dataset.templateKey;
            const item = document.querySelector(`[data-template-key="${key}"]`);
            const fd = new FormData();
            fd.append('template_key', key);
            fd.append('nome', item.dataset.nome);
            fd.append('descricao', item.dataset.descricao);
            fd.append('assunto', this.querySelector('input').value);
            fd.append('corpo', this.querySelector('textarea').value);
            fd.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

            fetch('ajax_salvar_template.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(data => {
                    if (data.sucesso) mostrarPopup('Template guardado!', 'sucesso');
                });
        });
    });

    // --- CLICK TO COPY ---
    document.querySelectorAll('.coluna-placeholders code').forEach(code => {
        code.onclick = function() {
            navigator.clipboard.writeText(this.textContent).then(() => {
                const old = this.textContent; this.textContent = 'Copiado!';
                setTimeout(() => this.textContent = old, 1000);
            });
        };
    });
});
</script>


<?php include '../templates/footer.php'; ?>
