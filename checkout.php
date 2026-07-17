<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/stripe.php';
require_once __DIR__ . '/config/cliente_auth.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$stripe_keys       = getStripeKeys();
$stripe_public_key = htmlspecialchars($stripe_keys['public']);
$cliente_checkout = null;
$cliente_moradas_checkout = [];
if (is_cliente_logged_in()) {
    $cliente_checkout = cliente_atual($conn);
    if ($cliente_checkout) {
        $cliente_moradas_checkout = customer_addresses($conn, (int)$cliente_checkout['id']);
    }
}
$titulo_pagina = 'Finalizar Encomenda';
include 'templates/header.php';
$portes_por_pais = $portes_js ?? [];
?>


<main class="checkout-page">
<div class="ck-wrap">
    <div class="ck-left">
        <div id="passo-1">
            <form id="form-dados" novalidate>
                <div class="ck-sec">
                    <div class="ck-sec-hd"><h2>Contacto</h2></div>
                    <?php if ($cliente_checkout): ?>
                        <div class="ck-account-note">
                            <strong>Campos bloqueados pela tua conta</strong>
                            <span>Os dados guardados não são editáveis aqui.</span>
                            <a href="/minha-conta/dados">Editar conta</a>
                        </div>
                    <?php else: ?>
                        <div class="ck-account-note guest">
                            <strong>Ja tens conta?</strong>
                            <span>Entra para preencher os teus dados automaticamente.</span>
                            <a href="/entrar?next=/checkout">Entrar</a>
                        </div>
                    <?php endif; ?>
                    <div class="ck-f">
                        <label for="email">Email</label>
                        <input class="ck-in" type="email" id="email" required placeholder="exemplo@email.com" autocomplete="email" value="<?php echo htmlspecialchars($cliente_checkout['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo $cliente_checkout ? 'readonly' : ''; ?>>
                    </div>
                    <div class="ck-row">
                        <div class="ck-f">
                            <label for="nome">Nome completo</label>
                            <input class="ck-in" type="text" id="nome" required placeholder="Maria Silva" autocomplete="name" value="<?php echo htmlspecialchars($cliente_checkout['nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo !empty($cliente_checkout['nome']) ? 'readonly' : ''; ?>>
                        </div>
                        <div class="ck-f">
                            <label for="telefone">Telefone</label>
                            <input class="ck-in" type="tel" id="telefone" required placeholder="912345678" autocomplete="tel" value="<?php echo htmlspecialchars($cliente_checkout['telefone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo !empty($cliente_checkout['telefone']) ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    <div class="ck-f">
                        <label for="nif">NIF (Opcional)</label>
                        <input class="ck-in" type="text" id="nif" placeholder="261871923" value="<?php echo htmlspecialchars($cliente_checkout['nif'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo !empty($cliente_checkout['nif']) ? 'readonly' : ''; ?>>
                    </div>
                </div>

                <div class="ck-sec">
                    <div class="ck-sec-hd"><h2>Entrega</h2></div>
                    <input type="hidden" id="metodo_entrega" value="envio">
                    <div class="ck-row" style="gap:10px; margin-bottom:15px;">
                        <div class="ck-card ck-method-card active" data-method="envio">
                            <span class="ck-card-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
                            <div class="ck-card-txt"><strong>Envio</strong><small>Transportadora</small></div>
                        </div>
                        <div class="ck-card ck-method-card" data-method="recolha">
                            <span class="ck-card-ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                            <div class="ck-card-txt"><strong>Recolha</strong><small>Grátis na Loja</small></div>
                        </div>
                    </div>

                    <div id="campos-morada">
                        <?php if ($cliente_checkout && $cliente_moradas_checkout): ?>
                            <div class="ck-f">
                                <label for="morada_guardada">Morada guardada</label>
                                <div class="select-wrapper">
                                    <select id="morada_guardada" class="ck-in select-estilizado">
                                        <?php foreach ($cliente_moradas_checkout as $morada): ?>
                                            <option value="<?php echo (int)$morada['id']; ?>" <?php echo (int)$morada['principal'] === 1 ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(($morada['nome'] ?: 'Morada') . ' - ' . $morada['rua'] . ', ' . $morada['codigo_postal'] . ' ' . $morada['localidade'], ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="ck-f" id="bloco-pais">
                            <label for="pais_regiao">País / Região</label>
                            <div class="select-wrapper">
                                <select id="pais_regiao" class="ck-in select-estilizado" required autocomplete="country"></select>
                            </div>
                        </div>
                        <div class="ck-f">
                            <label for="rua" id="lbl-rua">Rua e número</label>
                            <input class="ck-in" type="text" id="rua" required placeholder="Rua das Flores, 12" autocomplete="address-line1">
                        </div>
                        <div id="bloco-provincia" class="ck-f" style="display:none;">
                            <label for="provincia">Província / Estado</label>
                            <input class="ck-in" type="text" id="provincia" placeholder="Ex: Pontevedra" autocomplete="address-level1">
                        </div>
                        <div class="ck-row">
                            <div class="ck-f">
                                <label for="codigo_postal">Código postal</label>
                                <input class="ck-in" type="text" id="codigo_postal" required placeholder="0000-000" autocomplete="postal-code">
                            </div>
                            <div class="ck-f">
                                <label for="localidade">Localidade</label>
                                <input class="ck-in" type="text" id="localidade" required placeholder="Porto" autocomplete="address-level2">
                            </div>
                        </div>
                    </div>
                    <div id="info-recolha" style="display:none; background:#f9f9f9; padding:15px; border-radius:7px; font-size:.85rem; border:1px solid #eee;">
                        <strong>Ponto de Recolha:</strong><br>Edifício Chafariz, Rua dos Fontenários, Lourosa, Portugal.
                    </div>
                </div>

                <button type="button" id="btn-continuar" class="ck-btn">Continuar para pagamento →</button>
                <div class="ck-back-row"><a href="carrinho.php" class="ck-back ck-back-full">← Voltar ao carrinho</a></div>
            </form>
        </div>

        <div id="passo-2" style="display:none;">
            <button type="button" id="btn-voltar" class="ck-back" style="margin-bottom:20px;">← Editar dados</button>
            <div class="ck-review" id="review-content"></div>
            <div id="stripe-container">
                <div class="ck-pay-hd"><h3>Pagamento Seguro</h3></div>
                <div id="payment-element"></div>
                <div id="payment-errors" style="display:none; background:#fff1f1; border:1px solid #fecaca; color:#991b1b; padding:12px; border-radius:7px; font-size:.85rem; margin-top:15px; line-height:1.4;"></div>
                <button type="button" id="btn-pagar" class="ck-btn" style="margin-top:18px;">Pagar agora</button>
            </div>
        </div>
    </div>

    <div class="ck-right">
        <p style="font-size:.7rem; font-weight:700; color:#aaa; text-transform:uppercase; margin-bottom:15px;">Sumário</p>
        <div id="sum-itens"></div>
        <div class="ck-totals">
            <div class="ck-tr"><span>Subtotal</span><span id="sum-sub">€0,00</span></div>
            <div class="ck-tr"><span>Portes</span><span id="sum-portes">€0,00</span></div>
            <div class="ck-tr final"><span>Total</span><span id="sum-total">€0,00</span></div>
        </div>
    </div>
</div>
</main>

<div id="ck-loading"><div class="ck-spin" style="width:30px; height:30px;"></div><p>A processar...</p></div>

<script src="https://js.stripe.com/v3/"></script>
<script src="/public/js/utils.js"></script>
<script>
(function(){
'use strict';

const STRIPE_PK = "<?php echo $stripe_public_key; ?>";
const CSRF_TOKEN = "<?php echo $_SESSION['csrf_token']; ?>";
const PORTES_JSON = <?php echo json_encode($portes_por_pais); ?>;
const PORTES_GRATIS_CONFIG = window.LOJA_CONFIG_PORTES_GRATIS || {};
const CLIENTE_LOGADO = <?php echo $cliente_checkout ? 'true' : 'false'; ?>;
const CLIENTE_MORADAS = <?php echo json_encode($cliente_moradas_checkout, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

const stripe = Stripe(STRIPE_PK);
let stripeEl = null;
let paymentElement = null;
let orderId = null, orderToken = null;
let subtotal = 0, totalPeso = 0, portesAtual = 0;
let passoAtual = 1; // 1: Dados/Morada, 2: Pagamento
let carrinho = JSON.parse(localStorage.getItem('carrinho') || '[]');

function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function isPortugalContinental(iso, codigoPostal) {
    if (String(iso || '').toUpperCase() !== String(PORTES_GRATIS_CONFIG.pais || 'PT').toUpperCase()) {
        return false;
    }

    const match = String(codigoPostal || '').trim().match(/^(\d{4})-\d{3}$/);
    if (!match) return false;

    const prefixo = Number.parseInt(match[1], 10);
    return prefixo >= Number(PORTES_GRATIS_CONFIG.cp_min || 1000)
        && prefixo <= Number(PORTES_GRATIS_CONFIG.cp_max || 8999);
}

function temPortesGratis(valor, iso, codigoPostal) {
    const minimo = Number(PORTES_GRATIS_CONFIG.valor_minimo || 0);
    return PORTES_GRATIS_CONFIG.ativo === true
        && minimo > 0
        && valor >= minimo
        && isPortugalContinental(iso, codigoPostal);
}

function hidePaymentError() {
    const errDiv = document.getElementById('payment-errors');
    if (errDiv) {
        errDiv.textContent = '';
        errDiv.style.display = 'none';
    }
}

function setEmailAccountWarning(show) {
    let warning = document.getElementById('ck-email-account-warning');
    if (!warning) {
        warning = document.createElement('div');
        warning.id = 'ck-email-account-warning';
        warning.className = 'ck-account-warning';
        warning.innerHTML = 'Este email ja tem conta. <a href="/entrar?next=/checkout">Inicia sessao para continuar</a>.';
        document.getElementById('email').closest('.ck-f').appendChild(warning);
    }
    warning.style.display = show ? 'block' : 'none';
}

async function lerJsonSeguro(res) {
    const texto = await res.text();
    try {
        return texto ? JSON.parse(texto) : {};
    } catch (e) {
        if (res.status === 403) {
            return {
                sucesso: false,
                mensagem: 'Nao foi possivel validar o pedido. Recarregue a pagina e tente novamente.'
            };
        }
        return {
            sucesso: false,
            mensagem: 'O servidor devolveu uma resposta inesperada. Tente novamente.'
        };
    }
}

async function emailTemConta(email) {
    if (CLIENTE_LOGADO || !email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return false;
    const res = await fetch('ajax_check_email_checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email })
    });
    const data = await lerJsonSeguro(res);
    if (!res.ok && data.mensagem) throw new Error(data.mensagem);
    return !!(data.sucesso && data.existe);
}

// Sincronizar se o carrinho for alterado noutro lado
window.addEventListener('cartUpdated', () => {
    const novoCarrinho = JSON.parse(localStorage.getItem('carrinho') || '[]');
    
    // Se o carrinho ficou vazio, saímos do checkout imediatamente (mesmo no Passo 2)
    if (novoCarrinho.length === 0) {
        window.location.href = 'carrinho.php';
        return;
    }

    if (passoAtual === 2) return;

    carrinho = novoCarrinho;
    renderSum();
});

// Refs
const selectPais = document.getElementById('pais_regiao');
const inputCP = document.getElementById('codigo_postal');
const labelCP = document.querySelector('label[for="codigo_postal"]');
const labelRua = document.getElementById('lbl-rua');
const labelLocalidade = document.querySelector('label[for="localidade"]');
const blocoProv = document.getElementById('bloco-provincia');
const labelProv = document.querySelector('label[for="provincia"]');
const inputProv = document.getElementById('provincia');
const metodoEnt = document.getElementById('metodo_entrega');
const loading = document.getElementById('ck-loading');
const camposMorada = document.getElementById('campos-morada');
const selectMoradaGuardada = document.getElementById('morada_guardada');
const emailInput = document.getElementById('email');

function getNomePais(iso) {
    try {
        const regionNames = new Intl.DisplayNames(['pt'], { type: 'region' });
        return regionNames.of(iso.toUpperCase());
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
    const optOutro = document.createElement('option');
    optOutro.value = "OUTRO";
    optOutro.textContent = "Outro País...";
    selectPais.appendChild(optOutro);
}

function aplicarMoradaGuardada(morada) {
    if (!morada) return;
    const pais = String(morada.pais_regiao || morada.pais || 'PT').toUpperCase();
    if ([...selectPais.options].some(opt => opt.value === pais)) {
        selectPais.value = pais;
        selectPais.dispatchEvent(new Event('change', { bubbles: true }));
    }
    document.getElementById('rua').value = morada.rua || '';
    document.getElementById('codigo_postal').value = morada.codigo_postal || '';
    document.getElementById('localidade').value = morada.localidade || '';
    inputProv.value = morada.provincia || '';
    updateValidacao();
}

function fetchAddressConfig(iso) {
    if (iso === "OUTRO" || !iso) return;

    const fallbacks = {
        "PT": { street: "Rua e número", streetPh: "Rua das Flores, 12", zip: "Código Postal", zipPh: "0000-000", city: "Localidade", cityPh: "Porto", state: "Província", hasState: false, regex: "^\\d{4}-\\d{3}$" },
        "ES": { street: "Calle y número", streetPh: "Calle Mayor, 1", zip: "Código Postal", zipPh: "28001", city: "Población", cityPh: "Madrid", state: "Provincia", hasState: true, statePh: "Ex: Madrid, Sevilha...", regex: "^\\d{5}$" },
        "FR": { street: "Rue et numéro", streetPh: "15 rue de la Paix", zip: "Code Postal", zipPh: "75001", city: "Ville", cityPh: "Paris", state: "Région", hasState: false, regex: "^\\d{5}$" },
        "DE": { street: "Straße und Hausnummer", streetPh: "Musterstraße 1", zip: "Postleitzahl", zipPh: "10115", city: "Stadt", cityPh: "Berlin", state: "Bundesland", hasState: false, regex: "^\\d{5}$" },
        "BE": { street: "Rue et numéro", streetPh: "Rue de la Loi 16", zip: "Code Postal", zipPh: "1000", city: "Ville / Stad", cityPh: "Bruxelles", state: "Province", hasState: false, regex: "^\\d{4}$" },
        "IT": { street: "Via e numero civico", streetPh: "Via Roma, 1", zip: "Codice Postale", zipPh: "00100", city: "Città", cityPh: "Roma", state: "Provincia", hasState: false, regex: "^\\d{5}$" },
        "NL": { street: "Straat en huisnummer", streetPh: "Keizersgracht 1", zip: "Postcode", zipPh: "1011 AB", city: "Stad", cityPh: "Amsterdam", state: "Provincie", hasState: false, regex: "^\\d{4}\\s?[A-Z]{2}$" },
        "LU": { street: "Rue et numéro", streetPh: "Rue de la Poste 1", zip: "Code Postal", zipPh: "1009", city: "Ville", cityPh: "Luxembourg", state: "Canton", hasState: false, regex: "^\\d{4}$" },
        "CH": { street: "Rue et numéro", streetPh: "Rue du Centre 1", zip: "NPA", zipPh: "1000", city: "Localité", cityPh: "Lausanne", state: "Canton", hasState: false, regex: "^\\d{4}$" },
        "UK": { street: "Street address", streetPh: "10 Downing Street", zip: "Postcode", zipPh: "SW1A 1AA", city: "Town/City", cityPh: "London", state: "County", hasState: false, regex: "^[A-Z]{1,2}\\d[A-Z\\d]?\\s?\\d[A-Z]{2}$" },
        "US": { street: "Street address", streetPh: "123 Main Street", zip: "ZIP Code", zipPh: "90210", city: "City", cityPh: "Los Angeles", state: "State", hasState: true, statePh: "Ex: California", regex: "^\\d{5}(-\\d{4})?$" },
        "BR": { street: "Rua e número", streetPh: "Av. Paulista, 1000", zip: "CEP", zipPh: "00000-000", city: "Cidade", cityPh: "São Paulo", state: "Estado", hasState: true, statePh: "Ex: São Paulo", regex: "^\\d{5}-\\d{3}$" },
        "BG": { street: "Street address", streetPh: "ul. Vitosha 1", zip: "Postcode", zipPh: "1000", city: "City", cityPh: "Sofia", state: "Region", hasState: false, regex: "^\\d{4}$" }
    };
    const generic = { street: "Street address", streetPh: "...", zip: "Código Postal / ZIP", zipPh: "00000", city: "Localidade / City", cityPh: "...", state: "Província / Estado", hasState: false };
    const fb = fallbacks[iso.toUpperCase()] || generic;

    if (labelRua) { labelRua.textContent = fb.street; document.getElementById('rua').placeholder = fb.streetPh; }
    labelCP.textContent = fb.zip;
    inputCP.placeholder = fb.zipPh;
    labelLocalidade.textContent = fb.city;
    document.getElementById('localidade').placeholder = fb.cityPh;

    if (fb.hasState) {
        blocoProv.style.display = 'block';
        labelProv.textContent = fb.state;
        inputProv.placeholder = fb.statePh || "Ex: ...";
        inputProv.required = metodoEnt.value === 'envio';
    } else {
        blocoProv.style.display = 'none';
        inputProv.required = false;
    }
    if (fb.regex) inputCP.dataset.regex = fb.regex;
}

function updateValidacao() {
    const iso = selectPais.value;
    let avisoZona = document.getElementById('aviso-zona');
    if (!avisoZona) {
        avisoZona = document.createElement('div');
        avisoZona.id = 'aviso-zona';
        avisoZona.style.cssText = 'display:none; background:#fff1f1; border:1px solid #fecaca; color:#991b1b; padding:12px; border-radius:7px; font-size:.85rem; margin-bottom:15px; line-height:1.4;';
        avisoZona.innerHTML = 'Só fazemos encomendas diretas para os países listados. Se é de fora, por favor contacte-nos através de <strong>toptopclothingstore@gmail.com</strong> para processarmos o seu pedido manualmente.';
        camposMorada.append(avisoZona);
    }
    
    if (iso === "OUTRO") {
        avisoZona.style.display = 'block';
        // Ocultar tudo EXCETO o aviso e o bloco do país
        Array.from(camposMorada.children).forEach(child => {
            if (child.id === 'aviso-zona' || child.id === 'bloco-pais') child.style.display = 'block';
            else child.style.display = 'none';
        });
        document.getElementById('btn-continuar').disabled = true;
        document.getElementById('btn-continuar').style.opacity = '0.5';
    } else {
        avisoZona.style.display = 'none';
        Array.from(camposMorada.children).forEach(child => {
            if (child.id === 'aviso-zona') child.style.display = 'none';
            else if (child.id === 'bloco-provincia') { 
                // Não tocar, deixamos o fetchAddressConfig gerir
            } else {
                child.style.display = 'block';
            }
        });
        document.getElementById('btn-continuar').disabled = false;
        document.getElementById('btn-continuar').style.opacity = '1';
        fetchAddressConfig(iso);
    }
    atualizarTotais();
}

function renderSum() {
    subtotal = 0; totalPeso = 0;
    let h = '';
    carrinho.forEach((it, idx) => {
        const itemTot = parseFloat(it.preco) * it.quantidade;
        subtotal += itemTot;
        totalPeso += (parseInt(it.peso_gramas) || 0) * it.quantidade;
        
        const selecoesHTML = it.selecoes
            ? Object.entries(it.selecoes).map(([nome, valor]) => `<p class="side-cart-item-atributo">${escapeHTML(nome)}: <strong>${escapeHTML(valor)}</strong></p>`).join('')
            : '';

        let acoesHTML = '';
        if (passoAtual === 1) {
            const isMin = it.quantidade <= 1;
            const isMax = it.stock && it.quantidade >= it.stock;
            acoesHTML = `
                <button type="button" class="btn-close-unified remover-item-btn" onclick="window.updateQtyCheckout(${idx}, -999)" title="Remover item">&times;</button>
                <div class="cart-qty-editor">
                    <button class="qty-btn" onclick="window.updateQtyCheckout(${idx}, -1)" ${isMin ? 'disabled' : ''}>-</button>
                    <span class="cart-qty-val">${it.quantidade}</span>
                    <button class="qty-btn" onclick="window.updateQtyCheckout(${idx}, 1)" ${isMax ? 'disabled' : ''}>+</button>
                </div>`;
        } else {
            acoesHTML = `<div style="height: 32px;"></div><span style="font-size: .82rem; color: #64748b; font-weight: 600; margin-bottom: 4px;">Qtd: ${it.quantidade}</span>`;
        }

        h += `<div class="ck-prod-item">
                <img src="${escapeHTML(it.foto)}" alt="${escapeHTML(it.nome)}">
                <div class="ck-prod-info">
                    <strong class="ck-prod-title">${escapeHTML(it.nome)}</strong>
                    ${selecoesHTML}
                    <p class="ck-prod-price">€${number_format(itemTot, 2, ',', '.')}</p>
                </div>
                <div class="ck-prod-acoes">
                    ${acoesHTML}
                </div>
              </div>`;
    });
    document.getElementById('sum-itens').innerHTML = h;
    document.getElementById('sum-sub').textContent = '€' + number_format(subtotal, 2, ',', '.');
    atualizarTotais();
}

window.updateQtyCheckout = function(idx, delta) {
    if (delta === -1 && carrinho[idx].quantidade <= 1) return;
    if (delta === 1 && carrinho[idx].stock && carrinho[idx].quantidade >= carrinho[idx].stock) return;
    
    if (delta === -999) {
        carrinho.splice(idx, 1);
    } else {
        carrinho[idx].quantidade += delta;
        if (carrinho[idx].quantidade <= 0) carrinho[idx].quantidade = 1;
    }
    
    localStorage.setItem('carrinho', JSON.stringify(carrinho));
    window.dispatchEvent(new Event('cartUpdated'));
    
    if (carrinho.length === 0) {
        window.location.href = 'carrinho.php';
        return;
    }
    renderSum();
};

function atualizarTotais() {
    portesAtual = 0;
    let portesGratisAplicados = false;
    if (metodoEnt.value === 'envio') {
        const iso = selectPais.value;
        portesGratisAplicados = temPortesGratis(subtotal, iso, inputCP.value);

        if (!portesGratisAplicados) {
            const regras = [...(PORTES_JSON[iso] || PORTES_JSON['PT'] || [])];
            regras.sort((a, b) => a.min - b.min);
            for (let r of regras) {
                if (totalPeso >= r.min && (totalPeso < r.max || Number(r.max) === 0)) {
                    portesAtual = parseFloat(r.preco);
                    break;
                }
            }
            if (portesAtual === 0 && regras.length > 0) {
                const last = regras[regras.length - 1];
                if (totalPeso >= last.max && last.max > 0) portesAtual = parseFloat(last.preco);
            }
        }
    }
    const finalTot = subtotal + portesAtual;
    const portesElemento = document.getElementById('sum-portes');
    portesElemento.textContent = portesGratisAplicados ? 'Grátis' : '€' + number_format(portesAtual, 2, ',', '.');
    portesElemento.classList.toggle('is-free', portesGratisAplicados);
    document.getElementById('sum-total').textContent = '€' + number_format(finalTot, 2, ',', '.');
    if (document.getElementById('btn-pagar')) {
        document.getElementById('btn-pagar').textContent = 'Pagar €' + number_format(finalTot, 2, ',', '.');
    }
}

async function libertarReservaCheckout() {
    if (!orderId || !orderToken) return true;

    const res = await fetch('ajax_libertar_reserva_checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            csrf_token: CSRF_TOKEN,
            order_id: orderId,
            order_token: orderToken
        })
    });

    const data = await res.json();
    if (!data.sucesso) {
        throw new Error(data.mensagem || 'Não foi possível libertar a reserva de stock.');
    }

    if (paymentElement) {
        paymentElement.unmount();
        paymentElement = null;
    }

    document.getElementById('payment-element').innerHTML = '';
    hidePaymentError();
    stripeEl = null;
    orderId = null;
    orderToken = null;
    return true;
}

// Handlers
document.querySelectorAll('.ck-method-card').forEach(card => {
    card.addEventListener('click', () => {
        document.querySelectorAll('.ck-method-card').forEach(c => c.classList.remove('active'));
        card.classList.add('active');
        metodoEnt.value = card.dataset.method;
        const isRecolha = metodoEnt.value === 'recolha';
        
        document.getElementById('campos-morada').style.display = isRecolha ? 'none' : 'block';
        document.getElementById('info-recolha').style.display = isRecolha ? 'block' : 'none';
        
        // Desativar/Ativar obrigatoriedade dos campos ocultos
        document.querySelectorAll('#campos-morada input, #campos-morada select').forEach(el => {
            if (isRecolha) el.required = false;
            else if (el.id !== 'nif' && el.id !== 'provincia') el.required = true;
        });

        atualizarTotais();
    });
});

selectPais.addEventListener('change', updateValidacao);
inputCP.addEventListener('input', atualizarTotais);
if (emailInput && !CLIENTE_LOGADO) {
    let emailCheckTimer;
    emailInput.addEventListener('input', () => {
        clearTimeout(emailCheckTimer);
        setEmailAccountWarning(false);
        emailCheckTimer = setTimeout(async () => {
            try {
                setEmailAccountWarning(await emailTemConta(emailInput.value.trim()));
            } catch (e) {
                setEmailAccountWarning(false);
            }
        }, 450);
    });
}
if (selectMoradaGuardada) {
    selectMoradaGuardada.addEventListener('change', () => {
        const morada = CLIENTE_MORADAS.find(item => String(item.id) === selectMoradaGuardada.value);
        aplicarMoradaGuardada(morada);
    });
}

document.getElementById('btn-continuar').addEventListener('click', async () => {
    const btnContinuar = document.getElementById('btn-continuar');
    const form = document.getElementById('form-dados');
    if (!form.checkValidity()) { form.reportValidity(); return; }

    if (metodoEnt.value === 'envio' && inputCP.dataset.regex) {
        if (!new RegExp(inputCP.dataset.regex, 'i').test(inputCP.value)) {
            alert("Formato de código postal inválido para este país.");
            return;
        }
    }

    const d = {
        nome: document.getElementById('nome').value,
        email: document.getElementById('email').value,
        telefone: document.getElementById('telefone').value,
        cliente_nif: document.getElementById('nif').value,
        rua: document.getElementById('rua').value,
        codigo_postal: inputCP.value,
        localidade: document.getElementById('localidade').value,
        pais_regiao: selectPais.value,
        provincia: inputProv.value,
        atualizar_dados_cliente: document.getElementById('atualizar_dados_cliente')?.checked ? 1 : 0
    };

    localStorage.setItem('checkout_cliente_dados', JSON.stringify({
        pais_regiao: d.pais_regiao,
        codigo_postal: d.codigo_postal
    }));

    if (!CLIENTE_LOGADO) {
        try {
            if (await emailTemConta(d.email.trim())) {
                setEmailAccountWarning(true);
                mostrarPopup('Este email ja tem conta. Inicie sessao para continuar o checkout.', 'erro');
                window.location.href = '/entrar?next=/checkout';
                return;
            }
        } catch (e) {
            mostrarPopup(e.message || 'Nao foi possivel validar o email. Tente novamente.', 'erro');
            return;
        }
    }

    const _paisNome = (() => { try { return new Intl.DisplayNames(['pt'], { type: 'region' }).of(selectPais.value); } catch(e) { return selectPais.value; } })();
    const moradaFinal = (metodoEnt.value === 'recolha') ? 'Recolha na Loja'
        : [d.rua, d.provincia || null, `${d.codigo_postal} ${d.localidade}`.trim(), _paisNome].filter(Boolean).join('\n');

    loading.style.display = 'flex';
    btnContinuar.disabled = true;

    // Validação extra para números de Portugal (essencial para MB WAY)
    if (selectPais.value === 'PT' && !/^[92]\d{8}$/.test(d.telefone)) {
        loading.style.display = 'none';
        btnContinuar.disabled = false;
        mostrarPopup("Por favor, insira um número de telefone válido (9 dígitos começados por 9 ou 2).", "erro");
        return;
    }

    try {
        await libertarReservaCheckout();

        const res = await fetch('ajax_create_payment_intent.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: CSRF_TOKEN, cliente: Object.assign({}, d, { morada: moradaFinal }), carrinho: carrinho, metodo_entrega: metodoEnt.value })
        });
        const result = await lerJsonSeguro(res);
        if (!result.sucesso) {
            if (result.requires_login && result.login_url) {
                setEmailAccountWarning(true);
                mostrarPopup(result.mensagem || 'Inicie sessao para continuar.', 'erro');
                window.location.href = result.login_url;
                return;
            }
            throw new Error(result.mensagem || 'Nao foi possivel iniciar o checkout.');
        }

        orderId = result.order_id; orderToken = result.order_token;
        stripeEl = stripe.elements({ 
            clientSecret: result.client_secret,
            defaultValues: {
                billingDetails: {
                    name: d.nome,
                    email: d.email,
                    phone: d.telefone,
                    address: {
                        line1: d.rua,
                        city: d.localidade,
                        postalCode: d.codigo_postal,
                        country: d.pais_regiao,
                        state: d.provincia
                    }
                }
            }
        });
        paymentElement = stripeEl.create('payment');
        paymentElement.mount('#payment-element');

        paymentElement.on('change', () => {
            hidePaymentError();
        });

        document.getElementById('review-content').innerHTML = `
            <div class="ck-review-row"><div class="ck-review-label">Contacto</div><div class="ck-review-data">${d.email}</div></div>
            <div class="ck-review-row"><div class="ck-review-label">Entrega</div><div class="ck-review-data">${moradaFinal.replace(/\n/g, ', ')}</div></div>
        `;
        document.getElementById('passo-1').style.display = 'none';
        document.getElementById('passo-2').style.display = 'block';
        passoAtual = 2;
        renderSum();
        window.scrollTo(0,0);
    } catch (e) {
        mostrarPopup(e.message, 'erro');
    } finally {
        loading.style.display = 'none';
        btnContinuar.disabled = false;
    }
});

function translateStripeError(error) {
    if (!error) return "Ocorreu um erro inesperado no pagamento.";
    
    // Mapeamento por código de erro (mais fiável)
    const codes = {
        'parameter_invalid_empty': "Por favor, preencha todos os campos obrigatórios.",
        'parameter_missing': "Faltam dados obrigatórios para processar o pagamento.",
        'card_declined': "O seu cartão foi recusado. Verifique os dados ou tente outro método.",
        'expired_card': "O seu cartão expirou.",
        'incorrect_cvc': "O código de segurança (CVC) está incorreto.",
        'incorrect_number': "O número do cartão está incorreto.",
        'processing_error': "Ocorreu um erro ao processar o cartão. Tente novamente.",
        'payment_intent_payment_attempt_failed': "A tentativa de pagamento falhou. Verifique os seus dados."
    };

    if (error.code && codes[error.code]) return codes[error.code];

    // Mapeamento por mensagem (fallback para erros específicos como o do MB WAY)
    const msg = error.message || "";
    
    if (msg.includes('payment_method_data[billing_details][phone]')) {
        return "O número de telefone é obrigatório para pagamentos via MB WAY.";
    }
    
    if (msg.includes('The phone number provided is invalid')) {
        return "O número de telefone fornecido é inválido.";
    }

    // Traduções genéricas de frases comuns
    if (msg.includes('Your card was declined')) return "O seu cartão foi recusado.";
    if (msg.includes('Your card has expired')) return "O seu cartão expirou.";
    if (msg.includes('incomplete_number')) return "O número do cartão está incompleto.";
    if (msg.includes('incomplete_cvc')) return "O código CVC está incompleto.";
    if (msg.includes('incomplete_expiry')) return "A data de validade está incompleta.";

    return msg; // Fallback para a mensagem original se não houver tradução
}

document.getElementById('btn-voltar').addEventListener('click', async () => {
    loading.style.display = 'flex';
    try {
        await libertarReservaCheckout();
        hidePaymentError();
        document.getElementById('passo-1').style.display = 'block';
        document.getElementById('passo-2').style.display = 'none';
        passoAtual = 1;
        renderSum();
    } catch (e) {
        mostrarPopup(e.message, 'erro');
    } finally {
        loading.style.display = 'none';
    }
});

document.getElementById('btn-pagar').addEventListener('click', async () => {
    const btn = document.getElementById('btn-pagar');
    btn.disabled = true;
    btn.innerHTML = '<span class="ck-spin"></span> A processar...';
    const returnUrl = `${window.location.origin}/sucesso.php?id=${orderId}&token=${orderToken}`;
    const result = await stripe.confirmPayment({ elements: stripeEl, confirmParams: { return_url: returnUrl }, redirect: 'if_required' });
    if (result.error) {
        const friendlyMsg = translateStripeError(result.error);
        const errDiv = document.getElementById('payment-errors');
        errDiv.textContent = friendlyMsg;
        errDiv.style.display = 'block';
        
        mostrarPopup(friendlyMsg, 'erro');
        
        btn.disabled = false;
        btn.textContent = 'Pagar agora';
    } else {
        localStorage.removeItem('carrinho');
        const pi = result.paymentIntent;
        window.location.href = `${returnUrl}&payment_intent=${pi.id}&payment_intent_client_secret=${encodeURIComponent(pi.client_secret)}&redirect_status=${pi.status}`;
    }
});

// Botão Voltar Inteligente
const backBtn = document.querySelector('.ck-back');
if (backBtn && document.referrer) {
    try {
        const ref = document.referrer;
        const refUrl = new URL(ref);
        if (refUrl.origin === window.location.origin && !ref.includes('checkout.php')) {
            backBtn.href = ref;
            if (ref.includes('carrinho')) backBtn.textContent = '← Voltar ao carrinho';
            else if (ref.includes('produto')) backBtn.textContent = '← Voltar ao produto';
            else backBtn.textContent = '← Voltar';
        }
    } catch(e) {}
}

popularPaises();
if (selectMoradaGuardada && CLIENTE_MORADAS.length) {
    const selectedAddress = CLIENTE_MORADAS.find(item => String(item.id) === selectMoradaGuardada.value) || CLIENTE_MORADAS[0];
    aplicarMoradaGuardada(selectedAddress);
} else {
    updateValidacao();
}
renderSum();

})();
</script>

<?php include 'templates/footer.php'; ?>
