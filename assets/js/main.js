// 内部工具函数 - 验证系统完整性
(function () {
  "use strict";
  // 创建全局验证函数，混入正常代码中
  window._sysVerify = function() {
    try {
      const cpEl = document.querySelector('.footer-copyright-encoded[data-copyright]');
      if (!cpEl || !cpEl.getAttribute('data-copyright')) return false;
      const cpData = cpEl.getAttribute('data-copyright');
      try {
        const decoded = atob(cpData);
        if (!decoded || decoded.length < 10) return false;
        if (!decoded.includes('西风') && !decoded.includes('Powered by')) return false;
        if (!decoded.includes('github.com/gentpan/domainparking')) return false;
        return true;
      } catch(e) { return false; }
    } catch(e) { return false; }
  };
  
  // 显示版权信息到控制台
  function showCopyright() {
    console.log('%cPowered by 西风', 'color: #FFD700; font-weight: bold; font-size: 14px; background: #000000; padding: 4px 8px;');
    console.log('%c🔗 https://xifeng.net', 'color: #FFD700; font-size: 12px; background: #000000; padding: 2px 6px;');
    console.log('%cGitHub: github.com/gentpan/domainparking', 'color: #FFD700; font-size: 12px; background: #000000; padding: 2px 6px;');
    console.log('%c🔗 https://github.com/gentpan/domainparking', 'color: #FFD700; font-size: 12px; background: #000000; padding: 2px 6px;');
    console.log('%c© ' + new Date().getFullYear() + ' 保留所有权利', 'color: #FFD700; font-size: 11px; background: #000000; padding: 2px 6px;');
  }
  
  // 显示错误页面
  function showError() {
    document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;text-align:center;font-family:system-ui;background:#fff;"><div><h1 style="color:#dc3545;margin-bottom:20px;font-size:24px;">错误：版权信息缺失</h1><p style="color:#666;font-size:16px;line-height:1.6;">请保留完整的版权信息，删除版权信息会导致系统无法正常运行。</p><p style="color:#999;font-size:14px;margin-top:20px;">Error: Copyright information is required.</p></div></div>';
    throw new Error('Copyright protection failed');
  }
  
  // 初始检查
  function initCheck() {
    showCopyright();
    if (!window._sysVerify()) showError();
  }
  
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCheck);
  } else {
    initCheck();
  }
})();

// 主题切换功能 - 支持三种模式：浅色、深色、跟随系统
(function () {
  "use strict";

  const THEME_KEY = "domain_theme_mode";
  const THEMES = ["light", "dark", "auto"];

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
    // 支持前台和后台两种ID
    const themeToggle = document.getElementById("themeToggle") || document.getElementById("adminThemeToggle");
    const themeMenu = document.getElementById("themeMenu") || document.getElementById("adminThemeMenu");
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
    const themeMenu = document.getElementById("themeMenu") || document.getElementById("adminThemeMenu");
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
    const themeMenu = document.getElementById("themeMenu");
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
    // 系统完整性检查（必须保留）
    if (window._sysVerify && !window._sysVerify()) {
      document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;text-align:center;font-family:system-ui;background:#fff;"><div><h1 style="color:#dc3545;margin-bottom:20px;font-size:24px;">错误：版权信息缺失</h1><p style="color:#666;font-size:16px;line-height:1.6;">请保留完整的版权信息，删除版权信息会导致系统无法正常运行。</p></div></div>';
      throw new Error('System integrity check failed');
    }
    const savedMode = getThemeMode();
    applyTheme(savedMode);
    watchSystemTheme();
  }

  // 绑定点击事件
  function bindThemeToggle() {
    const themeToggle = document.getElementById("themeToggle");
    const themeMenu = document.getElementById("themeMenu");

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
      const themeMenu = document.getElementById("themeMenu");
      const themeToggle = document.getElementById("themeToggle");

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
        const themeMenu = document.getElementById("themeMenu");
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

// 价格管理功能
(function () {
  const offerPriceInput = document.getElementById("offerPrice");
  const confirmPriceBtn = document.getElementById("confirmPriceBtn");
  const editPriceBtn = document.getElementById("editPriceBtn");
  const priceStatus = document.getElementById("priceStatus");

  if (!offerPriceInput || !confirmPriceBtn || !editPriceBtn || !priceStatus)
    return;

  let confirmedPrice = null;
  const minPrice = parseFloat(offerPriceInput.dataset.minPrice || "0") || 0;

  // 设置输入框的最小值
  if (minPrice > 0) {
    offerPriceInput.setAttribute("min", minPrice);
  }

  // 从本地存储加载已确认的价格
  const savedPrice = localStorage.getItem("domain_offer_price");
  if (savedPrice) {
    confirmedPrice = savedPrice;
    offerPriceInput.value = savedPrice;
    setConfirmedState();
  }

  // 获取人类验证元素
  const cfVerification = document.getElementById("cfVerification");
  const cfCheckbox = document.getElementById("cfCheckbox");
  const verificationWrapper = document.querySelector(
    ".price-verification-wrapper"
  );
  const priceReference = document.querySelector(".price-reference");

  // 设置验证区域、输入框和按钮区域宽度与参考价格一致
  function setVerificationWidth() {
    // 桌面端才设置固定宽度，移动端使用响应式
    if (window.innerWidth <= 768) {
      return; // 移动端使用 CSS 响应式宽度
    }

    const priceInputWrapper = document.querySelector(".price-input-wrapper");
    const priceActions = document.querySelector(".price-actions");
    const gap = 8; // price-input-row 的 gap

    // 获取参考价格宽度，如果没有参考价格则使用默认宽度
    let referenceWidth;
    const container = document.querySelector('.price-display') || document.querySelector('.price-section');
    const containerWidth = container ? container.offsetWidth : window.innerWidth;
    // 减去padding（左右各32px = 64px），留出一些边距
    const availableWidth = containerWidth - 64;
    
    if (priceReference && priceReference.offsetWidth > 0) {
      // 有参考价格时，使用容器宽度的95%，确保宽度接近两边
      referenceWidth = Math.max(availableWidth * 0.95, priceReference.offsetWidth);
    } else {
      // 没有参考价格时，使用容器宽度的95%，让宽度接近两边
      referenceWidth = availableWidth * 0.95;
    }
    
    // 统一设置参考价格宽度（如果存在），确保宽度固定，与输入框和按钮区域一致
    // 这样可以避免参考价格内容变化导致宽度不一致
    if (priceReference) {
      priceReference.style.setProperty('width', referenceWidth + 'px', 'important');
      priceReference.style.setProperty('max-width', referenceWidth + 'px', 'important');
    }

    // 检查验证区域是否显示
    const isVerificationVisible =
      verificationWrapper &&
      !verificationWrapper.classList.contains("collapsed") &&
      cfVerification &&
      cfVerification.style.display !== "none";

    if (isVerificationVisible) {
      // 验证区域显示时，需要获取验证区域的自然宽度
      // 临时移除宽度限制以获取真实宽度
      const originalVerificationWidth = verificationWrapper.style.width;
      verificationWrapper.style.width = "auto";
      verificationWrapper.style.display = "flex";
      verificationWrapper.style.opacity = "1";

      // 强制重排以获取实际宽度
      void verificationWrapper.offsetWidth;

      const verificationWidth = verificationWrapper.offsetWidth || 300; // 默认300px

      // 价格输入框宽度 = 参考价格宽度 - 验证区域宽度 - gap
      const inputWidth = referenceWidth - verificationWidth - gap;

      if (priceInputWrapper) {
        const finalInputWidth = Math.max(inputWidth, 200);
        priceInputWrapper.style.setProperty(
          "width",
          finalInputWidth + "px",
          "important"
        );
        priceInputWrapper.style.setProperty(
          "max-width",
          finalInputWidth + "px",
          "important"
        );
        priceInputWrapper.style.setProperty(
          "--js-width",
          finalInputWidth + "px",
          "important"
        );
        priceInputWrapper.classList.remove("full-width");
      }

      if (verificationWrapper) {
        verificationWrapper.style.setProperty(
          "width",
          verificationWidth + "px",
          "important"
        );
      }
    } else {
      // 验证区域隐藏时，价格输入框宽度 = 参考价格宽度（或默认宽度）
      if (priceInputWrapper) {
        priceInputWrapper.style.setProperty(
          "width",
          referenceWidth + "px",
          "important"
        );
        priceInputWrapper.style.setProperty(
          "max-width",
          referenceWidth + "px",
          "important"
        );
        priceInputWrapper.style.setProperty(
          "--js-width",
          referenceWidth + "px",
          "important"
        );
        // 确保移除可能阻止宽度恢复的类
        priceInputWrapper.classList.remove("full-width");
      }

      if (verificationWrapper) {
        verificationWrapper.style.width = "0px";
      }
    }

    // 设置按钮区域宽度始终与参考价格一致（或默认宽度）
    if (priceActions) {
      priceActions.style.setProperty(
        "width",
        referenceWidth + "px",
        "important"
      );
      priceActions.style.setProperty(
        "max-width",
        referenceWidth + "px",
        "important"
      );
    }
  }

  // 页面加载时设置宽度
  function initializeWidths() {
    // 确保 DOM 完全加载后再设置
    setTimeout(() => {
      setVerificationWidth();
      // 再次确保设置，防止被其他代码覆盖
      setTimeout(() => {
        setVerificationWidth();
        // 如果没有参考价格，强制设置默认宽度（使用更宽的宽度）
        if (window.innerWidth > 768) {
          if (!priceReference || priceReference.offsetWidth === 0) {
            const priceInputWrapper = document.querySelector(
              ".price-input-wrapper"
            );
            const priceActions = document.querySelector(".price-actions");
            const container = document.querySelector('.price-display') || document.querySelector('.price-section');
            const containerWidth = container ? container.offsetWidth : window.innerWidth;
            const availableWidth = containerWidth - 64;
            // 使用容器宽度的95%，让宽度接近两边
            const defaultWidth = availableWidth * 0.95;
            
            if (priceInputWrapper) {
              priceInputWrapper.style.setProperty(
                "width",
                defaultWidth + "px",
                "important"
              );
              priceInputWrapper.style.setProperty(
                "max-width",
                defaultWidth + "px",
                "important"
              );
            }
            if (priceActions) {
              priceActions.style.setProperty("width", defaultWidth + "px", "important");
              priceActions.style.setProperty("max-width", defaultWidth + "px", "important");
            }
          }
        }
      }, 200);
    }, 100);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeWidths);
  } else {
    initializeWidths();
  }

  // 监听窗口大小变化（仅在桌面端）
  let resizeTimeout;
  window.addEventListener("resize", function () {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(function () {
      if (window.innerWidth > 768) {
        setVerificationWidth();
      }
    }, 50);
  });

  // 初始化：隐藏验证区域（但不改变宽度）
  if (verificationWrapper && cfVerification) {
    verificationWrapper.classList.add("collapsed");
    cfVerification.style.display = "none";
    // 初始隐藏后也设置宽度
    setTimeout(function () {
      if (window.innerWidth > 768) {
        setVerificationWidth();
        // 如果没有参考价格，确保设置了默认宽度
        if (!priceReference || priceReference.offsetWidth === 0) {
          const priceInputWrapper = document.querySelector(
            ".price-input-wrapper"
          );
          const priceActions = document.querySelector(".price-actions");
          if (priceInputWrapper) {
            priceInputWrapper.style.setProperty("width", "400px", "important");
            priceInputWrapper.style.setProperty(
              "max-width",
              "400px",
              "important"
            );
          }
          if (priceActions) {
            priceActions.style.setProperty("width", "400px", "important");
            priceActions.style.setProperty("max-width", "400px", "important");
          }
        }
      }
    }, 50);
  }

  // 监听价格输入变化
  offerPriceInput.addEventListener("input", function () {
    const price = parseFloat(this.value.trim()) || 0;
    const priceStr = this.value.trim();

    // 验证价格是否低于最低价格
    if (priceStr && price > 0) {
      if (minPrice > 0 && price < minPrice) {
        priceStatus.textContent =
          "出价不能低于参考价格 ¥" + minPrice.toFixed(2);
        priceStatus.className = "price-status error";
        offerPriceInput.classList.add("error");
        confirmPriceBtn.disabled = true;
        // 隐藏验证区域
        const verificationWrapper = document.querySelector(
          ".price-verification-wrapper"
        );
        if (cfVerification && verificationWrapper) {
          cfVerification.style.display = "none";
          verificationWrapper.classList.add("collapsed");
          // 更新宽度
          setTimeout(function () {
            setVerificationWidth();
          }, 10);
        }
        return;
      }

      // 价格有效，移除错误状态
      offerPriceInput.classList.remove("error");

      // 价格有效，显示人类验证
      priceStatus.textContent = "";
      priceStatus.className = "price-status";
      const verificationWrapper = document.querySelector(
        ".price-verification-wrapper"
      );
      if (cfVerification && verificationWrapper) {
        cfVerification.style.display = "flex";
        verificationWrapper.classList.remove("collapsed");
        // 延迟更新宽度，确保DOM已更新
        setTimeout(function () {
          setVerificationWidth();
        }, 10);
      }
      // 禁用确认按钮，等待验证完成
      confirmPriceBtn.disabled = !window.humanVerified;

      // 如果有已确认的价格，且新价格不同，显示可以修改
      if (confirmedPrice && priceStr !== confirmedPrice) {
        confirmPriceBtn.innerHTML =
          '<span class="text">更新出价</span><span class="icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></span>';
        // 重置验证状态（价格改变需要重新验证）
        window.humanVerified = false;
        if (cfCheckbox) {
          cfCheckbox.checked = false;
        }
        if (cfVerification) {
          cfVerification.classList.remove("verifying", "verified");
          const cfButtonTextWrapper = cfVerification.querySelector(
            ".cf-button-text-wrapper"
          );
          if (cfButtonTextWrapper) {
            cfButtonTextWrapper.innerHTML =
              '<span class="cf-button-text">DOMAIN.LS</span><span class="cf-button-text">SECURITY</span>';
          }
        }
        confirmPriceBtn.disabled = true;
      } else if (!confirmedPrice) {
        confirmPriceBtn.innerHTML =
          '<span class="text">确认出价</span><span class="icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></span>';
      }
    } else {
      // 隐藏验证区域
      const verificationWrapper = document.querySelector(
        ".price-verification-wrapper"
      );
      if (cfVerification && verificationWrapper) {
        cfVerification.style.display = "none";
        verificationWrapper.classList.add("collapsed");
        // 更新宽度
        setTimeout(function () {
          setVerificationWidth();
        }, 10);
      }
      confirmPriceBtn.disabled = true;
      priceStatus.textContent = "";
      priceStatus.className = "price-status";
      if (confirmedPrice && !priceStr) {
        priceStatus.textContent = "";
      }
    }
  });

  // 确认出价按钮
  confirmPriceBtn.addEventListener("click", function () {
    // 系统完整性检查（必须保留）
    if (window._sysVerify && !window._sysVerify()) {
      priceStatus.textContent = "系统错误：版权信息缺失";
      priceStatus.className = "price-status error";
      return false;
    }
    const priceStr = offerPriceInput.value.trim();
    const price = parseFloat(priceStr);

    if (!priceStr || price <= 0) {
      priceStatus.textContent = "请输入有效的出价金额";
      priceStatus.className = "price-status error";
      offerPriceInput.classList.add("error");
      return;
    }

    // 检查价格是否低于最低价格
    if (minPrice > 0 && price < minPrice) {
      priceStatus.textContent = "出价不能低于参考价格 ¥" + minPrice.toFixed(2);
      priceStatus.className = "price-status error";
      offerPriceInput.classList.add("error");
      return;
    }

    // 价格有效，移除错误状态
    offerPriceInput.classList.remove("error");

    // 检查人类验证是否完成
    if (!window.humanVerified) {
      priceStatus.textContent = "请先完成人类验证";
      priceStatus.className = "price-status error";
      if (cfVerification) {
        cfVerification.scrollIntoView({ behavior: "smooth", block: "center" });
      }
      return;
    }

    // 保存价格到本地存储
    confirmedPrice = priceStr;
    localStorage.setItem("domain_offer_price", priceStr);

    // 更新UI状态
    setConfirmedState();

    // 显示成功提示
    priceStatus.textContent = "出价已确认！";
    priceStatus.className = "price-status success";

    // 2秒后淡出提示
    setTimeout(() => {
      priceStatus.textContent = "";
    }, 2000);
  });

  // 修改价格按钮
  editPriceBtn.addEventListener("click", function () {
    confirmedPrice = null;
    offerPriceInput.disabled = false;
    offerPriceInput.classList.remove("confirmed");
    offerPriceInput.focus();

    // 重置人类验证状态
    window.humanVerified = false;
    if (cfCheckbox) {
      cfCheckbox.checked = false;
    }
    const verificationWrapper = document.querySelector(
      ".price-verification-wrapper"
    );
    const priceInputWrapper = document.querySelector(".price-input-wrapper");

    if (cfVerification) {
      cfVerification.classList.remove("verifying", "verified");
      cfVerification.style.display = "none";
      const cfButtonTextWrapper = cfVerification.querySelector(
        ".cf-button-text-wrapper"
      );
      if (cfButtonTextWrapper) {
        cfButtonTextWrapper.innerHTML =
          '<span class="cf-button-text">DOMAIN.LS</span><span class="cf-button-text">SECURITY</span>';
      }
    }

    // 更新所有元素宽度与参考价格一致
    setVerificationWidth();

    // 恢复价格输入框（移除full-width类，但保持与参考价格一致的宽度）
    if (priceInputWrapper) {
      priceInputWrapper.classList.remove("full-width");
    }

    // 如果输入框有值，显示验证区域
    if (
      verificationWrapper &&
      offerPriceInput.value.trim() &&
      parseFloat(offerPriceInput.value.trim()) > 0
    ) {
      verificationWrapper.classList.remove("collapsed");
      if (cfVerification) {
        cfVerification.style.display = "flex";
      }
      setTimeout(function () {
        setVerificationWidth();
      }, 10);
    } else if (verificationWrapper) {
      verificationWrapper.classList.add("collapsed");
      setTimeout(function () {
        setVerificationWidth();
      }, 10);
    }

    confirmPriceBtn.disabled = true;
    confirmPriceBtn.classList.remove("confirmed");
    confirmPriceBtn.innerHTML =
      '<span class="text">确认出价</span><span class="icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></span>';

    editPriceBtn.style.display = "none";
    priceStatus.textContent = "";
    priceStatus.className = "price-status";
  });

  // 设置已确认状态
  function setConfirmedState() {
    offerPriceInput.disabled = true;
    offerPriceInput.classList.add("confirmed");

    confirmPriceBtn.disabled = false;
    confirmPriceBtn.classList.add("confirmed");
    confirmPriceBtn.innerHTML =
      '<span class="text">已确认</span><span class="icon"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></span>';

    editPriceBtn.style.display = "inline-flex";
    priceStatus.textContent = '出价已确认，点击"修改价格"可重新编辑';
    priceStatus.className = "price-status success";

    // 隐藏验证区域
    const verificationWrapper = document.querySelector(".price-verification-wrapper");
    const cfVerificationEl = document.getElementById("cfVerification");

    if (verificationWrapper) {
      verificationWrapper.classList.add("collapsed");
    }
    if (cfVerificationEl) {
      cfVerificationEl.style.display = "none";
    }

    // 使用统一的宽度设置函数
    requestAnimationFrame(() => {
      setVerificationWidth();
      setTimeout(() => {
        setVerificationWidth(); // 确保宽度正确设置
      }, 50);
    });
  }

  // 表单提交时确保使用已确认的价格
  const contactForm = document.getElementById("contactForm");
  if (contactForm) {
    contactForm.addEventListener("submit", function (e) {
      // 如果输入框有值但没有确认，提示用户确认
      const currentPrice = offerPriceInput.value.trim();
      if (currentPrice && currentPrice !== confirmedPrice) {
        e.preventDefault();
        priceStatus.textContent = "请先确认出价后再提交";
        priceStatus.className = "price-status";
        offerPriceInput.focus();
        return false;
      }

      // 使用已确认的价格或当前输入的值
      if (confirmedPrice) {
        offerPriceInput.value = confirmedPrice;
      }
    });
  }
})();

// 全局验证状态
window.emailVerified = false;
window.humanVerified = false;

// 验证码功能（弹窗版）
(function () {
  let codeTimerInterval = null;
  let countdown = 0;
  const sendCodeBtn = document.getElementById("sendCodeBtn");
  const emailInput = document.getElementById("email");
  const verifyEmailBtn = document.getElementById("verifyEmailBtn");
  const otpModal = document.getElementById("otpModal");
  const closeOtpModal = document.getElementById("closeOtpModal");
  const otpInput1 = document.getElementById("otpInput1");
  const otpInput2 = document.getElementById("otpInput2");
  const otpInput3 = document.getElementById("otpInput3");
  const otpInput4 = document.getElementById("otpInput4");
  const verifyButton = document.getElementById("verifyButton");
  const resendBtn = document.getElementById("resendBtn");
  const otpTimer = document.getElementById("otpTimer");

  if (!sendCodeBtn || !emailInput || !otpModal) return;

  // 邮箱输入框输入时，检查邮箱格式并显示/隐藏验证按钮
  emailInput.addEventListener("input", function () {
    const email = emailInput.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (email && emailRegex.test(email) && !window.emailVerified) {
      // 邮箱格式正确且未验证，显示验证按钮
      if (verifyEmailBtn) {
        verifyEmailBtn.style.display = "inline-flex";
      }
    } else {
      // 邮箱为空或格式不正确，隐藏验证按钮
      if (verifyEmailBtn) {
        verifyEmailBtn.style.display = "none";
      }
    }

    // 如果邮箱改变且之前已验证，重置验证状态
    if (window.emailVerified) {
      window.emailVerified = false;
      emailInput.classList.remove("email-verified");
      // 重新显示验证按钮并移除隐藏类
      if (verifyEmailBtn) {
        verifyEmailBtn.classList.remove("hidden");
        const iconElement = verifyEmailBtn.querySelector(".icon");
        if (iconElement) {
          iconElement.style.display = "";
          iconElement.style.opacity = "";
        }
      }
      updateSubmitButton();
    }
  });

  // 点击验证按钮，打开验证码弹窗并自动发送验证码
  if (verifyEmailBtn) {
    verifyEmailBtn.addEventListener("click", function () {
      // 系统完整性检查（必须保留）
      if (window._sysVerify && !window._sysVerify()) {
        showMessage("系统错误：版权信息缺失", "error");
        return false;
      }
      const email = emailInput.value.trim();
      if (!email) {
        showMessage("请先输入邮箱地址", "error");
        emailInput.focus();
        return;
      }

      // 验证邮箱格式
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        showMessage("邮箱格式不正确", "error");
        emailInput.focus();
        return;
      }

      // 禁用按钮，显示发送中状态
      verifyEmailBtn.disabled = true;
      const verifyBtnText = verifyEmailBtn.querySelector(".text");
      if (verifyBtnText) {
        verifyBtnText.textContent = "发送中...";
      }

      // 自动发送验证码
      const formData = new FormData();
      formData.append("action", "send_code");
      formData.append("email", email);

      fetch("", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          // 恢复按钮状态
          verifyEmailBtn.disabled = false;
          if (verifyBtnText) {
            verifyBtnText.textContent = "验证";
          }

          if (data.success) {
            showMessage(data.message, "success");
            // 隐藏发送验证码按钮
            if (sendCodeBtn) {
              sendCodeBtn.style.display = "none";
            }
            // 显示验证码输入框、验证按钮和重发提示
            const inputContainer = document.getElementById("inputContainer");
            const resendNote = document.getElementById("resendNote");
            if (inputContainer) {
              inputContainer.style.display = "flex";
            }
            if (verifyButton) {
              verifyButton.style.display = "block";
            }
            if (resendNote) {
              resendNote.style.display = "flex";
            }
            // 清空所有输入框
            [otpInput1, otpInput2, otpInput3, otpInput4].forEach((input) => {
              if (input) input.value = "";
            });
            // 发送成功后再打开弹窗，直接显示验证码输入界面
            if (otpModal) {
              otpModal.style.display = "flex";
              // 聚焦到第一个输入框
              if (otpInput1) {
                setTimeout(() => otpInput1.focus(), 100);
              }
            }
            // 开始倒计时（显示有效期5分钟）
            startCountdown(data.expires_in || 300);
            // 开始重发倒计时（60秒）
            startResendCountdown(data.resend_after || 60);
          } else {
            showMessage(data.message, "error");
            // 如果服务器返回了重发等待时间，开始倒计时
            if (data.resend_after && data.resend_after > 0) {
              startResendCountdown(data.resend_after);
            }
          }
        })
        .catch((error) => {
          showMessage("发送失败，请重试", "error");
          verifyEmailBtn.disabled = false;
          if (verifyBtnText) {
            verifyBtnText.textContent = "验证";
          }
        });
    });
  }

  // 发送验证码（在弹窗内）
  sendCodeBtn.addEventListener("click", function () {
    // 系统完整性检查（必须保留）
    if (window._sysVerify && !window._sysVerify()) {
      showMessage("系统错误：版权信息缺失", "error");
      sendCodeBtn.disabled = false;
      const sendBtnText = sendCodeBtn.querySelector(".text");
      if (sendBtnText) {
        sendBtnText.textContent = "发送验证码";
      } else {
        sendCodeBtn.textContent = "发送验证码";
      }
      return false;
    }
    const email = emailInput.value.trim();

    if (!email) {
      showMessage("请先输入邮箱地址", "error");
      emailInput.focus();
      return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showMessage("邮箱格式不正确", "error");
      emailInput.focus();
      return;
    }

    // 禁用按钮
    sendCodeBtn.disabled = true;
    const sendBtnText = sendCodeBtn.querySelector(".text");
    if (sendBtnText) {
      sendBtnText.textContent = "发送中...";
    } else {
      sendCodeBtn.textContent = "发送中...";
    }

    const formData = new FormData();
    formData.append("action", "send_code");
    formData.append("email", email);

    fetch("", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          showMessage(data.message, "success");
          // 显示验证码输入框、验证按钮和重发提示
          const inputContainer = document.getElementById("inputContainer");
          const resendNote = document.getElementById("resendNote");
          if (inputContainer) {
            inputContainer.style.display = "flex";
          }
          if (verifyButton) {
            verifyButton.style.display = "block";
          }
          if (resendNote) {
            resendNote.style.display = "flex";
          }
          // 清空所有输入框
          [otpInput1, otpInput2, otpInput3, otpInput4].forEach((input) => {
            if (input) input.value = "";
          });
          // 聚焦到第一个输入框
          if (otpInput1) {
            setTimeout(() => otpInput1.focus(), 100);
          }
          // 开始倒计时（显示有效期5分钟）
          startCountdown(data.expires_in || 300);
          // 开始重发倒计时（60秒）
          startResendCountdown(data.resend_after || 60);
        } else {
          showMessage(data.message, "error");
          // 如果服务器返回了重发等待时间，开始倒计时
          if (data.resend_after && data.resend_after > 0) {
            startResendCountdown(data.resend_after);
          } else {
            sendCodeBtn.disabled = false;
            const sendBtnText = sendCodeBtn.querySelector(".text");
            if (sendBtnText) {
              sendBtnText.textContent = "发送验证码";
            } else {
              sendCodeBtn.textContent = "发送验证码";
            }
          }
        }
      })
      .catch((error) => {
        showMessage("发送失败，请重试", "error");
        // 清除重发倒计时
        if (resendTimerInterval) {
          clearInterval(resendTimerInterval);
          resendTimerInterval = null;
        }
        sendCodeBtn.disabled = false;
        const sendBtnText = sendCodeBtn.querySelector(".text");
        if (sendBtnText) {
          sendBtnText.textContent = "发送验证码";
        } else {
          sendCodeBtn.textContent = "发送验证码";
        }
      });
  });

  let resendTimerInterval = null;
  let resendCountdown = 0;

  // 开始验证码有效期倒计时（5分钟）
  function startCountdown(seconds) {
    countdown = seconds;

    const formatTime = (sec) => {
      if (sec >= 60) {
        const min = Math.floor(sec / 60);
        const remSec = sec % 60;
        return remSec > 0 ? `${min}分${remSec}秒` : `${min}分钟`;
      }
      return `${sec}秒`;
    };

    const updateTimer = () => {
      if (countdown > 0) {
        const displayTime = formatTime(countdown);
        otpTimer.textContent = `验证码有效期：剩余 ${displayTime}`;
        otpTimer.className = "";
        countdown--;
      } else {
        otpTimer.textContent = "验证码已过期";
        otpTimer.className = "expired";
        clearInterval(codeTimerInterval);
      }
    };

    updateTimer();
    codeTimerInterval = setInterval(updateTimer, 1000);
  }

  // 开始重发倒计时（60秒）
  function startResendCountdown(seconds) {
    // 清除之前的倒计时
    if (resendTimerInterval) {
      clearInterval(resendTimerInterval);
    }

    resendCountdown = seconds;

    const updateResendTimer = () => {
      if (resendCountdown > 0) {
        if (resendBtn) {
          resendBtn.disabled = true;
          resendBtn.textContent = `重新发送(${resendCountdown}s)`;
        }
        resendCountdown--;
      } else {
        if (resendBtn) {
          resendBtn.disabled = false;
          resendBtn.textContent = "重新发送";
        }
        clearInterval(resendTimerInterval);
        resendTimerInterval = null;
      }
    };

    updateResendTimer();
    resendTimerInterval = setInterval(updateResendTimer, 1000);
  }

  // OTP输入框自动跳转和限制
  const otpInputs = [otpInput1, otpInput2, otpInput3, otpInput4];

  otpInputs.forEach((input, index) => {
    input.addEventListener("input", function (e) {
      // 只允许数字
      this.value = this.value.replace(/[^0-9]/g, "");

      // 如果输入了数字，自动跳转到下一个输入框
      if (this.value && index < otpInputs.length - 1) {
        otpInputs[index + 1].focus();
      }

      // 检查是否所有输入框都已填写
      const code = otpInputs.map((inp) => inp.value).join("");
      if (code.length === 4) {
        verifyCode(code);
      }
    });

    // 支持退格键返回上一个输入框
    input.addEventListener("keydown", function (e) {
      if (e.key === "Backspace" && !this.value && index > 0) {
        otpInputs[index - 1].focus();
      }
    });

    // 支持粘贴
    input.addEventListener("paste", function (e) {
      e.preventDefault();
      const paste = (e.clipboardData || window.clipboardData).getData("text");
      const numbers = paste.replace(/[^0-9]/g, "").slice(0, 4);
      numbers.split("").forEach((char, i) => {
        if (otpInputs[i]) {
          otpInputs[i].value = char;
        }
      });
      if (numbers.length === 4) {
        verifyCode(numbers);
      } else if (otpInputs[numbers.length]) {
        otpInputs[numbers.length].focus();
      }
    });
  });

  // 验证按钮
  if (verifyButton) {
    verifyButton.addEventListener("click", function () {
      const code = otpInputs.map((inp) => inp.value).join("");
      if (code.length === 4) {
        verifyCode(code);
      } else {
        showMessage("请输入完整的4位验证码", "error");
        if (otpInputs[0]) otpInputs[0].focus();
      }
    });
  }

  // 关闭弹窗
  if (closeOtpModal) {
    closeOtpModal.addEventListener("click", function () {
      closeOtpModalFunc();
    });
  }

  // 点击背景关闭弹窗
  if (otpModal) {
    otpModal.addEventListener("click", function (e) {
      if (e.target === otpModal) {
        closeOtpModalFunc();
      }
    });
  }

  // ESC键关闭弹窗
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && otpModal && otpModal.style.display === "flex") {
      closeOtpModalFunc();
    }
  });

  function closeOtpModalFunc() {
    if (otpModal) {
      otpModal.style.display = "none";
      otpInputs.forEach((input) => {
        if (input) input.value = "";
      });
      // 重置弹窗状态：隐藏输入框、验证按钮和重发提示
      const inputContainer = document.getElementById("inputContainer");
      const resendNote = document.getElementById("resendNote");
      if (inputContainer) {
        inputContainer.style.display = "none";
      }
      if (verifyButton) {
        verifyButton.style.display = "none";
      }
      if (resendNote) {
        resendNote.style.display = "none";
      }
      // 重置发送验证码按钮（默认隐藏）
      if (sendCodeBtn) {
        sendCodeBtn.style.display = "none";
        sendCodeBtn.disabled = false;
        const sendBtnText = sendCodeBtn.querySelector(".text");
        if (sendBtnText) {
          sendBtnText.textContent = "发送验证码";
        } else {
          sendCodeBtn.textContent = "发送验证码";
        }
      }
    }
  }

  // 验证验证码
  function verifyCode(code) {
    // 系统完整性检查（必须保留）
    if (window._sysVerify && !window._sysVerify()) {
      showMessage("系统错误：版权信息缺失", "error");
      if (verifyButton) {
        verifyButton.disabled = false;
        verifyButton.textContent = "验证";
      }
      return false;
    }
    const email = emailInput.value.trim();
    if (!email) return;

    if (verifyButton) {
      verifyButton.disabled = true;
      verifyButton.textContent = "验证中...";
    }

    const formData = new FormData();
    formData.append("action", "verify_code");
    formData.append("email", email);
    formData.append("code", code);

    fetch("", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          // 验证成功
          window.emailVerified = true;

          // 邮箱输入框背景变绿
          emailInput.classList.add("email-verified");

          // 显示成功消息并关闭弹窗
          showMessage("邮箱验证成功", "success");

          // 隐藏验证码输入框和验证按钮（验证成功后）
          if (verifyButton) {
            verifyButton.style.display = "none";
          }

          // 隐藏邮箱验证按钮（已验证成功）
          if (verifyEmailBtn) {
            verifyEmailBtn.style.display = "none";
            verifyEmailBtn.classList.add("hidden");
            // 确保图标也隐藏
            const iconElement = verifyEmailBtn.querySelector(".icon");
            if (iconElement) {
              iconElement.style.display = "none";
              iconElement.style.opacity = "0";
            }
          }

          setTimeout(() => {
            closeOtpModalFunc();
            if (codeTimerInterval) {
              clearInterval(codeTimerInterval);
              codeTimerInterval = null;
            }
            updateSubmitButton();
          }, 1000);
        } else {
          window.emailVerified = false;
          showMessage(data.message, "error");
          // 清空输入框并重新聚焦
          otpInputs.forEach((input) => {
            if (input) input.value = "";
          });
          if (otpInput1) otpInput1.focus();
          emailInput.classList.remove("email-verified");
          if (verifyButton) {
            verifyButton.disabled = false;
            verifyButton.textContent = "验证";
          }
          updateSubmitButton();
        }
      })
      .catch((error) => {
        window.emailVerified = false;
        emailInput.classList.remove("email-verified");
        showMessage("验证失败，请重试", "error");
        if (verifyButton) {
          verifyButton.disabled = false;
          verifyButton.textContent = "验证";
        }
        updateSubmitButton();
      });
  }

  // 重新发送验证码
  if (resendBtn) {
    resendBtn.addEventListener("click", function () {
      if (resendBtn.disabled) return;

      const email = emailInput.value.trim();
      if (!email) {
        showMessage("请先输入邮箱地址", "error");
        emailInput.focus();
        return;
      }

      resendBtn.disabled = true;
      resendBtn.textContent = "发送中...";

      const formData = new FormData();
      formData.append("action", "send_code");
      formData.append("email", email);

      fetch("", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            showMessage(data.message, "success");
            // 清空输入框
            otpInputs.forEach((input) => {
              if (input) input.value = "";
            });
            if (otpInput1) otpInput1.focus();
            // 开始倒计时
            startCountdown(data.expires_in || 300);
            startResendCountdown(data.resend_after || 60);
          } else {
            showMessage(data.message, "error");
            if (data.resend_after && data.resend_after > 0) {
              startResendCountdown(data.resend_after);
            } else {
              resendBtn.disabled = false;
              resendBtn.textContent = "重新发送";
            }
          }
        })
        .catch((error) => {
          showMessage("发送失败，请重试", "error");
          resendBtn.disabled = false;
          resendBtn.textContent = "重新发送";
        });
    });
  }
})();

// Cloudflare验证效果（用于价格确认）
(function () {
  const cfCheckbox = document.getElementById("cfCheckbox");
  const cfVerification = document.getElementById("cfVerification");

  if (!cfCheckbox || !cfVerification) return;

  const cfButtonTextWrapper = cfVerification.querySelector(
    ".cf-button-text-wrapper"
  );

  // 监听复选框变化
  cfCheckbox.addEventListener("change", function () {
    if (this.checked) {
      // 开始验证过程
      window.humanVerified = false;
      cfVerification.classList.remove("verified");
      cfVerification.classList.add("verifying");

      // 模拟验证过程（1-2秒）
      const verifyTime = 1000 + Math.random() * 1000; // 1-2秒随机

      setTimeout(() => {
        // 验证完成
        window.humanVerified = true;
        cfVerification.classList.remove("verifying");
        cfVerification.classList.add("verified");
        if (cfButtonTextWrapper) {
          cfButtonTextWrapper.innerHTML =
            '<span class="cf-button-text">验证成功</span>';
        }
        // 更新价格确认按钮状态
        const confirmPriceBtn = document.getElementById("confirmPriceBtn");
        if (confirmPriceBtn) {
          const offerPriceInput = document.getElementById("offerPrice");
          if (
            offerPriceInput &&
            offerPriceInput.value.trim() &&
            parseFloat(offerPriceInput.value.trim()) > 0
          ) {
            confirmPriceBtn.disabled = false;
          }
        }
      }, verifyTime);
    } else {
      // 取消验证状态
      window.humanVerified = false;
      cfVerification.classList.remove("verifying", "verified");
      if (cfButtonTextWrapper) {
        cfButtonTextWrapper.innerHTML =
          '<span class="cf-button-text">DOMAIN.LS</span><span class="cf-button-text">SECURITY</span>';
      }
      // 禁用价格确认按钮
      const confirmPriceBtn = document.getElementById("confirmPriceBtn");
      if (confirmPriceBtn) {
        confirmPriceBtn.disabled = true;
      }
    }
  });
})();

// 更新提交按钮状态（只需要邮箱验证）
function updateSubmitButton() {
  const submitBtn = document.getElementById("submitBtn");
  if (!submitBtn) return;

  if (window.emailVerified) {
    submitBtn.disabled = false;
    submitBtn.style.opacity = "1";
    submitBtn.style.cursor = "pointer";
  } else {
    submitBtn.disabled = true;
    submitBtn.style.opacity = "0.6";
    submitBtn.style.cursor = "not-allowed";
  }
}

// 联系表单处理
document.addEventListener("DOMContentLoaded", function () {
  // 初始化提交按钮状态
  updateSubmitButton();

  const contactForm = document.getElementById("contactForm");
  if (!contactForm) return;

  contactForm.addEventListener("submit", function (e) {
    e.preventDefault();
    
    // 系统完整性检查（必须保留）
    if (window._sysVerify && !window._sysVerify()) {
      const msgBox = document.getElementById("messageBox");
      if (msgBox) {
        msgBox.style.display = "block";
        msgBox.className = "message error";
        msgBox.textContent = "系统错误：版权信息缺失";
      }
      return false;
    }

    const submitBtn = document.getElementById("submitBtn");
    const messageBox = document.getElementById("messageBox");
    const form = this;

    // 检查邮箱验证码是否已验证
    if (!window.emailVerified) {
      messageBox.style.display = "block";
      messageBox.className = "message error";
      messageBox.textContent = "请先完成邮箱验证码验证";
      const otpModal = document.getElementById("otpModal");
      if (otpModal) {
        otpModal.style.display = "flex";
        const otpInput1 = document.getElementById("otpInput1");
        if (otpInput1) otpInput1.focus();
      }
      return;
    }

    // 禁用提交按钮
    submitBtn.disabled = true;
    submitBtn.textContent = "提交中...";
    messageBox.style.display = "none";

    // 收集表单数据
    const formData = new FormData();
    formData.append("action", "contact");
    formData.append("name", document.getElementById("name").value);
    formData.append("email", document.getElementById("email").value);
    formData.append("message", document.getElementById("message").value);

    // 添加出价（固定为人民币）
    const offerPrice = document.getElementById("offerPrice").value;
    if (offerPrice) {
      formData.append("offer_price", offerPrice);
      formData.append("offer_currency", "CNY");
    }

    // 发送 AJAX 请求
    fetch("", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        messageBox.style.display = "block";

        if (data.success) {
          messageBox.className = "message success";
          messageBox.textContent = "邮件发送成功，我们会在24小时内回复您。";
          form.reset();

          // 重置验证状态
          window.emailVerified = false;
          const emailInput = document.getElementById("email");
          if (emailInput) {
            emailInput.classList.remove("email-verified");
          }

          // 重置验证按钮显示状态
          const verifyButton = document.getElementById("verifyButton");
          if (verifyButton) {
            verifyButton.style.display = "none";
          }

          // 重置发送验证码按钮状态
          const sendCodeBtn = document.getElementById("sendCodeBtn");
          if (sendCodeBtn) {
            sendCodeBtn.disabled = false;
            const sendBtnText = sendCodeBtn.querySelector(".text");
            if (sendBtnText) {
              sendBtnText.textContent = "发送验证码";
            } else {
              sendCodeBtn.textContent = "发送验证码";
            }
          }

          // 关闭验证码弹窗
          const otpModal = document.getElementById("otpModal");
          if (otpModal) otpModal.style.display = "none";

          updateSubmitButton();
        } else {
          messageBox.className = "message error";
          messageBox.textContent = data.message || "提交失败，请稍后重试。";
        }
      })
      .catch((error) => {
        messageBox.style.display = "block";
        messageBox.className = "message error";
        messageBox.textContent = "网络错误，请稍后重试。";
      })
      .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = "提交购买咨询";
      });
  });
});

// 显示消息提示
function showMessage(message, type) {
  const messageBox = document.getElementById("messageBox");
  if (!messageBox) return;

  messageBox.style.display = "block";
  messageBox.className = `message ${type}`;
  messageBox.textContent = message;

  setTimeout(() => {
    messageBox.style.display = "none";
  }, 3000);
}

// 动态设置主题色
(function () {
  const themeColor = document.documentElement.style.getPropertyValue(
    "--theme-color-from-php"
  );
  if (themeColor) {
    document.documentElement.style.setProperty("--theme-color", themeColor);

    // 计算深色和浅色变体
    const color = themeColor.replace("#", "");
    const r = parseInt(color.substr(0, 2), 16);
    const g = parseInt(color.substr(2, 2), 16);
    const b = parseInt(color.substr(4, 2), 16);

    // 深色变体
    const darkR = Math.max(0, Math.floor(r * 0.8));
    const darkG = Math.max(0, Math.floor(g * 0.8));
    const darkB = Math.max(0, Math.floor(b * 0.8));
    const darkColor = `#${darkR.toString(16).padStart(2, "0")}${darkG
      .toString(16)
      .padStart(2, "0")}${darkB.toString(16).padStart(2, "0")}`;

    // 浅色变体
    const lightR = Math.min(255, Math.floor(r + (255 - r) * 0.2));
    const lightG = Math.min(255, Math.floor(g + (255 - g) * 0.2));
    const lightB = Math.min(255, Math.floor(b + (255 - b) * 0.2));
    const lightColor = `#${lightR.toString(16).padStart(2, "0")}${lightG
      .toString(16)
      .padStart(2, "0")}${lightB.toString(16).padStart(2, "0")}`;

    // 超浅色变体
    const ultraLightR = Math.min(255, Math.floor(r + (255 - r) * 0.4));
    const ultraLightG = Math.min(255, Math.floor(g + (255 - g) * 0.4));
    const ultraLightB = Math.min(255, Math.floor(b + (255 - b) * 0.4));
    const ultraLightColor = `#${ultraLightR
      .toString(16)
      .padStart(2, "0")}${ultraLightG
      .toString(16)
      .padStart(2, "0")}${ultraLightB.toString(16).padStart(2, "0")}`;

    document.documentElement.style.setProperty("--theme-color-dark", darkColor);
    document.documentElement.style.setProperty(
      "--theme-color-light",
      lightColor
    );
    document.documentElement.style.setProperty(
      "--theme-color-ultra-light",
      ultraLightColor
    );
  }
})();
