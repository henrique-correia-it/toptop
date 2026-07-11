document.addEventListener("DOMContentLoaded", () => {
  const SEARCH_HISTORY_KEY = "toptop_search_history";
  const MAX_HISTORY_ITEMS = 6;

  const searchBtnMobile = document.getElementById("search-btn-mobile");
  const searchOverlay = document.getElementById("search-overlay");
  const closeSearchBtn = document.getElementById("fechar-pesquisa-btn");
  const searchInputMobile = searchOverlay?.querySelector('input[type="search"]');
  const historyList = searchOverlay?.querySelector(".search-history-list");
  const historyClearBtn = searchOverlay?.querySelector(".search-history-clear");
  const assistPanel = searchOverlay?.querySelector(".search-assist-panel");

  const getHistory = () => {
    try {
      const data = JSON.parse(localStorage.getItem(SEARCH_HISTORY_KEY) || "[]");
      return Array.isArray(data) ? data.filter(Boolean) : [];
    } catch (error) {
      return [];
    }
  };

  const saveHistoryTerm = (term) => {
    const cleanTerm = term.trim();
    if (!cleanTerm) return;
    const normalized = cleanTerm.toLocaleLowerCase();
    const nextHistory = [
      cleanTerm,
      ...getHistory().filter((item) => item.toLocaleLowerCase() !== normalized),
    ].slice(0, MAX_HISTORY_ITEMS);
    localStorage.setItem(SEARCH_HISTORY_KEY, JSON.stringify(nextHistory));
    renderHistory();
  };

  const removeHistoryTerm = (term) => {
    const normalized = term.toLocaleLowerCase();
    const nextHistory = getHistory().filter((item) => item.toLocaleLowerCase() !== normalized);

    if (nextHistory.length > 0) {
      localStorage.setItem(SEARCH_HISTORY_KEY, JSON.stringify(nextHistory));
    } else {
      localStorage.removeItem(SEARCH_HISTORY_KEY);
    }

    renderHistory();
  };

  const goToSearch = (term) => {
    const cleanTerm = term.trim();
    if (!cleanTerm) return;
    saveHistoryTerm(cleanTerm);
    window.location.href = `/produtos.php?q=${encodeURIComponent(cleanTerm)}`;
  };

  const renderHistory = () => {
    if (!historyList) return;
    const history = getHistory();
    historyList.innerHTML = "";

    if (history.length === 0) {
      historyList.innerHTML = '<p class="search-empty-note">As suas pesquisas recentes aparecem aqui.</p>';
      if (historyClearBtn) historyClearBtn.hidden = true;
      return;
    }

    if (historyClearBtn) historyClearBtn.hidden = false;
    history.forEach((term) => {
      const item = document.createElement("span");
      item.className = "search-history-chip";

      const termButton = document.createElement("button");
      termButton.type = "button";
      termButton.className = "search-history-term";
      termButton.textContent = term;
      termButton.addEventListener("click", () => {
        if (searchInputMobile) searchInputMobile.value = term;
        goToSearch(term);
      });

      const removeButton = document.createElement("button");
      removeButton.type = "button";
      removeButton.className = "search-history-remove";
      removeButton.setAttribute("aria-label", `Remover "${term}" do historico`);
      removeButton.innerHTML = '<span aria-hidden="true">&times;</span>';
      removeButton.addEventListener("click", (event) => {
        event.stopPropagation();
        removeHistoryTerm(term);
      });

      item.append(termButton, removeButton);
      historyList.appendChild(item);
    });
  };

  const setAssistVisible = (visible) => {
    if (!assistPanel) return;
    assistPanel.classList.toggle("is-hidden", !visible);
  };

  const atualizarEstadoBotaoPesquisa = (aberto) => {
    if (!searchBtnMobile) return;
    searchBtnMobile.classList.toggle("ativo", aberto);
    searchBtnMobile.setAttribute("aria-expanded", aberto ? "true" : "false");
    searchBtnMobile.setAttribute("aria-label", aberto ? "Fechar pesquisa" : "Abrir pesquisa");
  };

  const closeSearch = () => {
    if (searchOverlay) searchOverlay.classList.remove("ativo");
    atualizarEstadoBotaoPesquisa(false);
    document.body.classList.remove("app-panel-open");
    document.body.style.overflow = "";

    document.querySelectorAll(".search-results-container").forEach((container) => {
      container.style.display = "none";
      container.innerHTML = "";
    });
    setAssistVisible(true);
  };

  if (searchBtnMobile && searchOverlay) {
    const openSearch = () => {
      window.dispatchEvent(new CustomEvent("app:close-main-nav"));
      window.dispatchEvent(new CustomEvent("app:close-side-cart"));
      window.dispatchEvent(new CustomEvent("app:close-account-login"));
      renderHistory();
      setAssistVisible(true);
      searchOverlay.classList.add("ativo");
      atualizarEstadoBotaoPesquisa(true);
      document.body.classList.add("app-panel-open");
      document.body.style.overflow = "hidden";
      setTimeout(() => searchInputMobile?.focus(), 260);
    };

    searchBtnMobile.addEventListener("click", () => {
      if (searchOverlay.classList.contains("ativo")) {
        closeSearch();
      } else {
        openSearch();
      }
    });

    if (closeSearchBtn) closeSearchBtn.addEventListener("click", closeSearch);
    window.addEventListener("app:close-search", closeSearch);
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") closeSearch();
    });
  }

  if (historyClearBtn) {
    historyClearBtn.addEventListener("click", () => {
      localStorage.removeItem(SEARCH_HISTORY_KEY);
      renderHistory();
    });
  }

  const initPredictiveSearch = (formElement) => {
    const input = formElement.querySelector('input[type="search"]');
    const resultsContainer = formElement.querySelector(".search-results-container");
    let debounceTimer;
    let lastTerm = "";

    const hideResults = () => {
      resultsContainer.style.display = "none";
      resultsContainer.innerHTML = "";
      if (formElement.classList.contains("form-procura-overlay")) setAssistVisible(true);
    };

    const showMessage = (message, actionTerm = "") => {
      resultsContainer.innerHTML = "";
      const empty = document.createElement("div");
      empty.className = "search-results-empty";

      const title = document.createElement("strong");
      title.textContent = message;
      empty.appendChild(title);

      if (actionTerm) {
        const link = document.createElement("a");
        link.href = `/produtos.php?q=${encodeURIComponent(actionTerm)}`;
        link.textContent = `Ver resultados para "${actionTerm}"`;
        link.addEventListener("click", () => saveHistoryTerm(actionTerm));
        empty.appendChild(link);
      }

      resultsContainer.appendChild(empty);
      resultsContainer.style.display = "block";
      if (formElement.classList.contains("form-procura-overlay")) setAssistVisible(false);
    };

    const renderResults = (produtos, termo) => {
      resultsContainer.innerHTML = "";
      lastTerm = termo;

      if (!produtos.length) {
        showMessage("Não encontrámos produtos com esse termo.", termo);
        return;
      }

      produtos.forEach((p) => {
        const precoFinal = p.preco_promocional > 0 ? p.preco_promocional : p.preco;
        const item = document.createElement("a");
        item.href = "/produtos.php";
        item.classList.add("search-result-item");
        item.dataset.produtoId = p.id;
        item.dataset.searchTerm = termo;

        const image = document.createElement("img");
        image.src = `/public/images/${p.foto_principal}`;
        image.alt = p.nome;

        const info = document.createElement("div");
        info.className = "search-result-info";

        const name = document.createElement("span");
        name.className = "nome";
        name.textContent = p.nome;

        const price = document.createElement("span");
        price.className = "preco";
        price.textContent = `€${parseFloat(precoFinal).toFixed(2)}`;

        info.append(name, price);
        item.append(image, info);
        resultsContainer.appendChild(item);
      });

      const footerLink = document.createElement("a");
      footerLink.href = `/produtos.php?q=${encodeURIComponent(termo)}`;
      footerLink.classList.add("search-results-footer");
      footerLink.textContent = "Ver todos os resultados";
      footerLink.addEventListener("click", () => saveHistoryTerm(termo));
      resultsContainer.appendChild(footerLink);

      resultsContainer.style.display = "block";
      if (formElement.classList.contains("form-procura-overlay")) setAssistVisible(false);
    };

    formElement.addEventListener("submit", (event) => {
      const term = input.value.trim();
      if (!term) {
        event.preventDefault();
        input.focus();
        return;
      }
      saveHistoryTerm(term);
    });

    input.addEventListener("input", () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        const termo = input.value.trim();
        if (termo.length >= 2) {
          fetch(`/ajax_search.php?q=${encodeURIComponent(termo)}`)
            .then((response) => response.json())
            .then((data) => renderResults(data, termo))
            .catch(() => showMessage("A pesquisa falhou. Tente novamente.", termo));
        } else if (termo.length === 1) {
          showMessage("Escreva pelo menos 2 caracteres.");
        } else {
          hideResults();
        }
      }, 250);
    });

    input.addEventListener("focus", () => {
      if (!input.value.trim()) hideResults();
    });

    resultsContainer.addEventListener("click", function (e) {
      const resultItem = e.target.closest(".search-result-item");
      if (resultItem && resultItem.dataset.produtoId) {
        e.preventDefault();
        saveHistoryTerm(resultItem.dataset.searchTerm || lastTerm || input.value);
        sessionStorage.setItem("abrirModalProdutoId", resultItem.dataset.produtoId);
        window.location.href = "/produtos.php";
      }
    });

    document.addEventListener("click", function (e) {
      if (!formElement.contains(e.target)) {
        hideResults();
      }
    });
  };

  const desktopForm = document.querySelector(".form-procura");
  const mobileForm = document.querySelector(".form-procura-overlay");

  renderHistory();
  if (desktopForm) initPredictiveSearch(desktopForm);
  if (mobileForm) initPredictiveSearch(mobileForm);
});
