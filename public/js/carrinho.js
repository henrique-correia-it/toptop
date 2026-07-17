// public/js/carrinho.js

document.addEventListener("DOMContentLoaded", () => {
  let carrinho = JSON.parse(localStorage.getItem("carrinho")) || [];

  const sideCart = document.getElementById("side-cart");
  const sideCartOverlay = document.getElementById("side-cart-overlay");
  const sideCartItemsContainer = document.getElementById("side-cart-items");
  const sideCartSubtotal = document.getElementById("side-cart-subtotal");
  const sideCartTitle = document.querySelector(".side-cart-header h4");
  const fecharSideCartBtn = document.getElementById("btn-fechar-side-cart");
  const iconCarrinhoHeader = document.querySelector(".icon-carrinho");
  const finalizarSideCartBtn = document.getElementById("side-cart-finalizar");
  const CART_FLIGHT_DURATION = 800;

  // Esta variável agora é usada de forma consistente
  const isPaginaCarrinho = document.getElementById("itens-carrinho") !== null;
  const isPaginaCheckout = document.getElementById("formCheckout") !== null;

  const atualizarEstadoIconeCarrinho = (aberto) => {
    if (!iconCarrinhoHeader) return;
    iconCarrinhoHeader.classList.toggle("ativo", aberto);
    iconCarrinhoHeader.setAttribute("aria-expanded", aberto ? "true" : "false");
    iconCarrinhoHeader.setAttribute("aria-label", aberto ? "Fechar carrinho" : "Abrir carrinho");
    iconCarrinhoHeader.setAttribute("title", aberto ? "Fechar Carrinho" : "Ver Carrinho");
  };

  const abrirSideCart = () => {
    // CORREÇÃO DEFINITIVA: A verificação é feita aqui.
    // Se estivermos na página do carrinho ou do checkout, a função para imediatamente.
    if (isPaginaCarrinho || isPaginaCheckout) {
      return;
    }

    window.dispatchEvent(new CustomEvent("app:close-main-nav"));
    window.dispatchEvent(new CustomEvent("app:close-search"));
    window.dispatchEvent(new CustomEvent("app:close-account-login"));
    renderSideCart();
    if (sideCart) sideCart.classList.add("ativo");
    if (sideCartOverlay) sideCartOverlay.classList.add("ativo");
    atualizarEstadoIconeCarrinho(true);
    document.body.classList.add("app-panel-open");
    document.body.style.overflow = "hidden";
  };

  const fecharSideCart = () => {
    if (sideCart) sideCart.classList.remove("ativo");
    if (sideCartOverlay) sideCartOverlay.classList.remove("ativo");
    atualizarEstadoIconeCarrinho(false);
    document.body.classList.remove("app-panel-open");
    document.body.style.overflow = "";
  };
  
  const getDadosEntregaEstimados = () => {
    try {
      return JSON.parse(localStorage.getItem("checkout_cliente_dados") || "{}");
    } catch (e) {
      return {};
    }
  };

  const temPortesGratisEstimados = (subtotal) => {
    const regra = window.LOJA_CONFIG_PORTES_GRATIS || {};
    if (regra.ativo !== true) return false;
    const dadosEntrega = getDadosEntregaEstimados();
    const pais = String(dadosEntrega.pais_regiao || "").toUpperCase();
    const match = String(dadosEntrega.codigo_postal || "").trim().match(/^(\d{4})-\d{3}$/);
    if (!match || pais !== String(regra.pais || "PT").toUpperCase()) return false;

    const prefixo = Number.parseInt(match[1], 10);
    const minimo = Number(regra.valor_minimo || 0);
    return minimo > 0
      && subtotal >= minimo
      && prefixo >= Number(regra.cp_min || 1000)
      && prefixo <= Number(regra.cp_max || 8999);
  };

  const atingiuLimitePortesGratis = (subtotal) => {
    const regra = window.LOJA_CONFIG_PORTES_GRATIS || {};
    const minimo = Number(regra.valor_minimo || 0);
    return regra.ativo === true && minimo > 0 && subtotal >= minimo;
  };

  const getPortesEstimados = (pesoTotal, subtotal) => {
    const config = window.LOJA_CONFIG_PORTES || {};
    const pais = String(getDadosEntregaEstimados().pais_regiao || "PT").toUpperCase();
    const regras = config[pais] || config["PT"] || [];

    if (temPortesGratisEstimados(subtotal)) return 0;
    
    let portes = 0;
    for (let r of regras) {
        if (pesoTotal >= r.min && (pesoTotal < r.max || Number(r.max) === 0)) {
            portes = parseFloat(r.preco);
            break;
        }
    }
    // Fallback para peso acima do definido
    if (portes === 0 && regras.length > 0 && pesoTotal >= regras[regras.length - 1].max) {
        portes = parseFloat(regras[regras.length - 1].preco);
    }
    return portes;
  };

  const renderSideCart = () => {
    if (!sideCartItemsContainer || !sideCartSubtotal) return;

    sideCartItemsContainer.innerHTML = "";
    const totalItemsCarrinho = carrinho.reduce((acc, item) => acc + item.quantidade, 0);
    if (sideCartTitle) {
      sideCartTitle.innerHTML = `O Meu Carrinho <span>${totalItemsCarrinho} ${totalItemsCarrinho === 1 ? "item" : "itens"}</span>`;
    }
    if (carrinho.length === 0) {
      sideCartItemsContainer.innerHTML = `
        <div class="side-cart-vazio">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="9" cy="21" r="1"></circle>
            <circle cx="20" cy="21" r="1"></circle>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
          </svg>
          <h5>O seu carrinho está vazio.</h5>
          <p>Explore a loja e adicione os seus produtos favoritos.</p>
          <a href="/produtos.php" class="button voltar-btn">Explorar produtos</a>
        </div>`;
      sideCartSubtotal.parentElement.style.display = "none";
      if (finalizarSideCartBtn) finalizarSideCartBtn.style.display = "none";
      return;
    }

    sideCartSubtotal.parentElement.style.display = "block";
    if (finalizarSideCartBtn) finalizarSideCartBtn.style.display = "block";
    let totalPreco = 0;
    let totalPeso = 0;

    carrinho.forEach((item, index) => {
      totalPreco += parseFloat(item.preco) * item.quantidade;
      totalPeso += (parseInt(item.peso_gramas) || 0) * item.quantidade;

      let selecoesHTML = "";
      if (item.selecoes && Object.keys(item.selecoes).length > 0) {
        selecoesHTML = Object.entries(item.selecoes)
          .map(
            ([nome, valor]) =>
              `<p class="side-cart-item-atributo">${nome}: <strong>${valor}</strong></p>`,
          )
          .join("");
      }

      const isMaxStock = item.quantidade >= item.stock;
      const isMinStock = item.quantidade <= 1;
      const quantidadeHTML = `
                <div class="cart-qty-editor">
                    <button class="qty-btn" onclick="diminuirQuantidade(${index})" ${isMinStock ? "disabled" : ""}>-</button>
                    <span class="cart-qty-val">${item.quantidade}</span>
                    <button class="qty-btn" onclick="aumentarQuantidade(${index})" ${isMaxStock ? "disabled" : ""}>+</button>
                </div>
            `;

      const itemHTML = `
                <div class="side-cart-item">
                    <img src="${item.foto}" alt="${item.nome}">
                    <div class="side-cart-item-info">
                        <h5>${item.nome}</h5>
                        ${selecoesHTML}
                        <p class="side-cart-item-preco"><strong>€${number_format(item.preco, 2, ",", ".")}</strong></p>
                    </div>
                    <div class="side-cart-item-acoes">
                         <button class="remover-item-btn" onclick="removerDoCarrinho(${index})" title="Remover item">&times;</button>
                        ${quantidadeHTML}
                    </div>
                </div>
            `;
      sideCartItemsContainer.innerHTML += itemHTML;
    });
    
    const valorPortes = getPortesEstimados(totalPeso, totalPreco);
    const portesGratis = temPortesGratisEstimados(totalPreco);
    const valorSubtotal = sideCartSubtotal.children[1];
    valorSubtotal.innerHTML =
      `€${number_format(totalPreco, 2, ",", ".")} <small>${portesGratis ? "Portes grátis para Portugal Continental" : `+ €${number_format(valorPortes, 2, ",", ".")} de portes estimados`}</small>`;

    sideCartSubtotal.querySelector('.free-shipping-pending')?.remove();
    if (atingiuLimitePortesGratis(totalPreco) && !portesGratis) {
      sideCartSubtotal.insertAdjacentHTML(
        'beforeend',
        '<p class="free-shipping-pending">Portes grátis para Portugal Continental</p>'
      );
    }
  };

  const guardarCarrinho = (notificar = true) => {
    localStorage.setItem("carrinho", JSON.stringify(carrinho));
    if (notificar) window.dispatchEvent(new Event('cartUpdated'));
  };

  // Sincronização em tempo real (mesma aba e abas diferentes)
  const syncCarrinho = () => {
    const data = localStorage.getItem("carrinho");
    carrinho = data ? JSON.parse(data) : [];
    atualizarIconeCarrinho();
    if (isPaginaCarrinho) carregarItensCarrinho();
    if (sideCart && sideCart.classList.contains("ativo")) renderSideCart();
  };

  window.addEventListener('cartUpdated', syncCarrinho);

  window.addEventListener('storage', (e) => {
    if (e.key === 'carrinho') syncCarrinho();
  });

  const atualizarIconeCarrinho = (animar = false) => {
    const contagemElemento = document.getElementById("contagem-carrinho");
    if (contagemElemento) {
      const totalItems = carrinho.reduce(
        (acc, item) => acc + item.quantidade,
        0,
      );
      contagemElemento.textContent = totalItems;
      
      contagemElemento.style.display = ""; // Limpar inline style antigo se existir
      
      if (totalItems > 0) {
        contagemElemento.classList.add("visivel");
      } else {
        contagemElemento.classList.remove("visivel");
      }

      if (animar && totalItems > 0) {
        contagemElemento.classList.remove("pop");
        void contagemElemento.offsetWidth;
        contagemElemento.classList.add("pop");
        setTimeout(() => {
          contagemElemento.classList.remove("pop");
        }, 260);
      }

    }
  };
  window.atualizarIconeCarrinho = atualizarIconeCarrinho;

  const finalizarEncomenda = () => {
    if (carrinho.length === 0) {
      mostrarPopup("O seu carrinho está vazio.", "erro");
      return;
    }
    window.location.href = "/checkout";
  };

  window.adicionarAoCarrinho = (produto, startElement = null) => {
    const itemExistenteIndex = carrinho.findIndex(
      (item) =>
        item.id === produto.id &&
        JSON.stringify(item.selecoes) === JSON.stringify(produto.selecoes),
    );

    if (itemExistenteIndex > -1) {
      const novaQuantidade =
        carrinho[itemExistenteIndex].quantidade + produto.quantidade;
      if (novaQuantidade > carrinho[itemExistenteIndex].stock) {
        mostrarPopup(
          `Não pode adicionar mais. Stock máximo (${carrinho[itemExistenteIndex].stock}) atingido.`,
          "erro",
        );
        return;
      }
      carrinho[itemExistenteIndex].quantidade = novaQuantidade;
    } else {
      if (produto.quantidade > produto.stock) {
        mostrarPopup(
          `A quantidade excede o stock disponível (${produto.stock}).`,
          "erro",
        );
        return;
      }
      carrinho.push(produto);
    }

    // --- ANIMAÇÃO DE ADICIONAR AO CARRINHO ---
    const temAnimacaoVoo = startElement && iconCarrinhoHeader;
    if (temAnimacaoVoo) {
      const imgToClone = document.createElement('img');
      imgToClone.src = produto.foto;
      imgToClone.style.position = 'fixed';
      imgToClone.style.zIndex = '10005'; // Acima de modais
      imgToClone.style.width = '60px';
      imgToClone.style.height = '60px';
      imgToClone.style.objectFit = 'cover';
      imgToClone.style.borderRadius = '50%';
      imgToClone.style.boxShadow = '0 5px 15px rgba(0,0,0,0.2)';
      imgToClone.style.transition = `all ${CART_FLIGHT_DURATION}ms cubic-bezier(0.2, 0.8, 0.2, 1)`;
      imgToClone.style.pointerEvents = 'none';
      
      const startRect = startElement.getBoundingClientRect();
      const endRect = iconCarrinhoHeader.getBoundingClientRect();
      
      imgToClone.style.top = `${startRect.top + startRect.height / 2 - 30}px`;
      imgToClone.style.left = `${startRect.left + startRect.width / 2 - 30}px`;
      
      document.body.appendChild(imgToClone);
      
      // Force reflow
      imgToClone.getBoundingClientRect();
      
      // Target
      imgToClone.style.top = `${endRect.top + endRect.height / 2 - 30}px`;
      imgToClone.style.left = `${endRect.left + endRect.width / 2 - 30}px`;
      imgToClone.style.transform = 'scale(0.1)';
      imgToClone.style.opacity = '0.5';
      
      setTimeout(() => {
        if (document.body.contains(imgToClone)) {
            imgToClone.remove();
        }
      }, CART_FLIGHT_DURATION);
    }

    guardarCarrinho(!temAnimacaoVoo);
    if (temAnimacaoVoo) {
      setTimeout(() => {
        window.dispatchEvent(new Event('cartUpdated'));
        atualizarIconeCarrinho(true);
      }, CART_FLIGHT_DURATION);
    } else {
      atualizarIconeCarrinho(true);
    }
    if (typeof mostrarPopup === "function") {
      mostrarPopup(`"${produto.nome}" foi adicionado ao carrinho!`);
    }
  };

  window.removerDoCarrinho = (index) => {
    carrinho.splice(index, 1);
    guardarCarrinho();
  };

  window.aumentarQuantidade = (index) => {
    if (carrinho[index]) {
      if (carrinho[index].quantidade < carrinho[index].stock) {
        carrinho[index].quantidade++;
        guardarCarrinho();
      } else {
        mostrarPopup("Stock máximo atingido para este item.", "erro");
      }
    }
  };

  window.diminuirQuantidade = (index) => {
    if (carrinho[index] && carrinho[index].quantidade > 1) {
      carrinho[index].quantidade--;
      guardarCarrinho();
    }
  };

  const carregarItensCarrinho = () => {
    if (!isPaginaCarrinho) return;

    const container = document.getElementById("itens-carrinho");
    const totalContainer = document.getElementById("total-carrinho");
    const btnFinalizar = document.getElementById(
      "finalizar-encomenda-checkout",
    );
    const cartContainer = document.querySelector('.cart-container');

    if (cartContainer) cartContainer.classList.remove('vazio');
    container.innerHTML = "";
    if (carrinho.length === 0) {
      container.innerHTML = `
        <div class="cart-empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" style="width:64px; height:64px; margin-bottom:20px;">
                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <p>O seu carrinho está vazio.</p>
            <a href="produtos.php" class="button voltar-btn" style="margin-top:20px; display:inline-block;">Explorar Produtos</a>
        </div>`;
      const cartContainer = document.querySelector('.cart-container');
      if (cartContainer) cartContainer.classList.add('vazio');

      if (totalContainer) totalContainer.parentElement.style.display = "none";
      const checkoutWrapper = document.querySelector('.cart-summary-wrapper');
      if (checkoutWrapper) checkoutWrapper.style.display = "none";
      const actionsBottom = document.querySelector('.cart-actions-bottom');
      if (actionsBottom) actionsBottom.style.display = "none";
      return;
    }

    const checkoutWrapper = document.querySelector('.cart-summary-wrapper');
    if (checkoutWrapper) checkoutWrapper.style.display = "block";
    const actionsBottom = document.querySelector('.cart-actions-bottom');
    if (actionsBottom) actionsBottom.style.display = "flex";

    let totalPreco = 0;
    let totalPeso = 0;
    carrinho.forEach((item, index) => {
      totalPreco += parseFloat(item.preco) * item.quantidade;
      totalPeso += (parseInt(item.peso_gramas) || 0) * item.quantidade;
      
      let precoHTML = item.emPromocao
        ? `<strong>€${number_format(item.preco, 2, ",", ".")} <del style="font-weight:400; color:#94a3b8; font-size:0.85rem; margin-left:5px;">€${number_format(item.precoOriginal, 2, ",", ".")}</del></strong>`
        : `<strong>€${number_format(item.preco, 2, ",", ".")}</strong>`;
      
      let selecoesHTML = "";
      if (item.selecoes) {
        for (const nomeAtributo in item.selecoes) {
          selecoesHTML += `<p class="side-cart-item-atributo">${nomeAtributo}: <strong>${item.selecoes[nomeAtributo]}</strong></p>`;
        }
      }

      const isMaxStock = item.stock && item.quantidade >= item.stock;
      const isMinStock = item.quantidade <= 1;

      const quantidadeHTML = `
        <div class="cart-qty-editor">
            <button class="qty-btn" onclick="diminuirQuantidade(${index})" ${isMinStock ? "disabled" : ""}>-</button>
            <span class="cart-qty-val">${item.quantidade}</span>
            <button class="qty-btn" onclick="aumentarQuantidade(${index})" ${isMaxStock ? "disabled" : ""}>+</button>
        </div>
      `;

      container.innerHTML += `
        <div class="item-carrinho side-cart-item">
            <img src="${item.foto}" alt="${item.nome}">
            <div class="side-cart-item-info">
                <h5>${item.nome}</h5>
                ${selecoesHTML}
                <p class="side-cart-item-preco">${precoHTML}</p>
            </div>
            <div class="side-cart-item-acoes">
                <button class="remover-item-btn" onclick="removerDoCarrinho(${index})" title="Remover item">&times;</button>
                ${quantidadeHTML}
            </div>
        </div>`;
    });

    if (totalContainer) {
      const valorPortes = getPortesEstimados(totalPeso, totalPreco);
      const portesGratis = temPortesGratisEstimados(totalPreco);
      const mostrarNotaPortesGratis = atingiuLimitePortesGratis(totalPreco) && !portesGratis;
      const totalFinal = totalPreco + valorPortes;
      totalContainer.innerHTML = `
        <h3>Sumário da Encomenda</h3>
        <div class="cart-sum-row">
            <span>Subtotal</span>
            <span>€${number_format(totalPreco, 2, ",", ".")}</span>
        </div>
        <div class="cart-sum-row">
            <span>Envio Estimado</span>
            <span>${portesGratis ? "Grátis" : `€${number_format(valorPortes, 2, ",", ".")}`}</span>
        </div>
        ${mostrarNotaPortesGratis ? '<p class="cart-shipping-note">Portes grátis para Portugal Continental</p>' : ''}
        <div class="cart-sum-row total">
            <span>Total</span>
            <div>
                €${number_format(totalFinal, 2, ",", ".")}
                <small>IVA incluído</small>
            </div>
        </div>`;
    }
  };

  // A lógica de clique no ícone do header não precisa de ser alterada,
  // pois a função `abrirSideCart` já faz a verificação.
  if (iconCarrinhoHeader) {
    iconCarrinhoHeader.addEventListener("click", (e) => {
      e.preventDefault();
      if (sideCart && sideCart.classList.contains("ativo")) {
        fecharSideCart();
      } else {
        abrirSideCart();
      }
    });
  }

  window.addEventListener("app:close-side-cart", fecharSideCart);
  window.fecharSideCart = fecharSideCart;

  if (fecharSideCartBtn)
    fecharSideCartBtn.addEventListener("click", fecharSideCart);
  if (sideCartOverlay)
    sideCartOverlay.addEventListener("click", fecharSideCart);
  if (finalizarSideCartBtn) {
    finalizarSideCartBtn.addEventListener("click", (e) => {
      e.preventDefault();
      finalizarEncomenda();
    });
  }

  atualizarIconeCarrinho();
  if (isPaginaCarrinho) {
    carregarItensCarrinho();
  }
});
