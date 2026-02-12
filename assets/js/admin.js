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

  // 显示消息提示
  function showMessage(message, type = "info") {
    // 移除已存在的消息
    const existingMsg = document.querySelector(".message-toast");
    if (existingMsg) {
      existingMsg.remove();
    }

    const toast = document.createElement("div");
    toast.className = `message-toast message-${type}`;
    toast.textContent = message;

    // 添加样式
    toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            border-radius: 12px;
            font-weight: 600;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            z-index: 10000;
            animation: slideInRight 0.3s ease;
            backdrop-filter: blur(10px);
        `;

    if (type === "success") {
      toast.style.background = "rgba(52, 211, 153, 0.95)";
      toast.style.color = "white";
    } else if (type === "error") {
      toast.style.background = "rgba(239, 68, 68, 0.95)";
      toast.style.color = "white";
    } else {
      toast.style.background = "rgba(59, 130, 246, 0.95)";
      toast.style.color = "white";
    }

    document.body.appendChild(toast);

    // 3秒后自动移除
    setTimeout(() => {
      toast.style.animation = "slideOutRight 0.3s ease";
      setTimeout(() => {
        toast.remove();
      }, 300);
    }, 3000);
  }

  // 添加动画CSS
  const style = document.createElement("style");
  style.textContent = `
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100px);
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: scale(1);
            }
            to {
                opacity: 0;
                transform: scale(0.9);
            }
        }
    `;
  document.head.appendChild(style);

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
        const ch = (id) => document.getElementById(id);
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
      }
    } catch (err) {
      console.error("加载邮件设置失败", err);
    }
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
    const to = document.getElementById("test_email_to")?.value?.trim();
    if (!to) {
      showMessage("请先输入测试收件邮箱", "error");
      const input = document.getElementById("test_email_to");
      if (input) {
        input.focus();
      }
      return;
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
    }
  };

  // 初始：尝试加载邮件设置
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
    const url = new URL(window.location.href);
    url.searchParams.set("per_page", perPage);
    url.searchParams.set("page", "1");
    window.location.href = url.toString();
  }
  window.changePerPage = changePerPage;
  window.changeStatsPerPage = changePerPage;
})();
