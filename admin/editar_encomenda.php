<?php
require_once __DIR__ . '/../config/session.php';
include '../config/database.php';

// Segurança: Apenas admins logados podem aceder
if (!isset($_SESSION['admin_logado']) || !$_SESSION['admin_logado']) {
    header("Location: /entrar"); exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$encomenda_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$encomenda_id) {
    header("Location: encomendas.php"); exit;
}

$stmt_enc = $conn->prepare("SELECT * FROM encomendas WHERE id = ?");
$stmt_enc->bind_param("i", $encomenda_id);
$stmt_enc->execute();
$encomenda = $stmt_enc->get_result()->fetch_assoc();
$stmt_enc->close();

if (!$encomenda || !in_array($encomenda['estado'], ['pendente', 'a aguardar pagamento'])) {
    $_SESSION['flash_message'] = ['tipo' => 'erro', 'texto' => 'Esta encomenda não pode ser editada.'];
    header("Location: detalhes_encomenda.php?id=" . $encomenda_id);
    exit;
}

$stmt_itens = $conn->prepare(
    "SELECT ei.*, p.id as produto_id, pv.quantidade + ei.quantidade as stock_disponivel, pv.preco as preco_variacao, p.preco as preco_base, p.preco_promocional as promo_base
     FROM encomenda_itens ei
     JOIN produtos p ON ei.produto_id = p.id
     JOIN produto_variacoes pv ON ei.variacao_id = pv.id
     WHERE ei.encomenda_id = ?"
);
$stmt_itens->bind_param("i", $encomenda_id);
$stmt_itens->execute();
$itens_atuais_result = $stmt_itens->get_result()->fetch_all(MYSQLI_ASSOC);

$itens_atuais_js = [];
foreach ($itens_atuais_result as $item) {
    $preco_unitario = !empty($item['preco_variacao']) ? $item['preco_variacao'] : (!empty($item['promo_base']) ? $item['promo_base'] : $item['preco_base']);
    $itens_atuais_js[] = [
        'produto_id' => $item['produto_id'],
        'variacao_id' => $item['variacao_id'],
        'nome' => $item['nome_produto'],
        'selecoes' => json_decode($item['selecoes_atributos'], true),
        'quantidade' => $item['quantidade'],
        'stock_max' => $item['stock_disponivel'],
        'preco_unitario' => $preco_unitario
    ];
}

include '../templates/header.php';
?>
<main class="admin-main-content">
    <div class="admin-page-header">
        <a href="detalhes_encomenda.php?id=<?php echo $encomenda_id; ?>" class="btn-back-arrow" title="Voltar à Encomenda">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h2>Editar Itens da Encomenda #<?php echo $encomenda_id; ?></h2>
    </div>

    <form id="form-editar-encomenda" class="admin-form-container" style="max-width: 800px;">
        <div class="form-card">
            <div class="form-card-header"><h3>Produtos da Encomenda</h3></div>
            <div class="form-card-body">
                <div class="form-group">
                    <label for="pesquisa-produto">Adicionar Novo Produto</label>
                    <div class="search-wrapper">
                        <input type="text" id="pesquisa-produto" placeholder="Comece a escrever o nome ou ref...">
                        <div id="search-results-container"></div>
                    </div>
                </div>
                <div id="itens-encomenda-container" class="itens-encomenda-preview">
                    </div>
                <div id="total-encomenda-preview" class="total-preview">
                    <strong>Subtotal:</strong> <span>€0.00</span>
                </div>
            </div>
        </div>

        <div class="form-actions" style="justify-content: flex-end;">
            <button type="submit" class="button add-btn">Guardar Alterações na Encomenda</button>
        </div>
    </form>
</main>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // A lógica de pesquisa e gestão de itens é muito semelhante à de `criar_encomenda.php`
    // A principal diferença é que começamos com os itens já existentes.
    let itensEncomenda = <?php echo json_encode($itens_atuais_js); ?>;
    
    // O resto do JavaScript pode ser copiado/adaptado da página `criar_encomenda.php`
    // com a seguinte alteração na submissão:
    const form = document.getElementById('form-editar-encomenda');
    
    // (Cole aqui o JavaScript de `criar_encomenda.php` desde a linha `const pesquisaInput = ...` até ao final)
    // ...
    // E substitua o listener de 'submit' pelo seguinte:
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');

        const dados = {
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>',
            encomenda_id: <?php echo $encomenda_id; ?>,
            itens: itensEncomenda
        };
        
        btn.disabled = true;
        btn.textContent = 'A guardar...';

        try {
            const res = await fetch('ajax_atualizar_encomenda.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dados)
            });
            const result = await res.json();
            if (result.sucesso) {
                window.location.href = result.redirect_url;
            } else {
                throw new Error(result.mensagem);
            }
        } catch (error) {
            mostrarPopup(error.message, 'erro');
            btn.disabled = false;
            btn.textContent = 'Guardar Alterações na Encomenda';
        }
    });

    renderizarItens(); // Renderiza os itens iniciais
});
</script>

<?php include '../templates/footer.php'; ?>
