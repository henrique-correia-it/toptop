<?php
$titulo_pagina = 'Trocas e Devoluções';
$descricao_pagina = 'Política de trocas da TopTop: prazos, condições e como proceder. Trocas simples até 6 dias após a receção da tua encomenda.';
include 'templates/header.php';

// Modo de edição global (apenas superadmin/desenvolvedor com edição ativada — definido no header.php).
$isGlobalEditMode = $isAdminHeader ?? false;

// Conteúdo padrão (seed) — usado enquanto não houver conteúdo guardado em BD.
$trocas_default = [
    'kicker' => 'Apoio ao Cliente',
    'titulo' => 'Política de Trocas',
    'intro'  => 'Garantimos um processo de troca simples e transparente para que possas comprar com total confiança.',
    'cards'  => [
        [
            'titulo' => 'Prazos e Condições',
            'icon'   => 'clock',
            'itens'  => [
                'Aceitamos trocas até 6 dias após a receção da sua encomenda.',
                'O artigo deve estar em perfeitas condições, com etiquetas e sem sinais de uso.',
                'Não aceitamos trocas de artigos em promoção, de festa ou acessórios por motivos de higiene e rotatividade.',
            ],
        ],
        [
            'titulo' => 'Como Proceder',
            'icon'   => 'package',
            'itens'  => [
                'Contacte-nos previamente informando qual o artigo que pretende trocar.',
                'Inclua dentro da embalagem o seu nome de utilizador (Instagram ou Facebook).',
                'Os portes de envio da devolução e do novo envio são da responsabilidade da cliente.',
            ],
        ],
        [
            'titulo' => 'Saldo e Utilização',
            'icon'   => 'card',
            'itens'  => [
                'Após a receção e validação do artigo, o valor ficará em saldo na sua conta.',
                'Dispõe de 1 mês para utilizar o valor em saldo em qualquer nova compra.',
                'O saldo é pessoal, intransmissível e não convertível em dinheiro.',
            ],
        ],
    ],
];

// Carrega conteúdo editável (JSON em loja_configuracoes) com fallback para o seed.
$raw_trocas = function_exists('getLojaConfig') ? getLojaConfig('trocas_content', null) : null;
$trocas = $raw_trocas ? json_decode($raw_trocas, true) : null;
if (!is_array($trocas)) {
    $trocas = $trocas_default;
}
$trocas['kicker'] = $trocas['kicker'] ?? '';
$trocas['titulo'] = $trocas['titulo'] ?? '';
$trocas['intro']  = $trocas['intro'] ?? '';
$trocas['cards']  = (isset($trocas['cards']) && is_array($trocas['cards'])) ? $trocas['cards'] : [];

$ed = $isGlobalEditMode;

// Conjunto de ícones disponíveis (chave => SVG). Guardamos só a CHAVE em BD e
// resolvemos aqui no servidor — nunca aceitamos SVG vindo do cliente (evita XSS).
$troca_icones = [
    'exchange' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg>',
    'clock'    => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
    'shield'   => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
    'package'  => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>',
    'card'     => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>',
    'truck'    => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>',
];
$troca_icone_default = 'exchange';
$troca_icone = function ($chave) use ($troca_icones, $troca_icone_default) {
    return $troca_icones[$chave] ?? $troca_icones[$troca_icone_default];
};
?>

<main class="pagina-info info-premium<?php echo $ed ? ' troca-editavel' : ''; ?>"<?php echo $ed ? ' data-troca-editor' : ''; ?>>
    <div class="pagina-info-header">
        <p class="info-kicker"<?php echo $ed ? ' data-field="kicker" contenteditable="true" data-placeholder="Kicker (ex: Apoio ao Cliente)"' : ''; ?>><?php echo htmlspecialchars($trocas['kicker']); ?></p>
        <h2<?php echo $ed ? ' data-field="titulo" contenteditable="true" data-placeholder="Título da página"' : ''; ?>><?php echo htmlspecialchars($trocas['titulo']); ?></h2>
        <p<?php echo $ed ? ' data-field="intro" contenteditable="true" data-placeholder="Texto introdutório"' : ''; ?>><?php echo htmlspecialchars($trocas['intro']); ?></p>
    </div>

    <div class="info-grid">
        <?php foreach ($trocas['cards'] as $card):
            $card_titulo = $card['titulo'] ?? '';
            $card_itens  = (isset($card['itens']) && is_array($card['itens'])) ? $card['itens'] : [];
            $card_icone  = ($card['icon'] ?? '') && array_key_exists($card['icon'], $troca_icones) ? $card['icon'] : $troca_icone_default;
        ?>
            <div class="info-card<?php echo $ed ? ' troca-card' : ''; ?>">
                <?php if ($ed): ?><button type="button" class="troca-del-card" title="Remover card" aria-label="Remover card">&times;</button><?php endif; ?>
                <h3>
                    <?php if ($ed): ?>
                        <button type="button" class="troca-icon-btn" data-icon="<?php echo htmlspecialchars($card_icone); ?>" title="Mudar ícone" aria-label="Mudar ícone"><?php echo $troca_icone($card_icone); ?></button>
                        <span class="troca-card-title" contenteditable="true" data-placeholder="Título do card"><?php echo htmlspecialchars($card_titulo); ?></span>
                    <?php else: ?>
                        <?php echo $troca_icone($card_icone); ?>
                        <?php echo htmlspecialchars($card_titulo); ?>
                    <?php endif; ?>
                </h3>
                <ul>
                    <?php foreach ($card_itens as $item): ?>
                        <li<?php echo $ed ? ' class="troca-item"' : ''; ?>>
                            <?php if ($ed): ?>
                                <span class="troca-item-text" contenteditable="true" data-placeholder="Texto da linha"><?php echo htmlspecialchars($item); ?></span>
                                <button type="button" class="troca-del-item" title="Remover linha" aria-label="Remover linha">&times;</button>
                            <?php else: ?>
                                <?php echo htmlspecialchars($item); ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($ed): ?><button type="button" class="troca-add-item">+ Adicionar linha</button><?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if ($ed): ?>
            <button type="button" class="troca-add-card-tile" aria-label="Adicionar card">
                <span class="troca-add-card-plus">+</span>
                <span>Adicionar card</span>
            </button>
        <?php endif; ?>
    </div>
</main>

<?php if ($ed): ?>
<template id="troca-card-template">
    <div class="info-card troca-card">
        <button type="button" class="troca-del-card" title="Remover card" aria-label="Remover card">&times;</button>
        <h3>
            <button type="button" class="troca-icon-btn" data-icon="<?php echo $troca_icone_default; ?>" title="Mudar ícone" aria-label="Mudar ícone"><?php echo $troca_icone($troca_icone_default); ?></button>
            <span class="troca-card-title" contenteditable="true" data-placeholder="Título do card"></span>
        </h3>
        <ul>
            <li class="troca-item">
                <span class="troca-item-text" contenteditable="true" data-placeholder="Texto da linha"></span>
                <button type="button" class="troca-del-item" title="Remover linha" aria-label="Remover linha">&times;</button>
            </li>
        </ul>
        <button type="button" class="troca-add-item">+ Adicionar linha</button>
    </div>
</template>

<template id="troca-item-template">
    <li class="troca-item">
        <span class="troca-item-text" contenteditable="true" data-placeholder="Texto da linha"></span>
        <button type="button" class="troca-del-item" title="Remover linha" aria-label="Remover linha">&times;</button>
    </li>
</template>

<div id="troca-icon-popover" class="troca-icon-popover" hidden>
    <?php foreach ($troca_icones as $chave => $svg): ?>
        <button type="button" class="troca-icon-option" data-icon="<?php echo htmlspecialchars($chave); ?>" aria-label="Ícone <?php echo htmlspecialchars($chave); ?>"><?php echo $svg; ?></button>
    <?php endforeach; ?>
</div>

<script>
(function () {
    const TROCA_CSRF = '<?php echo function_exists('csrf_token') ? csrf_token() : ($_SESSION['csrf_token'] ?? ''); ?>';
    const editor = document.querySelector('[data-troca-editor]');
    if (!editor) return;

    const grid = editor.querySelector('.info-grid');
    const tile = grid.querySelector('.troca-add-card-tile');
    const cardTpl = document.getElementById('troca-card-template');
    const itemTpl = document.getElementById('troca-item-template');
    const popover = document.getElementById('troca-icon-popover');
    let activeIconBtn = null;

    const notificar = (msg, tipo) => {
        if (typeof mostrarPopup === 'function') mostrarPopup(msg, tipo);
    };

    // --- Auto-save (debounced): grava sozinho ao perder o foco ou ao
    //     adicionar/remover linha/card e trocar ícone. Sem botão de guardar. ---
    let saveTimer = null;
    let aGravar = false;
    let ultimoGuardado = JSON.stringify(serializar());

    function agendarGravacao() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(gravar, 700);
    }

    async function gravar() {
        const conteudo = serializar();
        const json = JSON.stringify(conteudo);
        if (json === ultimoGuardado) return;        // nada mudou
        if (aGravar) { agendarGravacao(); return; } // gravação a decorrer, reagenda
        aGravar = true;
        try {
            const res = await fetch('/dev/ajax_save_trocas.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ conteudo, csrf_token: TROCA_CSRF }),
            });
            const data = await res.json();
            if (data.sucesso) {
                ultimoGuardado = json;
                notificar('Alterações guardadas.', 'sucesso');
            } else {
                notificar(data.mensagem || 'Erro ao guardar.', 'erro');
            }
        } catch (err) {
            notificar('Erro de ligação ao guardar.', 'erro');
        } finally {
            aGravar = false;
        }
    }

    // Grava quando um campo editável perde o foco.
    editor.addEventListener('blur', (e) => {
        if (e.target.isContentEditable) agendarGravacao();
    }, true);

    function focarFim(el) {
        el.focus();
        const range = document.createRange();
        range.selectNodeContents(el);
        range.collapse(false);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    }

    function novaLinha(ul) {
        const li = itemTpl.content.cloneNode(true).firstElementChild;
        ul.appendChild(li);
        return li.querySelector('.troca-item-text');
    }

    // --- Seletor de ícones (popover) ---
    function abrirPopover(btn) {
        activeIconBtn = btn;
        const r = btn.getBoundingClientRect();
        popover.hidden = false;
        popover.style.top = (window.scrollY + r.bottom + 8) + 'px';
        popover.style.left = (window.scrollX + r.left) + 'px';
    }
    function fecharPopover() {
        popover.hidden = true;
        activeIconBtn = null;
    }
    popover.addEventListener('click', (e) => {
        const opt = e.target.closest('.troca-icon-option');
        if (!opt || !activeIconBtn) return;
        activeIconBtn.dataset.icon = opt.dataset.icon;
        activeIconBtn.innerHTML = opt.innerHTML; // SVG vem do servidor (confiável)
        fecharPopover();
        agendarGravacao();
    });
    document.addEventListener('click', (e) => {
        if (popover.hidden) return;
        if (e.target.closest('#troca-icon-popover, .troca-icon-btn')) return;
        fecharPopover();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') fecharPopover();
    });

    // --- Delegação de cliques (adicionar/remover/ícone) ---
    editor.addEventListener('click', (e) => {
        const iconBtn = e.target.closest('.troca-icon-btn');
        if (iconBtn) {
            e.stopPropagation();
            (activeIconBtn === iconBtn) ? fecharPopover() : abrirPopover(iconBtn);
            return;
        }

        const addItemBtn = e.target.closest('.troca-add-item');
        if (addItemBtn) {
            const ul = addItemBtn.closest('.info-card').querySelector('ul');
            focarFim(novaLinha(ul));
            return;
        }

        const delItemBtn = e.target.closest('.troca-del-item');
        if (delItemBtn) {
            const ul = delItemBtn.closest('ul');
            delItemBtn.closest('li').remove();
            if (!ul.querySelector('li')) focarFim(novaLinha(ul)); // nunca deixar lista vazia
            agendarGravacao();
            return;
        }

        const delCardBtn = e.target.closest('.troca-del-card');
        if (delCardBtn) {
            delCardBtn.closest('.info-card').remove();
            agendarGravacao();
            return;
        }

        const addCardBtn = e.target.closest('.troca-add-card-tile');
        if (addCardBtn) {
            const card = cardTpl.content.cloneNode(true).firstElementChild;
            grid.insertBefore(card, tile);
            focarFim(card.querySelector('.troca-card-title'));
            return;
        }
    });

    // --- Teclado: Enter cria nova linha (em itens) ou sai do campo (títulos) ---
    editor.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;

        const item = e.target.closest('.troca-item-text');
        if (item) {
            e.preventDefault();
            focarFim(novaLinha(item.closest('ul')));
            return;
        }

        // Campos de uma só linha: Enter confirma (não insere quebra).
        if (e.target.closest('.troca-card-title, [data-field="kicker"], [data-field="titulo"], [data-field="intro"]')) {
            e.preventDefault();
            e.target.blur();
        }
    });

    // --- Serialização do DOM -> estrutura JSON ---
    function serializar() {
        const txt = (sel) => (editor.querySelector(sel)?.innerText || '').trim();
        const cards = [...grid.querySelectorAll('.troca-card')].map((card) => ({
            titulo: (card.querySelector('.troca-card-title')?.innerText || '').trim(),
            icon: card.querySelector('.troca-icon-btn')?.dataset.icon || 'exchange',
            itens: [...card.querySelectorAll('.troca-item-text')]
                .map((s) => s.innerText.trim())
                .filter(Boolean),
        }));
        return {
            kicker: txt('[data-field="kicker"]'),
            titulo: txt('[data-field="titulo"]'),
            intro: txt('[data-field="intro"]'),
            cards,
        };
    }

})();
</script>
<?php endif; ?>

<?php include 'templates/footer.php'; ?>
