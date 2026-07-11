<?php
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true ||
    !in_array($_SESSION['admin_role'], ['superadmin', 'desenvolvedor'])) {
    header("Location: /admin"); exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../config/database.php';

$result_cats = $conn->query("SELECT * FROM categorias ORDER BY id ASC");
$categorias  = ($result_cats !== false) ? $result_cats->fetch_all(MYSQLI_ASSOC) : [];

$titulo_pagina = 'Gerir Categorias';

include '../templates/header.php';
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
            <?php renderBackButton('/admin', 'Painel'); ?>
            <h2>Gerir Categorias</h2>
        </div>
        <div class="header-actions-container">
            <button type="button" class="btn-primary btn-with-plus btn-with-plus-text" id="btn-nova-categoria">
                Nova Categoria
            </button>
        </div>
    </div>

    <div class="gc-wrap">
        <div class="gc-list" id="gc-list">
            <?php if (empty($categorias)): ?>
                <div class="gc-empty" id="gc-empty-msg">Ainda não existem categorias. Cria a primeira acima.</div>
            <?php else: ?>
                <?php foreach ($categorias as $cat): ?>
                <div class="gc-item" data-id="<?php echo $cat['id']; ?>">


                    <div class="gc-foto" id="gc-foto-wrap-<?php echo $cat['id']; ?>"
                         onclick="abrirUploadFoto(<?php echo $cat['id']; ?>)"
                         title="Clica para alterar a foto de capa">
                        <?php if ($cat['foto_capa']): ?>
                            <img src="/public/<?php echo htmlspecialchars($cat['foto_capa']); ?>"
                                 alt="<?php echo htmlspecialchars($cat['nome']); ?>"
                                 id="gc-foto-img-<?php echo $cat['id']; ?>">
                        <?php else: ?>
                            <div class="gc-foto-placeholder" id="gc-foto-img-<?php echo $cat['id']; ?>">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21 15 16 10 5 21"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                        <div class="gc-foto-overlay"><span>Alterar</span></div>
                        <input type="file"
                               class="gc-foto-input"
                               id="gc-foto-input-<?php echo $cat['id']; ?>"
                               accept="image/*"
                               style="display:none;"
                               data-cat-id="<?php echo $cat['id']; ?>">
                    </div>

                    <div class="gc-info">
                        <input type="text"
                               class="gc-nome-input"
                               id="gc-nome-<?php echo $cat['id']; ?>"
                               value="<?php echo htmlspecialchars($cat['nome']); ?>"
                               data-original="<?php echo htmlspecialchars($cat['nome']); ?>"
                               data-cat-id="<?php echo $cat['id']; ?>">
                        <div class="gc-meta">
                            <label class="toggle-visiveis" title="Alternar visibilidade">
                                <input type="checkbox"
                                       class="gc-toggle-visivel"
                                       data-cat-id="<?php echo $cat['id']; ?>"
                                       <?php echo $cat['visivel'] ? 'checked' : ''; ?>>
                                <div class="toggle-track"><div class="toggle-thumb"></div></div>
                            </label>
                            <span class="gc-tag <?php echo $cat['visivel'] ? 'visivel' : 'invisivel'; ?>"
                                  id="gc-tag-<?php echo $cat['id']; ?>">
                                <?php echo $cat['visivel'] ? 'Pág. Inicial' : 'Oculta Home'; ?>
                            </span>
                        </div>
                    </div>

                    <div class="gc-acoes">
                        <button type="button"
                                class="gc-btn-guardar"
                                id="gc-btn-guardar-<?php echo $cat['id']; ?>"
                                data-cat-id="<?php echo $cat['id']; ?>"
                                style="display:none;">
                            Guardar
                        </button>

                        <button type="button"
                                class="btn-del-single gc-btn-apagar"
                                data-cat-id="<?php echo $cat['id']; ?>"
                                data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"
                                title="Apagar categoria">
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- ── Modal: Nova Categoria ─────────────────────────────────────────────── -->
<div id="modal-nova-cat" class="qe-modal">
    <div class="qe-card modal-cat-card">
        <div class="qe-hd">
            <h3>Nova Categoria</h3>
            <button type="button" class="btn-close-unified qe-close" id="btn-fechar-modal-cat" title="Fechar">&times;</button>
        </div>
        <div class="qe-body">
            <div class="qe-f">
                <label for="nova-cat-nome">Nome da Categoria</label>
                <input type="text" id="nova-cat-nome" class="qe-in"
                       placeholder="Ex: Novidades" autocomplete="off">
            </div>
        </div>
        <div class="qe-footer">
            <button type="button" class="btn-admin-secondary" id="btn-cancelar-modal-cat">Cancelar</button>
            <button type="button" class="btn-admin-primary" id="btn-criar-categoria">Criar Categoria</button>
        </div>
    </div>
</div>


<script>
const CSRF = <?php echo json_encode($_SESSION['csrf_token'], JSON_HEX_TAG | JSON_HEX_AMP); ?>;

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function ajaxCat(dados) {
    return fetch('ajax_categoria.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...dados, csrf_token: CSRF })
    }).then(r => r.json());
}

const modalNovaCat = document.getElementById('modal-nova-cat');
const inputNovaNome = document.getElementById('nova-cat-nome');
const gcList = document.getElementById('gc-list');
const btnCriarCategoria = document.getElementById('btn-criar-categoria');

document.getElementById('btn-nova-categoria')?.addEventListener('click', () => {
    inputNovaNome.value = '';
    modalNovaCat.classList.add('ativo');
    setTimeout(() => inputNovaNome.focus(), 50);
});

document.getElementById('btn-fechar-modal-cat')?.addEventListener('click', () => modalNovaCat.classList.remove('ativo'));
document.getElementById('btn-cancelar-modal-cat')?.addEventListener('click', () => modalNovaCat.classList.remove('ativo'));
modalNovaCat?.addEventListener('click', e => { if (e.target === modalNovaCat) modalNovaCat.classList.remove('ativo'); });
inputNovaNome?.addEventListener('keydown', e => { if (e.key === 'Enter') btnCriarCategoria.click(); });

btnCriarCategoria?.addEventListener('click', function() {
    const nome = inputNovaNome.value.trim();
    if (!nome) {
        mostrarPopup('Escreve o nome da categoria.', 'erro');
        return;
    }

    this.disabled = true;
    this.textContent = 'A criar...';

    ajaxCat({ acao: 'criar', nome })
        .then(data => {
            if (data.sucesso) {
                modalNovaCat.classList.remove('ativo');
                mostrarPopup(data.mensagem, 'sucesso');
                adicionarLinhaCategoria(data.id, nome);
            } else {
                mostrarPopup(data.mensagem, 'erro');
            }
        })
        .catch(() => mostrarPopup('Erro de ligação.', 'erro'))
        .finally(() => {
            this.disabled = false;
            this.textContent = 'Criar Categoria';
        });
});

function adicionarLinhaCategoria(id, nome) {
    document.getElementById('gc-empty-msg')?.remove();
    const safeNome = escapeHtml(nome);

    const html = `
    <div class="gc-item" data-id="${id}">
        <div class="gc-foto" id="gc-foto-wrap-${id}" onclick="abrirUploadFoto(${id})" title="Clica para alterar a foto de capa">
            <div class="gc-foto-placeholder" id="gc-foto-img-${id}">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <polyline points="21 15 16 10 5 21"/>
                </svg>
            </div>
            <div class="gc-foto-overlay"><span>Alterar</span></div>
            <input type="file" class="gc-foto-input" id="gc-foto-input-${id}" accept="image/*" style="display:none;" data-cat-id="${id}">
        </div>
        <div class="gc-info">
            <input type="text" class="gc-nome-input" id="gc-nome-${id}" value="${safeNome}" data-original="${safeNome}" data-cat-id="${id}">
            <div class="gc-meta">
                <label class="toggle-visiveis" title="Alternar visibilidade">
                    <input type="checkbox" class="gc-toggle-visivel" data-cat-id="${id}" checked>
                    <div class="toggle-track"><div class="toggle-thumb"></div></div>
                </label>
                <span class="gc-tag visivel" id="gc-tag-${id}">Pág. Inicial</span>
            </div>
        </div>
        <div class="gc-acoes">
            <button type="button" class="gc-btn-guardar" id="gc-btn-guardar-${id}" data-cat-id="${id}" style="display:none;">Guardar</button>
            <button type="button" class="btn-del-single gc-btn-apagar" data-cat-id="${id}" data-nome="${safeNome}" title="Apagar categoria"></button>
        </div>
    </div>`;

    gcList.insertAdjacentHTML('beforeend', html);
    ligarEventosLinha(gcList.lastElementChild);
}

function ligarEventosNomeInput(input) {
    input.addEventListener('input', function() {
        const btn = document.getElementById('gc-btn-guardar-' + this.dataset.catId);
        if (btn) btn.style.display = this.value.trim() !== this.dataset.original ? 'inline-block' : 'none';
    });

    input.addEventListener('keydown', function(e) {
        const btn = document.getElementById('gc-btn-guardar-' + this.dataset.catId);
        if (e.key === 'Enter' && btn) btn.click();
        if (e.key === 'Escape') {
            this.value = this.dataset.original;
            if (btn) btn.style.display = 'none';
        }
    });
}

function ligarEventosGuardar(btn) {
    btn.addEventListener('click', function() {
        const id = this.dataset.catId;
        const input = document.getElementById('gc-nome-' + id);
        const nome = input.value.trim();
        if (!nome) {
            mostrarPopup('O nome não pode estar vazio.', 'erro');
            return;
        }

        this.disabled = true;
        this.textContent = '...';

        ajaxCat({ acao: 'editar', id: parseInt(id), nome })
            .then(data => {
                if (data.sucesso) {
                    input.dataset.original = nome;
                    this.style.display = 'none';
                    const apagar = document.querySelector(`.gc-btn-apagar[data-cat-id="${id}"]`);
                    if (apagar) apagar.dataset.nome = nome;
                    mostrarPopup(data.mensagem, 'sucesso');
                } else {
                    mostrarPopup(data.mensagem, 'erro');
                }
            })
            .catch(() => mostrarPopup('Erro de ligação.', 'erro'))
            .finally(() => {
                this.disabled = false;
                this.textContent = 'Guardar';
            });
    });
}

function ligarEventosToggle(toggle) {
    toggle.addEventListener('change', function() {
        const id = this.dataset.catId;

        if (this.checked) {
            const visiveis = Array.from(document.querySelectorAll('.gc-toggle-visivel')).filter(i => i.checked).length;
            if (visiveis > 8) {
                this.checked = false;
                mostrarPopup('Máximo de 8 categorias atingido. Desativa outra primeiro.', 'erro');
                return;
            }
        }

        ajaxCat({ acao: 'toggle_visivel', id: parseInt(id) })
            .then(data => {
                if (data.sucesso) {
                    const tag = document.getElementById('gc-tag-' + id);
                    if (tag) {
                        tag.textContent = data.visivel ? 'Pág. Inicial' : 'Oculta Home';
                        tag.className = 'gc-tag ' + (data.visivel ? 'visivel' : 'invisivel');
                    }
                    mostrarPopup(data.mensagem, data.visivel ? 'sucesso' : 'info');
                } else {
                    this.checked = !this.checked;
                    mostrarPopup(data.mensagem, 'erro');
                }
            })
            .catch(() => {
                this.checked = !this.checked;
                mostrarPopup('Erro de ligação.', 'erro');
            });
    });
}

function ligarEventosApagar(btn) {
    btn.addEventListener('click', function() {
        const id = parseInt(this.dataset.catId);
        const nome = this.dataset.nome;

        window.mostrarModalConfirmacao(
            'Apagar Categoria',
            `Tens a certeza que queres apagar a categoria "<strong>${escapeHtml(nome)}</strong>"?<br><br><span style="color:var(--cor-erro);font-size:0.85rem;">Os produtos com esta categoria não são apagados, mas a categoria deixa de aparecer nos filtros e formulários.</span>`,
            () => {
                ajaxCat({ acao: 'apagar', id })
                    .then(data => {
                        if (data.sucesso) {
                            const linha = document.querySelector(`.gc-item[data-id="${id}"]`);
                            if (linha) {
                                linha.style.opacity = '0';
                                linha.style.transform = 'translateX(-20px)';
                                setTimeout(() => {
                                    linha.remove();
                                    if (!gcList.querySelector('.gc-item')) {
                                        gcList.insertAdjacentHTML('beforeend', '<div class="gc-empty" id="gc-empty-msg">Ainda não existem categorias. Cria a primeira acima.</div>');
                                    }
                                }, 300);
                            }
                            mostrarPopup(data.mensagem, 'sucesso');
                        } else {
                            mostrarPopup(data.mensagem, 'erro');
                        }
                    })
                    .catch(() => mostrarPopup('Erro de ligação.', 'erro'));
            }
        );
    });
}

function abrirUploadFoto(id) {
    document.getElementById('gc-foto-input-' + id)?.click();
}

function setFotoCapaThumb(id, url) {
    const wrap = document.getElementById('gc-foto-wrap-' + id);
    const thumbnail = document.getElementById('gc-foto-img-' + id);
    if (!wrap) return;

    if (thumbnail && thumbnail.tagName === 'IMG') {
        thumbnail.src = url;
        return;
    }

    const img = document.createElement('img');
    img.id = 'gc-foto-img-' + id;
    img.src = url;
    if (thumbnail) {
        thumbnail.replaceWith(img);
    } else {
        wrap.insertBefore(img, wrap.firstChild);
    }
}

function ligarEventosUpload(input) {
    input.addEventListener('change', async function() {
        const id = this.dataset.catId;
        const file = this.files[0];
        if (!file) return;

        const wrap = document.getElementById('gc-foto-wrap-' + id);
        if (wrap) wrap.classList.add('uploading');

        try {
            const localUrl = URL.createObjectURL(file);
            setFotoCapaThumb(id, localUrl);

            const compressedFile = await comprimirImagem(file, 1200, 1200, 0.85);
            const fd = new FormData();
            fd.append('acao', 'upload_capa');
            fd.append('id', id);
            fd.append('foto', compressedFile, 'upload.jpg');
            fd.append('csrf_token', CSRF);

            const res = await fetch('ajax_categoria.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.sucesso) {
                setFotoCapaThumb(id, data.url + '?t=' + Date.now());
                mostrarPopup(data.mensagem, 'sucesso');
            } else {
                mostrarPopup(data.mensagem, 'erro');
            }
            URL.revokeObjectURL(localUrl);
        } catch (err) {
            console.error(err);
            mostrarPopup('Erro ao carregar imagem.', 'erro');
        } finally {
            if (wrap) wrap.classList.remove('uploading');
            this.value = '';
        }
    });
}

function comprimirImagem(file, maxWidth, maxHeight, quality) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = e => {
            const img = new Image();
            img.src = e.target.result;
            img.onload = () => {
                let width = img.width;
                let height = img.height;
                if (width > maxWidth || height > maxHeight) {
                    if (width > height) {
                        height *= maxWidth / width;
                        width = maxWidth;
                    } else {
                        width *= maxHeight / height;
                        height = maxHeight;
                    }
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(blob => {
                    if (blob) {
                        resolve(new File([blob], file.name.replace(/\.[^/.]+$/, '') + '.webp', { type: 'image/webp' }));
                    } else {
                        canvas.toBlob(blobJpeg => {
                            resolve(new File([blobJpeg], file.name, { type: 'image/jpeg' }));
                        }, 'image/jpeg', quality);
                    }
                }, 'image/webp', quality);
            };
            img.onerror = reject;
        };
        reader.onerror = reject;
    });
}

function ligarEventosLinha(linha) {
    ligarEventosNomeInput(linha.querySelector('.gc-nome-input'));
    const btnGuardar = linha.querySelector('.gc-btn-guardar');
    if (btnGuardar) ligarEventosGuardar(btnGuardar);
    const toggle = linha.querySelector('.gc-toggle-visivel');
    if (toggle) ligarEventosToggle(toggle);
    const btnApagar = linha.querySelector('.gc-btn-apagar');
    if (btnApagar) ligarEventosApagar(btnApagar);
    const uploadInput = linha.querySelector('.gc-foto-input');
    if (uploadInput) ligarEventosUpload(uploadInput);
}

// Liga eventos a todas as linhas existentes
document.querySelectorAll('.gc-item').forEach(ligarEventosLinha);
</script>

<?php include '../templates/footer.php'; ?>
