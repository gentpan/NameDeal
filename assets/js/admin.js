/**
 * 域名管理后台 JavaScript
 */

(function () {
  "use strict";
  const ADMIN_ENDPOINT =
    document.body &&
    document.body.dataset &&
    document.body.dataset.adminEndpoint
      ? document.body.dataset.adminEndpoint
      : "admin.php";
  const CSRF_TOKEN =
    document.body && document.body.dataset ? document.body.dataset.csrfToken : "";

  function appendCsrfToken(formData) {
    if (CSRF_TOKEN) {
      formData.append("csrf_token", CSRF_TOKEN);
    }
    return formData;
  }

  function syncTopbarHeight() {
    const topbar = document.querySelector(".topbar");
    if (!topbar) return;
    document.documentElement.style.setProperty(
      "--topbar-height",
      `${Math.ceil(topbar.getBoundingClientRect().height)}px`
    );
  }

  window.addEventListener("resize", syncTopbarHeight);
  syncTopbarHeight();

  let domainsCurrentPage = 1;
  let domainsPerPage =
    parseInt(document.getElementById("perPageSelect")?.value || "10", 10) || 10;
  try {
    domainsCurrentPage =
      parseInt(new URL(window.location.href).searchParams.get("page") || "1", 10) ||
      1;
  } catch (err) {
    domainsCurrentPage = 1;
  }

  function escapeAttr(value) {
    return escapeHtml(value).replace(/`/g, "&#096;");
  }

  function formatDomainName(domain) {
    const safeDomain = escapeHtml(domain || "");
    const parts = safeDomain.split(".", 2);
    if (parts.length === 2 && parts[0] && parts[1]) {
      return `<span class="domain-name-part">${parts[0]}</span><span class="domain-name-dot">.</span><span class="domain-name-part">${parts[1]}</span>`;
    }
    return `<span class="domain-name-part">${safeDomain}</span>`;
  }

  function truncateText(value, maxLength) {
    const text = String(value || "");
    return text.length > maxLength ? `${text.slice(0, maxLength)}...` : text;
  }

  function renderDomainRow(domain) {
    const id = Number(domain.id || 0);
    const domainName = String(domain.domain || "");
    const title = String(domain.title || "");
    const intro = String(domain.domain_intro || "");
    const price = String(domain.domain_price || "");
    const themeColor = String(domain.theme_color || "#0066FC");
    const deleteArg = JSON.stringify(domainName).replace(/'/g, "&#039;");

    return `
      <div class="domain-row" data-id="${id}" data-domain="${escapeAttr(domainName.toLowerCase())}" data-title="${escapeAttr(title.toLowerCase())}">
        <div class="domain-row-main">
          <div class="domain-row-name">
            <span class="domain-name-text">${formatDomainName(domainName)}</span>
          </div>
          <div class="domain-row-info">
            <span class="row-info-item row-info-title">${escapeHtml(title)}</span>
            <span class="row-info-item row-info-color">
              <span class="color-preview" style="background-color: ${escapeAttr(themeColor)}"></span>
              ${escapeHtml(themeColor)}
            </span>
            ${intro ? `<span class="row-info-item row-info-intro" title="${escapeAttr(intro)}">${escapeHtml(truncateText(intro, 30))}</span>` : ""}
            ${price ? `<span class="row-info-item row-info-price">¥${escapeHtml(price)}</span>` : ""}
          </div>
          <div class="domain-row-actions">
            <button class="btn-icon btn-whois-toggle" type="button" data-domain="${escapeAttr(domainName)}" title="WHOIS 一键查询" aria-expanded="false">
              <svg class="whois-icon" viewBox="0 0 1024 1024" aria-hidden="true" focusable="false">
                <path d="M707.621926 350.549333l-45.037037 6.637037C636.416 179.465481 567.580444 60.681481 498.839704 60.681481c-68.077037 0-136.343704 116.508444-163.081482 291.802075l-44.980148-6.864593C320.587852 150.243556 399.701333 15.17037 498.839704 15.17037c99.972741 0 179.617185 137.462519 208.782222 335.378963zM290.664296 677.641481l44.999111-6.826666c26.661926 175.653926 95.004444 292.503704 163.176297 292.503704 68.266667 0 136.722963-117.229037 163.271111-293.281186l44.999111 6.788741C677.546667 872.997926 598.224593 1008.82963 498.839704 1008.82963c-99.252148 0-178.460444-135.433481-208.175408-331.188149z" fill="currentColor"></path>
                <path d="M512 1008.82963C237.605926 1008.82963 15.17037 786.394074 15.17037 512 15.17037 237.605926 237.605926 15.17037 512 15.17037 786.394074 15.17037 1008.82963 237.605926 1008.82963 512c0 274.394074-222.435556 496.82963-496.82963 496.82963z m0-45.511111c249.249185 0 451.318519-202.069333 451.318519-451.318519S761.249185 60.681481 512 60.681481 60.681481 262.750815 60.681481 512 262.750815 963.318519 512 963.318519z" fill="currentColor"></path>
                <path d="M64.265481 376.737185v-45.511111H959.715556v45.511111H64.284444zM959.715556 647.262815v45.511111H64.284444v-45.511111H959.715556z" fill="currentColor"></path>
              </svg>
              <span class="whois-btn-spinner" aria-hidden="true"></span>
            </button>
            <button class="btn-icon" onclick="editDomain(${id})" title="编辑">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="18" height="18">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke-linejoin="round" stroke-linecap="round"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke-linejoin="round" stroke-linecap="round"></path>
              </svg>
            </button>
            <button class="btn-icon btn-delete" onclick='deleteDomain(${id}, ${deleteArg})' title="删除">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="18" height="18">
                <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke-linejoin="round" stroke-linecap="round"></path>
                <line x1="10" y1="11" x2="10" y2="17" stroke-linejoin="round" stroke-linecap="round"></line>
                <line x1="14" y1="11" x2="14" y2="17" stroke-linejoin="round" stroke-linecap="round"></line>
              </svg>
            </button>
          </div>
        </div>
      </div>
    `;
  }

  // 显示添加域名模态框
  window.showAddModal = function () {
    document.getElementById("modalTitle").textContent = "添加域名";
    document.getElementById("domainForm").reset();
    document.getElementById("domainId").value = "";
    document.getElementById("domain").disabled = false;
    document.getElementById("domainModal").classList.add("show");
  };

  // 显示编辑域名模态框
  window.editDomain = async function (id) {
    try {
      const formData = new FormData();
      formData.append("action", "get");
      formData.append("id", id);
      appendCsrfToken(formData);

      const response = await fetch(ADMIN_ENDPOINT, {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        const domain = result.data;
        document.getElementById("modalTitle").textContent = "编辑域名";
        document.getElementById("domainId").value = domain.id;
        document.getElementById("domain").value = domain.domain;
        document.getElementById("domain").disabled = false;
        document.getElementById("title").value = domain.title;
        document.getElementById("theme_color").value = domain.theme_color;
        document.getElementById("domain_intro").value =
          domain.domain_intro || "";
        document.getElementById("domain_price").value =
          domain.domain_price || "";
        document.getElementById("domainModal").classList.add("show");
      } else {
        showMessage(result.message, "error");
      }
    } catch (error) {
      showMessage("加载域名信息失败", "error");
      console.error("Error:", error);
    }
  };

  // 关闭模态框
  window.closeModal = function () {
    document.getElementById("domainModal").classList.remove("show");
  };

  // 保存域名
  window.saveDomain = async function (event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const domainId = document.getElementById("domainId").value;

    // 设置操作类型
    formData.append("action", domainId ? "update" : "add");
    appendCsrfToken(formData);

    // 禁用提交按钮
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = "保存中...";

    try {
      const response = await fetch(ADMIN_ENDPOINT, {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        showMessage(result.message, "success");
        closeModal();
        // 延迟刷新以显示成功消息
        setTimeout(() => {
          window.location.reload();
        }, 1000);
      } else {
        showMessage(result.message, "error");
        submitBtn.disabled = false;
        submitBtn.textContent = "保存";
      }
    } catch (error) {
      showMessage("保存失败，请重试", "error");
      submitBtn.disabled = false;
      submitBtn.textContent = "保存";
      console.error("Error:", error);
    }
  };

  // 删除域名
  window.deleteDomain = function (id, domain) {
    // 显示确认弹窗
    const confirmModal = document.getElementById("confirmDeleteModal");
    const confirmText = document.getElementById("confirmDeleteText");
    const confirmBtn = document.getElementById("confirmDeleteBtn");
    const cancelBtn = document.getElementById("cancelDeleteBtn");

    if (!confirmModal || !confirmText || !confirmBtn || !cancelBtn) {
      // 如果模态框不存在，使用原生 confirm
      if (!confirm(`确定要删除域名 "${domain}" 吗？\n\n此操作不可撤销！`)) {
        return;
      }
    } else {
      confirmText.textContent = `确定要删除域名 "${domain}" 吗？\n\n此操作不可撤销！`;
      confirmModal.classList.add("show");

      // 等待用户确认
      return new Promise((resolve) => {
        let handleModalClick, handleEsc;

        const handleConfirm = () => {
          confirmModal.classList.remove("show");
          confirmBtn.removeEventListener("click", handleConfirm);
          cancelBtn.removeEventListener("click", handleCancel);
          if (handleModalClick)
            confirmModal.removeEventListener("click", handleModalClick);
          if (handleEsc) document.removeEventListener("keydown", handleEsc);
          resolve(true);
        };

        const handleCancel = () => {
          confirmModal.classList.remove("show");
          confirmBtn.removeEventListener("click", handleConfirm);
          cancelBtn.removeEventListener("click", handleCancel);
          if (handleModalClick)
            confirmModal.removeEventListener("click", handleModalClick);
          if (handleEsc) document.removeEventListener("keydown", handleEsc);
          resolve(false);
        };

        confirmBtn.addEventListener("click", handleConfirm);
        cancelBtn.addEventListener("click", handleCancel);

        // 点击模态框外部关闭
        handleModalClick = (e) => {
          if (e.target === confirmModal) {
            confirmModal.classList.remove("show");
            confirmBtn.removeEventListener("click", handleConfirm);
            cancelBtn.removeEventListener("click", handleCancel);
            confirmModal.removeEventListener("click", handleModalClick);
            document.removeEventListener("keydown", handleEsc);
            resolve(false);
          }
        };
        confirmModal.addEventListener("click", handleModalClick);

        // ESC 键关闭
        handleEsc = (e) => {
          if (e.key === "Escape") {
            confirmModal.classList.remove("show");
            confirmBtn.removeEventListener("click", handleConfirm);
            cancelBtn.removeEventListener("click", handleCancel);
            confirmModal.removeEventListener("click", handleModalClick);
            document.removeEventListener("keydown", handleEsc);
            resolve(false);
          }
        };
        document.addEventListener("keydown", handleEsc);
      }).then((confirmed) => {
        if (!confirmed) return;
        performDelete(id);
      });
      return;
    }

    performDelete(id);
  };

  // 执行删除操作
  function performDelete(id) {
    const formData = new FormData();
    formData.append("action", "delete");
    formData.append("id", id);
    appendCsrfToken(formData);

    fetch(ADMIN_ENDPOINT, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((result) => {
        if (result.success) {
          showMessage(result.message, "success");
          // 从DOM中移除行
          const row = document.querySelector(`.domain-row[data-id="${id}"]`);
          if (row) {
            row.style.animation = "fadeOut 0.3s ease";
            setTimeout(() => {
              row.remove();
              // 更新计数
              updateDomainCount();
            }, 300);
          }
        } else {
          showMessage(result.message, "error");
        }
      })
      .catch((error) => {
        showMessage("删除失败，请重试", "error");
        console.error("Error:", error);
      });
  }

  // 更新域名计数
  function updateDomainCount() {
    const visibleRows = document.querySelectorAll(
      ".domain-row:not([style*='display: none'])"
    ).length;
    const countElement = document.getElementById("domainCountDisplay");
    if (countElement) {
      const totalCount = document.querySelectorAll(".domain-row").length;
      if (visibleRows === totalCount) {
        countElement.textContent = `共 ${totalCount} 个域名`;
      } else {
        countElement.textContent = `共 ${visibleRows} / ${totalCount} 个域名`;
      }
    }

    // 如果没有可见的域名了，显示空状态
    if (visibleRows === 0) {
      const list = document.getElementById("domainsList");
      const existingEmpty = list.querySelector(".empty-state");
      if (!existingEmpty && list) {
        const emptyState = document.createElement("div");
        emptyState.className = "empty-state";
        emptyState.innerHTML = `
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="64" height="64">
                            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                        </svg>
                    </div>
                    <p>未找到匹配的域名</p>
                `;
        list.appendChild(emptyState);
      }
    } else {
      // 如果有可见的域名，移除空状态
      const existingEmpty = document.querySelector("#domainsList .empty-state");
      if (existingEmpty) {
        existingEmpty.remove();
      }
    }
  }

  function renderDomainsPagination(currentPage, totalPages) {
    if (totalPages <= 0) return "";
    const prev =
      currentPage > 1
        ? `<button type="button" class="pagination-btn" data-domains-page="${currentPage - 1}">上一页</button>`
        : '<span class="pagination-btn disabled">上一页</span>';
    const next =
      currentPage < totalPages
        ? `<button type="button" class="pagination-btn" data-domains-page="${currentPage + 1}">下一页</button>`
        : '<span class="pagination-btn disabled">下一页</span>';

    return `
      <div class="pagination-wrapper">
        <div class="pagination">
          ${prev}
          <span class="pagination-info">第 ${currentPage} / ${totalPages} 页</span>
          ${next}
        </div>
      </div>
    `;
  }

  function renderDomainsList(domains, totalCount, currentPage, totalPages) {
    const list = document.getElementById("domainsList");
    if (!list) return;

    if (!domains.length) {
      list.innerHTML = `
        <div class="empty-state">
          <div class="empty-icon">
            <svg viewBox="0 0 24 24" fill="currentColor" width="64" height="64">
              <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" />
            </svg>
          </div>
          <p>还没有添加任何域名</p>
          <button class="btn btn-primary" onclick="showAddModal()">添加第一个域名</button>
        </div>
      `;
    } else {
      list.innerHTML =
        domains.map((domain) => renderDomainRow(domain)).join("") +
        renderDomainsPagination(currentPage, totalPages);
    }

    const countElement = document.getElementById("domainCountDisplay");
    if (countElement) {
      countElement.textContent = `共 ${totalCount} 个域名`;
    }

    const searchInput = document.getElementById("domainSearch");
    if (searchInput && searchInput.value.trim()) {
      searchInput.dispatchEvent(new Event("input"));
    }
  }

  async function loadDomainsPage(page, perPage) {
    const list = document.getElementById("domainsList");
    if (!list) return;

    const fd = new FormData();
    fd.append("action", "get_domains_page");
    fd.append("page", String(page));
    fd.append("per_page", String(perPage));
    appendCsrfToken(fd);

    list.classList.add("is-loading");
    try {
      const res = await fetch(ADMIN_ENDPOINT, { method: "POST", body: fd });
      const result = await res.json();
      if (!result.success || !result.data) {
        showMessage(result.message || "加载域名失败", "error");
        return;
      }

      const data = result.data;
      domainsCurrentPage = Number(data.current_page || 1);
      domainsPerPage = Number(data.per_page || perPage);
      renderDomainsList(
        Array.isArray(data.domains) ? data.domains : [],
        Number(data.total_count || 0),
        domainsCurrentPage,
        Number(data.total_pages || 1)
      );
    } catch (err) {
      showMessage("加载域名失败", "error");
      console.error("Load domains failed:", err);
    } finally {
      list.classList.remove("is-loading");
    }
  }

  // 通用搜索功能
  function initSearch(searchInputId, rowSelector, dataAttrs, emptyStateClass, emptyStateContainer) {
    const searchInput = document.getElementById(searchInputId);
    if (!searchInput) return;

    searchInput.addEventListener("input", function () {
      const keyword = this.value.trim().toLowerCase();
      const rows = document.querySelectorAll(rowSelector);
      const container = emptyStateContainer ? document.getElementById(emptyStateContainer) : null;
      let visibleCount = 0;

      rows.forEach((row) => {
        let matches = false;
        if (keyword === "") {
          matches = true;
        } else {
          for (const attr of dataAttrs) {
            const text = row.getAttribute(attr) || "";
            if (text.includes(keyword)) {
              matches = true;
              break;
            }
          }
        }
        row.style.display = matches ? "" : "none";
        if (matches) visibleCount++;
      });

      // 处理空状态
      if (container) {
        const existingEmpty = container.querySelector("." + emptyStateClass);
      if (visibleCount === 0 && keyword) {
          if (!existingEmpty) {
          const emptyState = document.createElement("div");
            emptyState.className = "empty-state " + emptyStateClass;
          emptyState.innerHTML = `
            <div class="empty-icon">
              <svg viewBox="0 0 24 24" fill="currentColor" width="64" height="64">
                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
              </svg>
            </div>
            <p>未找到匹配的域名</p>
          `;
            container.appendChild(emptyState);
        }
        } else if (existingEmpty) {
          existingEmpty.remove();
        }
      }

      if (searchInputId === "domainSearch") {
        updateDomainCount();
      }
    });

    searchInput.addEventListener("focus", function () {
      if (this.value) this.select();
    });
  }

  // 初始化搜索功能
  initSearch("statsDomainSearch", ".stats-row", ["data-domain"], "empty-state-search", "statsList");
  initSearch("domainSearch", ".domain-row", ["data-domain", "data-title"], "empty-state", "domainsList");

  // WHOIS 一键查询（事件委托 + Modal）
  (function initWhoisLookup() {
    const domainsList = document.getElementById("domainsList");
    const whoisModal = document.getElementById("whoisModal");
    const whoisModalBody = document.getElementById("whoisModalBody");
    const whoisModalTitle = document.getElementById("whoisModalTitle");
    const whoisCopyBtn = document.getElementById("whoisCopyBtn");
    if (
      !domainsList ||
      !whoisModal ||
      !whoisModalBody ||
      !whoisModalTitle ||
      !whoisCopyBtn
    )
      return;

    const cache = new Map();
    let activeButton = null;
    let whoisCopyText = "";

    const formatDate = (value) => {
      if (!value) return "-";
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return String(value);
      return date.toISOString().slice(0, 10);
    };

    const isExpiringSoon = (value) => {
      if (!value) return false;
      const expiry = new Date(value);
      if (Number.isNaN(expiry.getTime())) return false;
      const now = new Date();
      const diff = expiry.getTime() - now.getTime();
      return diff > 0 && diff <= 30 * 24 * 60 * 60 * 1000;
    };

    const escapeHtml = (value) =>
      String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

    const setButtonLoading = (button, isLoading) => {
      if (!button) return;
      button.classList.toggle("is-loading", isLoading);
      button.disabled = isLoading;
    };

    const showWhoisModalLoading = () => {
      whoisModalBody.innerHTML = `
        <div class="whois-loading">
          <span class="whois-loading-spinner" aria-hidden="true"></span>
          <span>正在查询 WHOIS...</span>
        </div>
      `;
      whoisCopyText = "";
      whoisCopyBtn.disabled = true;
    };

    const buildWhoisCopyText = (data) => {
      if (data.available) {
        return `Domain Name: ${data.domain || "-"}\nThis domain is available`;
      }
      const statuses = Array.isArray(data.status)
        ? data.status.join(", ")
        : data.status || "-";
      const nameServers = Array.isArray(data.nameservers)
        ? data.nameservers
        : [];
      const nsText = nameServers.length
        ? nameServers.map((ns) => `- ${ns}`).join("\n")
        : "-";
      return [
        `Domain Name: ${data.domain || "-"}`,
        `Registrar: ${data.registrar || "-"}`,
        `Creation Date: ${formatDate(data.created)}`,
        `Expiry Date: ${formatDate(data.expires)}`,
        `Updated Date: ${formatDate(data.updated)}`,
        `Domain Status: ${statuses}`,
        "Name Servers:",
        nsText,
      ].join("\n");
    };

    const copyToClipboard = async (text) => {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return;
      }
      const textarea = document.createElement("textarea");
      textarea.value = text;
      textarea.setAttribute("readonly", "");
      textarea.style.position = "fixed";
      textarea.style.left = "-9999px";
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand("copy");
      document.body.removeChild(textarea);
    };

    const renderWhois = (data) => {
      if (data.available) {
        return '<div class="whois-available">👉 This domain is available</div>';
      }

      const expires = formatDate(data.expires);
      const expireClass = isExpiringSoon(data.expires) ? "whois-expiring" : "";
      const statuses = Array.isArray(data.status)
        ? data.status.join(", ")
        : data.status || "-";
      const nameServers = (Array.isArray(data.nameservers) ? data.nameservers : []).filter((ns) => {
        const value = String(ns || "").trim().toLowerCase().replace(/\.$/, "");
        return value && !["not.defined", "undefined", "unknown", "none", "n/a", "null", "-"].includes(value);
      });
      const nsHtml = nameServers.length
        ? `<ul class="whois-ns-list">${nameServers
            .map((ns) => `<li><span class="whois-tag">${escapeHtml(ns)}</span></li>`)
            .join("")}</ul>`
        : '<div class="whois-value">-</div>';

      return `
        <div class="whois-grid">
          <div class="whois-row"><span class="whois-label">Domain Name</span><span class="whois-value">${escapeHtml(data.domain || "-")}</span></div>
          <div class="whois-row"><span class="whois-label">Registrar</span><span class="whois-value">${escapeHtml(data.registrar || "-")}</span></div>
          <div class="whois-row"><span class="whois-label">Creation Date</span><span class="whois-value">${escapeHtml(formatDate(data.created))}</span></div>
          <div class="whois-row"><span class="whois-label">Expiry Date</span><span class="whois-value ${expireClass}">${escapeHtml(expires)}</span></div>
          <div class="whois-row"><span class="whois-label">Updated Date</span><span class="whois-value">${escapeHtml(formatDate(data.updated))}</span></div>
          <div class="whois-row"><span class="whois-label">Domain Status</span><span class="whois-value">${escapeHtml(statuses || "-")}</span></div>
        </div>
        <div class="whois-row whois-row-stack"><span class="whois-label">Name Servers</span>${nsHtml}</div>
      `;
    };

    window.closeWhoisModal = function () {
      whoisModal.classList.remove("show");
      whoisModal.setAttribute("aria-hidden", "true");
      document.documentElement.classList.remove("admin-whois-overlay-active");
      if (activeButton) {
        activeButton.setAttribute("aria-expanded", "false");
      }
    };

    whoisCopyBtn.addEventListener("click", async () => {
      if (!whoisCopyText) return;
      try {
        await copyToClipboard(whoisCopyText);
        showMessage("WHOIS 结果已复制", "success");
      } catch (err) {
        showMessage("复制失败，请手动复制", "error");
      }
    });

    whoisModal.addEventListener("click", (event) => {
      if (event.target === whoisModal) {
        window.closeWhoisModal();
      }
    });

    domainsList.addEventListener("click", async (event) => {
      const toggleBtn = event.target.closest(".btn-whois-toggle");
      if (!toggleBtn) return;

      const domain = (toggleBtn.dataset.domain || "").trim();
      if (!domain) return;

      if (activeButton && activeButton !== toggleBtn) {
        setButtonLoading(activeButton, false);
        activeButton.setAttribute("aria-expanded", "false");
      }
      activeButton = toggleBtn;
      toggleBtn.setAttribute("aria-expanded", "true");
      setButtonLoading(toggleBtn, true);

      whoisModalTitle.textContent = `WHOIS 查询 - ${domain}`;
      showWhoisModalLoading();
      whoisModal.classList.add("show");
      whoisModal.setAttribute("aria-hidden", "false");
      document.documentElement.classList.add("admin-whois-overlay-active");

      try {
        let whoisData = cache.get(domain);
        if (!whoisData) {
          const res = await fetch(
            `/api/whois.php?domain=${encodeURIComponent(domain)}`,
            { method: "GET" }
          );
          whoisData = await res.json();
          cache.set(domain, whoisData);
        }

        if (whoisData.error) {
          whoisModalBody.innerHTML = `<div class="whois-value">${escapeHtml(
            whoisData.error
          )}</div>`;
          whoisCopyText = "";
          whoisCopyBtn.disabled = true;
        } else {
          whoisModalBody.innerHTML = renderWhois(whoisData);
          whoisCopyText = buildWhoisCopyText(whoisData);
          whoisCopyBtn.disabled = false;
        }
      } catch (err) {
        whoisModalBody.innerHTML =
          '<div class="whois-value">WHOIS 查询失败，请稍后重试</div>';
        whoisCopyText = "";
        whoisCopyBtn.disabled = true;
      } finally {
        setButtonLoading(toggleBtn, false);
      }
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && whoisModal.classList.contains("show")) {
        window.closeWhoisModal();
      }
    });
  })();

  // 显示消息提示
  function showMessage(message, type = "info") {
    const oldToasts = document.querySelectorAll(".message-toast");
    oldToasts.forEach((item) => item.remove());

    const safeType = ["success", "error", "info"].includes(type)
      ? type
      : "info";
    const toast = document.createElement("div");
    toast.className = `message-toast message-${safeType}`;
    toast.innerHTML = `
      <span class="message-toast-icon" aria-hidden="true"></span>
      <span class="message-toast-text"></span>
    `;
    const text = toast.querySelector(".message-toast-text");
    if (text) text.textContent = message;

    let removed = false;
    const removeToast = () => {
      if (removed) return;
      removed = true;
      toast.classList.remove("show");
      toast.classList.add("hide");
      setTimeout(() => toast.remove(), 260);
    };

    document.body.appendChild(toast);
    requestAnimationFrame(() => {
      toast.classList.add("show");
    });

    setTimeout(removeToast, 3200);
  }

  // 点击模态框背景关闭 - 已禁用，只能通过取消按钮或右上角X关闭
  // document.addEventListener("click", function (e) {
  //   const modal = document.getElementById("domainModal");
  //   if (e.target === modal) {
  //     closeModal();
  //   }
  // });

  // ESC键关闭模态框
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      const modal = document.getElementById("domainModal");
      if (modal.classList.contains("show")) {
        closeModal();
      }
    }
  });

  // ========== 邮件设置：加载/保存/测试 ==========
  function updateEmailProviderFields() {
    const provider = document.getElementById("email_provider")?.value || "smtp";
    document.querySelectorAll("[data-provider-fields]").forEach((section) => {
      const isActive = section.dataset.providerFields === provider;
      section.style.display = isActive ? "" : "none";
    });
  }

  async function loadEmailSettingsIfNeeded() {
    const form = document.getElementById("emailSettingsForm");
    if (!form) return;
    try {
      const fd = new FormData();
      fd.append("action", "get_email_settings");
      appendCsrfToken(fd);
      const res = await fetch(ADMIN_ENDPOINT, { method: "POST", body: fd });
      const result = await res.json();
      if (result.success && result.data) {
        document.getElementById("from_name").value =
          result.data.from_name || "";
        document.getElementById("from_email").value =
          result.data.from_email || "";
        const defTo = document.getElementById("default_to_email");
        if (defTo) defTo.value = result.data.default_to_email || "";
        const testTo = document.getElementById("test_email_to");
        if (testTo && !testTo.value.trim()) {
          testTo.value = result.data.default_to_email || "";
        }
        const ch = (id) => document.getElementById(id);
        if (ch("email_provider"))
          ch("email_provider").value = result.data.email_provider || "smtp";
        if (ch("smtp_host"))
          ch("smtp_host").value = result.data.smtp_host || "";
        if (ch("smtp_port"))
          ch("smtp_port").value = result.data.smtp_port || 587;
        if (ch("smtp_encryption"))
          ch("smtp_encryption").value = result.data.smtp_encryption || "tls";
        if (ch("smtp_username"))
          ch("smtp_username").value = result.data.smtp_username || "";
        if (ch("smtp_password"))
          ch("smtp_password").value = result.data.smtp_password || "";
        if (ch("sendflare_api_key"))
          ch("sendflare_api_key").value = result.data.sendflare_api_key || "";
        if (ch("resend_api_key"))
          ch("resend_api_key").value = result.data.resend_api_key || "";
        updateEmailProviderFields();
      }
    } catch (err) {
      console.error("加载邮件设置失败", err);
    }
  }

  function bindTestEmailDefaultSync() {
    const defTo = document.getElementById("default_to_email");
    const testTo = document.getElementById("test_email_to");
    if (!defTo || !testTo) return;

    defTo.addEventListener("input", function () {
      if (!testTo.value.trim()) {
        testTo.value = defTo.value.trim();
      }
    });
  }

  function bindEmailProviderFields() {
    const provider = document.getElementById("email_provider");
    if (!provider) return;
    provider.addEventListener("change", updateEmailProviderFields);
    updateEmailProviderFields();
  }

  window.saveEmailSettings = async function (event) {
    event.preventDefault();
    const form = document.getElementById("emailSettingsForm");
    const fd = new FormData(form);
    fd.append("action", "save_email_settings");
    appendCsrfToken(fd);
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = "保存中...";
    try {
      const res = await fetch(ADMIN_ENDPOINT, { method: "POST", body: fd });
      const result = await res.json();
      if (result.success) {
        showMessage(result.message, "success");
      } else {
        showMessage(result.message || "保存失败", "error");
      }
    } catch (err) {
      showMessage("保存失败", "error");
    } finally {
      btn.disabled = false;
      btn.textContent = "保存设置";
    }
  };

  window.sendTestEmail = async function () {
    const testBtn = document.getElementById("testEmailBtn");
    const defaultBtnHtml = testBtn ? testBtn.innerHTML : "";
    const to = document.getElementById("test_email_to")?.value?.trim();
    if (!to) {
      showMessage("请先输入测试收件邮箱", "error");
      const input = document.getElementById("test_email_to");
      if (input) {
        input.focus();
      }
      return;
    }

    if (testBtn) {
      testBtn.disabled = true;
      testBtn.innerHTML =
        '<span class="btn-loading-spinner" aria-hidden="true"></span><span>发送中...</span>';
    }

    const fd = new FormData();
    fd.append("action", "test_email");
    fd.append("to", to);
    appendCsrfToken(fd);
    try {
      const res = await fetch(ADMIN_ENDPOINT, { method: "POST", body: fd });
      const result = await res.json();
      if (result.success) {
        showMessage(result.message || "测试邮件已发送", "success");
      } else {
        showMessage(result.message || "发送失败", "error");
      }
    } catch (err) {
      showMessage("发送失败", "error");
    } finally {
      if (testBtn) {
        testBtn.disabled = false;
        testBtn.innerHTML = defaultBtnHtml || "发送测试邮件";
      }
    }
  };

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function createFooterLinkRow(link = {}) {
    const row = document.createElement("div");
    row.className = "footer-link-row";
    row.innerHTML = `
      <div class="form-row form-row-three footer-link-row-grid">
        <div class="form-group">
          <label>链接名称</label>
          <input type="text" class="footer-link-name" placeholder="例如：WHOIS查询" value="${escapeHtml(
            link.name || ""
          )}">
        </div>
        <div class="form-group">
          <label>链接地址</label>
          <input type="text" class="footer-link-url" placeholder="https://example.com/{domain}" value="${escapeHtml(
            link.url || ""
          )}">
        </div>
        <div class="form-group">
          <label>图标</label>
          <input type="text" class="footer-link-icon" placeholder="fa-solid fa-globe 或 SVG 代码" value="${escapeHtml(
            link.icon_class || "fa-solid fa-link"
          )}">
        </div>
      </div>
      <div class="footer-link-actions">
        <button type="button" class="btn btn-secondary footer-link-remove">删除</button>
      </div>
    `;

    const removeBtn = row.querySelector(".footer-link-remove");
    removeBtn.addEventListener("click", () => row.remove());
    return row;
  }

  window.addFooterLinkRow = function (link = null) {
    const container = document.getElementById("footerLinksContainer");
    if (!container) return;
    if (container.querySelectorAll(".footer-link-row").length >= 3) {
      showMessage("最多只允许 3 个页脚链接", "error");
      return;
    }
    container.appendChild(createFooterLinkRow(link || {}));
  };

  async function loadFooterSettingsIfNeeded() {
    const form = document.getElementById("footerSettingsForm");
    if (!form) return;
    const container = document.getElementById("footerLinksContainer");
    if (!container) return;

    try {
      const fd = new FormData();
      fd.append("action", "get_footer_settings");
      appendCsrfToken(fd);
      const res = await fetch(ADMIN_ENDPOINT, { method: "POST", body: fd });
      const result = await res.json();
      if (!result.success || !result.data) return;

      container.innerHTML = "";
      const links = Array.isArray(result.data.footer_links)
        ? result.data.footer_links.slice(0, 3)
        : [];
      if (links.length === 0) {
        window.addFooterLinkRow();
      } else {
        links.forEach((link) => window.addFooterLinkRow(link));
      }

      const analytics = document.getElementById("footer_analytics_code");
      if (analytics) analytics.value = result.data.footer_analytics_code || "";
    } catch (err) {
      console.error("加载页脚设置失败", err);
    }
  }

  window.saveFooterSettings = async function (event) {
    event.preventDefault();
    const form = document.getElementById("footerSettingsForm");
    if (!form) return;

    const rows = Array.from(document.querySelectorAll(".footer-link-row"));
    const links = rows.map((row) => ({
      name: row.querySelector(".footer-link-name")?.value?.trim() || "",
      url: row.querySelector(".footer-link-url")?.value?.trim() || "",
      icon_class:
        row.querySelector(".footer-link-icon")?.value?.trim() ||
        "fa-solid fa-link",
    }));

    const fd = new FormData();
    fd.append("action", "save_footer_settings");
    fd.append("footer_links_json", JSON.stringify(links));
    fd.append(
      "footer_analytics_code",
      document.getElementById("footer_analytics_code")?.value || ""
    );
    appendCsrfToken(fd);

    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = "保存中...";
    try {
      const res = await fetch(ADMIN_ENDPOINT, { method: "POST", body: fd });
      const result = await res.json();
      if (result.success) {
        showMessage(result.message || "页脚设置已保存", "success");
      } else {
        showMessage(result.message || "保存失败", "error");
      }
    } catch (err) {
      showMessage("保存失败", "error");
    } finally {
      btn.disabled = false;
      btn.textContent = "保存设置";
    }
  };

  function createDomainValueItemRow(item = {}) {
    const row = document.createElement("div");
    row.className = "domain-value-item-row";
    row.innerHTML = `
      <div class="form-row form-row-three">
        <div class="form-group">
          <label>标题</label>
          <input type="text" class="domain-value-item-title" placeholder="例如：提升品牌价值" value="${escapeHtml(
            item.title || ""
          )}">
        </div>
        <div class="form-group">
          <label>描述</label>
          <input type="text" class="domain-value-item-description" placeholder="例如：简短易记的域名让用户更容易找到您" value="${escapeHtml(
            item.description || ""
          )}">
        </div>
        <div class="form-group">
          <label>图标</label>
          <input type="text" class="domain-value-item-icon" placeholder="fa-solid fa-chart-line" value="${escapeHtml(
            item.icon_class || "fa-solid fa-circle-check"
          )}">
        </div>
      </div>
      <div class="domain-value-item-actions">
        <button type="button" class="btn btn-secondary domain-value-item-remove">删除</button>
      </div>
    `;

    const removeBtn = row.querySelector(".domain-value-item-remove");
    removeBtn.addEventListener("click", () => row.remove());
    return row;
  }

  window.addDomainValueItemRow = function (item = null) {
    const container = document.getElementById("domainValueItemsContainer");
    if (!container) return;
    if (container.querySelectorAll(".domain-value-item-row").length >= 6) {
      showMessage("最多只允许 6 个价值卡片条目", "error");
      return;
    }
    container.appendChild(createDomainValueItemRow(item || {}));
  };

  async function loadDomainValueSettingsIfNeeded() {
    const form = document.getElementById("domainValueSettingsForm");
    if (!form) return;
    const container = document.getElementById("domainValueItemsContainer");
    if (!container) return;

    try {
      const fd = new FormData();
      fd.append("action", "get_domain_value_settings");
      appendCsrfToken(fd);
      const res = await fetch(ADMIN_ENDPOINT, { method: "POST", body: fd });
      const result = await res.json();
      if (!result.success || !result.data) return;

      const title = document.getElementById("domain_value_title");
      const text = document.getElementById("domain_value_text");
      const slogan = document.getElementById("domain_value_slogan");
      if (title) title.value = result.data.title || "";
      if (text) text.value = result.data.text || "";
      if (slogan) slogan.value = result.data.slogan || "";

      container.innerHTML = "";
      const items = Array.isArray(result.data.items)
        ? result.data.items.slice(0, 6)
        : [];
      if (items.length === 0) {
        window.addDomainValueItemRow();
      } else {
        items.forEach((item) => window.addDomainValueItemRow(item));
      }
    } catch (err) {
      console.error("加载价值卡片设置失败", err);
    }
  }

  window.saveDomainValueSettings = async function (event) {
    event.preventDefault();
    const form = document.getElementById("domainValueSettingsForm");
    if (!form) return;

    const rows = Array.from(
      document.querySelectorAll(".domain-value-item-row")
    );
    const settings = {
      title: document.getElementById("domain_value_title")?.value?.trim() || "",
      text: document.getElementById("domain_value_text")?.value?.trim() || "",
      slogan:
        document.getElementById("domain_value_slogan")?.value?.trim() || "",
      items: rows.map((row) => ({
        title:
          row.querySelector(".domain-value-item-title")?.value?.trim() || "",
        description:
          row.querySelector(".domain-value-item-description")?.value?.trim() ||
          "",
        icon_class:
          row.querySelector(".domain-value-item-icon")?.value?.trim() ||
          "fa-solid fa-circle-check",
      })),
    };

    const fd = new FormData();
    fd.append("action", "save_domain_value_settings");
    fd.append("domain_value_json", JSON.stringify(settings));
    appendCsrfToken(fd);

    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = "保存中...";
    try {
      const res = await fetch(ADMIN_ENDPOINT, { method: "POST", body: fd });
      const result = await res.json();
      if (result.success) {
        showMessage(result.message || "价值卡片设置已保存", "success");
      } else {
        showMessage(result.message || "保存失败", "error");
      }
    } catch (err) {
      showMessage("保存失败", "error");
    } finally {
      btn.disabled = false;
      btn.textContent = "保存设置";
    }
  };

  // 初始：尝试加载邮件设置
  bindEmailProviderFields();
  bindTestEmailDefaultSync();
  loadEmailSettingsIfNeeded();

  // 加载站点设置
  async function loadSiteSettingsIfNeeded() {
    const form = document.getElementById("siteSettingsForm");
    if (!form) return;
    try {
      const fd = new FormData();
      fd.append("action", "get_site_settings");
      appendCsrfToken(fd);
      const res = await fetch(ADMIN_ENDPOINT, { method: "POST", body: fd });
      const result = await res.json();
      if (result.success && result.data) {
        document.getElementById("site_name").value =
          result.data.site_name || "";
        // 密码不加载，留空
      }
    } catch (err) {
      console.error("加载站点设置失败", err);
    }
  }

  // 保存站点设置
  window.saveSiteSettings = async function (event) {
    event.preventDefault();
    const form = document.getElementById("siteSettingsForm");
    const fd = new FormData(form);
    fd.append("action", "save_site_settings");
    appendCsrfToken(fd);
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = "保存中...";
    try {
      const res = await fetch(ADMIN_ENDPOINT, { method: "POST", body: fd });
      const result = await res.json();
      if (result.success) {
        showMessage(result.message, "success");
        // 清空密码字段
        document.getElementById("old_password").value = "";
        document.getElementById("admin_password").value = "";
        document.getElementById("confirm_password").value = "";
      } else {
        showMessage(result.message || "保存失败", "error");
      }
    } catch (err) {
      showMessage("保存失败", "error");
    } finally {
      btn.disabled = false;
      btn.textContent = "保存设置";
    }
  };

  loadSiteSettingsIfNeeded();
  loadDomainValueSettingsIfNeeded();
  loadFooterSettingsIfNeeded();

  // 管理后台主题切换功能
  (function initAdminTheme() {
    "use strict";

    const THEME_KEY = "domain_theme_mode";

    // 检测系统主题偏好
    function getSystemTheme() {
      if (
        window.matchMedia &&
        window.matchMedia("(prefers-color-scheme: dark)").matches
      ) {
        return "dark";
      }
      return "light";
    }

    // 获取当前主题模式
    function getThemeMode() {
      return localStorage.getItem(THEME_KEY) || "auto";
    }

    // 保存主题模式
    function saveThemeMode(mode) {
      localStorage.setItem(THEME_KEY, mode);
    }

    // 应用主题
    function applyTheme(mode) {
      let actualTheme;

      if (mode === "auto") {
        actualTheme = getSystemTheme();
      } else {
        actualTheme = mode;
      }

      document.documentElement.setAttribute("data-theme", actualTheme);
      document.documentElement.setAttribute("data-theme-mode", mode);
      updateThemeIcon(mode);
    }

    // 更新主题图标和菜单状态
    function updateThemeIcon(mode) {
      const themeToggle = document.getElementById("adminThemeToggle");
      const themeMenu = document.getElementById("adminThemeMenu");
      if (!themeToggle || !themeMenu) return;

      const currentIcon = themeToggle.querySelector(".current-icon");

      // 定义图标路径
      const iconPaths = {
        light:
          '<path d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM18.894 6.166a.75.75 0 00-1.06-1.06l-1.591 1.59a.75.75 0 101.06 1.061l1.591-1.59zM21.75 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5H21a.75.75 0 01.75.75zM17.834 18.894a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 10-1.061 1.06l1.59 1.591zM12 18a.75.75 0 01.75.75V21a.75.75 0 01-1.5 0v-2.25A.75.75 0 0112 18zM7.758 17.303a.75.75 0 00-1.061-1.06l-1.591 1.59a.75.75 0 001.06 1.061l1.591-1.59zM6 12a.75.75 0 01-.75.75H3a.75.75 0 010-1.5h2.25A.75.75 0 016 12zM6.697 7.757a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 00-1.061 1.06l1.59 1.591z" />',
        dark: '<path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 01.162.819A8.97 8.97 0 009 6a9 9 0 009 9 8.97 8.97 0 003.463-.69.75.75 0 01.981.98 10.503 10.503 0 01-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 01.818.162z" clip-rule="evenodd" />',
        auto: '<path d="M20 3H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h4v2H8v2h8v-2h-4v-2h4c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 12H4V5h16v10z"/>',
      };

      // 更新当前显示的图标
      if (currentIcon && iconPaths[mode]) {
        currentIcon.innerHTML = iconPaths[mode];
      }

      // 更新菜单中的选中状态
      const menuItems = themeMenu.querySelectorAll(".theme-menu-item");
      menuItems.forEach((item) => {
        const check = item.querySelector(".theme-menu-check");
        if (item.getAttribute("data-theme-mode") === mode) {
          item.classList.add("active");
          if (check) check.style.display = "block";
        } else {
          item.classList.remove("active");
          if (check) check.style.display = "none";
        }
      });
    }

    // 显示/隐藏主题菜单
    function toggleThemeMenu() {
      const themeMenu = document.getElementById("adminThemeMenu");
      if (!themeMenu) return;

      if (themeMenu.classList.contains("show")) {
        themeMenu.classList.remove("show");
      } else {
        themeMenu.classList.add("show");
      }
    }

    // 选择主题
    function selectTheme(mode) {
      saveThemeMode(mode);
      applyTheme(mode);

      // 关闭菜单
      const themeMenu = document.getElementById("adminThemeMenu");
      if (themeMenu) {
        themeMenu.classList.remove("show");
      }
    }

    // 监听系统主题变化（仅在auto模式下）
    function watchSystemTheme() {
      if (window.matchMedia) {
        const mediaQuery = window.matchMedia("(prefers-color-scheme: dark)");

        const handleChange = function (e) {
          const currentMode = getThemeMode();
          if (currentMode === "auto") {
            const newTheme = e.matches ? "dark" : "light";
            document.documentElement.setAttribute("data-theme", newTheme);
          }
        };

        // 兼容新旧API
        if (mediaQuery.addEventListener) {
          mediaQuery.addEventListener("change", handleChange);
        } else if (mediaQuery.addListener) {
          mediaQuery.addListener(handleChange);
        }
      }
    }

    // 初始化主题
    function initTheme() {
      const savedMode = getThemeMode();
      applyTheme(savedMode);
      watchSystemTheme();
    }

    // 绑定点击事件
    function bindThemeToggle() {
      const themeToggle = document.getElementById("adminThemeToggle");
      const themeMenu = document.getElementById("adminThemeMenu");

      if (themeToggle) {
        // 点击主题按钮显示/隐藏菜单
        themeToggle.addEventListener("click", function (e) {
          e.stopPropagation();
          toggleThemeMenu();
        });
      }

      // 绑定菜单项点击事件
      if (themeMenu) {
        const menuItems = themeMenu.querySelectorAll(".theme-menu-item");
        menuItems.forEach((item) => {
          item.addEventListener("click", function (e) {
            e.stopPropagation();
            const mode = item.getAttribute("data-theme-mode");
            if (mode) {
              selectTheme(mode);
            }
          });
        });
      }

      // 点击页面其他地方关闭菜单
      document.addEventListener("click", function (e) {
        const themeMenu = document.getElementById("adminThemeMenu");
        const themeToggle = document.getElementById("adminThemeToggle");

        if (
          themeMenu &&
          themeToggle &&
          !themeMenu.contains(e.target) &&
          !themeToggle.contains(e.target)
        ) {
          themeMenu.classList.remove("show");
        }
      });

      // 按ESC键关闭菜单
      document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
          const themeMenu = document.getElementById("adminThemeMenu");
          if (themeMenu) {
            themeMenu.classList.remove("show");
          }
        }
      });
    }

    // 页面加载完成后初始化
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", function () {
        initTheme();
        bindThemeToggle();
      });
    } else {
      initTheme();
      bindThemeToggle();
    }
  })();

  // 分页功能（统一函数）
  function changePerPage(perPage) {
    const value = Math.max(10, Math.min(50, parseInt(perPage, 10) || 10));
    domainsPerPage = value;
    loadDomainsPage(1, domainsPerPage);
  }
  window.changePerPage = changePerPage;
  window.changeStatsPerPage = function (perPage) {
    const url = new URL(window.location.href);
    url.searchParams.set("per_page", perPage);
    url.searchParams.set("page", "1");
    window.location.href = url.toString();
  };

  document.addEventListener("click", function (event) {
    const pageButton = event.target.closest("[data-domains-page]");
    if (!pageButton) return;
    event.preventDefault();
    const page = parseInt(pageButton.getAttribute("data-domains-page"), 10);
    if (!page || page === domainsCurrentPage) return;
    loadDomainsPage(page, domainsPerPage);
  });
})();
