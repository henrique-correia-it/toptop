<?php
// Verificação de sessão ANTES de qualquer output HTML
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/formatters.php';
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: /entrar");
    exit;
}

include '../templates/header.php';
include '../config/database.php';
require_once '../config/interface_labels.php';

// Variável de controlo para superadmin e dev
$isSuperAdmin = isset($_SESSION['admin_role']) && in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor']);
$isDev        = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'desenvolvedor';

// --- QUERIES PARA ESTATÍSTICAS E ATIVIDADE RECENTE ---
$res_prod = $conn->query("SELECT COUNT(id) FROM produtos WHERE ativo = 1");
if (!$res_prod) { log_sql("Dashboard: falha em COUNT(produtos): " . $conn->error, 'admin.php'); }
$total_produtos = $res_prod ? $res_prod->fetch_row()[0] : 0;

$res_enc_count = $conn->query("SELECT COUNT(id) FROM encomendas WHERE estado IN ('pago', 'em processamento')");
if (!$res_enc_count) { log_sql("Dashboard: falha em COUNT(encomendas): " . $conn->error, 'admin.php'); }
$encomendas_nao_enviadas_count = $res_enc_count ? $res_enc_count->fetch_row()[0] : 0;

$res_msg_count = $conn->query("SELECT COUNT(id) FROM contactos WHERE respondida = 0");
if (!$res_msg_count) { log_sql("Dashboard: falha em COUNT(contactos): " . $conn->error, 'admin.php'); }
$mensagens_pendentes_count = $res_msg_count ? $res_msg_count->fetch_row()[0] : 0;

// --- LÓGICA PARA ATIVIDADE UNIFICADA ---
$atividade_recente = [];
$res_enc_rec = $conn->query("SELECT id, cliente_nome, data_encomenda as data_evento FROM encomendas WHERE estado != 'incompleta' ORDER BY data_encomenda DESC LIMIT 5");
if (!$res_enc_rec) { log_sql("Dashboard: falha em SELECT encomendas recentes: " . $conn->error, 'admin.php'); }
$encomendas_recentes = $res_enc_rec ? $res_enc_rec->fetch_all(MYSQLI_ASSOC) : [];
foreach ($encomendas_recentes as $item) {
    $item['tipo'] = 'encomenda';
    $atividade_recente[] = $item;
}

$res_msg_rec = $conn->query("SELECT id, nome, data_hora as data_evento FROM contactos ORDER BY data_hora DESC LIMIT 5");
if (!$res_msg_rec) { log_sql("Dashboard: falha em SELECT contactos recentes: " . $conn->error, 'admin.php'); }
$mensagens_recentes = $res_msg_rec ? $res_msg_rec->fetch_all(MYSQLI_ASSOC) : [];
foreach ($mensagens_recentes as $item) {
    $item['tipo'] = 'mensagem';
    $atividade_recente[] = $item;
}

usort($atividade_recente, function($a, $b) {
    return strtotime($b['data_evento']) - strtotime($a['data_evento']);
});
$atividade_recente = array_slice($atividade_recente, 0, 5);

?>

<main class="dashboard-container animate-entry">
    <div class="dashboard-header">
        <div class="header-welcome">
            <h2>Painel de Administração</h2>
            <p>Bem-vindo de volta, <strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong>. Aqui está o resumo da sua loja.</p>
        </div>
        <div class="header-actions">
            <?php if ($isSuperAdmin): ?>
            <button id="toggleGlobalEditBtn" class="action-btn-pill <?php echo (isset($_SESSION['global_edit_mode']) && $_SESSION['global_edit_mode']) ? 'btn-edit-active' : ''; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4L18.5 2.5z"></path></svg>
                <?php echo (isset($_SESSION['global_edit_mode']) && $_SESSION['global_edit_mode']) ? 'Desativar Edição' : 'Ativar Edição do Site'; ?>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                mostrarPopup(<?php echo json_encode($_SESSION['flash_message']['texto'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo json_encode($_SESSION['flash_message']['tipo'], JSON_HEX_TAG | JSON_HEX_AMP); ?>);
            });
        </script>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <div class="stats-grid">
        <?php $label = getInterfaceString('card_produtos_ativos', 'Produtos Ativos'); ?>
        <a href="admin_produtos.php" class="stat-card editable-card" data-label-key="card_produtos_ativos">
            <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg></div>
            <div class="stat-card-info">
                <span class="stat-number"><?php echo $total_produtos; ?></span>
                <span class="stat-label"><?php echo htmlspecialchars($label['title']); ?></span>
            </div>
        </a>

        <?php $label = getInterfaceString('card_encomendas_por_enviar', 'Encomendas por Enviar'); ?>
        <a href="encomendas.php?estado=por_enviar" class="stat-card editable-card" data-label-key="card_encomendas_por_enviar">
            <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg></div>
            <div class="stat-card-info">
                <span class="stat-number"><?php echo $encomendas_nao_enviadas_count; ?></span>
                <span class="stat-label"><?php echo htmlspecialchars($label['title']); ?></span>
            </div>
        </a>

        <?php $label = getInterfaceString('card_mensagens_por_responder', 'Mensagens por Responder'); ?>
        <a href="mensagens.php" class="stat-card editable-card" data-label-key="card_mensagens_por_responder">
            <div class="stat-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg></div>
            <div class="stat-card-info">
                <span class="stat-number"><?php echo $mensagens_pendentes_count; ?></span>
                <span class="stat-label"><?php echo htmlspecialchars($label['title']); ?></span>
            </div>
        </a>
    </div>

    <div class="dashboard-grid-cols">
        <div class="main-actions-col">
            <h3 class="dashboard-section-title">Ações do Dia a Dia</h3>
            <div class="dashboard-grid">
                <?php $label = getInterfaceString('nav_adicionar_produto', 'Adicionar Produto', 'Criar um novo item para a loja.'); ?>
                <a href="adicionar.php" class="nav-card editable-card" data-label-key="nav_adicionar_produto">
                    <div class="nav-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></div>
                    <div class="nav-card-body">
                        <h3><?php echo htmlspecialchars($label['title']); ?></h3>
                        <p><?php echo htmlspecialchars($label['description']); ?></p>
                    </div>
                </a>

                <?php $label = getInterfaceString('nav_gerir_produtos', 'Gerir Produtos', 'Editar, apagar ou duplicar produtos.'); ?>
                <a href="admin_produtos.php" class="nav-card editable-card" data-label-key="nav_gerir_produtos">
                    <div class="nav-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg></div>
                    <div class="nav-card-body">
                        <h3><?php echo htmlspecialchars($label['title']); ?></h3>
                        <p><?php echo htmlspecialchars($label['description']); ?></p>
                    </div>
                </a>

                <a href="reservas_stock.php" class="nav-card">
                    <div class="nav-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 7h-9"></path><path d="M14 17H5"></path><circle cx="17" cy="17" r="3"></circle><circle cx="7" cy="7" r="3"></circle></svg></div>
                    <div class="nav-card-body">
                        <h3>Reservas de Stock</h3>
                        <p>Ver produtos temporariamente reservados no checkout.</p>
                    </div>
                </a>

                <?php $label = getInterfaceString('nav_gerir_encomendas', 'Gerir Encomendas', 'Processar e atualizar estado dos pedidos.'); ?>
                <a href="encomendas.php" class="nav-card editable-card" data-label-key="nav_gerir_encomendas">
                    <div class="nav-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg></div>
                    <div class="nav-card-body">
                        <h3><?php echo htmlspecialchars($label['title']); ?></h3>
                        <p><?php echo htmlspecialchars($label['description']); ?></p>
                    </div>
                </a>

                <a href="clientes.php" class="nav-card">
                    <div class="nav-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></div>
                    <div class="nav-card-body">
                        <h3>Contas de Clientes</h3>
                        <p>Ver, gerir e remover contas de clientes registados.</p>
                    </div>
                </a>
            </div>

            <?php if ($isSuperAdmin): ?>
            <h3 class="dashboard-section-title section-spacer">Configurações Avançadas</h3>
            <div class="dashboard-grid">
                <a href="gerir_categorias.php" class="nav-card">
                    <div class="nav-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg></div>
                    <div class="nav-card-body">
                        <h3>Categorias</h3>
                        <p>Gerir categorias e fotos de capa.</p>
                    </div>
                </a>

                <a href="paleta_cores.php" class="nav-card">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"></circle><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"></circle><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"></circle><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"></circle><path d="M12 2a10 10 0 0 0 0 20h1.65a2.35 2.35 0 0 0 1.72-3.95 1.8 1.8 0 0 1 1.32-3.02H19a3 3 0 0 0 3-3A10 10 0 0 0 12 2z"></path></svg>
                    </div>
                    <div class="nav-card-body">
                        <h3>Paleta de Cores</h3>
                        <p>Personalizar a cor de fundo global do site.</p>
                    </div>
                </a>

                <?php $label = getInterfaceString('nav_atributos', 'Atributos', 'Gerir cores, tamanhos, etc.'); ?>
                <a href="gerir_atributos.php" class="nav-card editable-card" data-label-key="nav_atributos">
                    <div class="nav-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg></div>
                    <div class="nav-card-body">
                        <h3><?php echo htmlspecialchars($label['title']); ?></h3>
                        <p><?php echo htmlspecialchars($label['description']); ?></p>
                    </div>
                </a>

                <?php $label = getInterfaceString('nav_guias_tamanho', 'Guias de Tamanho', 'Gerir tabelas de medidas.'); ?>
                <a href="gerir_guias_tamanho.php" class="nav-card editable-card" data-label-key="nav_guias_tamanho">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="10" rx="2" ry="2"></rect><line x1="7" y1="7" x2="7" y2="12"></line><line x1="12" y1="7" x2="12" y2="12"></line><line x1="17" y1="7" x2="17" y2="12"></line><line x1="7" y1="12" x2="17" y2="12"></line></svg>
                    </div>
                    <div class="nav-card-body">
                        <h3><?php echo htmlspecialchars($label['title']); ?></h3>
                        <p><?php echo htmlspecialchars($label['description']); ?></p>
                    </div>
                </a>

                <?php $label = getInterfaceString('nav_templates_email', 'Templates de Email', 'Personalizar emails automáticos.'); ?>
                <a href="gestor_emails.php" class="nav-card editable-card" data-label-key="nav_templates_email">
                    <div class="nav-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg></div>
                    <div class="nav-card-body">
                        <h3><?php echo htmlspecialchars($label['title']); ?></h3>
                        <p><?php echo htmlspecialchars($label['description']); ?></p>
                    </div>
                </a>

                <?php $label = getInterfaceString('nav_administradores', 'Administradores', 'Gerir contas de acesso.'); ?>
                <a href="listar_admins.php" class="nav-card editable-card" data-label-key="nav_administradores">
                    <div class="nav-card-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></div>
                    <div class="nav-card-body">
                        <h3><?php echo htmlspecialchars($label['title']); ?></h3>
                        <p><?php echo htmlspecialchars($label['description']); ?></p>
                    </div>
                </a>

                <?php $label = getInterfaceString('nav_portes', 'Configurações de Portes', 'Gerir preços de envio por peso.'); ?>
                <a href="portes.php" class="nav-card editable-card" data-label-key="nav_portes">
                    <div class="nav-card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    </div>
                    <div class="nav-card-body">
                        <h3><?php echo htmlspecialchars($label['title']); ?></h3>
                        <p><?php echo htmlspecialchars($label['description']); ?></p>
                    </div>
                </a>


            </div>
            <?php endif; ?>
        </div>
        <div class="activity-col">
            <h3 class="dashboard-section-title">Atividade Recente</h3>
            <div class="activity-feed-container">
                <?php if (!empty($atividade_recente)): ?>
                    <?php foreach ($atividade_recente as $item): ?>
                        <?php if ($item['tipo'] === 'encomenda'): ?>
                            <a href="detalhes_encomenda.php?id=<?php echo $item['id']; ?>&return_to=admin"
                               class="activity-item activity-item-ctx"
                               data-tipo="encomenda"
                               data-id="<?php echo $item['id']; ?>"
                               data-nome="Encomenda #<?php echo $item['id']; ?>">
                                <div class="activity-icon-wrapper encomenda"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg></div>
                                <div class="activity-content"><strong>Nova Encomenda #<?php echo $item['id']; ?></strong><span>De: <?php echo htmlspecialchars($item['cliente_nome']); ?></span></div>
                                <span class="activity-time"><?php echo format_time_ago($item['data_evento']); ?></span>
                            </a>
                        <?php elseif ($item['tipo'] === 'mensagem'): ?>
                            <a href="mensagens.php"
                               class="activity-item activity-item-ctx"
                               data-tipo="mensagem"
                               data-id="<?php echo $item['id']; ?>"
                               data-nome="Mensagem de <?php echo htmlspecialchars($item['nome']); ?>">
                                <div class="activity-icon-wrapper mensagem"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg></div>
                                <div class="activity-content"><strong>Nova Mensagem</strong><span>De: <?php echo htmlspecialchars($item['nome']); ?></span></div>
                                <span class="activity-time"><?php echo format_time_ago($item['data_evento']); ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="activity-placeholder">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        <h4>Tudo em dia!</h4>
                        <p>Não há novas encomendas ou mensagens para mostrar.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php if ($isSuperAdmin): ?>
<?php
// Context Menu for Cards
renderContextMenu([
    [
        'id' => 'ctx-edit-label',
        'label' => 'Personalizar Card',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>'
    ]
], 'ctx-menu');

// Context Menu for Activity
renderContextMenu([
    [
        'href' => '#',
        'id' => 'ctx-activity-view',
        'label' => 'Ver Detalhes',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>'
    ],
    [
        'id' => 'ctx-activity-delete',
        'class' => 'ctx-item-danger',
        'label' => 'Eliminar Permanentemente',
        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>'
    ]
], 'ctx-menu-activity');
?>

<!-- ── Quick Edit Modal ── -->
<?php renderQuickEditModal('qe-modal', 'Personalizar Card'); ?>

<template id="tpl-card-modal">
    <input type="hidden" id="qe-key">
    <div class="qe-body">
        <div class="qe-f">
            <label>Título do Card</label>
            <input type="text" id="qe-title" class="qe-in" required>
        </div>
        <div class="qe-f" id="qe-desc-group">
            <label>Descrição / Legenda</label>
            <textarea id="qe-desc" class="qe-in" rows="3"></textarea>
        </div>
    </div>
</template>

<!-- ── Hidden Deletion Forms ── -->
<form id="form-delete-activity" method="POST" style="display:none;">
    <input type="hidden" name="id" id="delete-activity-id">
    <input type="hidden" name="action" value="apagar">
    <input type="hidden" name="return_to" value="admin.php">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentTarget = null;

    // --- MANUSEAMENTO DE MENUS DE CONTEXTO ---
    document.addEventListener('admin:contextmenu', function(e) {
        const { row, menu } = e.detail;
        currentTarget = row;

        if (row.classList.contains('editable-card')) {
            // Context Menu de Cards
            menu.querySelector('#ctx-edit-label').onclick = () => {
                const modal = document.getElementById('qe-modal');
                const form = modal.querySelector('form');
                form.innerHTML = document.getElementById('tpl-card-modal').innerHTML + form.querySelector('.qe-btns').outerHTML;

                const key = row.dataset.labelKey;
                const titleEl = row.querySelector('h3, .stat-label');
                const descEl = row.querySelector('p');

                modal.querySelector('#qe-key').value = key;
                modal.querySelector('#qe-title').value = titleEl?.textContent.trim() || '';
                modal.querySelector('#qe-desc').value = descEl?.textContent.trim() || '';
                modal.querySelector('#qe-desc-group').style.display = descEl ? 'flex' : 'none';
                modal.style.display = 'flex';
                form.onsubmit = submeterEdicaoCard;
            };
        } else if (row.classList.contains('activity-item-ctx')) {
            // Context Menu de Atividade
            const type = row.dataset.tipo;
            const id = row.dataset.id;
            const viewBtn = menu.querySelector('#ctx-activity-view');

            if (type === 'encomenda') {
                viewBtn.href = `detalhes_encomenda.php?id=${id}&return_to=admin`;
                viewBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> Ver Detalhes';
            } else {
                viewBtn.href = 'mensagens.php';
                viewBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg> Ir para Mensagens';
            }

            menu.querySelector('#ctx-activity-delete').onclick = () => {
                const nome = row.dataset.nome;
                window.mostrarModalConfirmacao('Confirmar Eliminação', `Eliminar permanentemente <strong>${nome}</strong>?`, () => {
                    const form = document.getElementById('form-delete-activity');
                    document.getElementById('delete-activity-id').value = id;
                    form.action = (type === 'encomenda') ? 'apagar_encomenda.php' : 'mensagens.php';
                    form.submit();
                });
            };
        }
    });

    function submeterEdicaoCard(e) {
        e.preventDefault();
        const form = e.target;
        const btnSave = form.querySelector('.qe-btn-save');
        btnSave.disabled = true; btnSave.textContent = 'A guardar...';

        const fd = new FormData(form);
        fd.set('csrf_token', <?php echo json_encode($_SESSION['csrf_token']); ?>);
        fetch('ajax_edit_interface_label.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(data => {
                if (data.sucesso) {
                    const titleEl = currentTarget.querySelector('h3, .stat-label');
                    const descEl = currentTarget.querySelector('p');
                    if (titleEl) titleEl.textContent = data.title;
                    if (descEl) descEl.textContent = data.description;
                    document.getElementById('qe-modal').style.display = 'none';
                    mostrarPopup('Interface atualizada!', 'sucesso');
                }
            }).finally(() => { btnSave.disabled = false; btnSave.textContent = 'Guardar'; });
    }

    // Toggle Global Edit Mode
    const toggleBtn = document.getElementById('toggleGlobalEditBtn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', async function() {
            const btn = this;
            btn.disabled = true;
            const originalText = btn.textContent;
            btn.textContent = 'A processar...';

            try {
                const res = await fetch('ajax_toggle_edit_mode.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ csrf_token: '<?php echo $_SESSION['csrf_token']; ?>' })
                });
                const data = await res.json();
                if (data.sucesso) {
                    const icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4L18.5 2.5z"></path></svg> ';
                    if (data.edit_mode) {
                        btn.innerHTML = icon + 'Desativar Edição';
                        btn.classList.add('btn-edit-active');
                    } else {
                        btn.innerHTML = icon + 'Ativar Edição do Site';
                        btn.classList.remove('btn-edit-active');
                    }
                    mostrarPopup('Modo de edição ' + (data.edit_mode ? 'ativado' : 'desativado') + ' globalmente.', 'sucesso');
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                } else {
                    mostrarPopup('Erro: ' + data.erro, 'erro');
                    btn.textContent = originalText;
                }
            } catch (e) {
                mostrarPopup('Erro de comunicação.', 'erro');
                btn.textContent = originalText;
            } finally {
                btn.disabled = false;
            }
        });
    }
});
</script>

<?php endif; ?>

<?php include '../templates/footer.php'; ?>

