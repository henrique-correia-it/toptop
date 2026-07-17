<?php
require_once __DIR__ . '/../config/session.php';

// Segurança: Apenas superadmins e desenvolvedores podem aceder
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true || !in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor'])) {
    header("Location: /admin");
    exit;
}

include '../templates/header.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// O header.php já incluiu o database.php e carregou $portes_js
$portes_por_pais = $portes_js ?? [];
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
            <h2>Configurações de Portes</h2>
        </div>
        <div id="autosave-status" class="autosave-status header-actions-container">
            <span class="dot"></span>
            <span class="text">Tudo guardado</span>
        </div>
    </div>

    <div class="admin-container" style="max-width: 1200px; margin: 0 auto;">
        <section class="free-shipping-config" aria-labelledby="free-shipping-title">
            <div class="free-shipping-copy">
                <span class="free-shipping-region">Portugal Continental</span>
                <h3 id="free-shipping-title">Portes grátis por valor da encomenda</h3>
            </div>
            <div class="free-shipping-controls">
                <label class="free-shipping-toggle" for="portes-gratis-ativo">
                    <span class="free-shipping-toggle-copy">
                        <strong id="portes-gratis-estado"><?php echo $portes_gratis_ativo ? 'Ativo' : 'Desativado'; ?></strong>
                        <small>Aplicar esta oferta na loja</small>
                    </span>
                    <span class="switch-premium">
                        <input type="checkbox" id="portes-gratis-ativo" <?php echo $portes_gratis_ativo ? 'checked' : ''; ?>>
                        <span class="slider" aria-hidden="true"></span>
                    </span>
                </label>
                <label class="free-shipping-value" for="portes-gratis-minimo">
                    <span>Valor mínimo</span>
                    <span class="money-input">
                        <span aria-hidden="true">€</span>
                        <input
                            type="number"
                            id="portes-gratis-minimo"
                            min="0.01"
                            step="0.01"
                            inputmode="decimal"
                            value="<?php echo htmlspecialchars(number_format($portes_gratis_minimo, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                        >
                    </span>
                </label>
            </div>
        </section>

        <div id="configuracoes-shipping">
            <div class="toolbar-shipping">
                <h3>Tabelas de Portes</h3>
                <button type="button" id="btn-adicionar-pais" class="btn-primary-plus btn-with-plus">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Adicionar País de Envio
                </button>
            </div>

            <div id="paises-container" class="paises-grid">
                <!-- Os cards dos países serão injetados aqui via JS -->
            </div>
        </div>
    </div>
</main>

<!-- Modal Adicionar País -->
<div id="modal-add-pais" class="modal-overlay-shipping">
    <div class="modal-content-shipping">
        <div class="modal-header-shipping">
            <h3>Selecionar País</h3>
            <button type="button" class="btn-close-unified btn-close-modal" id="btn-close-modal" title="Fechar">&times;</button>
        </div>
        <div class="modal-body-shipping">
            <div class="search-wrap">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="search-pais" placeholder="Pesquisar por nome ou código ISO..." autocomplete="off">
            </div>
            <div id="lista-paises" class="lista-paises-scroll">
                <!-- Injetado via JS -->
            </div>
        </div>
    </div>
</div>


<script>
function getNomePais(iso) {
    try {
        const regionNames = new Intl.DisplayNames(['pt'], { type: 'region' });
        return regionNames.of(iso.toUpperCase());
    } catch (e) {
        return iso.toUpperCase();
    }
}

// Lista completa de códigos ISO 3166-1 alpha-2
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

let dadosPortes = <?php echo json_encode($portes_por_pais); ?>;
let limitePortesGratis = <?php echo json_encode($portes_gratis_minimo); ?>;
let portesGratisAtivo = <?php echo $portes_gratis_ativo ? 'true' : 'false'; ?>;
let autoSaveTimer = null;

function renderizarPaises() {
    const container = document.getElementById('paises-container');
    container.innerHTML = '';

    Object.keys(dadosPortes).forEach((iso, idx) => {
        const nome = getNomePais(iso);
        const card = document.createElement('div');
        card.className = 'country-card';
        card.style.animationDelay = `${idx * 0.05}s`;
        card.innerHTML = `
            <div class="country-header">
                <div class="country-title">
                    <span class="iso-badge">${iso}</span>
                    ${nome}
                </div>
                <button type="button" class="btn-del-single" onclick="removerPais('${iso}')" title="Remover País"></button>
            </div>
            <div class="country-body">
                <div class="weight-rows-container" id="body-${iso}">
                    ${renderizarLinhasPeso(iso)}
                </div>
            </div>
            <div class="country-footer">
                <span class="interval-count">${dadosPortes[iso].length} intervalo(s)</span>
                <button type="button" class="btn-add-row btn-with-plus btn-with-plus-text" onclick="adicionarLinhaPeso('${iso}')">Adicionar Linha</button>
            </div>
        `;
        container.appendChild(card);
    });
}

function renderizarLinhasPeso(iso) {
    return dadosPortes[iso].map((regra, index) => `
        <div class="weight-row" data-iso="${iso}" data-index="${index}">
            <div class="input-group">
                <label>Mín (g)</label>
                <input type="number" value="${regra.min}" onchange="atualizarRegra('${iso}', ${index}, 'min', this.value)">
            </div>
            <div class="input-group">
                <label>Máx (g)</label>
                <input type="number" value="${regra.max}" onchange="atualizarRegra('${iso}', ${index}, 'max', this.value)">
            </div>
            <div class="input-group">
                <label>Preço (€)</label>
                <input type="number" step="0.01" value="${regra.preco}" onchange="atualizarRegra('${iso}', ${index}, 'preco', this.value)">
            </div>
            <button type="button" class="btn-del-single" onclick="removerLinhaPeso('${iso}', ${index})" title="Remover"></button>
        </div>
    `).join('');
}

function updateAutoSaveStatus(status) {
    const el = document.getElementById('autosave-status');
    if (!el) return;
    
    if (status === 'saving') {
        el.classList.add('saving');
        el.querySelector('.text').textContent = 'A guardar...';
    } else if (status === 'saved') {
        el.classList.remove('saving');
        el.querySelector('.text').textContent = 'Tudo guardado';
    } else if (status === 'error') {
        el.classList.remove('saving');
        el.querySelector('.text').textContent = 'Erro ao guardar';
        el.style.color = 'var(--danger-color)';
    }
}

function salvarConfiguracoes(imediato = false) {
    if (!validarDados()) return;

    if (autoSaveTimer) clearTimeout(autoSaveTimer);

    const performSave = () => {
        updateAutoSaveStatus('saving');

        const formData = new FormData();
        formData.append('portes_por_pais', JSON.stringify(dadosPortes));
        formData.append('portes_gratis_minimo', limitePortesGratis.toFixed(2));
        formData.append('portes_gratis_ativo', portesGratisAtivo ? '1' : '0');
        formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

        fetch('ajax_salvar_portes.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.sucesso) {
                updateAutoSaveStatus('saved');
            } else {
                updateAutoSaveStatus('error');
                mostrarPopup(data.mensagem || 'Erro ao guardar.', 'erro');
            }
        })
        .catch(() => {
            updateAutoSaveStatus('error');
            mostrarPopup('Erro de comunicação.', 'erro');
        });
    };

    if (imediato) {
        performSave();
    } else {
        autoSaveTimer = setTimeout(performSave, 1000);
    }
}

// ── Lógica do Modal de Países ──
const modalAdd = document.getElementById('modal-add-pais');
const searchInput = document.getElementById('search-pais');
const listaPaises = document.getElementById('lista-paises');
const btnClose = document.getElementById('btn-close-modal');

function abrirModalPais() {
    modalAdd.style.display = 'flex';
    searchInput.value = '';
    filtrarPaises('');
    searchInput.focus();
}

function fecharModalPais() {
    modalAdd.style.display = 'none';
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
            <span>${getNomePais(iso)}</span>
            <span class="iso">${iso}</span>
        </div>
    `).join('');

    if (filtrados.length === 0) {
        listaPaises.innerHTML = '<div style="padding:20px; text-align:center; color:#94a3b8; font-weight:600;">Nenhum país encontrado...</div>';
    }
}

function selecionarPais(iso) {
    dadosPortes[iso] = [{ min: 0, max: 1000, preco: 0 }];
    renderizarPaises();
    fecharModalPais();
    salvarConfiguracoes(true);
}

searchInput.addEventListener('input', e => filtrarPaises(e.target.value));
btnClose.addEventListener('click', fecharModalPais);
window.addEventListener('click', e => { if (e.target === modalAdd) fecharModalPais(); });

function removerPais(iso) {
    mostrarModalConfirmacao(
        'Remover País',
        `Tem a certeza que deseja remover as configurações de portes para <strong>${getNomePais(iso)}</strong>?`,
        () => {
            delete dadosPortes[iso];
            renderizarPaises();
            salvarConfiguracoes(true);
        }
    );
}

function adicionarLinhaPeso(iso) {
    const ultimaRegra = dadosPortes[iso][dadosPortes[iso].length - 1];
    const novoMin = ultimaRegra ? parseInt(ultimaRegra.max) : 0;
    dadosPortes[iso].push({ min: novoMin, max: novoMin + 1000, preco: 0 });
    renderizarPaises();
    salvarConfiguracoes(true);
}

function removerLinhaPeso(iso, index) {
    mostrarModalConfirmacao(
        'Remover Intervalo',
        `Tem a certeza que deseja remover este intervalo de peso para <strong>${getNomePais(iso)}</strong>?`,
        () => {
            dadosPortes[iso].splice(index, 1);
            if (dadosPortes[iso].length === 0) delete dadosPortes[iso];
            renderizarPaises();
            salvarConfiguracoes(true);
        }
    );
}

function atualizarRegra(iso, index, campo, valor) {
    dadosPortes[iso][index][campo] = parseFloat(valor);
    salvarConfiguracoes();
}

function validarDados() {
    if (!Number.isFinite(limitePortesGratis) || limitePortesGratis <= 0) {
        mostrarPopup('Indique um valor válido para os portes grátis.', 'erro');
        return false;
    }

    for (let iso in dadosPortes) {
        let regras = dadosPortes[iso];
        for (let i = 0; i < regras.length; i++) {
            let r = regras[i];
            if (r.min >= r.max) {
                mostrarPopup(`Erro em ${iso}: O peso mínimo (${r.min}) não pode ser maior ou igual ao máximo (${r.max}).`, 'erro');
                return false;
            }
            // Verificar sobreposições
            for (let j = 0; j < regras.length; j++) {
                if (i === j) continue;
                let r2 = regras[j];
                if (r.min < r2.max && r.max > r2.min) {
                    mostrarPopup(`Erro em ${iso}: Existe uma sobreposição de pesos entre os intervalos.`, 'erro');
                    return false;
                }
            }
        }
    }
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    renderizarPaises();

    document.getElementById('btn-adicionar-pais').addEventListener('click', abrirModalPais);

    const inputPortesGratis = document.getElementById('portes-gratis-minimo');
    const inputPortesGratisAtivo = document.getElementById('portes-gratis-ativo');
    const estadoPortesGratis = document.getElementById('portes-gratis-estado');
    const configPortesGratis = document.querySelector('.free-shipping-config');

    const atualizarEstadoPortesGratis = () => {
        inputPortesGratis.disabled = !portesGratisAtivo;
        estadoPortesGratis.textContent = portesGratisAtivo ? 'Ativo' : 'Desativado';
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
