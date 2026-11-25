<?php
/**
 * includes/gestor_emails_view.php
 * Vista partilhada neutra para gestão de templates de email (/admin/gestor_emails e /dev/emails_sistema).
 * Espera $email_scope ('loja' | 'sistema'), $page_title, $back_url, $back_title,
 * $can_edit_meta (bool), $save_url (string), $conn e sessão validada pelo caller.
 */

$email_scope   = (($email_scope ?? 'loja') === 'sistema') ? 'sistema' : 'loja';
$page_title    = $page_title ?? ($email_scope === 'sistema' ? 'Emails de Sistema' : 'Templates de Email');
$back_url      = $back_url   ?? '/admin';
$back_title    = $back_title ?? 'Voltar ao Painel';
$can_edit_meta = !empty($can_edit_meta);
$save_url      = (string)($save_url ?? '/admin/ajax_salvar_template.php');

// Chaves de sistema (fallback antes da migração da coluna `scope` na BD)
$system_keys = [
    'verificacao_email',
    'recuperacao_cliente',
    'recuperacao_admin',
    'password_alterada',
    'conta_eliminada',
    'nova_encomenda_loja',
    'novo_contacto_loja',
];

$has_scope_col = false;
if ($res_col = $conn->query("SHOW COLUMNS FROM email_templates LIKE 'scope'")) {
    $has_scope_col = $res_col->num_rows > 0;
    $res_col->free();
}

if ($has_scope_col) {
    $scope_sql    = 'scope = ?';
    $scope_types  = 's';
    $scope_params = [$email_scope];
} else {
    $ph_in        = implode(',', array_fill(0, count($system_keys), '?'));
    $scope_sql    = ($email_scope === 'sistema') ? "template_key IN ({$ph_in})" : "template_key NOT IN ({$ph_in})";
    $scope_types  = str_repeat('s', count($system_keys));
    $scope_params = $system_keys;
}

// Carrega todos os templates do scope para popular filtro de grupos e cache JS completa
$stmt_all = $conn->prepare("SELECT * FROM email_templates WHERE {$scope_sql} ORDER BY group_name DESC, id ASC");
$stmt_all->bind_param($scope_types, ...$scope_params);
$stmt_all->execute();
$all_scope_templates = $stmt_all->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_all->close();

$grupos_opcoes = ['' => 'Todos os grupos'];
$templates_js  = [];
foreach ($all_scope_templates as $tpl) {
    $g = trim((string)($tpl['group_name'] ?? ''));
    if ($g !== '' && !isset($grupos_opcoes[$g])) {
        $grupos_opcoes[$g] = $g;
    }
    $key = (string)$tpl['template_key'];
    $templates_js[$key] = [
        'nome'      => (string)$tpl['template_name'],
        'descricao' => (string)$tpl['description'],
        'grupo'     => (string)$tpl['group_name'],
        'gatilho'   => (string)$tpl['trigger_type'],
        'assunto'   => (string)$tpl['subject'],
        'corpo'     => (string)$tpl['body'],
    ];
}

$filtro_grupo = trim((string)($_GET['grupo'] ?? ''));
$filtro_q     = trim((string)($_GET['q'] ?? ''));

$where  = [$scope_sql];
$params = $scope_params;
$types  = $scope_types;

if ($filtro_grupo !== '') {
    $where[]  = 'group_name = ?';
    $params[] = $filtro_grupo;
    $types   .= 's';
}
if ($filtro_q !== '') {
    $like     = '%' . $filtro_q . '%';
    $where[]  = '(template_name LIKE ? OR description LIKE ? OR subject LIKE ? OR trigger_type LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ssss';
}

$sort_map = [
    'nome'  => 'template_name',
    'grupo' => 'group_name',
];
$sort_by   = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sort_map) ? $_GET['sort'] : '';
$sort_dir  = (isset($_GET['dir']) && strtolower((string)$_GET['dir']) === 'desc') ? 'desc' : 'asc';
$order_sql = $sort_by !== '' ? ($sort_map[$sort_by] . ' ' . strtoupper($sort_dir) . ', id ASC') : 'group_name DESC, id ASC';

$where_sql = 'WHERE ' . implode(' AND ', $where);
$stmt = $conn->prepare("SELECT * FROM email_templates {$where_sql} ORDER BY {$order_sql}");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$templates_db = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!function_exists('tone_grupo_email')) {
    function tone_grupo_email(string $grupo): string
    {
        $g = mb_strtolower($grupo, 'UTF-8');
        if (str_contains($g, 'encomenda')) return 'info';
        if (str_contains($g, 'notifica'))  return 'warning';
        if (str_contains($g, 'autentic'))  return 'purple';
        if (str_contains($g, 'cliente'))   return 'success';
        return 'neutral';
    }
}

$titulo_pagina = $page_title;
include __DIR__ . '/../templates/header.php';
?>

<main class="dashboard-container animate-entry">

    <?php echo ui_page_header($page_title, ['layout' => 'admin', 'back_url' => $back_url, 'back_title' => $back_title]); ?>

    <?php
    $toolbarOpts = [
        'search'     => ['name' => 'q', 'value' => $filtro_q],
        'selectable' => false,
    ];
    if (count($grupos_opcoes) > 2) {
        $toolbarOpts['filter'] = [
            'name'    => 'grupo',
            'options' => $grupos_opcoes,
            'value'   => $filtro_grupo,
            'title'   => 'Filtrar por grupo',
        ];
    }
    echo ui_table_toolbar($toolbarOpts);
    ?>

    <?php
    $tplRows = [];
    foreach ($templates_db as $tpl) {
        $key   = (string)$tpl['template_key'];
        $nome  = (string)$tpl['template_name'];
        $desc  = (string)$tpl['description'];
        $grupo = (string)$tpl['group_name'];
        $trig  = (string)$tpl['trigger_type'];

        $tplCell = '<div class="email-tpl-cell">'
            . '<strong class="email-tpl-nome">' . ui_e($nome) . '</strong>'
            . ($desc !== '' ? '<span class="email-tpl-desc">' . ui_e($desc) . '</span>' : '')
            . '</div>';

        $acoesCell = '<div class="acoes-tabela">'
            . '<button type="button" class="btn-expand-single" data-key="' . ui_e($key) . '" title="Editar conteúdo do email" aria-label="Editar conteúdo do email" aria-expanded="false"></button>'
            . ($can_edit_meta ? ui_icon_button('edit', ['class' => 'btn-editar-meta', 'title' => 'Editar nome e descrição', 'attrs' => ['data-key' => $key]]) : '')
            . '</div>';

        $tplRows[] = [
            'attrs' => [
                'data-template-key' => $key,
            ],
            'cells' => [
                ['style' => 'text-align: left;', 'html' => $tplCell],
                ui_badge($grupo, tone_grupo_email($grupo)),
                '<span class="email-tpl-gatilho">' . ui_e($trig) . '</span>',
                $acoesCell,
            ],
        ];
    }

    echo ui_table([
        ['label' => 'Template', 'sort' => 'nome', 'attrs' => ['style' => 'text-align: left;']],
        ['label' => 'Grupo', 'sort' => 'grupo'],
        'Gatilho',
        'Ações',
    ], $tplRows, [
        'wrap_id'       => 'table-wrapper',
        'wrap_style'    => 'max-width: 1200px; margin: 0 auto;',
        'table_class'   => 'admin-table tabela-emails',
        'empty_message' => ($filtro_q !== '' || $filtro_grupo !== '') ? 'Nenhum template encontrado com os filtros selecionados.' : 'Ainda não existem templates de email.',
        'sort'          => ['by' => $sort_by, 'dir' => $sort_dir],
    ]);
    ?>
</main>

<?php if ($can_edit_meta): ?>
<!-- Modal: Editar Nome e Descrição -->
<?php
$editMetaBody = '<form id="form-editar-template-meta" class="email-meta-form">'
    . '<input type="hidden" id="modal-template-key">'
    . '<div class="qe-f">'
    . '  <label for="modal-template-nome">Nome</label>'
    . ui_input('', ['id' => 'modal-template-nome', 'class' => 'qe-in', 'required' => true, 'attrs' => ['autocomplete' => 'off']])
    . '</div>'
    . '<div class="qe-f">'
    . '  <label for="modal-template-descricao">Descrição</label>'
    . ui_textarea('', '', ['id' => 'modal-template-descricao', 'class' => 'qe-in', 'rows' => 3])
    . '</div>'
    . '</form>';

echo ui_modal('modal-editar-template', 'Editar Template', $editMetaBody, [
    'class'  => 'email-meta-modal',
    'footer' => ui_button('Cancelar', ['id' => 'btn-cancelar-meta', 'class' => 'qe-btn-cancel'])
        . ui_button('Guardar', ['type' => 'submit', 'variant' => 'primary', 'form' => 'form-editar-template-meta', 'id' => 'btn-guardar-meta']),
]);
?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken     = <?php echo json_encode($_SESSION['csrf_token'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const templatesData = <?php echo json_encode($templates_js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const canEditMeta   = <?php echo $can_edit_meta ? 'true' : 'false'; ?>;
    const saveUrl       = <?php echo json_encode($save_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const tableWrap     = document.getElementById('table-wrapper');

    const modalMeta    = document.getElementById('modal-editar-template');
    const formMeta     = document.getElementById('form-editar-template-meta');
    const inputMetaKey = document.getElementById('modal-template-key');
    const inputMetaNom = document.getElementById('modal-template-nome');
    const inputMetaDes = document.getElementById('modal-template-descricao');

    let openKey = '';

    const PLACEHOLDERS_POR_TEMPLATE = {
        confirmacao_contacto: ['{nome_cliente}', '{nome_remetente}', '{mensagem}'],
        boas_vindas:          ['{nome_cliente}'],
        password_alterada:    ['{nome_cliente}'],
        conta_eliminada:      ['{nome_cliente}'],
        verificacao_email:    ['{nome_cliente}', '{link_verificacao}'],
        recuperacao_cliente:  ['{nome_cliente}', '{link_recuperacao}'],
        recuperacao_admin:    ['{nome_admin}', '{link_recuperacao}'],
        nova_encomenda_loja:  ['{id_encomenda}', '{nome_cliente}', '{email_cliente}', '{total_final}', '{metodo_pagamento}', '{link_admin}'],
        novo_contacto_loja:   ['{nome_remetente}', '{email_remetente}', '{mensagem}', '{link_admin}'],
    };

    const PLACEHOLDERS_POR_GRUPO = {
        encomendas:   ['{nome_cliente}', '{id_encomenda}', '{subtotal_produtos}', '{portes_envio}', '{total_final}', '{lista_produtos}', '{metodo_pagamento}', '{link_acompanhamento}', '{codigo_tracking}'],
        notificacoes: ['{id_encomenda}', '{nome_cliente}', '{email_cliente}', '{total_final}', '{metodo_pagamento}', '{nome_remetente}', '{email_remetente}', '{mensagem}', '{link_admin}'],
        autenticacao: ['{nome_cliente}', '{nome_admin}', '{link_recuperacao}', '{link_verificacao}'],
    };

    function getPlaceholdersForTemplate(key, grupo) {
        if (PLACEHOLDERS_POR_TEMPLATE[key]) {
            return PLACEHOLDERS_POR_TEMPLATE[key];
        }
        const g = String(grupo || '').toLowerCase();
        if (g.includes('notifica')) return PLACEHOLDERS_POR_GRUPO.notificacoes;
        if (g.includes('autentic')) return PLACEHOLDERS_POR_GRUPO.autenticacao;
        return PLACEHOLDERS_POR_GRUPO.encomendas;
    }

    function esc(str) {
        return String(str ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    }

    function abrirModal(el) {
        if (!el) return;
        el.style.display = 'flex';
        document.documentElement.classList.add('scroll-lock');
    }
    function fecharModal(el) {
        if (!el) return;
        el.style.display = 'none';
        document.documentElement.classList.remove('scroll-lock');
    }

    if (canEditMeta && modalMeta) {
        document.getElementById('btn-cancelar-meta')?.addEventListener('click', () => fecharModal(modalMeta));
        modalMeta.addEventListener('click', e => { if (e.target === modalMeta) fecharModal(modalMeta); });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && modalMeta.style.display === 'flex') fecharModal(modalMeta);
        });
    }

    function autoResize(ta) {
        if (!ta) return;
        ta.style.height = 'auto';
        ta.style.height = Math.max(140, ta.scrollHeight) + 'px';
    }

    function fecharSubtabela(imediato = false) {
        document.querySelectorAll('.tabela-emails tbody tr.is-expanded').forEach(r => r.classList.remove('is-expanded'));
        document.querySelectorAll('.tabela-emails .btn-expand-single.is-open').forEach(b => {
            b.classList.remove('is-open');
            b.setAttribute('aria-expanded', 'false');
        });
        openKey = '';

        document.querySelectorAll('.tabela-emails tbody tr.email-subrow').forEach(r => {
            if (imediato) { r.remove(); return; }
            if (r.dataset.closing === '1') return;
            r.dataset.closing = '1';
            r.classList.remove('is-open');
            const collapse = r.querySelector('.email-subrow-collapse');
            let removed = false;
            const cleanup = () => { if (!removed) { removed = true; r.remove(); } };
            collapse?.addEventListener('transitionend', ev => {
                if (ev.propertyName === 'grid-template-rows') cleanup();
            }, { once: true });
            setTimeout(cleanup, 360);
        });
    }

    function renderSubrow(key, subTr) {
        const tpl = templatesData[key];
        if (!tpl) return;
        const placeholders = getPlaceholdersForTemplate(key, tpl.grupo);
        const phHtml = placeholders.map(p => `<button type="button" class="email-ph-chip" data-ph="${esc(p)}" title="Copiar ${esc(p)}">${esc(p)}</button>`).join('');

        subTr.innerHTML = `
            <td colspan="4" class="email-subrow-cell">
                <div class="email-subrow-collapse">
                    <div class="email-subrow-inner">
                        <div class="email-editor-panel">
                            <div class="email-ph-bar">
                                <span class="email-ph-label">Campos automáticos (clica para copiar)</span>
                                <div class="email-ph-list">${phHtml}</div>
                            </div>
                            <form class="form-template-email" data-template-key="${esc(key)}">
                                <div class="qe-f">
                                    <label for="assunto_${esc(key)}">Assunto do Email</label>
                                    <input type="text" id="assunto_${esc(key)}" class="qe-in" value="${esc(tpl.assunto)}" required autocomplete="off">
                                </div>
                                <div class="qe-f">
                                    <label for="corpo_${esc(key)}">Corpo do Email</label>
                                    <textarea id="corpo_${esc(key)}" class="qe-in email-body-ta" rows="7" required>${esc(tpl.corpo)}</textarea>
                                </div>
                                <div class="email-editor-actions">
                                    <button type="submit" class="ui-btn ui-btn--primary">Guardar Template</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </td>
        `;

        const ta = subTr.querySelector('.email-body-ta');
        if (ta) {
            autoResize(ta);
            ta.addEventListener('input', () => autoResize(ta));
        }
    }

    function toggleSubtabela(key) {
        const tr = document.querySelector(`.tabela-emails tbody tr[data-template-key="${CSS.escape(key)}"]`);
        if (!tr) return;
        if (openKey === key) {
            fecharSubtabela(false);
            return;
        }
        fecharSubtabela(false);
        openKey = key;
        tr.classList.add('is-expanded');
        const btn = tr.querySelector('.btn-expand-single');
        if (btn) {
            btn.classList.add('is-open');
            btn.setAttribute('aria-expanded', 'true');
        }
        const subTr = document.createElement('tr');
        subTr.className = 'email-subrow';
        subTr.dataset.parentKey = key;
        renderSubrow(key, subTr);
        tr.insertAdjacentElement('afterend', subTr);
        void subTr.offsetHeight;
        requestAnimationFrame(() => {
            if (subTr.dataset.closing !== '1') {
                subTr.classList.add('is-open');
                autoResize(subTr.querySelector('.email-body-ta'));
            }
        });
    }

    // Delegação de cliques na tabela (sobrevive a sort/filter/search AJAX)
    tableWrap?.addEventListener('click', e => {
        const expandBtn = e.target.closest('.btn-expand-single');
        if (expandBtn) {
            toggleSubtabela(expandBtn.dataset.key);
            return;
        }

        const editBtn = e.target.closest('.btn-editar-meta');
        if (editBtn && canEditMeta && modalMeta) {
            const key = editBtn.dataset.key;
            const tpl = templatesData[key];
            if (!tpl) return;
            inputMetaKey.value = key;
            inputMetaNom.value = tpl.nome;
            inputMetaDes.value = tpl.descricao;
            abrirModal(modalMeta);
            setTimeout(() => { inputMetaNom.focus(); inputMetaNom.select(); }, 50);
            return;
        }

        const phChip = e.target.closest('.email-ph-chip');
        if (phChip) {
            const orig = phChip.dataset.ph;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(orig).then(() => {
                    clearTimeout(phChip._t);
                    phChip.textContent = 'Copiado!';
                    phChip.classList.add('is-copied');
                    phChip._t = setTimeout(() => {
                        phChip.textContent = orig;
                        phChip.classList.remove('is-copied');
                    }, 900);
                });
            }
        }
    });

    tableWrap?.addEventListener('dblclick', e => {
        if (e.target.closest('button, input, textarea, label, .email-subrow')) return;
        const tr = e.target.closest('tr[data-template-key]');
        if (tr) toggleSubtabela(tr.dataset.templateKey);
    });

    // Submissão do conteúdo do email (delegado no tableWrap)
    tableWrap?.addEventListener('submit', e => {
        const form = e.target.closest('.form-template-email');
        if (!form) return;
        e.preventDefault();

        const btn = form.querySelector('button[type="submit"]');
        if (btn && btn.disabled) return;
        const key     = form.dataset.templateKey;
        const tpl     = templatesData[key] || { nome: '', descricao: '' };
        const assunto = form.querySelector('input[type="text"]').value;
        const corpo   = form.querySelector('textarea').value;

        if (btn) { btn.disabled = true; btn.textContent = 'A guardar...'; }
        const fd = new FormData();
        fd.append('template_key', key);
        fd.append('nome', tpl.nome);
        fd.append('descricao', tpl.descricao);
        fd.append('assunto', assunto);
        fd.append('corpo', corpo);
        fd.append('csrf_token', csrfToken);

        fetch(saveUrl, { method: 'POST', body: fd })
            .then(async r => {
                const data = await r.json().catch(() => null);
                if (!r.ok || !data) throw new Error(data?.mensagem || 'HTTP ' + r.status);
                return data;
            })
            .then(data => {
                if (data.sucesso) {
                    if (templatesData[key]) {
                        templatesData[key].assunto = assunto;
                        templatesData[key].corpo   = corpo;
                    }
                    mostrarPopup('Template guardado!', 'sucesso');
                } else {
                    mostrarPopup(data.mensagem || 'Erro ao guardar template.', 'erro');
                }
            })
            .catch(err => mostrarPopup(err.message && err.message.indexOf('HTTP ') !== 0 ? err.message : 'Erro de ligação ao guardar template.', 'erro'))
            .finally(() => { if (btn) { btn.disabled = false; btn.textContent = 'Guardar Template'; } });
    });

    // Guardar Nome e Descrição via Modal
    if (canEditMeta && formMeta) {
        formMeta.addEventListener('submit', e => {
            e.preventDefault();
            const key  = inputMetaKey.value;
            const nome = inputMetaNom.value.trim();
            const desc = inputMetaDes.value.trim();
            if (!key || !nome) return;

            const tpl = templatesData[key] || { assunto: '', corpo: '' };
            const btn = document.getElementById('btn-guardar-meta');
            btn.disabled = true;
            btn.textContent = 'A guardar...';

            const fd = new FormData();
            fd.append('template_key', key);
            fd.append('nome', nome);
            fd.append('descricao', desc);
            fd.append('assunto', tpl.assunto);
            fd.append('corpo', tpl.corpo);
            fd.append('csrf_token', csrfToken);

            fetch(saveUrl, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.sucesso) {
                        if (templatesData[key]) {
                            templatesData[key].nome      = nome;
                            templatesData[key].descricao = desc;
                        }
                        const tr = document.querySelector(`.tabela-emails tbody tr[data-template-key="${CSS.escape(key)}"]`);
                        if (tr) {
                            const cell = tr.querySelector('.email-tpl-cell');
                            if (cell) {
                                cell.innerHTML = `<strong class="email-tpl-nome">${esc(nome)}</strong>`
                                    + (desc ? `<span class="email-tpl-desc">${esc(desc)}</span>` : '');
                            }
                        }
                        fecharModal(modalMeta);
                        mostrarPopup('Template atualizado!', 'sucesso');
                    } else {
                        mostrarPopup(data.mensagem || 'Erro ao atualizar template.', 'erro');
                    }
                })
                .catch(() => mostrarPopup('Erro de ligação ao guardar template.', 'erro'))
                .finally(() => { btn.disabled = false; btn.textContent = 'Guardar'; });
        });
    }
});
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
