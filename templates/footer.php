<?php
// Garantir que a variável de versão existe, mesmo se o footer for incluído isoladamente
if (!isset($versao_global)) {
    $ficheiro_versao = __DIR__ . '/../config/versao_site.php';
    $versao_global = (file_exists($ficheiro_versao)) ? require($ficheiro_versao) : time();
}

// Carregar funções do footer e garantir base de dados
require_once __DIR__ . '/../config/footer_functions.php';
if (!isset($conn)) {
    require_once __DIR__ . '/../config/database.php';
}

$isAdminFooter = isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true && in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor']) && isset($_SESSION['global_edit_mode']) && $_SESSION['global_edit_mode'] === true;
$isGlobalEditMode = $isAdminFooter; // Helper para legibilidade
$isAdminHeader = isset($isAdminHeader) ? $isAdminHeader : $isAdminFooter;

// Fallbacks Hardcoded
$fallback_sobre = 'O teu destino de moda preferido, onde o estilo se une ao conforto. Descobre a nossa curadoria exclusiva de moda feminina, acessórios e peças para bebé com a máxima qualidade.';
$fallback_contactos = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> <span>Edifício Chafariz<br>Rua dos Fontenários<br>4535-221 Lourosa, Portugal</span>';
$fallback_pagamento = 'Garantimos a total segurança das tuas compras. Utilizamos a plataforma Stripe, líder mundial em pagamentos online, para processar cartões de débito, crédito e MB WAY com encriptação avançada.';
$fallback_copyright = '© ' . date("Y") . ' TopTop - Todos os direitos reservados.';
$footerBackgroundColor = strtoupper((string) ($siteBackgroundColor ?? getLojaConfig('site_background_color', '#FAF8F4')));
$footerHasWhiteBackground = $footerBackgroundColor === '#FFFFFF';
?>

<footer class="site-footer site-footer-home<?php echo $footerHasWhiteBackground ? ' site-footer-white-bg' : ''; ?>">
    <div class="footer-grid">
        <div class="footer-coluna">
            <h4 class="<?php echo $isGlobalEditMode ? 'footer-editable' : ''; ?>" data-seccao="titulo_sobre"><?php echo getFooterText('titulo_sobre', 'Sobre a TopTop'); ?></h4>
            <div class="<?php echo $isGlobalEditMode ? 'footer-editable' : ''; ?>" data-seccao="sobre_nos">
                <p><?php echo getFooterText('sobre_nos', $fallback_sobre); ?></p>
            </div>
        </div>
        <div class="footer-coluna">
            <h4 class="<?php echo $isGlobalEditMode ? 'footer-editable' : ''; ?>" data-seccao="titulo_info"><?php echo getFooterText('titulo_info', 'Informações'); ?></h4>
            <ul class="footer-links">
                <li class="<?php echo $isGlobalEditMode ? 'footer-editable' : ''; ?>" data-seccao="link_label_trocas">
                    <a href="/trocas.php"><?php echo getFooterText('link_label_trocas', 'Trocas e Devoluções'); ?></a>
                </li>
                <li class="<?php echo $isGlobalEditMode ? 'footer-editable' : ''; ?>" data-seccao="link_label_envios">
                    <a href="/envios.php"><?php echo getFooterText('link_label_envios', 'Portes e Envios'); ?></a>
                </li>
                <li class="<?php echo $isGlobalEditMode ? 'footer-editable' : ''; ?>" data-seccao="link_label_encomenda">
                    <a href="/consultar-encomenda.php"><?php echo getFooterText('link_label_encomenda', 'Acompanhar Encomenda'); ?></a>
                </li>
            </ul>
        </div>
        <div class="footer-coluna">
            <h4 class="<?php echo $isGlobalEditMode ? 'footer-editable' : ''; ?>" data-seccao="titulo_contactos"><?php echo getFooterText('titulo_contactos', 'Fale Connosco'); ?></h4>
            <ul class="footer-contato-info">
                <li class="<?php echo $isGlobalEditMode ? 'footer-editable' : ''; ?>" data-seccao="contactos_info">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    <span>
                        <?php 
                        $rua = getFooterText('morada_rua', 'Edifício Chafariz, Rua dos Fontenários');
                        $cp = getFooterText('morada_cp', '4535-221');
                        $local = getFooterText('morada_localidade', 'Lourosa, Portugal');
                        echo "$rua, $cp $local";
                        ?>
                    </span>
                </li>
                <li class="<?php echo $isGlobalEditMode ? 'footer-editable' : ''; ?>" data-seccao="telefone">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                    <a href="tel:<?php echo getFooterText('telefone', '351933169009'); ?>"><?php echo getFooterText('telefone', '(+351) 933 169 009'); ?></a>
                </li>
                <li class="<?php echo $isGlobalEditMode ? 'footer-editable' : ''; ?>" data-seccao="email">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    <a href="mailto:<?php echo getFooterText('email', 'toptopclothingstore@gmail.com'); ?>"><?php echo getFooterText('email', 'toptopclothingstore@gmail.com'); ?></a>
                </li>
            </ul>
            <div class="footer-social <?php echo $isGlobalEditMode ? 'footer-editable' : ''; ?>" data-seccao="redes_sociais" 
                 data-wa="<?php echo getFooterText('link_whatsapp', 'https://chat.whatsapp.com/K7IhtBOBJNtHRGysYttqnY?fbclid=PAZXh0bgNhZW0CMTEAAafGr0PtcF54B-Xs-3vTdfud9IjlLX7_8aIYPl4AbcuzcyR6YggHigJmb1gQ0g_aem_FI_fPyaedJRcm1Ctl243uA'); ?>"
                 data-ig="<?php echo getFooterText('link_instagram', 'https://www.instagram.com/toptop_clothingstore'); ?>"
                 data-fb="<?php echo getFooterText('link_facebook', 'https://www.facebook.com/share/1AqqQZ8YmL/'); ?>">
                <a href="<?php echo getFooterText('link_whatsapp', 'https://chat.whatsapp.com/K7IhtBOBJNtHRGysYttqnY?fbclid=PAZXh0bgNhZW0CMTEAAafGr0PtcF54B-Xs-3vTdfud9IjlLX7_8aIYPl4AbcuzcyR6YggHigJmb1gQ0g_aem_FI_fPyaedJRcm1Ctl243uA'); ?>" target="_blank" title="WhatsApp" class="footer-social-link wa"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg></a>
                <a href="<?php echo getFooterText('link_instagram', 'https://www.instagram.com/toptop_clothingstore'); ?>" target="_blank" title="Instagram" class="footer-social-link ig"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg></a>
                <a href="<?php echo getFooterText('link_facebook', 'https://www.facebook.com/share/1AqqQZ8YmL/'); ?>" target="_blank" title="Facebook" class="footer-social-link fb"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg></a>
            </div>
        </div>
        <div class="footer-coluna">
            <h4 class="<?php echo $isGlobalEditMode ? 'footer-editable' : ''; ?>" data-seccao="titulo_pagamento"><?php echo getFooterText('titulo_pagamento', 'Pagamento Seguro'); ?></h4>
            <div class="<?php echo $isGlobalEditMode ? 'footer-editable' : ''; ?>" data-seccao="pagamento_seguro">
                <p><?php echo getFooterText('pagamento_seguro', $fallback_pagamento); ?></p>
            </div>
        </div>
    </div>
    <div class="footer-copyright">
        <div class="<?php echo $isGlobalEditMode ? 'footer-editable' : ''; ?>" data-seccao="copyright">
            <p><?php echo getFooterText('copyright', $fallback_copyright); ?></p>
        </div>
    </div>
</footer>

<div id="popupMensagem" class="popup">
    <div class="popup-barra"></div>
    <div class="popup-conteudo">
        <span id="popupTitulo"></span>
        <span id="popupTexto"></span>
    </div>
    <button id="popupFechar" class="btn-close-unified popup-fechar" title="Fechar">&times;</button>
</div>

<div id="modalConfirmacao" class="modal-confirmacao">
    <div class="modal-confirmacao-conteudo">
        <h3 id="modalConfirmacaoTitulo">Tem a certeza?</h3>
        <p id="modalConfirmacaoTexto">Esta ação não pode ser revertida.</p>
        <div class="modal-confirmacao-acoes">
            <button id="modalConfirmacaoBtnCancelar" class="button voltar-btn">Cancelar</button>
            <button id="modalConfirmacaoBtnConfirmar" class="button delete-btn">Confirmar</button>
        </div>
    </div>
</div>

<?php if (basename($_SERVER['PHP_SELF']) !== 'gerir_guias_tamanho.php'): ?>
    <div id="modalGuiaTamanhos" class="qe-modal">
        <div class="qe-card" style="max-width: 850px;">
            <button type="button" class="btn-close-unified qe-close" title="Fechar">&times;</button>
            <h3 id="guia-titulo"></h3>
            <div id="guia-conteudo"></div>
        </div>
    </div>
<?php endif; ?>

<?php if ($isAdminFooter): ?>
<div id="modalEditorFooter" class="qe-modal footer-editor-modal">
    <div class="qe-card">
        <button type="button" class="btn-close-unified qe-close" onclick="fecharEditorFooter()">&times;</button>
        <div class="form-card-header">
            <div class="card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4L18.5 2.5z"></path></svg></div>
            <h3>Editar Elemento </h3>
        </div>
        <div class="form-card-body">
            <div class="admin-warning-box" id="footer-sync-warning">
                <svg class="admin-warning-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                <strong>Sincronização Ativa:</strong> Esta alteração será refletida automaticamente noutras áreas do site.
            </div>
            <input type="hidden" id="footer-edit-seccao">
            
            <div id="footer-edit-normal-fields">
                <div class="form-group">
                    <label id="footer-edit-label">Conteúdo</label>
                    <textarea id="footer-edit-conteudo" rows="6" class="admin-textarea"></textarea>
                </div>
            </div>

            <div id="footer-edit-address-fields">
                <div class="form-group">
                    <label>Rua / Edifício</label>
                    <input type="text" id="footer-edit-rua" class="admin-input-style">
                </div>
                <div class="form-group">
                    <label>Código Postal</label>
                    <input type="text" id="footer-edit-cp" class="admin-input-style">
                </div>
                <div class="form-group">
                    <label>Localidade / País</label>
                    <input type="text" id="footer-edit-localidade" class="admin-input-style">
                </div>
            </div>

            <div id="footer-edit-social-fields">
                <div class="form-group">
                    <label>Link WhatsApp</label>
                    <input type="text" id="footer-edit-wa" class="admin-input-style">
                </div>
                <div class="form-group">
                    <label>Link Instagram</label>
                    <input type="text" id="footer-edit-ig" class="admin-input-style">
                </div>
                <div class="form-group">
                    <label>Link Facebook</label>
                    <input type="text" id="footer-edit-fb" class="admin-input-style">
                </div>
            </div>

            <div id="footer-edit-schedule-fields">
                <div class="horario-editor-head">
                    <span>Defina o estado e o horário de cada dia.</span>
                </div>
                <div id="horario-editor-list" class="horario-editor-list"></div>
                <div class="form-group horario-note-group">
                    <label>Nota opcional</label>
                    <input type="text" id="footer-edit-horario-nota" class="admin-input-style" placeholder="Ex.: Horário sujeito a marcação em feriados">
                </div>
            </div>

            <div class="form-footer-actions">
                <button type="button" class="button voltar-btn" onclick="fecharEditorFooter()">Cancelar</button>
                <button type="button" class="button add-btn" onclick="guardarAlteracaoFooter()">Guardar Alterações</button>
            </div>
        </div>
    </div>
</div>



<div id="modalEditorHeader" class="qe-modal footer-editor-modal">
    <div class="qe-card">
        <button type="button" class="btn-close-unified qe-close" onclick="fecharEditorHeader()">&times;</button>
        <div class="form-card-header">
            <div class="card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4L18.5 2.5z"></path></svg></div>
            <h3>Editar Elemento do Cabecalho</h3>
        </div>
        <div class="form-card-body">
            <input type="hidden" id="header-edit-seccao">

            <div id="header-edit-text-fields">
                <div class="form-group">
                    <label id="header-edit-label">Conteudo</label>
                    <input type="text" id="header-edit-conteudo" class="admin-input-style">
                </div>
            </div>

            <div id="header-edit-logo-fields">
                <div class="header-logo-preview" id="header-logo-preview-container">
                    <img id="header-logo-preview-img" src="" alt="Preview do logo">
                </div>
                
                <!-- Novo contentor para o Cropper -->
                <div id="header-cropper-container" style="display: none; width: 100%; max-height: 400px; margin-bottom: 15px; border-radius: 8px; overflow: hidden; background: #f8fafc; border: 1px solid #e2e8f0; justify-content: center;">
                    <img id="header-cropper-img" src="" style="display: block; max-height: 400px; max-width: 100%;">
                </div>
                
                <!-- Ferramentas do Cropper -->
                <div id="header-cropper-actions" style="display: none; margin-bottom: 15px; gap: 10px; justify-content: center;">
                    <button type="button" class="button voltar-btn" style="padding: 6px 12px; font-size: 0.85rem;" onclick="window.headerCropper && window.headerCropper.rotate(-90)">↺ Rodar Esq.</button>
                    <button type="button" class="button voltar-btn" style="padding: 6px 12px; font-size: 0.85rem;" onclick="window.headerCropper && window.headerCropper.rotate(90)">↻ Rodar Dir.</button>
                </div>

                <div class="form-group">
                    <label class="custom-file-upload" style="display: block; border: 2px dashed #cbd5e1; border-radius: 8px; padding: 25px 20px; text-align: center; cursor: pointer; background: #f8fafc; transition: all 0.2s ease;">
                        <input type="file" id="header-edit-logo" accept="image/jpeg,image/png,image/webp,image/avif" style="display: none;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" style="margin-bottom: 10px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        <div style="font-weight: 500; color: #334155; font-size: 0.95rem;">Clique para escolher a nova foto</div>
                        <div style="font-size: 0.8rem; color: #94a3b8; margin-top: 6px;">Formatos suportados: JPG, PNG, WebP. Max: 2MB</div>
                    </label>
                </div>
            </div>

            <div class="form-footer-actions">
                <button type="button" class="button voltar-btn" onclick="fecharEditorHeader()">Cancelar</button>
                <button type="button" class="button add-btn" onclick="guardarAlteracaoHeader()">Guardar Alteracoes</button>
            </div>
        </div>
    </div>
</div>



<script>
console.log("Footer Admin Mode:", "<?php echo $isAdminFooter ? 'Ativo' : 'Inativo'; ?>", "Role:", "<?php echo $_SESSION['admin_role'] ?? 'Nenhum'; ?>");
let currentFooterElement = null;
let currentHeaderElement = null;



document.addEventListener('click', function(e) {
    // Detetar clique no ícone do lápis (pseudo-elemento ::after)
    const editable = e.target.closest('.footer-editable, .header-editable');
    if (editable && editable.classList.contains('inline-editavel')) return; // edição inline trata disto (sem modal)
    if (editable && window.innerWidth > 992) {
        const rect = editable.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const clickY = e.clientY - rect.top;
        
        // O ícone está posicionado no canto superior DIREITO (top: -10px, right: -10px)
        // Se o clique for na margem direita e no topo, acionamos o editor
        if (clickX >= rect.width - 20 && clickY <= 20) {
            e.preventDefault();
            e.stopPropagation();
            
            if (editable.classList.contains('header-editable')) {
                currentHeaderElement = editable;
                abrirEditorHeader();
            } else {
                currentFooterElement = editable;
                abrirEditorFooter();
            }
            return;
        }
    }

});

function abrirEditorHeader() {
    if (!currentHeaderElement) return;

    const seccao = currentHeaderElement.dataset.seccao;
    const modal = document.getElementById('modalEditorHeader');
    if (!modal) return;

    document.querySelectorAll('.header-editable.editing-current, .footer-editable.editing-current').forEach(el => el.classList.remove('editing-current'));
    currentHeaderElement.classList.add('editing-current');
    document.getElementById('header-edit-seccao').value = seccao;

    if (seccao === 'logo_src') {
        document.getElementById('header-edit-text-fields').style.display = 'none';
        document.getElementById('header-edit-logo-fields').style.display = 'block';
        document.getElementById('header-edit-logo').value = '';
        const previewImg = document.getElementById('header-logo-preview-img');
        const currentImg = currentHeaderElement.querySelector('img');
        if (previewImg && currentImg) previewImg.src = currentImg.src;
        
        if (window.headerCropper) {
            window.headerCropper.destroy();
            window.headerCropper = null;
        }
        const cropperContainer = document.getElementById('header-cropper-container');
        if (cropperContainer) cropperContainer.style.display = 'none';
        const cropperActions = document.getElementById('header-cropper-actions');
        if (cropperActions) cropperActions.style.display = 'none';
        const previewContainer = document.getElementById('header-logo-preview-container');
        if (previewContainer) previewContainer.style.display = 'flex';
        
    } else {
        document.getElementById('header-edit-text-fields').style.display = 'block';
        document.getElementById('header-edit-logo-fields').style.display = 'none';
        document.getElementById('header-edit-conteudo').value = currentHeaderElement.innerText.trim();
        document.getElementById('header-edit-label').innerText = 'Texto do menu';
    }

    modal.classList.add('active');
    modal.style.display = 'flex';
}

function fecharEditorHeader() {
    const modal = document.getElementById('modalEditorHeader');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }
    document.querySelectorAll('.header-editable.editing-current').forEach(el => el.classList.remove('editing-current'));
    
    if (window.headerCropper) {
        window.headerCropper.destroy();
        window.headerCropper = null;
    }
}

async function guardarAlteracaoHeader() {
    const seccao = document.getElementById('header-edit-seccao').value;
    const csrf_token = '<?php echo $_SESSION['csrf_token']; ?>';

    if (seccao === 'logo_src') {
        const input = document.getElementById('header-edit-logo');
        const btn = document.querySelector('#modalEditorHeader .add-btn');

        // Se o Cropper estiver ativo, gravamos a versão recortada
        if (window.headerCropper) {
            const origHTML = btn.innerHTML;
            btn.innerHTML = 'A Guardar...';
            btn.disabled = true;

            window.headerCropper.getCroppedCanvas({
                maxWidth: 1920,
                maxHeight: 1080
            }).toBlob(async (blob) => {
                if (!blob) {
                    mostrarPopup('Erro ao gerar o recorte.', 'erro');
                    btn.innerHTML = origHTML;
                    btn.disabled = false;
                    return;
                }

                const formData = new FormData();
                formData.append('seccao', 'logo_src');
                formData.append('csrf_token', csrf_token);
                // Gera uma extensão baseada no tipo MIME
                const ext = blob.type === 'image/webp' ? 'webp' : (blob.type === 'image/png' ? 'png' : 'jpg');
                formData.append('logo', blob, 'logo_cropped.' + ext);

                try {
                    const response = await fetch('/dev/ajax_save_header.php', { method: 'POST', body: formData });
                    const data = await response.json();

                    if (data.sucesso) {
                        document.querySelectorAll('.header-editable[data-seccao="logo_src"] img').forEach(img => {
                            img.src = data.url + '?v=' + Date.now();
                        });
                        fecharEditorHeader();
                        mostrarPopup(data.mensagem, 'sucesso');
                    } else {
                        mostrarPopup(data.mensagem, 'erro');
                    }
                } catch (err) {
                    mostrarPopup('Erro de comunicação.', 'erro');
                }
                
                btn.innerHTML = origHTML;
                btn.disabled = false;
            }, 'image/png'); // Força PNG para garantir suporte a transparência
            
            return;
        }

        // Se não houver cropper ativo (não escolheu ficheiro), apenas fecha o modal
        if (!input.files || !input.files[0]) {
            fecharEditorHeader();
            return;
        }
        return;
    }

    const conteudo = document.getElementById('header-edit-conteudo').value.trim();
    const response = await fetch('/dev/ajax_save_header.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ seccao, conteudo, csrf_token })
    });
    const data = await response.json();

    if (data.sucesso) {
        document.querySelectorAll(`.header-editable[data-seccao="${seccao}"]`).forEach(el => {
            el.innerText = conteudo;
        });
        fecharEditorHeader();
        mostrarPopup(data.mensagem, 'sucesso');
    } else {
        mostrarPopup(data.mensagem, 'erro');
    }
}

const HORARIO_DIAS_SEMANA = [
    { key: 'segunda', label: 'Segunda-feira' },
    { key: 'terca', label: 'Ter\u00e7a-feira' },
    { key: 'quarta', label: 'Quarta-feira' },
    { key: 'quinta', label: 'Quinta-feira' },
    { key: 'sexta', label: 'Sexta-feira' },
    { key: 'sabado', label: 'S\u00e1bado' },
    { key: 'domingo', label: 'Domingo' },
];

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));
}

function stripHorarioHtml(value) {
    const div = document.createElement('div');
    div.innerHTML = String(value ?? '');
    return div.textContent.trim();
}

function horarioDefaultConfig(nota = '') {
    return {
        tipo: 'horario_semana',
        versao: 1,
        nota,
        dias: HORARIO_DIAS_SEMANA.map(dia => ({
            key: dia.key,
            label: dia.label,
            fechado: false,
            inicio: '',
            fim: '',
            texto: '',
        })),
    };
}

function normalizarHorarioConfig(raw, textoVisivel = '') {
    const fallback = stripHorarioHtml(raw || textoVisivel);
    let parsed = null;

    try {
        parsed = raw ? JSON.parse(raw) : null;
    } catch (e) {
        parsed = null;
    }

    const config = horarioDefaultConfig(parsed && parsed.tipo === 'horario_semana' ? (parsed.nota || '') : fallback);
    if (!parsed || parsed.tipo !== 'horario_semana' || !Array.isArray(parsed.dias)) {
        return config;
    }

    const diasGuardados = new Map(parsed.dias.map(dia => [dia.key, dia]));
    config.dias = config.dias.map(diaBase => {
        const dia = diasGuardados.get(diaBase.key) || {};
        return {
            ...diaBase,
            fechado: Boolean(dia.fechado),
            inicio: /^\d{2}:\d{2}$/.test(dia.inicio || '') ? dia.inicio : '',
            fim: /^\d{2}:\d{2}$/.test(dia.fim || '') ? dia.fim : '',
            texto: String(dia.texto || '').trim(),
        };
    });
    config.nota = String(parsed.nota || '').trim();

    return config;
}

function renderHorarioEditor(config) {
    const lista = document.getElementById('horario-editor-list');
    const nota = document.getElementById('footer-edit-horario-nota');
    if (!lista || !nota) return;

    nota.value = config.nota || '';
    lista.innerHTML = config.dias.map(dia => `
        <div class="horario-editor-row${dia.fechado ? ' is-closed' : ''}" data-dia="${escapeHtml(dia.key)}">
            <div class="horario-editor-day">${escapeHtml(dia.label)}</div>
            <label class="horario-editor-toggle">
                <input type="checkbox" class="horario-fechado" ${dia.fechado ? 'checked' : ''}>
                <span>Encerrado</span>
            </label>
            <div class="horario-editor-times">
                <label class="horario-editor-time-field">
                    <span>Abre</span>
                    <input type="time" class="admin-input-style horario-inicio" value="${escapeHtml(dia.inicio)}" aria-label="${escapeHtml(dia.label)} abre">
                </label>
                <label class="horario-editor-time-field">
                    <span>Fecha</span>
                    <input type="time" class="admin-input-style horario-fim" value="${escapeHtml(dia.fim)}" aria-label="${escapeHtml(dia.label)} fecha">
                </label>
            </div>
            <label class="horario-editor-text-field">
                <span>Texto opcional</span>
                <input type="text" class="admin-input-style horario-texto" value="${escapeHtml(dia.texto)}" placeholder="Ex.: Só por marcação">
            </label>
        </div>
    `).join('');

    lista.querySelectorAll('.horario-editor-row').forEach(row => {
        const fechado = row.querySelector('.horario-fechado');
        const campos = row.querySelectorAll('.horario-inicio, .horario-fim, .horario-texto');
        const sync = () => {
            row.classList.toggle('is-closed', fechado.checked);
            campos.forEach(campo => { campo.disabled = fechado.checked; });
        };
        fechado.addEventListener('change', sync);
        sync();
    });
}

function recolherHorarioConfig() {
    return {
        tipo: 'horario_semana',
        versao: 1,
        nota: document.getElementById('footer-edit-horario-nota')?.value.trim() || '',
        dias: HORARIO_DIAS_SEMANA.map(dia => {
            const row = document.querySelector(`#horario-editor-list [data-dia="${dia.key}"]`);
            return {
                key: dia.key,
                label: dia.label,
                fechado: Boolean(row?.querySelector('.horario-fechado')?.checked),
                inicio: row?.querySelector('.horario-inicio')?.value || '',
                fim: row?.querySelector('.horario-fim')?.value || '',
                texto: row?.querySelector('.horario-texto')?.value.trim() || '',
            };
        }),
    };
}

function formatarHoraHorario(hora) {
    const match = String(hora || '').match(/^(\d{2}):(\d{2})$/);
    if (!match) return '';
    return `${Number(match[1])}h${match[2]}`;
}

function textoHorarioDia(dia) {
    if (dia.fechado) return 'Encerrado';
    if (dia.texto) return dia.texto;

    const inicio = formatarHoraHorario(dia.inicio);
    const fim = formatarHoraHorario(dia.fim);
    if (inicio && fim) return `${inicio} \u00e0s ${fim}`;
    if (inicio) return `A partir das ${inicio}`;
    if (fim) return `At\u00e9 \u00e0s ${fim}`;
    return 'Por consulta';
}

function renderHorarioPreview(config) {
    const hoje = ['domingo', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado'][new Date().getDay()];
    const dias = config.dias.map(dia => {
        const classes = ['horario-dia'];
        if (dia.fechado) classes.push('is-closed');
        if (dia.key === hoje) classes.push('is-today');
        const hojeHtml = dia.key === hoje ? '<span class="horario-hoje">Hoje</span>' : '';

        return `
            <div class="${classes.join(' ')}" role="listitem">
                <span class="horario-dia-nome">${escapeHtml(dia.label)}${hojeHtml}</span>
                <span class="horario-dia-horas">${escapeHtml(textoHorarioDia(dia))}</span>
            </div>
        `;
    }).join('');
    const nota = config.nota ? `<p class="horario-nota">${escapeHtml(config.nota)}</p>` : '';

    return `<div class="horario-semanal" role="list">${dias}</div>${nota}`;
}

function abrirEditorFooter() {
    if (!currentFooterElement) return;
    
    const seccao = currentFooterElement.dataset.seccao;
    const modal = document.getElementById('modalEditorFooter');
    if (!modal) return;
    modal.classList.toggle('schedule-editor-active', seccao === 'horario_funcionamento');

    document.querySelectorAll('.header-editable.editing-current, .footer-editable.editing-current').forEach(el => el.classList.remove('editing-current'));
    currentFooterElement.classList.add('editing-current');

    // Warning de sincronização
    const warning = document.getElementById('footer-sync-warning');
    const seccoesSincronizadas = ['telefone', 'email', 'contactos_info'];
    if (warning) warning.style.display = seccoesSincronizadas.includes(seccao) ? 'block' : 'none';

    document.getElementById('footer-edit-seccao').value = seccao;

    // Gestão das Visibilidades Baseado no Tipo de Secção
    if (seccao === 'contactos_info') {
        document.getElementById('footer-edit-normal-fields').style.display = 'none';
        document.getElementById('footer-edit-social-fields').style.display = 'none';
        document.getElementById('footer-edit-schedule-fields').style.display = 'none';
        document.getElementById('footer-edit-address-fields').style.display = 'block';
        
        fetch('/dev/ajax_get_footer_address.php')
            .then(r => r.json())
            .then(data => {
                document.getElementById('footer-edit-rua').value = data.rua || '';
                document.getElementById('footer-edit-cp').value = data.cp || '';
                document.getElementById('footer-edit-localidade').value = data.localidade || '';
            });

    } else if (seccao === 'redes_sociais') {
        document.getElementById('footer-edit-normal-fields').style.display = 'none';
        document.getElementById('footer-edit-address-fields').style.display = 'none';
        document.getElementById('footer-edit-schedule-fields').style.display = 'none';
        document.getElementById('footer-edit-social-fields').style.display = 'block';
        
        document.getElementById('footer-edit-wa').value = currentFooterElement.dataset.wa || '';
        document.getElementById('footer-edit-ig').value = currentFooterElement.dataset.ig || '';
        document.getElementById('footer-edit-fb').value = currentFooterElement.dataset.fb || '';

    } else if (seccao === 'horario_funcionamento') {
        document.getElementById('footer-edit-normal-fields').style.display = 'none';
        document.getElementById('footer-edit-address-fields').style.display = 'none';
        document.getElementById('footer-edit-social-fields').style.display = 'none';
        document.getElementById('footer-edit-schedule-fields').style.display = 'block';

        const config = normalizarHorarioConfig(currentFooterElement.dataset.horario || '', currentFooterElement.innerText);
        renderHorarioEditor(config);

    } else {
        document.getElementById('footer-edit-normal-fields').style.display = 'block';
        document.getElementById('footer-edit-address-fields').style.display = 'none';
        document.getElementById('footer-edit-social-fields').style.display = 'none';
        document.getElementById('footer-edit-schedule-fields').style.display = 'none';
        
        let textoParaEditar = currentFooterElement.innerText;
        document.getElementById('footer-edit-conteudo').value = textoParaEditar.trim();
        
        // Ajustar UI da textarea dependendo se é título ou link
        const isTituloOuLink = seccao.startsWith('titulo_') || seccao.startsWith('link_label_');
        document.getElementById('footer-edit-conteudo').rows = isTituloOuLink ? 2 : 6;
        document.getElementById('footer-edit-label').innerText = isTituloOuLink ? 'Nome do Título / Link' : 'Conteúdo';
    }

    modal.classList.add('active');
    modal.style.display = 'flex';
}

function fecharEditorFooter() {
    const modal = document.getElementById('modalEditorFooter');
    if (modal) {
        modal.classList.remove('active');
        modal.classList.remove('schedule-editor-active');
        modal.style.display = 'none';
    }
    document.querySelectorAll('.footer-editable.editing-current').forEach(el => el.classList.remove('editing-current'));
}

async function guardarAlteracaoFooter() {
    const seccao = document.getElementById('footer-edit-seccao').value;
    const csrf_token = '<?php echo $_SESSION['csrf_token']; ?>';
    let promessas = [];

    if (seccao === 'contactos_info') {
        const rua = document.getElementById('footer-edit-rua').value;
        const cp = document.getElementById('footer-edit-cp').value;
        const localidade = document.getElementById('footer-edit-localidade').value;

        promessas.push(gravarCampo('morada_rua', rua, csrf_token));
        promessas.push(gravarCampo('morada_cp', cp, csrf_token));
        promessas.push(gravarCampo('morada_localidade', localidade, csrf_token));

        await Promise.all(promessas);
        
        currentFooterElement.querySelector('span').innerText = `${rua}, ${cp} ${localidade}`;
        fecharEditorFooter();
        mostrarPopup('Morada atualizada com sucesso!', 'sucesso');

    } else if (seccao === 'redes_sociais') {
        const wa = document.getElementById('footer-edit-wa').value;
        const ig = document.getElementById('footer-edit-ig').value;
        const fb = document.getElementById('footer-edit-fb').value;

        promessas.push(gravarCampo('link_whatsapp', wa, csrf_token));
        promessas.push(gravarCampo('link_instagram', ig, csrf_token));
        promessas.push(gravarCampo('link_facebook', fb, csrf_token));

        await Promise.all(promessas);
        
        // Atualiza os data attributes para a próxima edição
        currentFooterElement.dataset.wa = wa;
        currentFooterElement.dataset.ig = ig;
        currentFooterElement.dataset.fb = fb;
        
        // Atualiza os hrefs dos links visíveis
        const links = currentFooterElement.querySelectorAll('a');
        if (links[0]) links[0].href = wa;
        if (links[1]) links[1].href = ig;
        if (links[2]) links[2].href = fb;

        fecharEditorFooter();
        mostrarPopup('Redes Sociais atualizadas com sucesso!', 'sucesso');

    } else if (seccao === 'horario_funcionamento') {
        const config = recolherHorarioConfig();
        const conteudo = JSON.stringify(config);
        const data = await gravarCampo(seccao, conteudo, csrf_token);

        if (data.sucesso) {
            currentFooterElement.dataset.horario = conteudo;
            currentFooterElement.innerHTML = renderHorarioPreview(config);
            fecharEditorFooter();
            mostrarPopup('Hor\u00e1rio atualizado com sucesso!', 'sucesso');
        } else {
            mostrarPopup(data.mensagem, 'erro');
        }

    } else {
        const conteudo = document.getElementById('footer-edit-conteudo').value;
        let conteudoFormatado = conteudo.replace(/\n/g, '<br>');
        
        const data = await gravarCampo(seccao, conteudo, csrf_token);

        if (data.sucesso) {
            let conteudoFinal = conteudoFormatado;
            
            if (seccao === 'telefone') {
                conteudoFinal = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg> <a href="tel:${conteudo.replace(/\s+/g, '')}">${conteudo}</a>`;
            } else if (seccao === 'email') {
                conteudoFinal = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> <a href="mailto:${conteudo}">${conteudo}</a>`;
            } else if (seccao.startsWith('link_label_')) {
                // Manter o link intacto na atualização visual em tempo real
                let href = "/";
                if(seccao === 'link_label_trocas') href = "/trocas.php";
                if(seccao === 'link_label_envios') href = "/envios.php";
                if(seccao === 'link_label_encomenda') href = "/consultar-encomenda.php";
                conteudoFinal = `<a href="${href}">${conteudoFormatado}</a>`;
            } else if (seccao.startsWith('titulo_')) {
                // Títulos não envolvem com <p>
                conteudoFinal = conteudoFormatado; 
            } else if (seccao !== 'copyright') {
                conteudoFinal = '<p>' + conteudoFormatado + '</p>';
            }

            currentFooterElement.innerHTML = conteudoFinal;
            fecharEditorFooter();
            mostrarPopup(data.mensagem, 'sucesso');
        } else {
            mostrarPopup(data.mensagem, 'erro');
        }
    }
}

function gravarCampo(seccao, conteudo, csrf_token) {
    return fetch('/dev/ajax_save_footer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ seccao, conteudo, csrf_token })
    }).then(r => r.json());
}

/* ============================================================
   EDIÇÃO INLINE (sem modal) — para elementos de TEXTO que não são
   links/botões/imagens. Reaproveita os endpoints existentes:
     .home-editable   -> /dev/ajax_save_home.php   (chave)
     .footer-editable -> /dev/ajax_save_footer.php (seccao)
     .header-editable -> /dev/ajax_save_header.php (seccao)
   Os elementos que SÃO links/imagens/multi-campo continuam com modal.
   ============================================================ */
(function () {
    const CSRF = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';

    // Secções/chaves que TÊM de continuar a abrir modal (imagem ou multi-campo).
    const MODAL_ONLY = ['logo_src', 'home_hero_bg', 'contactos_info', 'redes_sociais', 'horario_funcionamento'];

    function ehEditavelInline(el) {
        if (!el) return false;
        if (el.tagName === 'A' || el.tagName === 'BUTTON') return false; // é um link/botão
        if (el.closest('a, button')) return false;                       // está dentro de um link/botão
        if (el.querySelector('a, button')) return false;                 // contém um link/botão
        const chave = el.dataset.seccao || el.dataset.chave || '';
        if (MODAL_ONLY.includes(chave)) return false;                    // imagem / multi-campo
        return true;
    }

    function rota(el) {
        if (el.classList.contains('home-editable'))   return { url: '/dev/ajax_save_home.php',   campo: 'chave',  valor: el.dataset.chave };
        if (el.classList.contains('header-editable')) return { url: '/dev/ajax_save_header.php', campo: 'seccao', valor: el.dataset.seccao };
        return { url: '/dev/ajax_save_footer.php', campo: 'seccao', valor: el.dataset.seccao };
    }

    async function guardarInline(el) {
        const novo = el.innerText.trim();
        if (novo === (el.dataset.valorOriginal ?? '')) return; // sem alterações

        const r = rota(el);
        if (!r.valor) return;
        const corpo = { conteudo: novo, csrf_token: CSRF };
        corpo[r.campo] = r.valor;

        el.classList.add('inline-a-guardar');
        try {
            const resp = await fetch(r.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(corpo),
            });
            const data = await resp.json();
            if (data.sucesso) {
                el.dataset.valorOriginal = novo;
                if (typeof mostrarPopup === 'function') mostrarPopup(data.mensagem || 'Guardado.', 'sucesso');
            } else {
                if (typeof mostrarPopup === 'function') mostrarPopup(data.mensagem || 'Erro ao guardar.', 'erro');
            }
        } catch (err) {
            if (typeof mostrarPopup === 'function') mostrarPopup('Erro de ligação ao guardar.', 'erro');
        } finally {
            el.classList.remove('inline-a-guardar');
        }
    }

    function ativarInline() {
        if (window.innerWidth <= 992) return; // edição só em desktop (igual ao modal)
        document.querySelectorAll('.home-editable, .footer-editable, .header-editable').forEach((el) => {
            if (!ehEditavelInline(el) || el.classList.contains('inline-editavel')) return;

            el.classList.add('inline-editavel');
            el.setAttribute('contenteditable', 'true');
            el.setAttribute('spellcheck', 'false');

            el.addEventListener('focus', () => { el.dataset.valorOriginal = el.innerText.trim(); });
            el.addEventListener('blur', () => guardarInline(el));
            el.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); el.blur(); }
                else if (e.key === 'Escape') {
                    el.innerText = el.dataset.valorOriginal ?? el.innerText;
                    el.blur();
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ativarInline);
    } else {
        ativarInline();
    }
})();

document.getElementById('header-edit-logo')?.addEventListener('change', function(e) {
    const file = e.target.files && e.target.files[0];
    if (!file) return;

    const previewContainer = document.getElementById('header-logo-preview-container');
    const cropperContainer = document.getElementById('header-cropper-container');
    const cropperActions = document.getElementById('header-cropper-actions');
    const cropperImg = document.getElementById('header-cropper-img');

    // Mudar de preview normal para Cropper
    previewContainer.style.display = 'none';
    cropperContainer.style.display = 'flex';
    cropperActions.style.display = 'flex';

    if (window.headerCropper) {
        window.headerCropper.destroy();
    }

    cropperImg.src = URL.createObjectURL(file);
    
    // Pequeno delay para garantir que a imagem tem dimensões antes de iniciar o cropper
    setTimeout(() => {
        window.headerCropper = new Cropper(cropperImg, {
            aspectRatio: 1, // Corte 1:1 obrigatório
            viewMode: 1,
            autoCropArea: 0.9,
            background: false,
        });
    }, 50);
});
</script>
<?php endif; ?>

<div id="side-cart-overlay" class="side-cart-overlay"></div>
<div id="side-cart" class="side-cart">
    <div class="side-cart-header">
        <h4>O Meu Carrinho</h4>
        <button id="btn-fechar-side-cart" class="btn-fechar-side-cart" type="button" aria-label="Fechar carrinho">&times;</button>
    </div>
    <div id="side-cart-items" class="side-cart-items"></div>
    <div class="side-cart-footer">
        <div id="side-cart-subtotal" class="side-cart-subtotal">
            <span>Subtotal</span>
            <span>€0,00</span>
        </div>
        <a href="/carrinho.php" class="button voltar-btn">Ver Carrinho</a>
        <a href="/checkout" id="side-cart-finalizar" class="button add-btn">Finalizar Encomenda</a>
    </div>
</div>

<a href="#" id="voltarAoTopoBtn" class="voltar-ao-topo-btn" title="Voltar ao topo" aria-label="Voltar ao topo">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="18 15 12 9 6 15"></polyline>
    </svg>
</a>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<script src="/public/js/popup.js?v=<?php echo $versao_global; ?>"></script>
<script src="/public/js/confirmacao.js?v=<?php echo $versao_global; ?>"></script>
<script src="/public/js/carrinho.js?v=<?php echo $versao_global; ?>"></script>
<script src="/public/js/menu.js?v=<?php echo $versao_global; ?>"></script>
<script src="/public/js/header-scroll.js?v=<?php echo $versao_global; ?>"></script>
<script src="/public/js/utils.js?v=<?php echo $versao_global; ?>"></script>
<script src="/public/js/search.js?v=<?php echo $versao_global; ?>"></script>
<script src="/public/js/backToTop.js?v=<?php echo $versao_global; ?>"></script>

<?php if (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false): ?>
    <script src="/public/js/admin-ui.js?v=<?php echo $versao_global; ?>"></script>
<?php endif; ?>

<?php 
$currentScript = basename($_SERVER['PHP_SELF']);
if ($currentScript == 'entrar.php' || $currentScript == 'recuperar-conta.php' || $currentScript == 'redefinir-conta.php'): ?>
    <script src="/public/js/login_animation.js?v=<?php echo $versao_global; ?>"></script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    window.ativarTooltipsProfissionais = function() {
        if (window.innerWidth <= 768) {
            const produtos = document.querySelectorAll('.produto');
            produtos.forEach(produto => {
                const titulo = produto.querySelector('h4');
                if (titulo && titulo._tippy) {
                    titulo._tippy.destroy();
                }
            });
            return;
        }

        setTimeout(function() {
            const produtos = document.querySelectorAll('.produto');
            produtos.forEach(produto => {
                const titulo = produto.querySelector('h4');
                if (!titulo) return;

                if (titulo._tippy) {
                    titulo._tippy.destroy();
                }

                const isOverflowing = Math.ceil(titulo.scrollWidth) > titulo.clientWidth;
                if (isOverflowing) {
                    tippy(produto, {
                        content: titulo.textContent.trim(),
                        placement: 'bottom',
                        animation: 'shift-away-subtle',
                        theme: 'toptop-professional',
                        arrow: true,
                        delay: [200, 100],
                        touch: ['hold', 75],
                        popperOptions: {
                            modifiers: [{ name: 'flip', enabled: false }]
                        }
                    });
                }
            });
        }, 150);
    }
    
    window.ativarTooltipsProfissionais();

    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(window.ativarTooltipsProfissionais, 250);
    });

    // ── Toggle visibilidade de password ──
    document.querySelectorAll('.toggle-senha').forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            const input = document.getElementById(this.getAttribute('data-target'));
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            this.style.opacity = input.type === 'password' ? '1' : '0.5';
        });
    });

    // ── Limpar Cache via Header (Dev Only) ──
    const btnHeaderCache = document.getElementById('btn-header-cache');
    if (btnHeaderCache) {
        btnHeaderCache.addEventListener('click', function() {
            btnHeaderCache.classList.add('loading');
            
            fetch('/dev/ajax_limpar_cache_global.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>' })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.sucesso) {
                        mostrarPopup('Cache limpo! Versão: ' + data.nova_versao, 'sucesso');
                        setTimeout(() => location.reload(), 1800);
                    } else {
                        mostrarPopup('Erro: ' + (data.mensagem || 'Desconhecido'), 'erro');
                        btnHeaderCache.classList.remove('loading');
                    }
                })
                .catch(() => {
                    mostrarPopup('Erro de ligação.', 'erro');
                    btnHeaderCache.classList.remove('loading');
                });
        });
    }
});
</script>
</body>
</html>
