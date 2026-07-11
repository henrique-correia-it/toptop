<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/cliente_auth.php';

require_cliente();

$cliente = cliente_atual($conn);
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_from_post()) {
        $erro = 'Erro de seguranca. Recarregue a pagina.';
    } else {
        $acao = $_POST['acao'] ?? 'criar';
        try {
            if ($acao === 'criar') {
                $rua = trim($_POST['rua'] ?? '');
                $cp = trim($_POST['codigo_postal'] ?? '');
                $loc = trim($_POST['localidade'] ?? '');

                // Verificar duplicados
                $stmtCheck = $conn->prepare("SELECT id FROM cliente_moradas WHERE cliente_id = ? AND rua = ? AND codigo_postal = ? AND localidade = ? LIMIT 1");
                $stmtCheck->bind_param('isss', $cliente['id'], $rua, $cp, $loc);
                $stmtCheck->execute();
                if ($stmtCheck->get_result()->fetch_assoc()) {
                    throw new Exception('Esta morada já está guardada.');
                }
                $stmtCheck->close();

                $conn->begin_transaction();
                $principal = !empty($_POST['principal']) || count(customer_addresses($conn, (int)$cliente['id'])) === 0;
                customer_save_address($conn, (int)$cliente['id'], $_POST, $principal);
                $conn->commit();
                
                $_SESSION['sucesso_morada'] = 'Morada guardada com sucesso.';
                header('Location: /minha-conta/moradas');
                exit;
            } elseif ($acao === 'principal') {
                $moradaId = (int)($_POST['morada_id'] ?? 0);
                $conn->begin_transaction();
                $stmt = $conn->prepare("UPDATE cliente_moradas SET principal = 0 WHERE cliente_id = ?");
                $stmt->bind_param('i', $cliente['id']);
                $stmt->execute();
                $stmt->close();
                $stmt = $conn->prepare("UPDATE cliente_moradas SET principal = 1 WHERE id = ? AND cliente_id = ?");
                $stmt->bind_param('ii', $moradaId, $cliente['id']);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
                
                $_SESSION['sucesso_morada'] = 'Morada principal atualizada.';
                header('Location: /minha-conta/moradas');
                exit;
            } elseif ($acao === 'editar') {
                $moradaId = (int)($_POST['morada_id'] ?? 0);
                if (!$moradaId) throw new Exception('Morada inválida.');
                $rua  = trim($_POST['rua'] ?? '');
                $cp   = trim($_POST['codigo_postal'] ?? '');
                $loc  = trim($_POST['localidade'] ?? '');
                if ($rua === '' || $cp === '' || $loc === '') throw new Exception('Preencha a rua, código postal e localidade.');
                $prov      = trim($_POST['provincia'] ?? '');
                $pais      = strtoupper(trim($_POST['pais_regiao'] ?? 'PT'));
                $principal = !empty($_POST['principal']) ? 1 : 0;

                $conn->begin_transaction();
                $stmt = $conn->prepare("UPDATE cliente_moradas SET rua=?, codigo_postal=?, localidade=?, provincia=?, pais_regiao=?, principal=? WHERE id=? AND cliente_id=?");
                $stmt->bind_param('sssssiii', $rua, $cp, $loc, $prov, $pais, $principal, $moradaId, $cliente['id']);
                $stmt->execute();
                if ($stmt->affected_rows === 0) throw new Exception('Morada não encontrada.');
                $stmt->close();
                if ($principal) {
                    $stmt2 = $conn->prepare("UPDATE cliente_moradas SET principal=0 WHERE cliente_id=? AND id!=?");
                    $stmt2->bind_param('ii', $cliente['id'], $moradaId);
                    $stmt2->execute();
                    $stmt2->close();
                }
                $conn->commit();

                $_SESSION['sucesso_morada'] = 'Morada atualizada com sucesso.';
                header('Location: /minha-conta/moradas');
                exit;
            } elseif ($acao === 'apagar') {
                $moradaId = (int)($_POST['morada_id'] ?? 0);
                $stmt = $conn->prepare("DELETE FROM cliente_moradas WHERE id = ? AND cliente_id = ?");
                $stmt->bind_param('ii', $moradaId, $cliente['id']);
                $stmt->execute();
                $stmt->close();

                $_SESSION['sucesso_morada'] = 'Morada removida.';
                header('Location: /minha-conta/moradas');
                exit;
            }
        } catch (Throwable $e) {
            try { if ($conn->in_transaction) $conn->rollback(); } catch (Throwable $rollbackError) {}
            log_app($e->getMessage(), 'ERROR', 'minha-conta-moradas.php');
            $erro = $e->getMessage();
        }
    }
}

if (isset($_SESSION['sucesso_morada'])) {
    $sucesso = $_SESSION['sucesso_morada'];
    unset($_SESSION['sucesso_morada']);
}

$addresses = customer_addresses($conn, (int)$cliente['id']);
$titulo_pagina = 'As Minhas Moradas';
include __DIR__ . '/templates/header.php';
$portes_por_pais = $portes_js ?? [];
?>

<main class="cliente-area-page">
    <div class="cliente-shell">
        <?php 
        $active_page = 'moradas';
        include __DIR__ . '/templates/cliente-sidebar.php'; 
        ?>
        <section class="cliente-content">
            <div class="cliente-page-head">
                <h1>Moradas</h1>
                <p>Estas moradas podem ser usadas rapidamente no checkout.</p>
            </div>

            <?php if ($erro): ?>
                <script>document.addEventListener('DOMContentLoaded', () => mostrarPopup("<?php echo addslashes($erro); ?>", "erro"));</script>
            <?php endif; ?>
            <?php if ($sucesso): ?>
                <script>document.addEventListener('DOMContentLoaded', () => mostrarPopup("<?php echo addslashes($sucesso); ?>", "sucesso"));</script>
            <?php endif; ?>

            <div class="cliente-panel">
                <div class="cliente-panel-head"><h2>Moradas guardadas</h2></div>
                <?php if ($addresses): ?>
                    <div class="cliente-address-list">
                        <?php foreach ($addresses as $address): ?>
                            <div class="cliente-address-card <?php echo (int)$address['principal'] === 1 ? 'is-principal' : ''; ?>">
                                <div class="address-card-header">
                                    <div class="address-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                    </div>
                                    <?php if ((int)$address['principal'] === 1): ?>
                                        <span class="badge-principal">Principal</span>
                                    <?php endif; ?>
                                </div>
                                <?php
                                $paises_nomes = ['PT'=>'Portugal','ES'=>'Espanha','FR'=>'França','DE'=>'Alemanha','BE'=>'Bélgica','IT'=>'Itália','NL'=>'Países Baixos','LU'=>'Luxemburgo','CH'=>'Suíça','UK'=>'Reino Unido','US'=>'Estados Unidos','BR'=>'Brasil','BG'=>'Bulgária'];
                                $nome_pais = $paises_nomes[strtoupper($address['pais_regiao'])] ?? htmlspecialchars($address['pais_regiao']);
                                ?>
                                <div class="address-details">
                                    <strong><?php echo htmlspecialchars($address['rua']); ?></strong>
                                    <span><?php echo htmlspecialchars($address['codigo_postal'] . ' ' . $address['localidade']); ?>, <?php echo $nome_pais; ?></span>
                                    <?php if (!empty($address['provincia'])): ?>
                                        <p><?php echo htmlspecialchars($address['provincia']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <form method="post" class="cliente-inline-actions">
                                    <?php echo csrf_input(); ?>
                                    <input type="hidden" name="morada_id" value="<?php echo (int)$address['id']; ?>">

                                    <div class="address-action-group">
                                        <button type="button" class="btn-del-single btn-apagar-confirmado"
                                                data-titulo-confirmacao="Apagar Morada"
                                                data-mensagem-confirmacao="Tem a certeza que pretende apagar esta morada? Esta ação não pode ser revertida."
                                                name="acao" value="apagar"
                                                title="Apagar morada"></button>
                                        <button type="button" class="btn-edit-single"
                                                onclick="editarMorada(<?php echo htmlspecialchars(json_encode([
                                                    'id'            => (int)$address['id'],
                                                    'rua'           => $address['rua'],
                                                    'codigo_postal' => $address['codigo_postal'],
                                                    'localidade'    => $address['localidade'],
                                                    'provincia'     => $address['provincia'] ?? '',
                                                    'pais_regiao'   => $address['pais_regiao'],
                                                    'principal'     => (int)$address['principal'],
                                                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES); ?>)"
                                                title="Editar morada"></button>
                                    </div>

                                    <?php if ((int)$address['principal'] !== 1): ?>
                                        <button type="submit" name="acao" value="principal" class="button btn-set-main">Predefinir</button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="cliente-empty">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        <p>Ainda não tens moradas guardadas.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="cliente-panel" id="painel-form-morada" style="overflow: visible;">
                <div class="cliente-panel-head"><h2 id="form-morada-titulo">Adicionar morada</h2></div>
                <div class="cliente-form-wrap">
                    <form method="post" class="cliente-form-grid" id="form-nova-morada">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" id="mor-acao" name="acao" value="criar">
                        <input type="hidden" id="mor-edit-id" name="morada_id" value="">
                        <div class="cliente-form-field">
                            <label for="mor-pais">País</label>
                            <div class="select-wrapper">
                                <select id="mor-pais" name="pais_regiao" required></select>
                            </div>
                        </div>
                        <label for="mor-rua">
                            <span id="lbl-mor-rua">Rua e número</span>
                            <input type="text" id="mor-rua" name="rua" required placeholder="Rua das Flores, 12">
                        </label>
                        <label for="mor-cp">
                            <span id="lbl-mor-cp">Código Postal</span>
                            <input type="text" id="mor-cp" name="codigo_postal" required placeholder="0000-000">
                        </label>
                        <label for="mor-loc">
                            <span id="lbl-mor-loc">Localidade</span>
                            <input type="text" id="mor-loc" name="localidade" required placeholder="Porto">
                        </label>
                        <div id="bloco-mor-provincia" style="display:none; grid-column: 1 / -1;">
                            <label for="mor-prov">
                                <span id="lbl-mor-prov">Província/Estado</span>
                                <input type="text" id="mor-prov" name="provincia">
                            </label>
                        </div>
                        <label class="cliente-check">
                            <input type="checkbox" id="mor-principal" name="principal" value="1">
                            <span class="cliente-custom-check"></span>
                            <span class="cliente-check-text">Definir como principal</span>
                        </label>
                        <div class="form-morada-actions">
                            <button type="submit" id="btn-submit-morada" class="button">Guardar morada</button>
                            <button type="button" id="btn-cancelar-edicao" class="button voltar-btn" style="display:none;" onclick="cancelarEdicao()">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
</main>

<script>
(function () {
'use strict';

const PORTES_JSON = <?php echo json_encode($portes_por_pais, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

const selectPais   = document.getElementById('mor-pais');
const inputRua     = document.getElementById('mor-rua');
const lblRua       = document.getElementById('lbl-mor-rua');
const inputCP      = document.getElementById('mor-cp');
const lblCP        = document.getElementById('lbl-mor-cp');
const inputLoc     = document.getElementById('mor-loc');
const lblLoc       = document.getElementById('lbl-mor-loc');
const blocoProv    = document.getElementById('bloco-mor-provincia');
const lblProv      = document.getElementById('lbl-mor-prov');
const inputProv    = document.getElementById('mor-prov');

function getNomePais(iso) {
    try {
        return new Intl.DisplayNames(['pt'], { type: 'region' }).of(iso.toUpperCase());
    } catch (e) { return iso.toUpperCase(); }
}

function popularPaises() {
    selectPais.innerHTML = '';
    Object.keys(PORTES_JSON).forEach(iso => {
        const opt = document.createElement('option');
        opt.value = iso;
        opt.textContent = getNomePais(iso);
        selectPais.appendChild(opt);
    });
    selectPais.value = 'PT';
}

const fallbacks = {
    "PT": { street: "Rua e número", streetPh: "Rua das Flores, 12", zip: "Código Postal", zipPh: "0000-000", city: "Localidade", cityPh: "Porto", state: "Província", hasState: false },
    "ES": { street: "Calle y número", streetPh: "Calle Mayor, 1", zip: "Código Postal", zipPh: "28001", city: "Población", cityPh: "Madrid", state: "Provincia", hasState: true, statePh: "Ex: Madrid" },
    "FR": { street: "Rue et numéro", streetPh: "15 rue de la Paix", zip: "Code Postal", zipPh: "75001", city: "Ville", cityPh: "Paris", state: "Région", hasState: false },
    "DE": { street: "Straße und Hausnummer", streetPh: "Musterstraße 1", zip: "Postleitzahl", zipPh: "10115", city: "Stadt", cityPh: "Berlin", state: "Bundesland", hasState: false },
    "BE": { street: "Rue et numéro", streetPh: "Rue de la Loi 16", zip: "Code Postal", zipPh: "1000", city: "Ville / Stad", cityPh: "Bruxelles", state: "Province", hasState: false },
    "IT": { street: "Via e numero civico", streetPh: "Via Roma, 1", zip: "Codice Postale", zipPh: "00100", city: "Città", cityPh: "Roma", state: "Provincia", hasState: false },
    "NL": { street: "Straat en huisnummer", streetPh: "Keizersgracht 1", zip: "Postcode", zipPh: "1011 AB", city: "Stad", cityPh: "Amsterdam", state: "Provincie", hasState: false },
    "LU": { street: "Rue et numéro", streetPh: "Rue de la Poste 1", zip: "Code Postal", zipPh: "1009", city: "Ville", cityPh: "Luxembourg", state: "Canton", hasState: false },
    "CH": { street: "Rue et numéro", streetPh: "Rue du Centre 1", zip: "NPA", zipPh: "1000", city: "Localité", cityPh: "Lausanne", state: "Canton", hasState: false },
    "UK": { street: "Street address", streetPh: "10 Downing Street", zip: "Postcode", zipPh: "SW1A 1AA", city: "Town/City", cityPh: "London", state: "County", hasState: false },
    "US": { street: "Street address", streetPh: "123 Main Street", zip: "ZIP Code", zipPh: "90210", city: "City", cityPh: "Los Angeles", state: "State", hasState: true, statePh: "Ex: California" },
    "BR": { street: "Rua e número", streetPh: "Av. Paulista, 1000", zip: "CEP", zipPh: "00000-000", city: "Cidade", cityPh: "São Paulo", state: "Estado", hasState: true, statePh: "Ex: São Paulo" },
    "BG": { street: "Street address", streetPh: "ul. Vitosha 1", zip: "Postcode", zipPh: "1000", city: "City", cityPh: "Sofia", state: "Region", hasState: false }
};
const generic = { street: "Street address", streetPh: "...", zip: "Código Postal / ZIP", zipPh: "00000", city: "Localidade / City", cityPh: "...", state: "Província / Estado", hasState: false };

function fetchAddressConfig(iso) {
    if (!iso) return;
    const fb = fallbacks[iso.toUpperCase()] || generic;

    lblRua.textContent = fb.street;
    inputRua.placeholder = fb.streetPh;
    lblCP.textContent = fb.zip;
    inputCP.placeholder = fb.zipPh;
    lblLoc.textContent = fb.city;
    inputLoc.placeholder = fb.cityPh;

    if (fb.hasState) {
        blocoProv.style.display = '';
        lblProv.textContent = fb.state;
        inputProv.placeholder = fb.statePh || 'Ex: ...';
    } else {
        blocoProv.style.display = 'none';
    }
}

popularPaises();
fetchAddressConfig('PT');

selectPais.addEventListener('change', () => fetchAddressConfig(selectPais.value));

window.editarMorada = function(data) {
    document.getElementById('mor-acao').value = 'editar';
    document.getElementById('mor-edit-id').value = data.id;
    document.getElementById('form-morada-titulo').textContent = 'Editar morada';
    document.getElementById('btn-submit-morada').textContent = 'Atualizar morada';
    document.getElementById('btn-cancelar-edicao').style.display = '';

    const pais = (data.pais_regiao || 'PT').toUpperCase();
    selectPais.value = pais;
    selectPais.dispatchEvent(new Event('change', { bubbles: true }));

    inputRua.value = data.rua || '';
    inputCP.value  = data.codigo_postal || '';
    inputLoc.value = data.localidade || '';
    inputProv.value = data.provincia || '';
    document.getElementById('mor-principal').checked = !!data.principal;

    document.getElementById('painel-form-morada').scrollIntoView({ behavior: 'smooth', block: 'start' });
};

window.cancelarEdicao = function() {
    document.getElementById('mor-acao').value = 'criar';
    document.getElementById('mor-edit-id').value = '';
    document.getElementById('form-morada-titulo').textContent = 'Adicionar morada';
    document.getElementById('btn-submit-morada').textContent = 'Guardar morada';
    document.getElementById('btn-cancelar-edicao').style.display = 'none';
    document.getElementById('form-nova-morada').reset();
    selectPais.value = 'PT';
    selectPais.dispatchEvent(new Event('change', { bubbles: true }));
};
})();
</script>

<?php include __DIR__ . '/templates/footer.php'; ?>
