// public/js/modalProduto.js (VERSÃO FINAL E CORRIGIDA)

document.addEventListener("DOMContentLoaded", function () {
  // --- Seletores e Estado ---
  const modal = document.getElementById("produtoModal");
  // Se o modal não existe (estamos na página do produto), usa o body como referência
  const escopo = modal || document.body;

  if (!escopo) return;

  const modalConteudo = escopo.querySelector(".modal-conteudo");
  const modalNome = escopo.querySelector("#modalNome");
  const modalPreco = escopo.querySelector("#modalPreco");
  const modalDescricao = escopo.querySelector("#modalDescricao");
  const modalImagemPrincipal = escopo.querySelector("#modalImagemPrincipal");
  const modalThumbnails = escopo.querySelector("#modalThumbnails");
  const variacoesContainer = escopo.querySelector("#variacoesContainer");
  const fecharModalBtn = escopo.querySelector(".fechar");
  const inputQuantidade = escopo.querySelector("#modalQuantidade");
  const seletorQuantidadeWrapper = escopo.querySelector(".seletor-quantidade");
  const adicionarCarrinhoBtn = escopo.querySelector("#adicionarCarrinhoBtn");
  const relacionadosContainers = escopo.querySelectorAll(
    ".produtos-relacionados-container",
  );
  const zoomContainer = escopo.querySelector(".zoom-container");
  const metaReferencia = escopo.querySelector("#meta-referencia");
  const metaStock = escopo.querySelector("#meta-stock");

  function obterElementosGuia() {
    const modalGuia = document.getElementById("modalGuiaTamanhos");
    const guiaTitulo = document.getElementById("guia-titulo");
    const guiaConteudo = document.getElementById("guia-conteudo");
    const btnFecharGuia = modalGuia
      ? modalGuia.querySelector(".qe-close")
      : null;

    if (!modalGuia || !guiaTitulo || !guiaConteudo) return null;

    if (btnFecharGuia && !modalGuia.dataset.guiaListenersAtivos) {
      const fecharModalGuia = () => modalGuia.classList.remove("ativo");
      btnFecharGuia.addEventListener("click", fecharModalGuia);
      modalGuia.addEventListener("click", (e) => {
        if (e.target === modalGuia) {
          fecharModalGuia();
        }
      });
      modalGuia.dataset.guiaListenersAtivos = "true";
    }

    return { modalGuia, guiaTitulo, guiaConteudo };
  }

  let estado = {
    produtoAtual: null,
    variacoes: [],
    selecaoAtual: {},
    variacaoSelecionada: null,
    isModalOpen: false,
    fotosOriginais: [],
    galeriaCompleta: [],
    atributosDeVariacao: [],
  };

  function scrollModalParaTopo() {
    if (!modal) return;

    modal.scrollTop = 0;
    if (modalConteudo) modalConteudo.scrollTop = 0;

    requestAnimationFrame(() => {
      modal.scrollTop = 0;
      if (modalConteudo) modalConteudo.scrollTop = 0;
    });
  }

  function obterCorMediaImagem(img) {
    if (!img || !img.naturalWidth || !img.naturalHeight) return null;

    const tamanho = 32;
    const canvas = document.createElement("canvas");
    canvas.width = tamanho;
    canvas.height = tamanho;

    const ctx = canvas.getContext("2d", { willReadFrequently: true });
    if (!ctx) return null;

    try {
      ctx.drawImage(img, 0, 0, tamanho, tamanho);
      const pixels = ctx.getImageData(0, 0, tamanho, tamanho).data;
      let rTotal = 0;
      let gTotal = 0;
      let bTotal = 0;
      let pesoTotal = 0;

      for (let i = 0; i < pixels.length; i += 4) {
        const alpha = pixels[i + 3];
        if (alpha < 128) continue;

        const r = pixels[i];
        const g = pixels[i + 1];
        const b = pixels[i + 2];
        const max = Math.max(r, g, b);
        const min = Math.min(r, g, b);
        const saturacao = max === 0 ? 0 : (max - min) / max;
        const luminosidade = (r + g + b) / 3;
        const extremo = luminosidade > 246 || luminosidade < 18 ? 0.25 : 1;
        const peso = (0.2 + saturacao * saturacao) * extremo;

        rTotal += r * peso;
        gTotal += g * peso;
        bTotal += b * peso;
        pesoTotal += peso;
      }

      if (!pesoTotal) return null;

      return [
        Math.round(rTotal / pesoTotal),
        Math.round(gTotal / pesoTotal),
        Math.round(bTotal / pesoTotal),
      ];
    } catch (error) {
      return null;
    }
  }

  function atualizarFundoModalPelaImagem() {
    if (!modal || !modalConteudo || !modalImagemPrincipal) return;

    const cor = obterCorMediaImagem(modalImagemPrincipal);
    if (cor) {
      modalConteudo.style.setProperty("--modal-foto-rgb", cor.join(", "));
    }
  }

  window.abrirModalProduto = async function (
    elementoProduto,
    imagemPreSelecionada = null,
  ) {
    if (!elementoProduto || estado.isModalOpen) return;

    const modalLoadingOverlay = modal
      ? modal.querySelector(".modal-loading-overlay")
      : null;
    const modalGridConteudo = modal
      ? modal.querySelector(".modal-grid-conteudo")
      : null;

    // --- INÍCIO DA CORREÇÃO: Ativa o spinner da imagem ---
    if (zoomContainer) {
      zoomContainer.classList.add("loading");
    }
    // --- FIM DA CORREÇÃO ---

    if (modal) {
      modal.classList.add("ativo");
      document.body.style.overflow = "hidden";
      estado.isModalOpen = true;
      if (modalConteudo) {
        modalConteudo.style.removeProperty("--modal-foto-rgb");
      }
      if (modalLoadingOverlay) modalLoadingOverlay.classList.add("ativo");
      if (modalGridConteudo) modalGridConteudo.style.visibility = "hidden";
      scrollModalParaTopo();
    }

    document
      .querySelectorAll(".produto.loading")
      .forEach((p) => p.classList.remove("loading"));
    elementoProduto.classList.add("loading");

    resetarModalParaEstadoInicial();
    estado.produtoAtual = elementoProduto;
    const produtoData = elementoProduto.dataset;
    // --- INÍCIO DA ALTERAÇÃO ---
    // Lemos os filtros que vieram do URL, se existirem (relevante para a página produto.php)
    // --- INÍCIO DA CORREÇÃO ---
    const filtrosUrl = JSON.parse(produtoData.filtrosUrl || "{}");
    const filtrosEstavamAtivos = produtoData.filtrosAtivos === "true";
    const atributosDoProduto = JSON.parse(produtoData.atributos || "{}");
    const temAtributos = Object.keys(atributosDoProduto).length > 0;

    if (metaReferencia) metaReferencia.style.display = "none";
    if (metaStock) metaStock.style.display = "none";

    const blocoAcoes = escopo.querySelector(".bloco-acoes-compra");
    if (blocoAcoes && !temAtributos) {
      blocoAcoes.classList.add("sem-variacoes");
    }

    const promessasDeDados = [];
    const cacheBuster = `&t=${Date.now()}`;
    promessasDeDados.push(
      fetch(`/ajax_get_variacoes.php?id=${produtoData.id}${cacheBuster}`) // <-- LINHA MODIFICADA
        .then((response) =>
          response.ok
            ? response.json()
            : Promise.reject("Falha ao buscar variações."),
        ),
    );
    promessasDeDados.push(
      fetch(
        `/ajax_get_relacionados.php?id_atual=${produtoData.id}&categoria=${encodeURIComponent(produtoData.categoria)}`,
      ).then((response) =>
        response.ok
          ? response.json()
          : Promise.reject("Falha ao buscar relacionados."),
      ),
    );

    try {
      const resultados = await Promise.all(promessasDeDados);
      const dadosVariacoes = resultados[0];
      const dadosRelacionados = resultados[1];

      if (dadosVariacoes) {
        estado.variacoes = dadosVariacoes.variacoes || [];
        if (estado.variacoes.length > 0 && estado.variacoes[0].atributos) {
          estado.atributosDeVariacao = Object.keys(
            estado.variacoes[0].atributos,
          );
        }
        const imagensDeVariacoes = new Set(
          estado.variacoes
            .flatMap((v) => v.imagens || [])
            .map((img) => img.split("/").pop()),
        );
        estado.fotosOriginais = JSON.parse(produtoData.fotos || "[]");
        estado.galeriaCompleta = [
          ...new Set([
            ...estado.fotosOriginais,
            ...Array.from(imagensDeVariacoes),
          ]),
        ];
      } else {
        estado.fotosOriginais = JSON.parse(produtoData.fotos || "[]");
        estado.galeriaCompleta = [...estado.fotosOriginais];
      }

      if (!temAtributos && estado.variacoes.length > 0) {
        const variacaoBase = estado.variacoes.find(
          (v) => v.atributos && Object.keys(v.atributos).length === 0,
        );
        if (variacaoBase && variacaoBase.quantidade > 0) {
          estado.variacaoSelecionada = variacaoBase;
        }
      }

      const guiaId = produtoData.guiaTamanhoId;
      const descricaoAcordeao = escopo.querySelector(
        ".detalhes-produto-acordeao",
      );
      const oldBtn = escopo.querySelector("#btn-guia-tamanhos");
      if (oldBtn) oldBtn.remove();
      if (guiaId && descricaoAcordeao) {
        const btnGuia = document.createElement("button");
        btnGuia.id = "btn-guia-tamanhos";
        btnGuia.className = "button voltar-btn";
        btnGuia.textContent = "Guia de Tamanhos";
        btnGuia.style.marginTop = "8px";
        btnGuia.style.marginBottom = "15px";
        btnGuia.addEventListener("click", async () => {
          const guiaModal = obterElementosGuia();
          if (!guiaModal) {
            mostrarPopup(
              "Não foi possível abrir o guia de tamanhos.",
              "erro",
            );
            return;
          }

          try {
            const response = await fetch(
              `/ajax_get_guia_tamanho.php?id=${guiaId}&t=${Date.now()}`,
            );
            const data = await response.json();
            if (!response.ok) {
              mostrarPopup(
                data?.mensagem || "Não foi possível carregar o guia de tamanhos.",
                "erro",
              );
              return;
            }
            if (data.sucesso) {
              guiaModal.guiaTitulo.textContent = data.guia.titulo;
              guiaModal.guiaConteudo.innerHTML = data.guia.conteudo;
              guiaModal.modalGuia.classList.add("ativo");
            } else {
              mostrarPopup(
                "Não foi possível carregar o guia de tamanhos.",
                "erro",
              );
            }
          } catch (error) {
            mostrarPopup("Erro ao carregar o guia.", "erro");
          }
        });
        descricaoAcordeao.parentNode.insertBefore(
          btnGuia,
          descricaoAcordeao.nextSibling,
        );
      }

      modalNome.textContent = produtoData.nome;
      modalDescricao.textContent = produtoData.descricao;
      atualizarPreco(
        parseFloat(produtoData.preco) || 0,
        parseFloat(produtoData.precoPromocional) || 0,
      );
      renderizarGaleriaCompleta();
      if (temAtributos) renderizarSeletoresDeAtributos(atributosDoProduto);

      // Lógica de pré-seleção UNIFICADA
      const filtrosParaPreencher = {};
      let imagemFiltrada = imagemPreSelecionada
        ? imagemPreSelecionada.split("/").pop()
        : null;

      // Se está na página do produto e tem filtros no URL, tentamos encontrar uma imagem.
      if (!modal && Object.keys(filtrosUrl).length > 0) {
        const variacaoExata = estado.variacoes.find((v) => {
          return (
            v.atributos &&
            Object.keys(filtrosUrl).every(
              (key) => v.atributos[key] === filtrosUrl[key],
            )
          );
        });
        if (
          variacaoExata &&
          variacaoExata.imagens &&
          variacaoExata.imagens.length > 0
        ) {
          imagemFiltrada = variacaoExata.imagens[0].split("/").pop();
        }
      }

      // Se uma imagem foi determinada (pelo clique na grelha OU pelo URL na página do produto),
      // encontramos os atributos comuns a essa imagem.
      // Se uma imagem foi determinada (pelo clique na grelha OU pelo URL na página do produto),
      // encontramos os atributos comuns a essa imagem.
      if (imagemFiltrada) {
        // SÓ TENTA ADIVINHAR O ATRIBUTO SE OS FILTROS ESTAVAM ATIVOS
        if (filtrosEstavamAtivos) {
          const variacoesComImagem = estado.variacoes.filter(
            (v) =>
              v.imagens &&
              v.imagens.some((img) => img.endsWith(imagemFiltrada)),
          );

          if (
            variacoesComImagem.length > 0 &&
            variacoesComImagem[0].atributos
          ) {
            const primeiroAtributo = variacoesComImagem[0].atributos;
            for (const attr in primeiroAtributo) {
              const valor = primeiroAtributo[attr];
              if (
                variacoesComImagem.every(
                  (v) => v.atributos && v.atributos[attr] === valor,
                )
              ) {
                filtrosParaPreencher[attr] = valor;
              }
            }
          }
        }
        mudarImagemAtiva(imagemFiltrada);
      }

      // Se não encontrámos atributos pela imagem, mas temos filtros no URL, usamos esses como fallback.
      // Isto mantém o comportamento para links diretos com todos os parâmetros.
      if (
        Object.keys(filtrosParaPreencher).length === 0 &&
        Object.keys(filtrosUrl).length > 0
      ) {
        Object.assign(filtrosParaPreencher, filtrosUrl);
      }

      // Finalmente, aplicamos os filtros que foram determinados
      if (Object.keys(filtrosParaPreencher).length > 0) {
        estado.selecaoAtual = filtrosParaPreencher;
        for (const attr in estado.selecaoAtual) {
          const select = variacoesContainer.querySelector(
            `select[data-nome-atributo="${attr}"]`,
          );
          if (select) {
            select.value = estado.selecaoAtual[attr];
          }
        }
      }

      renderizarRelacionados(dadosRelacionados);

      atualizarUIComBaseNaSelecao();
      // --- FIM DA CORREÇÃO ---
      if (modal) {
        atualizarURL(produtoData.slug, produtoData.nome);
        if (modalLoadingOverlay) modalLoadingOverlay.classList.remove("ativo");
        if (modalGridConteudo) modalGridConteudo.style.visibility = "visible";
        scrollModalParaTopo();
      }
    } catch (error) {
      console.error("Erro ao carregar dados para o modal:", error);
      mostrarPopup("Não foi possível abrir os detalhes do produto.", "erro");
      if (modal) fecharModal();
    } finally {
      elementoProduto.classList.remove("loading");
    }
  };

  function renderizarRelacionados(produtos) {
    relacionadosContainers.forEach((container) => {
      container.innerHTML = "";
      if (produtos && produtos.length > 0) {
        container.innerHTML = "<h4>Também pode gostar</h4>";
        const grid = document.createElement("div");
        grid.className = "produtos-relacionados-grid";
        produtos.forEach((p) => {
          const slug = criar_slug(`${p.nome}-${p.id}`);
          const link = document.createElement("a");
          link.href = `/produto/${slug}`;
          link.className = "relacionado-item";
          link.dataset.slug = slug;
          link.dataset.id = p.id;
          link.title = p.nome;
          link.innerHTML = `<img src="/public/images/${p.foto_principal}" alt="${p.nome}" loading="lazy"><span>${p.nome}</span>`;
          link.addEventListener("click", (e) => {
            if (window.matchMedia("(max-width: 768px)").matches) return;
            e.preventDefault();
            const produtoParaAbrir = document.querySelector(
              `.produto[data-id='${p.id}']`,
            );
            if (produtoParaAbrir) {
              fecharModal(false);
              setTimeout(() => {
                abrirModalProduto(produtoParaAbrir);
              }, 150);
            } else {
              window.location.href = link.href;
            }
          });
          grid.appendChild(link);
        });
        container.appendChild(grid);
      }
    });
  }

  function resetarModalParaEstadoInicial() {
    estado.selecaoAtual = {};
    estado.variacaoSelecionada = null;
    estado.atributosDeVariacao = [];
    variacoesContainer.innerHTML = "";
    inputQuantidade.value = 1;
    adicionarCarrinhoBtn.textContent = "Selecione as opções";
    adicionarCarrinhoBtn.disabled = true;
    if (modalConteudo) modalConteudo.style.opacity = "1";
    const blocoAcoes = escopo.querySelector(".bloco-acoes-compra");
    if (blocoAcoes) {
      blocoAcoes.classList.remove("sem-variacoes");
    }

    if (relacionadosContainers) {
      relacionadosContainers.forEach((container) => (container.innerHTML = ""));
    }

    const acordeaoItem = escopo.querySelector(".acordeao-item");
    const acordeaoConteudo = escopo.querySelector(".acordeao-conteudo");
    if (acordeaoItem && acordeaoConteudo) {
      acordeaoItem.classList.remove("ativo");
      acordeaoConteudo.style.maxHeight = null;
    }

    const acoesPrincipaisContainer = escopo.querySelector(
      ".modal-acoes-principais",
    );
    if (acoesPrincipaisContainer) {
      acoesPrincipaisContainer.classList.remove("seletor-visivel");
    }

    // --- INÍCIO DA CORREÇÃO ---
    // Adiciona o reset do estado do zoom para dispositivos móveis
    if (modalImagemPrincipal) {
      // Remove qualquer transição para que a redefinição seja instantânea
      modalImagemPrincipal.style.transition = "none";
      isZoomed = false; // A variável global 'isZoomed' é reiniciada
      lastPan = { x: 0, y: 0 };
      currentPan = { x: 0, y: 0 };
      // Força a imagem a voltar à sua escala e posição originais
      modalImagemPrincipal.style.transform = "translate(0px, 0px) scale(1)";
    }
    // --- FIM DA CORREÇÃO ---
  }

  function renderizarGaleriaCompleta() {
    if (estado.galeriaCompleta.length === 0) {
      modalImagemPrincipal.src = "/public/images/default.jpg";
      modalThumbnails.innerHTML = "";
      // --- INÍCIO DA CORREÇÃO: Remove o spinner se não há imagem ---
      if (zoomContainer) {
        zoomContainer.classList.remove("loading");
      }
      // --- FIM DA CORREÇÃO ---
      return;
    }
    mudarImagemAtiva(estado.galeriaCompleta[0]); // Usa a função para carregar a primeira imagem
    modalThumbnails.innerHTML = estado.galeriaCompleta
      .map((foto, index) => {
        const isAtiva = index === 0 ? "ativa" : "";
        return `<img src="/public/images/${foto}" class="modal-thumbnail ${isAtiva}" data-foto-nome="${foto}">`;
      })
      .join("");
  }

  function mudarImagemAtiva(nomeFicheiro) {
    if (!nomeFicheiro || !modalImagemPrincipal) return;

    // --- INÍCIO DA CORREÇÃO: Lógica de loading ---
    if (zoomContainer) {
      zoomContainer.classList.add("loading");
    }

    const onImageLoad = () => {
      if (zoomContainer) {
        zoomContainer.classList.remove("loading");
      }
      modalImagemPrincipal.style.opacity = "1";
      atualizarFundoModalPelaImagem();
      // Remove o listener para não disparar em futuros loads
      modalImagemPrincipal.removeEventListener("load", onImageLoad);
    };
    modalImagemPrincipal.addEventListener("load", onImageLoad);
    // --- FIM DA CORREÇÃO ---

    const thumbParaAtivar = modalThumbnails.querySelector(
      `[data-foto-nome="${nomeFicheiro}"]`,
    );
    if (thumbParaAtivar) {
      escopo
        .querySelectorAll(".modal-thumbnail")
        .forEach((t) => t.classList.remove("ativa"));
      thumbParaAtivar.classList.add("ativa");
      modalImagemPrincipal.src = thumbParaAtivar.src;
    } else {
      modalImagemPrincipal.src = `/public/images/${nomeFicheiro}`;
    }
  }

  function obterThumbnailsGaleria() {
    if (!modalThumbnails) return [];
    return Array.from(modalThumbnails.querySelectorAll(".modal-thumbnail"));
  }

  function galeriaAceitaTeclado() {
    const guiaAtivo =
      document.getElementById("modalGuiaTamanhos")?.classList.contains("ativo");

    if (guiaAtivo || !estado.produtoAtual) return false;
    if (modal) return estado.isModalOpen && modal.classList.contains("ativo");

    return true;
  }

  function alvoUsaSetasProprias(alvo) {
    if (!(alvo instanceof Element)) return false;

    return Boolean(
      alvo.closest(
        'input, textarea, select, [contenteditable="true"], [contenteditable=""]',
      ),
    );
  }

  function navegarGaleriaPorTeclado(direcao) {
    const thumbnails = obterThumbnailsGaleria();
    if (thumbnails.length <= 1) return false;

    let indiceAtual = thumbnails.findIndex((thumb) =>
      thumb.classList.contains("ativa"),
    );

    if (indiceAtual === -1 && modalImagemPrincipal) {
      const srcAtual = modalImagemPrincipal.currentSrc || modalImagemPrincipal.src;
      indiceAtual = thumbnails.findIndex((thumb) => {
        const fotoNome = thumb.dataset.fotoNome;
        return fotoNome && (thumb.src === srcAtual || srcAtual.endsWith(`/${fotoNome}`));
      });
    }

    if (indiceAtual === -1) indiceAtual = 0;

    const proximoIndice =
      (indiceAtual + direcao + thumbnails.length) % thumbnails.length;
    const proximaFoto = thumbnails[proximoIndice]?.dataset.fotoNome;
    if (!proximaFoto || proximoIndice === indiceAtual) return false;

    mudarImagemAtiva(proximaFoto);
    thumbnails[proximoIndice].scrollIntoView({
      behavior: "smooth",
      block: "nearest",
      inline: "nearest",
    });

    return true;
  }

  modalThumbnails.addEventListener("click", (e) => {
    if (e.target.classList.contains("modal-thumbnail")) {
      mudarImagemAtiva(e.target.dataset.fotoNome);
    }
  });

  function atualizarPreco(precoNormal, precoPromocional, variacaoPreco = null) {
    const precoFinal =
      variacaoPreco > 0
        ? variacaoPreco
        : precoPromocional > 0
          ? precoPromocional
          : precoNormal;
    let precoOriginal =
      variacaoPreco > 0 ? null : precoPromocional > 0 ? precoNormal : null;
    modalPreco.innerHTML = precoOriginal
      ? `<span class="preco-promocao"><del>€${number_format(precoOriginal, 2, ",", ".")} </del> <strong>€${number_format(precoFinal, 2, ",", ".")}</strong></span>`
      : `€${number_format(precoFinal, 2, ",", ".")}`;
  }

  function renderizarSeletoresDeAtributos(atributosDoProduto) {
    variacoesContainer.innerHTML = "";
    for (const nomeAtributo in atributosDoProduto) {
      const valores = atributosDoProduto[nomeAtributo];
      if (Array.isArray(valores) && valores.length > 0) {
        const div = document.createElement("div");
        div.classList.add("selecao-variacao");
        const label = document.createElement("label");
        label.textContent = `${nomeAtributo}:`;
        const selectWrapper = document.createElement("div");
        selectWrapper.classList.add("select-wrapper");
        const select = document.createElement("select");
        select.classList.add("select-estilizado");
        select.dataset.nomeAtributo = nomeAtributo;
        select.innerHTML = `<option value="">Escolha um ${nomeAtributo.toLowerCase()}...</option>`;
        valores.forEach((valor) => {
          select.innerHTML += `<option value="${valor}">${valor}</option>`;
        });
        select.addEventListener("change", handleSelecaoDeAtributo);
        selectWrapper.appendChild(select);
        div.appendChild(label);
        div.appendChild(selectWrapper);
        variacoesContainer.appendChild(div);
      }
    }
    atualizarOpcoesDisponiveis();
  }

  function atualizarOpcoesDisponiveis() {
    const selects = variacoesContainer.querySelectorAll("select");
    selects.forEach((select) => {
      const nomeAtributoAtual = select.dataset.nomeAtributo;

      if (estado.atributosDeVariacao.length === 0) {
        return;
      }

      select.querySelectorAll("option").forEach((opt) => {
        if (opt.value) opt.disabled = false;
      });

      for (const option of select.options) {
        if (!option.value) continue;

        const selecaoTeste = {
          ...estado.selecaoAtual,
          [nomeAtributoAtual]: option.value,
        };

        const isPossivel = estado.variacoes.some((v) => {
          return estado.atributosDeVariacao.every((key) => {
            return (
              !selecaoTeste[key] ||
              !v.atributos[key] ||
              v.atributos[key] === selecaoTeste[key]
            );
          });
        });

        if (!isPossivel) option.disabled = true;
      }
    });
  }

  function atualizarUIComBaseNaSelecao() {
    const precoBase = parseFloat(estado.produtoAtual.dataset.preco) || 0;
    const precoPromoBase =
      parseFloat(estado.produtoAtual.dataset.precoPromocional) || 0;

    const totalAtributos = variacoesContainer.querySelectorAll("select").length;
    const selecaoCompleta =
      Object.keys(estado.selecaoAtual).length === totalAtributos;

    if (totalAtributos === 0) {
      if (estado.variacoes.length > 0) {
        const variacaoBase = estado.variacoes.find(
          (v) => v.atributos && Object.keys(v.atributos).length === 0,
        );
        if (variacaoBase && variacaoBase.quantidade > 0) {
          estado.variacaoSelecionada = variacaoBase;
        } else {
          estado.variacaoSelecionada = null;
        }
      }
    } else if (
      selecaoCompleta &&
      estado.atributosDeVariacao.every((key) => estado.selecaoAtual[key])
    ) {
      estado.variacaoSelecionada =
        estado.variacoes.find((v) => {
          return estado.atributosDeVariacao.every(
            (key) => v.atributos[key] === estado.selecaoAtual[key],
          );
        }) || null;
    } else {
      estado.variacaoSelecionada = null;
    }

    const v = estado.variacaoSelecionada;
    const acoesPrincipaisContainer = escopo.querySelector(
      ".modal-acoes-principais",
    );

    if (v) {
      const refTexto = v.referencia || estado.produtoAtual.dataset.referencia;
      if (refTexto && metaReferencia) {
        metaReferencia.querySelector(".meta-texto").textContent =
          `Ref: ${refTexto}`;
        metaReferencia.style.display = "inline-flex";
      }
      if (metaStock) {
        metaStock.classList.remove("stock-ok", "stock-low", "stock-out");

        if (v.quantidade > 1) {
          metaStock.querySelector(".meta-texto").textContent =
            `${v.quantidade} em stock`;
          metaStock.classList.add("stock-ok");
        } else if (v.quantidade === 1) {
          metaStock.querySelector(".meta-texto").textContent =
            "Apenas 1 em stock!";
          metaStock.classList.add("stock-low");
        } else {
          metaStock.querySelector(".meta-texto").textContent = "Esgotado";
          metaStock.classList.add("stock-out");
        }
        metaStock.style.display = "inline-flex";
      }
      atualizarPreco(
        precoBase,
        precoPromoBase,
        v.preco ? parseFloat(v.preco) : null,
      );
      if (v.quantidade > 0) {
        adicionarCarrinhoBtn.textContent = "Adicionar ao Carrinho";
        adicionarCarrinhoBtn.disabled = false;
        acoesPrincipaisContainer.classList.add("seletor-visivel");
        inputQuantidade.max = v.quantidade;
        if (parseInt(inputQuantidade.value) > v.quantidade)
          inputQuantidade.value = v.quantidade;
      } else {
        adicionarCarrinhoBtn.textContent = "Esgotado";
        adicionarCarrinhoBtn.disabled = true;
        acoesPrincipaisContainer.classList.remove("seletor-visivel");
      }
    } else {
      if (metaReferencia) metaReferencia.style.display = "none";
      if (metaStock) metaStock.style.display = "none";
      atualizarPreco(precoBase, precoPromoBase);
      adicionarCarrinhoBtn.textContent = "Selecione as opções";
      adicionarCarrinhoBtn.disabled = true;
      acoesPrincipaisContainer.classList.remove("seletor-visivel");
    }

    let imagemParaMostrar = estado.fotosOriginais[0] || null;
    if (v && v.imagens && v.imagens.length > 0) {
      imagemParaMostrar = v.imagens[0].split("/").pop();
    } else if (Object.keys(estado.selecaoAtual).length > 0) {
      const variacoesCompatveis = estado.variacoes.filter((variacao) => {
        return Object.keys(estado.selecaoAtual).every(
          (key) => variacao.atributos[key] === estado.selecaoAtual[key],
        );
      });
      const imagensUnicas = new Set(
        variacoesCompatveis
          .flatMap((comp) => comp.imagens || [])
          .map((img) => img.split("/").pop()),
      );
      if (imagensUnicas.size === 1) {
        imagemParaMostrar = imagensUnicas.values().next().value;
      }
    }
    mudarImagemAtiva(imagemParaMostrar);

    atualizarEstadoBotoesQty();
  }

  function handleSelecaoDeAtributo(e) {
    const select = e.target;
    const nomeAtributo = select.dataset.nomeAtributo;
    const valor = select.value;

    valor
      ? (estado.selecaoAtual[nomeAtributo] = valor)
      : delete estado.selecaoAtual[nomeAtributo];

    atualizarUIComBaseNaSelecao();
    atualizarOpcoesDisponiveis();
  }

  adicionarCarrinhoBtn.addEventListener("click", () => {
    if (!estado.variacaoSelecionada || !estado.produtoAtual) return;
    const v = estado.variacaoSelecionada;
    const precoBase = parseFloat(estado.produtoAtual.dataset.preco) || 0;
    const precoPromoBase =
      parseFloat(estado.produtoAtual.dataset.precoPromocional) || 0;
    const precoFinal = v.preco
      ? parseFloat(v.preco)
      : precoPromoBase > 0
        ? precoPromoBase
        : precoBase;

    let fotoParaCarrinho =
      v.imagens && v.imagens.length > 0
        ? `/public/images/${v.imagens[0].split("/").pop()}`
        : `/public/images/${estado.fotosOriginais[0]}`;

    const produtoParaCarrinho = {
      id: estado.produtoAtual.dataset.id,
      variacao_id: v.id,
      referencia: v.referencia || estado.produtoAtual.dataset.referencia,
      nome: estado.produtoAtual.dataset.nome,
      preco: precoFinal,
      precoOriginal: precoBase,
      emPromocao: precoFinal < precoBase,
      foto: fotoParaCarrinho,
      selecoes: estado.selecaoAtual,
      quantidade: parseInt(inputQuantidade.value, 10),
      stock: v.quantidade,
      peso_gramas: parseInt(estado.produtoAtual.dataset.peso, 10) || 0
    };

    if (typeof window.adicionarAoCarrinho === "function") {
      window.adicionarAoCarrinho(produtoParaCarrinho, adicionarCarrinhoBtn);
    }
    if (modal) fecharModal();
  });

  if (fecharModalBtn)
    fecharModalBtn.addEventListener("click", () => fecharModal());
  if (modal)
    modal.addEventListener("click", (e) => {
      if (e.target == modal) fecharModal();
    });
  window.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && estado.isModalOpen) {
      fecharModal();
      return;
    }

    if (
      e.key !== "ArrowLeft" &&
      e.key !== "ArrowRight"
    ) {
      return;
    }

    if (
      e.altKey ||
      e.ctrlKey ||
      e.metaKey ||
      !galeriaAceitaTeclado() ||
      alvoUsaSetasProprias(e.target)
    ) {
      return;
    }

    const direcao = e.key === "ArrowRight" ? 1 : -1;
    if (navegarGaleriaPorTeclado(direcao)) {
      e.preventDefault();
    }
  });

  const btnMinus = seletorQuantidadeWrapper.querySelector(
    '[data-action="minus"]',
  );
  const btnPlus = seletorQuantidadeWrapper.querySelector(
    '[data-action="plus"]',
  );

  function atualizarEstadoBotoesQty() {
    const qty = parseInt(inputQuantidade.value, 10);
    const max = parseInt(inputQuantidade.max, 10) || 1;
    const min = parseInt(inputQuantidade.min, 10) || 1;

    btnMinus.disabled = qty <= min;
    btnPlus.disabled = qty >= max;
  }

  seletorQuantidadeWrapper.addEventListener("click", (e) => {
    if (e.target.matches(".btn-qty:not(:disabled)")) {
      const action = e.target.dataset.action;
      let qty = parseInt(inputQuantidade.value, 10);
      const max = parseInt(inputQuantidade.max, 10);

      if (action === "plus" && qty < max) {
        inputQuantidade.value = qty + 1;
      } else if (action === "minus" && qty > 1) {
        inputQuantidade.value = qty - 1;
      }

      inputQuantidade.classList.add("qty-updated");
      setTimeout(() => {
        inputQuantidade.classList.remove("qty-updated");
      }, 200);

      atualizarEstadoBotoesQty();
    }
  });

  if (zoomContainer && modalImagemPrincipal) {
    const isTouchDevice =
      "ontouchstart" in window || navigator.maxTouchPoints > 0;

    if (!isTouchDevice) {
      zoomContainer.addEventListener("mousemove", (e) => {
        const rect = zoomContainer.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        modalImagemPrincipal.style.transition = "none";
        modalImagemPrincipal.style.transformOrigin = `${(x / rect.width) * 100}% ${(y / rect.height) * 100}%`;
        modalImagemPrincipal.style.transform = "scale(2)";
      });
      zoomContainer.addEventListener("mouseleave", () => {
        modalImagemPrincipal.style.transition = "transform 0.3s ease";
        modalImagemPrincipal.style.transform = "scale(1)";
        modalImagemPrincipal.style.transformOrigin = "center center";
      });
    } else {
      let isZoomed = false;
      let isDragging = false;
      let startX, startY;
      let lastPan = { x: 0, y: 0 };
      let currentPan = { x: 0, y: 0 };

      const applyTransform = () => {
        modalImagemPrincipal.style.transform = `translate(${currentPan.x}px, ${currentPan.y}px) scale(${isZoomed ? 2 : 1})`;
      };

      const resetZoom = () => {
        modalImagemPrincipal.style.transition = "transform 0.3s ease";
        isZoomed = false;
        lastPan = { x: 0, y: 0 };
        currentPan = { x: 0, y: 0 };
        applyTransform();
        setTimeout(() => {
          modalImagemPrincipal.style.transition = "none";
        }, 300);
      };

      zoomContainer.addEventListener("touchstart", (e) => {
        if (e.touches.length !== 1) return;
        isDragging = false;
        const touch = e.touches[0];
        startX = touch.clientX;
        startY = touch.clientY;
        if (isZoomed) {
          lastPan = { ...currentPan };
        }
      });
      zoomContainer.addEventListener("touchmove", (e) => {
        if (e.touches.length !== 1 || !isZoomed) return;

        e.preventDefault();
        isDragging = true;

        const touch = e.touches[0];
        const deltaX = touch.clientX - startX;
        const deltaY = touch.clientY - startY;

        // Calcula a nova posição de arrasto
        currentPan.x = lastPan.x + deltaX;
        currentPan.y = lastPan.y + deltaY;

        // --- INÍCIO DA CORREÇÃO ---
        // Adiciona limites para impedir o arrasto infinito
        const rect = zoomContainer.getBoundingClientRect();

        // A imagem está com escala 2x, então o espaço extra que pode ser "arrastado"
        // é metade da largura/altura do contentor em cada direção.
        const maxPanX = rect.width / 2;
        const maxPanY = rect.height / 2;

        // Restringe os valores de 'pan' dentro dos limites calculados
        currentPan.x = Math.max(-maxPanX, Math.min(maxPanX, currentPan.x));
        currentPan.y = Math.max(-maxPanY, Math.min(maxPanY, currentPan.y));
        // --- FIM DA CORREÇÃO ---

        applyTransform();
      });

      zoomContainer.addEventListener("touchend", (e) => {
        if (isDragging) {
          return;
        }

        if (!isZoomed) {
          const rect = zoomContainer.getBoundingClientRect();
          const touch = e.changedTouches[0];
          const tapX = touch.clientX - rect.left;
          const tapY = touch.clientY - rect.top;

          const centerX = rect.width / 2;
          const centerY = rect.height / 2;

          currentPan.x = -(tapX - centerX);
          currentPan.y = -(tapY - centerY);

          modalImagemPrincipal.style.transition = "transform 0.3s ease";
          isZoomed = true;
          applyTransform();
          setTimeout(() => {
            modalImagemPrincipal.style.transition = "none";
          }, 300);
        } else {
          resetZoom();
        }
      });

      modalThumbnails.addEventListener("click", (e) => {
        if (e.target.classList.contains("modal-thumbnail") && isZoomed) {
          resetZoom();
        }
      });
    }
  }
  const partilharBtn = escopo.querySelector("#partilharProdutoBtn");
  if (partilharBtn) {
    partilharBtn.addEventListener("click", async () => {
      if (!estado.produtoAtual) return;

      const nomeProduto = estado.produtoAtual.dataset.nome;
      const slug = estado.produtoAtual.dataset.slug;
      const urlParaPartilhar = `${window.location.origin}/produto/${slug}`;

      const dadosPartilha = {
        title: nomeProduto,
        text: `Espreita este produto incrível da TopTop: ${nomeProduto}`,
        url: urlParaPartilhar,
      };

      const copiarLinkComoFallback = () => {
        navigator.clipboard
          .writeText(urlParaPartilhar)
          .then(() => {
            mostrarPopup("Link do produto copiado!", "sucesso");
          })
          .catch((err) => {
            console.error("Erro ao copiar link: ", err);
            mostrarPopup("Não foi possível copiar o link.", "erro");
          });
      };

      if (navigator.share) {
        try {
          await navigator.share(dadosPartilha);
        } catch (err) {
          console.error("Erro na Web Share API, a usar fallback:", err);
          copiarLinkComoFallback();
        }
      } else {
        copiarLinkComoFallback();
      }
    });
  }

  function fecharModal(updateHistory = true) {
    if (!estado.isModalOpen) return;

    modal.classList.remove("ativo");
    document.body.style.overflow = "";
    estado.isModalOpen = false;

    resetarModalParaEstadoInicial();

    const activeCard = document.querySelector(
      ".produto.mobile-actions-visible",
    );

    if (activeCard) {
      activeCard.classList.remove("mobile-actions-visible");
    }

    if (updateHistory && window.location.hash) {
      history.pushState(
        {},
        "",
        window.location.pathname + window.location.search,
      );
    }
  }

  function atualizarURL(slug, nome) {
    const novaUrl =
      window.location.pathname + window.location.search + "#" + slug;
    if (window.location.hash !== "#" + slug) {
      history.pushState({ slug: slug }, nome, novaUrl);
    }
  }

  function criar_slug(texto) {
    const a =
      "àáâäæãåāăąçćčđďèéêëēėęěğǵḧîïíīįìłḿñńǹňôöòóœøōõőṕŕřßśšşșťțûüùúūǘůűųẃẍÿýžźż·/_,:;";
    const b =
      "aaaaaaaaaacccddeeeeeeeegghiiiiiilmnnnnoooooooooprrsssssttuuuuuuuuuwxyyzzz------";
    const p = new RegExp(a.split("").join("|"), "g");
    return texto
      .toString()
      .toLowerCase()
      .replace(/\s+/g, "-")
      .replace(p, (c) => b.charAt(a.indexOf(c)))
      .replace(/&/g, "-e-")
      .replace(/[^\w\-]+/g, "")
      .replace(/\-\-+/g, "-")
      .replace(/^-+/, "")
      .replace(/-+$/, "");
  }

  if (modal) {
    const slugNoUrl = window.location.hash.substring(1);
    if (slugNoUrl && !window.matchMedia("(max-width: 768px)").matches) {
      const produtoParaAbrir = document.querySelector(
        `.produto[data-slug='${slugNoUrl}']`,
      );
      if (
        produtoParaAbrir &&
        !produtoParaAbrir.classList.contains("esgotado")
      ) {
        setTimeout(() => abrirModalProduto(produtoParaAbrir), 100);
      }
    }
  }
});
