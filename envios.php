<?php
require_once __DIR__ . '/config/formatters.php';
$titulo_pagina = 'Portes e Envios';
$descricao_pagina = 'Tarifas de envio da TopTop por país e peso. Entregas rápidas e seguras em Portugal e em toda a Europa — consulta preços e prazos.';
include 'templates/header.php';

// Os portes já são carregados no templates/header.php na variável $portes_js
$portes_data = $portes_js ?? [];

// Função para obter o nome do país de forma dinâmica e no idioma nativo
function getCountryName($iso) {
    $iso = strtoupper($iso);
    
    // Mapeamento de ISO para o idioma principal do país (para garantir tradução nativa)
    $isoToLocale = [
        'CH' => 'de', // Suíça (Alemão)
        'LU' => 'fr', // Luxemburgo (Francês)
        'BE' => 'fr', // Bélgica (Francês)
        'GB' => 'en', // Reino Unido
        'IE' => 'en', // Irlanda
        'AT' => 'de'  // Áustria
    ];
    
    $locale = $isoToLocale[$iso] ?? strtolower($iso);
    
    if (class_exists('IntlDisplayNames')) {
        try {
            $displayNames = new IntlDisplayNames($locale, ['type' => 'region']);
            $name = $displayNames->of($iso);
            if ($name) return $name;
        } catch (Exception $e) {}
    }
    
    // Fallback: se falhar o PHP, o JavaScript no navegador tratará de preencher
    return $iso;
}
// Função para traduzir termos básicos de envio de forma dinâmica
function translateTerm($term, $iso) {
    $iso = strtoupper($iso);
    $translations = [
        'PT' => ['weight' => 'Intervalo de Peso', 'cost' => 'Custo de Envio'],
        'ES' => ['weight' => 'Intervalo de Peso', 'cost' => 'Gastos de Envío'],
        'FR' => ['weight' => 'Tranche de Poids', 'cost' => 'Frais de Port'],
        'DE' => ['weight' => 'Gewichtsbereich', 'cost' => 'Versandkosten'],
        'IT' => ['weight' => 'Fascia di Peso', 'cost' => 'Costi di Spedizione'],
        'GB' => ['weight' => 'Weight Range', 'cost' => 'Shipping Cost'],
        'IE' => ['weight' => 'Weight Range', 'cost' => 'Shipping Cost'],
        'NL' => ['weight' => 'Gewichtsklasse', 'cost' => 'Verzendkosten'],
        'BE' => ['weight' => 'Gewichtsklasse', 'cost' => 'Verzendkosten'], // Ou FR dependendo
        'LU' => ['weight' => 'Tranche de Poids', 'cost' => 'Frais de Port'],
        'CH' => ['weight' => 'Gewichtsbereich', 'cost' => 'Versandkosten'],
        'AT' => ['weight' => 'Gewichtsbereich', 'cost' => 'Versandkosten']
    ];

    $map = $translations[$iso] ?? $translations['PT'];
    return $map[$term] ?? $term;
}

// Função para formatar preço conforme a localidade
function formatPrice($price, $iso) {
    if (class_exists('NumberFormatter')) {
        $locale = strtolower($iso) . '_' . strtoupper($iso);
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        return $formatter->formatCurrency($price, 'EUR');
    }
    return format_money($price, '€', 'suffix');
}
?>

<main class="pagina-info info-premium">
    <div class="pagina-info-header">
        <p class="info-kicker">Entregas</p>
        <h2>Portes e Envios</h2>
        <p>Consulta as nossas tarifas de envio por país e peso. Entregas rápidas e seguras em toda a Europa.</p>
    </div>

    <div class="portes-free-shipping">
        <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 6 9 17l-5-5"></path>
        </svg>
        <p>
            <strong>Portes grátis em Portugal Continental</strong>
            <span>A partir de <?php echo number_format($portes_gratis_minimo, 2, ',', '.'); ?> € em produtos.</span>
        </p>
    </div>

    <div class="portes-container">
        <div class="portes-header">
            <h3>Tabela de Preços</h3>
            <div class="portes-filtro">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="filtroPais" placeholder="Procurar país...">
            </div>
        </div>

        <div id="listaPortes">
            <?php if (empty($portes_data)): ?>
                <div class="portes-vazio">Não foram encontradas tarifas de envio de momento.</div>
            <?php else: ?>
                <?php foreach ($portes_data as $iso => $regras): ?>
                    <div class="accordion-pais" data-pais="<?php echo strtolower(getCountryName($iso)) . ' ' . strtolower($iso); ?>">
                        <button type="button" class="accordion-header">
                            <div class="badge-pais">
                                <span class="iso-code"><?php echo $iso; ?></span>
                                <?php echo getCountryName($iso); ?>
                            </div>
                            <svg class="accordion-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </button>
                        <div class="accordion-content">
                            <div class="portes-tabela-wrapper">
                                <table class="portes-tabela">
                                    <thead>
                                        <tr>
                                            <th><?php echo translateTerm('weight', $iso); ?></th>
                                            <th><?php echo translateTerm('cost', $iso); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($regras as $regra): ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                        $min = $regra['min'] / 1000;
                                                        $max = $regra['max'] / 1000;
                                                        echo $min . "kg - " . $max . "kg";
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="preco-tag"><?php echo formatPrice($regra['preco'], $iso); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>


    <div class="info-grid portes-info-extra">
        <div class="info-card">
            <h3>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                Tempo de Entrega
            </h3>
            <ul>
                <li><strong>Portugal Continental:</strong> 24h a 48h úteis.</li>
                <li><strong>Ilhas e Espanha:</strong> 3 a 5 dias úteis.</li>
                <li><strong>Resto da Europa:</strong> 5 a 10 dias úteis.</li>
            </ul>
        </div>
        <div class="info-card">
            <h3>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                Rastreio
            </h3>
            <p>Assim que a tua encomenda for expedida, receberás um e-mail ou SMS com o código de rastreio para que possas acompanhar a entrega em tempo real.</p>
        </div>
    </div>
</main>

<script src="/public/js/envios.js?v=<?php echo $versao_global; ?>" defer></script>

<?php include 'templates/footer.php'; ?>

