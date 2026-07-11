<?php
$titulo_pagina = 'Contacto';
$descricao_pagina = 'Fale connosco. Encontre a morada, telefone, email e horário da TopTop, ou envie-nos uma mensagem directamente.';
include 'templates/header.php';
require_once __DIR__ . '/config/footer_functions.php';

$isAdmin = isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true && in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor']);
$adminClass = $isAdmin ? 'footer-editable' : '';
$horarioFuncionamentoRaw = getFooterText('horario_funcionamento', '');

$popupMensagem = "";
$popupTipo = "sucesso";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_from_post()) {
        $popupMensagem = "Erro de segurança. Recarregue a página e tente novamente.";
        $popupTipo = "erro";
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mensagem = trim($_POST['mensagem'] ?? '');

        if (empty($nome) || empty($email) || empty($mensagem)) {
            $popupMensagem = "Por favor, preencha todos os campos.";
            $popupTipo = "erro";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $popupMensagem = "O endereço de email que inseriu não é válido.";
            $popupTipo = "erro";
        } elseif (strlen($mensagem) > 1000) {
            $popupMensagem = "A sua mensagem excedeu o limite de 1000 caracteres.";
            $popupTipo = "erro";
        } else {
            $data_agora = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("INSERT INTO contactos (nome, email, mensagem, data_hora) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nome, $email, $mensagem, $data_agora);

            if ($stmt->execute()) {
                $popupMensagem = "Mensagem enviada com sucesso! Responderemos assim que possível.";
                $popupTipo = "sucesso";
            } else {
                $popupMensagem = "Ocorreu um erro ao enviar a mensagem. Por favor, tente novamente.";
                $popupTipo = "erro";
            }
            $stmt->close();
        }
    }
}
?>

<style>
.footer-editable-contacto svg { display: none !important; }
.footer-editable-contacto { display: inline-block; border-radius: 4px; }
</style>

<main class="pagina-contacto">

    <div class="contacto-hero" data-reveal>
        <p class="contacto-kicker">Contacto</p>
        <h1 class="contacto-titulo">Fale Connosco</h1>
        <p class="contacto-sub">Tem alguma questão ou quer visitar-nos? Estamos aqui para ajudar.</p>
    </div>

    <div class="contacto-grid" data-reveal>

        <div class="info-coluna">
            <h3>Informação de Contacto</h3>

            <div class="info-item">
                <div class="info-item-icona">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                </div>
                <div class="info-item-texto">
                    <h4>Morada</h4>
                    <p class="<?php echo $adminClass; ?> footer-editable-contacto" data-seccao="contactos_info">
                        <?php echo getFooterText('morada_rua', 'Edifício Chafariz, Rua dos Fontenários'); ?>,
                        <?php echo getFooterText('morada_cp', '4535-221'); ?>
                        <?php echo getFooterText('morada_localidade', 'Lourosa, Portugal'); ?>
                    </p>
                </div>
            </div>

            <div class="info-item">
                <div class="info-item-icona">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                </div>
                <div class="info-item-texto">
                    <h4>Telefone</h4>
                    <p class="<?php echo $adminClass; ?> footer-editable-contacto" data-seccao="telefone">
                        <a href="tel:<?php echo getFooterText('telefone', '351933169009'); ?>"><?php echo getFooterText('telefone', '(+351) 933 169 009'); ?></a>
                    </p>
                </div>
            </div>

            <div class="info-item">
                <div class="info-item-icona">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                </div>
                <div class="info-item-texto">
                    <h4>Email</h4>
                    <p class="<?php echo $adminClass; ?> footer-editable-contacto" data-seccao="email">
                        <a href="mailto:<?php echo getFooterText('email', 'toptopclothingstore@gmail.com'); ?>"><?php echo getFooterText('email', 'toptopclothingstore@gmail.com'); ?></a>
                    </p>
                </div>
            </div>

            <div class="info-item">
                <div class="info-item-icona">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                </div>
                <div class="info-item-texto">
                    <h4>Horário</h4>
                    <div class="<?php echo trim($adminClass . ' horario-contacto'); ?>"
                         data-seccao="horario_funcionamento"
                         data-horario="<?php echo htmlspecialchars($horarioFuncionamentoRaw, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo renderHorarioFuncionamento('Por consulta (temporário)'); ?>
                    </div>
                </div>
            </div>

            <div class="info-item">
                <div class="info-item-icona">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>
                </div>
                <div class="info-item-texto">
                    <h4>Redes Sociais</h4>
                    <div class="contacto-social-links <?php echo $adminClass; ?>" data-seccao="redes_sociais"
                         data-wa="<?php echo getFooterText('link_whatsapp', 'https://chat.whatsapp.com/K7IhtBOBJNtHRGysYttqnY?fbclid=PAZXh0bgNhZW0CMTEAAafGr0PtcF54B-Xs-3vTdfud9IjlLX7_8aIYPl4AbcuzcyR6YggHigJmb1gQ0g_aem_FI_fPyaedJRcm1Ctl243uA'); ?>"
                         data-ig="<?php echo getFooterText('link_instagram', 'https://www.instagram.com/toptop_clothingstore'); ?>"
                         data-fb="<?php echo getFooterText('link_facebook', 'https://www.facebook.com/share/1AqqQZ8YmL/'); ?>">
                        <a href="<?php echo getFooterText('link_whatsapp', 'https://chat.whatsapp.com/K7IhtBOBJNtHRGysYttqnY?fbclid=PAZXh0bgNhZW0CMTEAAafGr0PtcF54B-Xs-3vTdfud9IjlLX7_8aIYPl4AbcuzcyR6YggHigJmb1gQ0g_aem_FI_fPyaedJRcm1Ctl243uA'); ?>" target="_blank" title="WhatsApp" class="contacto-social-link wa">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                        </a>
                        <a href="<?php echo getFooterText('link_instagram', 'https://www.instagram.com/toptop_clothingstore'); ?>" target="_blank" title="Instagram" class="contacto-social-link ig">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                        </a>
                        <a href="<?php echo getFooterText('link_facebook', 'https://www.facebook.com/share/1AqqQZ8YmL/'); ?>" target="_blank" title="Facebook" class="contacto-social-link fb">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-coluna">
            <h3>Envie-nos uma Mensagem</h3>
            <form id="formContacto" action="/contacto.php" method="post">
                <?php echo csrf_input(); ?>
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" required autocomplete="name">

                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="email">

                <label for="mensagem">Mensagem</label>
                <textarea id="mensagem" name="mensagem" rows="6" required maxlength="1000"></textarea>
                <div class="contador-caracteres">
                    <span id="caracteresAtuais">0</span> / 1000
                </div>

                <button type="submit" class="contacto-submit">Enviar Mensagem</button>
            </form>
        </div>

    </div>

    <div class="contacto-mapa-head" data-reveal>
        <p class="contacto-kicker">Onde Estamos</p>
        <h2 class="contacto-titulo" style="font-size:clamp(1.6rem,3vw,2.2rem);">Visite-nos</h2>
    </div>

    <div class="mapa-wrapper-full" data-reveal>
        <iframe
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3011.918137026178!2d-8.539019487734008!3d40.98327337123456!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xd247f5b2be8d2d3%3A0x221c8b982d8842cd!2sChafariz!5e0!3m2!1spt-PT!2spt!4v1757012161468!5m2!1spt-PT!2spt"
            width="600"
            height="450"
            style="border:0;"
            allowfullscreen=""
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade">
        </iframe>
    </div>

</main>

<script>
<?php if (!empty($popupMensagem)): ?>
document.addEventListener('DOMContentLoaded', function () {
    mostrarPopup("<?php echo addslashes($popupMensagem); ?>", "<?php echo $popupTipo; ?>");
});
<?php endif; ?>

const mensagemTextarea = document.getElementById('mensagem');
const caracteresAtuaisSpan = document.getElementById('caracteresAtuais');

mensagemTextarea.addEventListener('input', () => {
    caracteresAtuaisSpan.textContent = mensagemTextarea.value.length;
});

// Reveal on scroll — apenas design (progressive enhancement, igual à home)
(function () {
    var main = document.querySelector('.pagina-contacto');
    if (!main) return;
    if (!('IntersectionObserver' in window)) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    main.classList.add('reveal-armed');
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('revealed');
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });

    main.querySelectorAll('[data-reveal]').forEach(function (el) { io.observe(el); });
})();
</script>

<?php include 'templates/footer.php'; ?>
