<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';

// Segurança: Apenas gestores da loja podem aceder
require_admin_manager('/admin');

include '../templates/header.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// O header.php já incluiu o database.php e carregou $portes_js
$portes_por_pais = $portes_js ?? [];
?>

<main class="dashboard-container animate-entry">

    <?php echo ui_page_header('Configurações de Portes', [
        'layout'     => 'admin',
        'back_url'   => '/admin',
        'back_title' => 'Voltar ao Painel',
    ]); ?>

    <div class="admin-container" style="max-width: 1200px; margin: 0 auto;">
        <?php
        $freeShippingHtml = ui_checkbox('', 'Portes grátis em Portugal Continental', (bool)$portes_gratis_ativo, [
            'id'    => 'portes-gratis-ativo',
            'class' => 'free-shipping-check',
        ]) . '<label class="free-shipping-value" for="portes-gratis-minimo">'
           . '<span>A partir de</span>'
           . ui_input('', [
               'type'      => 'number',
               'id'        => 'portes-gratis-minimo',
               'prefix'    => '€',
               'class'     => 'ui-field__control free-shipping-input',
               'min'       => '0.01',
               'step'      => '0.01',
               'inputmode' => 'decimal',
               'value'     => number_format($portes_gratis_minimo, 2, '.', ''),
           ])
           . '</label>';
        echo ui_card($freeShippingHtml, [
            'compact' => true,
            'class'   => 'free-shipping-config',
            'attrs'   => ['aria-label' => 'Portes grátis'],
        ]);
        ?>

        <?php echo ui_table_toolbar([
            'primary_action' => ui_button('Adicionar País', [
                'id'    => 'btn-adicionar-pais',
                'class' => 'button btn-with-plus btn-with-plus-text',
            ]),
            'search'         => [
                'name'        => 'q',
                'value'       => '',
                'placeholder' => 'Pesquisar país...',
            ],
            'form_id'        => 'form-pesquisa-portes',
            'preserve_sort'  => false,
        ]); ?>

        <?php echo ui_table(
            [
                ['label' => 'País'],
                ['label' => 'Escalões'],
                ['label' => 'Preço'],
                ['label' => 'Ações', 'sortable' => false],
            ],
            [],
            [
                'wrap_id'     => 'table-wrapper',
                'wrap_style'  => 'max-width: 1200px; margin: 0 auto;',
                'table_class' => 'admin-table tabela-portes',
                'tbody_id'    => 'portes-tbody',
                'show_empty'  => false,
            ]
        ); ?>
    </div>
</main>

<?php echo ui_bulk_bar('', '', [
    'unit'         => 'país(es)',
    'action_label' => 'Remover Selecionados',
]); ?>

<!-- Modal Adicionar País -->
<?php
echo ui_modal(
    'modal-add-pais',
    'Selecionar País',
    ui_search_input('', 'Pesquisar país...', [
        'id'    => 'search-pais',
        'class' => 'portes-modal-search',
    ]) . '<div id="lista-paises" class="lista-paises-scroll"></div>',
    [
        'class'  => 'portes-modal',
        'footer' => ui_button('Cancelar', ['id' => 'btn-close-modal', 'class' => 'qe-btn-cancel']),
    ]
);
?>

<script>
function getNomePais(iso) {
    try {
        const regionNames = new Intl.DisplayNames(['pt'], { type: 'region' });
        return regionNames.of(iso.toUpperCase());
    } catch (e) {
        return iso.toUpperCase();
    }
}

const TODOS_PAISES = [
    "AD","AE","AF","AG","AI","AL","AM","AO","AQ","AR","AS","AT","AU","AW","AX","AZ",
    "BA","BB","BD","BE","BF","BG","BH","BI","BJ","BL","BM","BN","BO","BQ","BR","BS","BT","BV","BW","BY","BZ",
    "CA","CC","CD","CF","CG","CH","CI","CK","CL","CM","CN","CO","CR","CU","CV","CW","CX","CY","CZ",
    "DE","DJ","DK","DM","DO","DZ","EC","EE","EG","EH","ER","ES","ET","FI","FJ","FK","FM","FO","FR",
    "GA","GB","GD","GE","GF","GG","GH","GI","GL","GM","GN","GP","GQ","GR","GS","GT","GU","GW","GY",
    "HK","HM","HN","HR","HT","HU","ID","IE","IL","IM","IN","IO","IQ","IR","IS","IT","JE","JM","JO","JP",
    "KE","KG","KH","KI","KM","KN","KP","KR","KW","KY","KZ","LA","LB","LC","LI","LK","LR","LS","LT","LU","LV","LY",
    "MA","MC","MD","ME","MF","MG","MH","MK","ML","MM","MN","MO","MP","MQ","MR","MS","MT","MU","MV","MW","MX","MY","MZ",
    "NA","NC","NE","NF","NG","NI","NL","NO","NP","NR","NU","NZ","OM","PA","PE","PF","PG","PH","PK","PL","PM","PN","PR","PS","PT","PW","PY",
    "QA","RE","RO","RS","RU","RW","SA","SB","SC","SD","SE","SG","SH","SI","SJ","SK","SL","SM","SN","SO","SR","SS","ST","SV","SX","SY","SZ",
    "TC","TD","TF","TG","TH","TJ","TK","TL","TM","TN","TO","TR","TT","TV","TW","TZ","UA","UG","UM","US","UY","UZ",
    "VA","VC","VE","VG","VI","VN","VU","WF","WS","YE","YT","ZA","ZM","ZW"
];

let dadosPortes        = <?php echo json_encode($portes_por_pais); ?>;
let limitePortesGratis = <?php echo json_encode($portes_gratis_minimo); ?>;
let portesGratisAtivo  = <?php echo $portes_gratis_ativo ? 'true' : 'false'; ?>;
let autoSaveTimer      = null;
let activeSaveController = null;
let openIso            = '';
let filtroTabela       = '';
let bulkSel            = null;

function esc(str) {
    return String(str ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
}

function fmtEuro(val) {
    const n = Number(val);
    if (!Number.isFinite(n)) return '-';
    return '€' + n.toFixed(2).replace('.', ',');
}

function resumirPrecos(regras) {
    if (!Array.isArray(regras) || regras.length === 0) return '-';
    const precos = regras.map(r => Number(r.preco)).filter(Number.isFinite);
    if (precos.length === 0) return '-';
    const min = Math.min(...precos);
    const max = Math.max(...precos);
    return min === max ? fmtEuro(min) : `${fmtEuro(min)} - ${fmtEuro(max)}`;
}

function atualizarResumoLinha(iso) {
    const tr = document.querySelector(`#portes-tbody tr[data-id="${iso}"]`);
    if (!tr || !dadosPortes[iso]) return;
    const regras = dadosPortes[iso];
    const total = regras.length;
    if (tr.children[1]) {
        tr.children[1].textContent = `${total} ${total === 1 ? 'escalão' : 'escalões'}`;
    }
    if (tr.children[2]) {
        tr.children[2].textContent = resumirPrecos(regras);
    }
}

function renderizarLinhasPeso(iso) {
    const regras = dadosPortes[iso] || [];
    if (regras.length === 0) {
        return '<div class="portes-sub-empty">Sem escalões configurados.</div>';
    }
    return regras.map((regra, index) => `
        <div class="weight-row" data-iso="${iso}" data-index="${index}">
            <div class="input-group">
                <label>Mín (g)</label>
                <input type="number" class="ui-field__control portes-weight-input" min="0" value="${regra.min}" onchange="atualizarRegra('${iso}', ${index}, 'min', this.value)">
            </div>
            <div class="input-group">
                <label>Máx (g)</label>
                <input type="number" class="ui-field__control portes-weight-input" min="0" value="${regra.max}" onchange="atualizarRegra('${iso}', ${index}, 'max', this.value)">
            </div>
            <div class="input-group">
                <label>Preço (€)</label>
                <input type="number" class="ui-field__control portes-weight-input" min="0" step="0.01" value="${regra.preco}" onchange="atualizarRegra('${iso}', ${index}, 'preco', this.value)">
            </div>
            <button type="button" class="btn-del-single" onclick="removerLinhaPeso('${iso}', ${index})" title="Remover escalão" aria-label="Remover escalão"></button>
        </div>
    `).join('');
}

function renderSubrowConteudo(iso, subTr) {
    subTr.innerHTML = `
        <td colspan="4" class="portes-subrow-cell">
            <div class="portes-subrow-collapse">
                <div class="portes-subrow-inner">
                    <div class="portes-subtable-panel">
                        <div class="portes-subtable-head">
                            <button type="button" class="ui-btn ui-btn--default button btn-with-plus btn-with-plus-text" onclick="adicionarLinhaPeso('${iso}')">Adicionar Escalão</button>
                        </div>
                        <div class="weight-grid-head">
                            <span>Mín (g)</span>
                            <span>Máx (g)</span>
                            <span>Preço (€)</span>
                            <span></span>
                        </div>
                        <div class="weight-rows-container" id="body-${iso}">
                            ${renderizarLinhasPeso(iso)}
                        </div>
                    </div>
                </div>
            </div>
        </td>
    `;
}

function fecharSubtabela(imediato = false) {
    document.querySelectorAll('#portes-tbody tr.is-expanded').forEach(r => r.classList.remove('is-expanded'));
    document.querySelectorAll('#portes-tbody .btn-expand-single.is-open').forEach(b => {
        b.classList.remove('is-open');
        b.setAttribute('aria-expanded', 'false');
    });
    openIso = '';

    document.querySelectorAll('#portes-tbody tr.portes-subrow').forEach(r => {
        if (imediato) {
            r.remove();
            return;
        }
        if (r.dataset.closing === '1') return;
        r.dataset.closing = '1';
        r.classList.remove('is-open');
        const collapse = r.querySelector('.portes-subrow-collapse');
        let removed = false;
        const cleanup = () => {
            if (removed) return;
            removed = true;
            r.remove();
        };
        collapse?.addEventListener('transitionend', ev => {
            if (ev.propertyName === 'grid-template-rows') cleanup();
        }, { once: true });
        setTimeout(cleanup, 360);
    });
}

function toggleSubtabela(iso) {
    const tr = document.querySelector(`#portes-tbody tr[data-id="${iso}"]`);
    if (!tr) return;
    if (openIso === iso) {
        fecharSubtabela(false);
        return;
    }
    fecharSubtabela(false);
    openIso = iso;
    tr.classList.add('is-expanded');
    const btn = tr.querySelector('.btn-expand-single');
    if (btn) {
        btn.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
    }
    const subTr = document.createElement('tr');
    subTr.className = 'portes-subrow';
    subTr.dataset.parentIso = iso;
    renderSubrowConteudo(iso, subTr);
    tr.insertAdjacentElement('afterend', subTr);
    void subTr.offsetHeight;
    requestAnimationFrame(() => {
        if (subTr.dataset.closing !== '1') {
            subTr.classList.add('is-open');
        }
    });
}

function renderizarPaises(reabrirIso = '') {
    const tbody = document.getElementById('portes-tbody');
    if (!tbody) return;
    const prevOpen = reabrirIso || openIso;
    fecharSubtabela(true);
    tbody.innerHTML = '';

    const termo = filtroTabela.toLowerCase().trim();
    const isos = Object.keys(dadosPortes).filter(iso => {
        if (!termo) return true;
        return getNomePais(iso).toLowerCase().includes(termo) || iso.toLowerCase().includes(termo);
    });

    if (isos.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="reservation-empty">${termo ? 'Nenhum país encontrado com essa pesquisa.' : 'Ainda não existem países configurados.'}</td></tr>`;
        return;
    }

    isos.forEach(iso => {
        const nome   = getNomePais(iso);
        const regras = dadosPortes[iso] || [];
        const total  = regras.length;
        const tr     = document.createElement('tr');
        tr.dataset.id = iso;
        tr.innerHTML = `
            <td>${esc(nome)} (${esc(iso)})</td>
            <td>${total} ${total === 1 ? 'escalão' : 'escalões'}</td>
            <td>${esc(resumirPrecos(regras))}</td>
            <td>
                <div class="acoes-tabela">
                    <button type="button" class="btn-expand-single" data-iso="${esc(iso)}" title="Gerir escalões" aria-label="Gerir escalões" aria-expanded="false"></button>
                    <button type="button" class="btn-del-single btn-remover-pais" data-iso="${esc(iso)}" title="Remover País" aria-label="Remover País"></button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });

    if (prevOpen && dadosPortes[prevOpen] && isos.includes(prevOpen)) {
        const tr = tbody.querySelector(`tr[data-id="${prevOpen}"]`);
        if (tr) {
            openIso = prevOpen;
            tr.classList.add('is-expanded');
            const btn = tr.querySelector('.btn-expand-single');
            if (btn) {
                btn.classList.add('is-open');
                btn.setAttribute('aria-expanded', 'true');
            }
            const subTr = document.createElement('tr');
            subTr.className = 'portes-subrow is-open';
            subTr.dataset.parentIso = prevOpen;
            renderSubrowConteudo(prevOpen, subTr);
            tr.insertAdjacentElement('afterend', subTr);
        }
    }
}

function salvarConfiguracoes(imediato = false, onErrorRollback = null) {
    if (!validarDados()) {
        if (typeof onErrorRollback === 'function') onErrorRollback();
        return;
    }

    if (autoSaveTimer) clearTimeout(autoSaveTimer);

    const performSave = () => {
        if (activeSaveController) {
            try { activeSaveController.abort(); } catch (_) {}
        }
        const controller = new AbortController();
        activeSaveController = controller;

        const formData = new FormData();
        formData.append('portes_por_pais', JSON.stringify(dadosPortes));
        formData.append('portes_gratis_minimo', limitePortesGratis.toFixed(2));
        formData.append('portes_gratis_ativo', portesGratisAtivo ? '1' : '0');
        formData.append('csrf_token', <?php echo json_encode($_SESSION['csrf_token'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);

        fetch('ajax_salvar_portes.php', {
            method: 'POST',
            body: formData,
            signal: controller.signal
        })
        .then(response => response.json())
        .then(data => {
            if (activeSaveController !== controller) return;
            if (!data.sucesso) {
                if (typeof onErrorRollback === 'function') onErrorRollback();
                mostrarPopup(data.mensagem || 'Erro ao guardar.', 'erro');
            }
        })
        .catch((err) => {
            if (err && err.name === 'AbortError') return;
            if (typeof onErrorRollback === 'function') onErrorRollback();
            mostrarPopup('Erro de comunicação.', 'erro');
        })
        .finally(() => {
            if (activeSaveController === controller) activeSaveController = null;
        });
    };

    if (imediato) {
        performSave();
    } else {
        autoSaveTimer = setTimeout(performSave, 800);
    }
}

// ── Modal de Países ──
const modalAdd    = document.getElementById('modal-add-pais');
const searchInput = document.getElementById('search-pais');
const listaPaises = document.getElementById('lista-paises');
const btnClose    = document.getElementById('btn-close-modal');

function abrirModalPais() {
    if (!modalAdd) return;
    modalAdd.style.display = 'flex';
    document.documentElement.classList.add('scroll-lock');
    if (searchInput) {
        searchInput.value = '';
        filtrarPaises('');
        setTimeout(() => searchInput.focus(), 50);
    }
}

function fecharModalPais() {
    if (!modalAdd) return;
    modalAdd.style.display = 'none';
    document.documentElement.classList.remove('scroll-lock');
}

function filtrarPaises(termo) {
    const termoLower = termo.toLowerCase();
    const paisesJaAdicionados = Object.keys(dadosPortes);
    const filtrados = TODOS_PAISES.filter(iso => {
        if (paisesJaAdicionados.includes(iso)) return false;
        const nome = getNomePais(iso).toLowerCase();
        return nome.includes(termoLower) || iso.toLowerCase().includes(termoLower);
    });

    listaPaises.innerHTML = filtrados.map(iso => `
        <div class="pais-item-select" onclick="selecionarPais('${iso}')">
            <span>${esc(getNomePais(iso))}</span>
            <span class="iso">${esc(iso)}</span>
        </div>
    `).join('');

    if (filtrados.length === 0) {
        listaPaises.innerHTML = '<div class="portes-sub-empty">Nenhum país encontrado...</div>';
    }
}

function selecionarPais(iso) {
    dadosPortes[iso] = [{ min: 0, max: 1000, preco: 0 }];
    fecharModalPais();
    renderizarPaises(iso);
    salvarConfiguracoes(true);
}

searchInput?.addEventListener('input', e => filtrarPaises(e.target.value));
btnClose?.addEventListener('click', fecharModalPais);
modalAdd?.addEventListener('click', e => { if (e.target === modalAdd) fecharModalPais(); });
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modalAdd?.style.display === 'flex') fecharModalPais();
});

function removerPais(iso) {
    if (Object.keys(dadosPortes).length <= 1) {
        mostrarPopup('Deve existir pelo menos um país configurado.', 'erro');
        return;
    }
    mostrarModalConfirmacao(
        'Remover País',
        `Tens a certeza que queres remover as configurações de portes para <strong>${esc(getNomePais(iso))}</strong>?`,
        () => {
            const snapshot = JSON.parse(JSON.stringify(dadosPortes));
            delete dadosPortes[iso];
            if (openIso === iso) openIso = '';
            renderizarPaises();
            salvarConfiguracoes(true, () => {
                dadosPortes = snapshot;
                renderizarPaises();
            });
        }
    );
}

function adicionarLinhaPeso(iso) {
    const ultimaRegra = dadosPortes[iso][dadosPortes[iso].length - 1];
    const novoMin = ultimaRegra ? parseInt(ultimaRegra.max, 10) : 0;
    dadosPortes[iso].push({ min: novoMin, max: novoMin + 1000, preco: 0 });
    const bodyEl = document.getElementById(`body-${iso}`);
    if (bodyEl) bodyEl.innerHTML = renderizarLinhasPeso(iso);
    atualizarResumoLinha(iso);
    salvarConfiguracoes(true);
}

function removerLinhaPeso(iso, index) {
    if (Object.keys(dadosPortes).length <= 1 && dadosPortes[iso] && dadosPortes[iso].length <= 1) {
        mostrarPopup('É necessária pelo menos uma regra de portes válida.', 'erro');
        return;
    }
    mostrarModalConfirmacao(
        'Remover Escalão',
        `Tens a certeza que queres remover este escalão de peso para <strong>${esc(getNomePais(iso))}</strong>?`,
        () => {
            const snapshot = JSON.parse(JSON.stringify(dadosPortes));
            dadosPortes[iso].splice(index, 1);
            if (dadosPortes[iso].length === 0) {
                delete dadosPortes[iso];
                if (openIso === iso) openIso = '';
                renderizarPaises();
            } else {
                const bodyEl = document.getElementById(`body-${iso}`);
                if (bodyEl) bodyEl.innerHTML = renderizarLinhasPeso(iso);
                atualizarResumoLinha(iso);
            }
            salvarConfiguracoes(true, () => {
                dadosPortes = snapshot;
                renderizarPaises(iso);
            });
        }
    );
}

function atualizarRegra(iso, index, campo, valor) {
    dadosPortes[iso][index][campo] = parseFloat(valor);
    atualizarResumoLinha(iso);
    salvarConfiguracoes();
}

function validarDados() {
    if (!Number.isFinite(limitePortesGratis) || limitePortesGratis <= 0) {
        mostrarPopup('Indique um valor válido para os portes grátis.', 'erro');
        return false;
    }

    if (Object.keys(dadosPortes).length === 0) {
        mostrarPopup('Deve configurar pelo menos um país.', 'erro');
        return false;
    }

    for (let iso in dadosPortes) {
        let regras = dadosPortes[iso];
        for (let i = 0; i < regras.length; i++) {
            let r = regras[i];
            if (!Number.isFinite(r.min) || !Number.isFinite(r.max) || !Number.isFinite(r.preco) || r.min < 0 || r.max < 0 || r.preco < 0) {
                mostrarPopup(`Erro em ${iso}: Preencha valores numéricos válidos e não negativos.`, 'erro');
                return false;
            }
            if (r.max > 0 && r.min >= r.max) {
                mostrarPopup(`Erro em ${iso}: O peso mínimo (${r.min}) não pode ser maior ou igual ao máximo (${r.max}).`, 'erro');
                return false;
            }
            const effMax1 = r.max === 0 ? Infinity : r.max;
            for (let j = 0; j < regras.length; j++) {
                if (i === j) continue;
                let r2 = regras[j];
                const effMax2 = r2.max === 0 ? Infinity : r2.max;
                if (r.min < effMax2 && effMax1 > r2.min) {
                    mostrarPopup(`Erro em ${iso}: Existe uma sobreposição de pesos entre os escalões.`, 'erro');
                    return false;
                }
            }
        }
    }
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    renderizarPaises();

    document.getElementById('btn-adicionar-pais')?.addEventListener('click', abrirModalPais);

    // Pesquisa client-side instantânea na toolbar
    const formPesq = document.getElementById('form-pesquisa-portes');
    const inputPesq = formPesq?.querySelector('input[name="q"]');
    formPesq?.addEventListener('submit', e => {
        e.preventDefault();
        filtroTabela = inputPesq ? inputPesq.value : '';
        renderizarPaises();
    });
    inputPesq?.addEventListener('input', () => {
        filtroTabela = inputPesq.value;
        renderizarPaises();
    });

    // Seleção em massa de países
    bulkSel = window.initAdminBulkSelection({
        onAction: ids => {
            const totalPaises = Object.keys(dadosPortes).length;
            if (ids.length >= totalPaises) {
                mostrarPopup('Deve permanecer pelo menos um país configurado.', 'erro');
                return;
            }
            mostrarModalConfirmacao(
                'Remover Países',
                `Tens a certeza que queres remover as configurações de portes de ${ids.length} país(es)?`,
                () => {
                    const snapshot = JSON.parse(JSON.stringify(dadosPortes));
                    ids.forEach(iso => { delete dadosPortes[iso]; });
                    if (bulkSel) bulkSel.setMode(false);
                    renderizarPaises();
                    salvarConfiguracoes(true, () => {
                        dadosPortes = snapshot;
                        renderizarPaises();
                    });
                }
            );
        }
    });

    document.getElementById('btn-sel-mode')?.addEventListener('click', () => fecharSubtabela(false));

    // Delegação na tabela (seta, remover, duplo clique)
    const tableWrap = document.getElementById('table-wrapper');
    tableWrap?.addEventListener('click', e => {
        if (bulkSel && bulkSel.isSelMode()) return;

        const expandBtn = e.target.closest('.btn-expand-single');
        if (expandBtn) {
            toggleSubtabela(expandBtn.dataset.iso);
            return;
        }

        const delBtn = e.target.closest('.btn-remover-pais');
        if (delBtn) {
            removerPais(delBtn.dataset.iso);
        }
    });

    tableWrap?.addEventListener('dblclick', e => {
        if (bulkSel && bulkSel.isSelMode()) return;
        if (e.target.closest('button, input, label, .portes-subrow')) return;
        const tr = e.target.closest('tr[data-id]');
        if (tr) toggleSubtabela(tr.dataset.id);
    });

    // Portes grátis
    const inputPortesGratis      = document.getElementById('portes-gratis-minimo');
    const inputPortesGratisAtivo = document.getElementById('portes-gratis-ativo');
    const configPortesGratis     = document.querySelector('.free-shipping-config');

    const atualizarEstadoPortesGratis = () => {
        inputPortesGratis.disabled = !portesGratisAtivo;
        configPortesGratis.classList.toggle('is-disabled', !portesGratisAtivo);
    };

    inputPortesGratisAtivo.addEventListener('change', function() {
        portesGratisAtivo = this.checked;
        atualizarEstadoPortesGratis();
        salvarConfiguracoes(true);
    });

    atualizarEstadoPortesGratis();
    inputPortesGratis.addEventListener('input', function() {
        const valor = Number.parseFloat(this.value);
        const valido = Number.isFinite(valor) && valor > 0;
        this.setCustomValidity(valido ? '' : 'Indique um valor superior a zero.');
        if (!valido) return;

        limitePortesGratis = Math.round(valor * 100) / 100;
        salvarConfiguracoes();
    });

    inputPortesGratis.addEventListener('blur', function() {
        if (!this.checkValidity()) this.reportValidity();
    });
});
</script>

<?php include '../templates/footer.php'; ?>
