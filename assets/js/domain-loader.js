/**
 * 名智展廊 - 完整前端脚本
 * 更新版:添加价格类型和在售状态显示支持 + 优化分页显示
 */

// ==================== 全局变量 ====================
let domainData = [];
let currentDomain = null;
let paginationEventsBound = false;
let initialLoaderHidden = false;
const initialLoaderStartedAt = Date.now();
let initialLoaderHideTimer = null;

function showDomainAreaLoader() {
    const loader = document.getElementById('domainAreaLoader');
    if (!loader) return;
    loader.classList.add('show');
}

function hideDomainAreaLoader() {
    const loader = document.getElementById('domainAreaLoader');
    if (!loader) return;
    loader.classList.remove('show');
}

function hideInitialPageLoader() {
    if (initialLoaderHidden) return;

    const isFullscreenMode = document.body.classList.contains('page-loading-mode-fullscreen');
    const isCornerMode = document.body.classList.contains('page-loading-mode-corner');
    const elapsed = Date.now() - initialLoaderStartedAt;
    const minVisibleMs = isFullscreenMode ? 1000 : (isCornerMode ? 450 : 0);
    if (elapsed < minVisibleMs) {
        if (!initialLoaderHideTimer) {
            initialLoaderHideTimer = setTimeout(() => {
                initialLoaderHideTimer = null;
                hideInitialPageLoader();
            }, minVisibleMs - elapsed);
        }
        return;
    }

    const loader = document.getElementById('initialPageLoader');
    if (!loader) {
        document.body.classList.remove('page-loading');
        initialLoaderHidden = true;
        return;
    }

    loader.classList.add('hidden');
    document.body.classList.remove('page-loading');
    initialLoaderHidden = true;

    setTimeout(() => {
        loader.remove();
    }, 420);
}

function initPageTransitionCornerLoader() {
    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target.closest('a[href]');
        if (!link) return;

        const href = link.getAttribute('href') || '';
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
        if (link.target && link.target !== '_self') return;
        if (link.hasAttribute('download')) return;

        let url;
        try {
            url = new URL(link.href, window.location.origin);
        } catch (e) {
            return;
        }

        if (url.origin !== window.location.origin) return;
        if (url.pathname === window.location.pathname && url.search === window.location.search) return;

        document.body.classList.add('page-loading', 'page-loading-mode-corner');
        document.body.classList.remove('page-loading-mode-fullscreen');
    }, true);
}

// ==================== 分页配置 ====================
const paginationConfig = {
    currentPage: 1,
    itemsPerPage: 8,
    totalPages: 0,
    maxVisiblePages: 5
};

const displayConfig = {
    siteMaxWidth: 1600,
    columnsPerRow: 4,
    cardStyle: 'standard'
};

function applyDisplaySettings() {
    const settings = (window.uiSettings && typeof window.uiSettings === 'object') ? window.uiSettings : {};

    const siteMaxWidth = Number(settings.site_max_width || 1600);
    const columnsPerRow = Number(settings.columns_per_row || 4);
    const itemsPerPage = Number(settings.items_per_page || 8);
    const cardStyle = String(settings.card_style || 'standard');

    displayConfig.siteMaxWidth = Math.max(1200, Math.min(2200, siteMaxWidth));
    displayConfig.columnsPerRow = Math.max(2, Math.min(6, columnsPerRow));
    displayConfig.cardStyle = ['standard', 'small', 'list'].includes(cardStyle) ? cardStyle : 'standard';

    const effectiveColumns = displayConfig.cardStyle === 'list' ? 1 : displayConfig.columnsPerRow;
    const adjustedItems = Math.max(effectiveColumns, itemsPerPage);
    paginationConfig.itemsPerPage = adjustedItems - (adjustedItems % effectiveColumns || 0);

    document.documentElement.style.setProperty('--site-max-width', `${displayConfig.siteMaxWidth}px`);
    document.documentElement.style.setProperty('--domain-columns', effectiveColumns);
}

function getCardClass(indexInPage) {
    if (displayConfig.cardStyle === 'list') {
        return 'domain-card domain-card-list';
    }
    if (displayConfig.cardStyle === 'small') {
        return 'domain-card domain-card-small';
    }
    return 'domain-card';
}

function getCardTemplate(domain, indexInPage) {
    const yearBadgeHtml = domain.regYearsBadge
        ? `<span class="year-badge ${domain.regYearsBadgeClass || ''}">${domain.regYearsBadge}</span>`
        : '';

    if (displayConfig.cardStyle === 'list') {
        return `
            <div class="${getCardClass(indexInPage)}" onclick="showDetailModal(${domain.id})">
                <div class="list-main">
                    <h3 class="domain-name">${domain.domain || ''}</h3>
                    <p class="domain-description">${domain.description || ''}</p>
                </div>
                <div class="list-meta">
                    <span class="meta-tag">${domain.type || ''}</span>
                    <span class="meta-tag">${domain.platform || ''}</span>
                    <span class="meta-tag">${domain.regDate || ''}</span>
                    ${yearBadgeHtml}
                </div>
                <div class="list-actions card-actions">
                    <button class="btn btn-primary" onclick="event.stopPropagation(); showDetailModal(${domain.id})">查看详情</button>
                    <button class="btn btn-secondary" onclick="event.stopPropagation(); visitDomainDirect(${domain.id})">访问购买</button>
                </div>
            </div>
        `;
    }

    return `
        <div class="${getCardClass(indexInPage)}" onclick="showDetailModal(${domain.id})">
            ${domain.badge ? `<span class="domain-badge badge-${domain.badge}">${getBadgeText(domain.badge)}</span>` : '<div style="height: 28px;"></div>'}
            <div class="domain-meta">
                <span class="meta-tag">${domain.type || ''}</span>
                <span class="meta-tag">${domain.platform || ''}</span>
                <span class="meta-tag">${domain.regDate || ''}</span>
                ${yearBadgeHtml}
            </div>
            <h3 class="domain-name">${domain.domain || ''}</h3>
            <p class="domain-description">${domain.description || ''}</p>

            <div class="domain-rating">
                <svg class="rating-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                </svg>
                ${domain.rating || ''}
            </div>
            <div class="card-actions">
                <button class="btn btn-primary" onclick="event.stopPropagation(); showDetailModal(${domain.id})">查看详情</button>
                <button class="btn btn-secondary" onclick="event.stopPropagation(); visitDomainDirect(${domain.id})">访问购买</button>
            </div>
        </div>
    `;
}

// ==================== 主题管理 ====================
const themeManager = {
    current: 'auto',
    dropdown: null,
    toggle: null,
    icon: null,
    
    init() {
        this.dropdown = document.getElementById('themeDropdown');
        this.toggle = document.getElementById('themeToggle');
        this.icon = document.getElementById('themeIcon');
        
        if (!this.dropdown || !this.toggle || !this.icon) {
            console.warn('主题切换器元素未找到');
            return;
        }
        
        const savedTheme = localStorage.getItem('theme') || 'auto';
        this.setTheme(savedTheme, false);
        
        // 监听系统主题变化
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
                if (this.current === 'auto') {
                    this.applyTheme(e.matches ? 'dark' : 'light');
                }
            });
        }
        
        // 切换按钮点击事件
        this.toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            this.dropdown.classList.toggle('active');
        });
        
        // 选项点击事件
        document.querySelectorAll('.theme-option').forEach(option => {
            option.addEventListener('click', () => {
                const theme = option.dataset.theme;
                this.setTheme(theme, true);
                this.dropdown.classList.remove('active');
            });
        });
        
        // 点击外部关闭下拉菜单
        document.addEventListener('click', (e) => {
            if (!this.toggle.contains(e.target) && !this.dropdown.contains(e.target)) {
                this.dropdown.classList.remove('active');
            }
        });
    },
    
    setTheme(theme, animate = false) {
        this.current = theme;
        localStorage.setItem('theme', theme);
        
        // 更新选中状态
        document.querySelectorAll('.theme-option').forEach(option => {
            option.classList.toggle('active', option.dataset.theme === theme);
        });
        
        // 更新图标
        this.updateIcon(theme);
        
        // 应用主题
        if (theme === 'auto') {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            this.applyTheme(prefersDark ? 'dark' : 'light');
        } else {
            this.applyTheme(theme);
        }
    },
    
    updateIcon(theme) {
        const icons = {
            light: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>',
            dark: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>',
            auto: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>'
        };
        if (this.icon) {
            this.icon.innerHTML = icons[theme];
        }
    },
    
    applyTheme(theme) {
        const html = document.documentElement;
        html.setAttribute('data-theme', theme);
        
        // 强制重新渲染确保样式应用
        setTimeout(() => {
            document.body.style.display = 'none';
            document.body.offsetHeight;
            document.body.style.display = '';
        }, 0);
    }
};

// ==================== 从API加载域名数据 ====================
async function loadDomainData() {
    if (!document.getElementById('domainGrid')) {
        const isCornerMode = document.body.classList.contains('page-loading-mode-corner');
        if (isCornerMode && document.readyState !== 'complete') {
            window.addEventListener('load', hideInitialPageLoader, { once: true });
            setTimeout(hideInitialPageLoader, 3000);
        } else {
            hideInitialPageLoader();
        }
        return;
    }

    showDomainAreaLoader();

    try {
        let url = '/api/domains.php';
        const params = new URLSearchParams();
        params.set('_t', Date.now().toString());

        if (window.domainFilter && typeof window.domainFilter === 'object') {
            Object.keys(window.domainFilter).forEach((key) => {
                const value = window.domainFilter[key];
                if (value) {
                    params.set(key, value);
                }
            });
        }

        url += '?' + params.toString();
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error('网络响应失败');
        }
        
        const text = await response.text();
        
        // 从JavaScript格式中提取JSON数据
        const match = text.match(/const domainData = (\[[\s\S]*?\]);/);
        if (match && match[1]) {
            domainData = JSON.parse(match[1]);
            console.log('✅ 成功加载', domainData.length, '个域名');
            
            // 初始化域名显示
            initializeDomainDisplay();
        } else {
            console.error('❌ 无法解析域名数据');
            showEmptyState();
        }
        
    } catch (error) {
        console.error('❌ 加载域名数据失败:', error);
        showEmptyState();
    }
}

// ==================== 初始化域名显示 ====================
function initializeDomainDisplay() {
    applyDisplaySettings();

    // 更新域名数量
    updateDomainCount();
    
    // 计算总页数
    paginationConfig.totalPages = Math.ceil(domainData.length / paginationConfig.itemsPerPage);
    
    // 渲染第一页
    renderDomainCards(1);
    
    // 绑定分页按钮事件
    const prevBtn = document.getElementById('prevPage');
    const nextBtn = document.getElementById('nextPage');
    
    if (!paginationEventsBound && prevBtn) {
        prevBtn.addEventListener('click', prevPage);
    }
    
    if (!paginationEventsBound && nextBtn) {
        nextBtn.addEventListener('click', nextPage);
    }

    if (!paginationEventsBound) {
        paginationEventsBound = true;
    }
}

function initializeWelcomeCarousel() {
    const slider = document.getElementById('welcomeSlider');
    if (!slider) return;

    const slides = Array.from(slider.querySelectorAll('.welcome-slide'));
    if (slides.length < 2) return;

    let currentIndex = 0;
    setInterval(() => {
        slides[currentIndex].classList.remove('active');
        currentIndex = (currentIndex + 1) % slides.length;
        slides[currentIndex].classList.add('active');
    }, 3200);
}

function normalizeDomainFilter() {
    const base = (window.domainFilter && typeof window.domainFilter === 'object') ? window.domainFilter : {};
    return {
        category: String(base.category || ''),
        suffix: String(base.suffix || '')
    };
}

function updateFilterChipState() {
    const filterState = normalizeDomainFilter();
    document.querySelectorAll('.filter-chip[data-filter-group]').forEach((chip) => {
        const group = chip.getAttribute('data-filter-group') || '';
        const value = chip.getAttribute('data-filter-value') || '';
        const activeValue = group === 'category' ? filterState.category : filterState.suffix;
        chip.classList.toggle('active', value === activeValue);
    });
}

function initializeFilterInteractions() {
    const chips = document.querySelectorAll('.filter-chip[data-filter-group]');
    if (!chips.length) return;

    const scrollToDomains = () => {
        const domainAnchor = document.getElementById('domains');
        if (!domainAnchor) return;

        domainAnchor.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    };

    chips.forEach((chip) => {
        chip.addEventListener('click', (event) => {
            event.preventDefault();

            const group = chip.getAttribute('data-filter-group') || '';
            const value = chip.getAttribute('data-filter-value') || '';
            if (!group) return;

            const current = normalizeDomainFilter();
            if (group === 'category') {
                current.category = value;
            }
            if (group === 'suffix') {
                current.suffix = value;
            }

            window.domainFilter = current;
            updateFilterChipState();
            paginationConfig.currentPage = 1;
            scrollToDomains();
            loadDomainData();
        });
    });

    updateFilterChipState();
}

// ==================== 渲染域名卡片(支持分页) ====================
function renderDomainCards(page = 1) {
    const grid = document.getElementById('domainGrid');
    if (!grid) return;

    showDomainAreaLoader();

    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        mainContent.classList.toggle('main-content-list-mode', displayConfig.cardStyle === 'list');
    }

    grid.classList.toggle('domain-grid-list', displayConfig.cardStyle === 'list');
    
    const startIndex = (page - 1) * paginationConfig.itemsPerPage;
    const endIndex = startIndex + paginationConfig.itemsPerPage;
    const currentDomains = domainData.slice(startIndex, endIndex);
    
    // 添加淡出动画
    grid.classList.add('fading');
    
    // 短暂延迟后更新内容
    setTimeout(() => {
        grid.innerHTML = currentDomains.map((domain, index) => getCardTemplate(domain, index)).join('');
        
        // 移除淡出类,触发淡入动画
        setTimeout(() => {
            grid.classList.remove('fading');
        }, 50);
        
        // 更新分页信息
        updatePaginationInfo(page);
        renderPaginationControls(page);

        if (page === 1) {
            hideInitialPageLoader();
        }

        hideDomainAreaLoader();
        
    }, 300);
}

// ==================== 显示空状态 ====================
function showEmptyState() {
    const grid = document.getElementById('domainGrid');
    if (!grid) return;
    
    grid.innerHTML = `
        <div class="empty-state">
            <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
            </svg>
            <p class="empty-text">暂无域名数据</p>
            <p class="empty-subtext">域名正在整理中,敬请期待</p>
        </div>
    `;
    
    // 隐藏分页
    const pagination = document.getElementById('paginationContainer');
    if (pagination) {
        pagination.style.display = 'none';
    }

    hideInitialPageLoader();
    hideDomainAreaLoader();
}

// ==================== 更新域名数量 ====================
function updateDomainCount() {
    const count = domainData.length;
    const countElement = document.getElementById('domainCount');
    if (countElement) {
        countElement.textContent = `共 ${count} 个域名`;
    }
}

// ==================== 获取标签文本 ====================
function getBadgeText(badge) {
    const badges = {
        'featured': '精选域名',
        'new': '最新推荐',
        'special': '非卖藏品',
        'flash': '限时特价'
    };
    return badges[badge] || '';
}

// ==================== 计算域名长度 ====================
function calculateDomainLength(domain) {
    const parts = domain.split('.');
    return parts[0].length;
}

// ==================== 弹窗控制 ====================
function closeAllModals() {
    document.querySelectorAll('.modal-overlay.active').forEach(modal => {
        modal.classList.remove('active');
    });
    document.body.classList.remove('modal-open');
}

function openModal(modalId) {
    closeAllModals();
    
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
    
    const hasActiveModal = document.querySelector('.modal-overlay.active');
    if (!hasActiveModal) {
        document.body.classList.remove('modal-open');
    }
    
    if (modalId === 'detailModal' || modalId === 'inquiryModal') {
        currentDomain = null;
        // 重置 Turnstile
        if (window.turnstile) {
            const turnstileWidget = document.querySelector('.cf-turnstile');
            if (turnstileWidget) {
                turnstile.reset(turnstileWidget);
            }
        }
    }
}

// ==================== 【更新】显示详情弹窗 ====================
function showDetailModal(domainId) {
    const domain = domainData.find(d => d.id === domainId);
    if (!domain) return;
    
    currentDomain = domain;
    
    // 填充详情信息
    const elements = {
        'detailDomainName': domain.domain,
        'detailType': domain.type,
        'detailSuffix': domain.suffix,
        'detailLength': calculateDomainLength(domain.domain) + ' 字符',
        'detailComposition': domain.composition,
        'detailRegDate': domain.regDate,
        'detailExpDate': domain.expDate,
        'detailYears': domain.regYearsBadge || (domain.regYears ? (domain.regYears + '年') : '-'),
        'detailIntro': domain.intro
    };
    
    for (const [id, value] of Object.entries(elements)) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value || '';
        }
    }
    
    // ==================== 【新增】设置价格类型 ====================
    const priceTypeElement = document.getElementById('detailPriceType');
    if (priceTypeElement) {
        const priceTypeLabels = {
            'not_for_sale': '非售卖品',
            'buyer_offer': '买方报价',
            'specific': domain.price ? 
                `¥${parseFloat(domain.price).toLocaleString('zh-CN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}` : 
                '具体价格'
        };
        priceTypeElement.textContent = priceTypeLabels[domain.price_type] || '未设置';
    }
    
    // ==================== 【新增】设置在售状态 ====================
    const saleStatusElement = document.getElementById('detailSaleStatus');
    if (saleStatusElement) {
        const saleStatusLabels = {
            'personal_collection': '个人收藏',
            'holding': '未售持有',
            'coming_soon': '即将开售',
            'on_sale': '正在开售',
            'negotiating': '洽商状态',
            'sold': '已售出'
        };
        saleStatusElement.textContent = saleStatusLabels[domain.sale_status] || '未设置';
    }
    
    openModal('detailModal');
}

function showInquiryModal() {
    if (currentDomain) {
        const titleElement = document.getElementById('inquiryTitle');
        const subjectElement = document.getElementById('inquirySubject');
        
        if (titleElement) {
            titleElement.textContent = `联系咨询有关 ${currentDomain.domain}`;
        }
        if (subjectElement) {
            subjectElement.value = `关于 ${currentDomain.domain} 的域名咨询`;
        }
    }
    openModal('inquiryModal');
}

function showGeneralContactModal() {
    const titleElement = document.getElementById('inquiryTitle');
    const subjectElement = document.getElementById('inquirySubject');
    
    if (titleElement) {
        titleElement.textContent = '联系我们';
    }
    if (subjectElement) {
        subjectElement.value = '';
    }
    openModal('inquiryModal');
}

function visitDomain() {
    if (currentDomain && currentDomain.buyLink && currentDomain.buyLink !== '#') {
        window.open(currentDomain.buyLink, '_blank');
    }
}

function visitDomainDirect(domainId) {
    const domain = domainData.find(d => d.id === domainId);
    if (domain && domain.buyLink && domain.buyLink !== '#') {
        window.open(domain.buyLink, '_blank');
    }
}

function showDocModal(event) {
    if (event) event.preventDefault();
    openModal('docModal');
}

function showTermsModal(event) {
    if (event) event.preventDefault();
    openModal('termsModal');
}

function showPrivacyModal(event) {
    if (event) event.preventDefault();
    openModal('privacyModal');
}

// ==================== 表单提交 ====================
function submitInquiry(event) {
    event.preventDefault();
    
    // 获取 Turnstile token
    const turnstileResponse = document.querySelector('[name="cf-turnstile-response"]');
    if (turnstileResponse && !turnstileResponse.value) {
        showNotification('请完成安全验证', 'error');
        return;
    }
    
    const formData = new FormData(event.target);
    const data = {
        name: formData.get('name'),
        email: formData.get('email'),
        subject: formData.get('subject'),
        message: formData.get('message'),
        domain: currentDomain ? currentDomain.domain : 'N/A',
        'cf-turnstile-response': turnstileResponse ? turnstileResponse.value : ''
    };
    
    // 禁用提交按钮,防止重复提交
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = '发送中...';
    
    // 发送邮件
    fetch('./mail_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('消息发送成功!我们会尽快回复您。', 'success');
            closeModal('inquiryModal');
            event.target.reset();
        } else {
            throw new Error(data.message || '发送失败');
        }
    })
    .catch(error => {
        let errorMsg = '发送失败,请稍后重试或直接发送邮件至: contact@domain.ls';
        
        if (error.errors) {
            errorMsg = error.errors.join('\n');
        } else if (error.message) {
            errorMsg = error.message;
        }
        
        showNotification(errorMsg, 'error');
        
        // 重置 Turnstile
        if (window.turnstile) {
            turnstile.reset();
        }
    })
    .finally(() => {
        // 恢复提交按钮
        submitBtn.disabled = false;
        submitBtn.textContent = originalBtnText;
    });
}

// ==================== 通知提示功能 ====================
function showNotification(message, type = 'info') {
    // 移除现有通知
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // 创建通知元素
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <svg class="notification-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                ${type === 'success' ? 
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>' :
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
                }
            </svg>
            <span class="notification-message">${message.replace(/\n/g, '<br>')}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    `;
    
    document.body.appendChild(notification);
    
    // 5秒后自动消失
    setTimeout(() => {
        notification.classList.add('notification-fade-out');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// ==================== 表单验证初始化 ====================
function initFormValidation() {
    // 表单验证逻辑
}

// ==================== 返回顶部功能 ====================
const backToTop = {
    button: null,
    scrollThreshold: 400,
    ticking: false,
    isScrolling: false,
    
    init() {
        this.button = document.getElementById('backToTop');
        if (!this.button) return;
        
        // 监听滚动事件
        window.addEventListener('scroll', () => {
            if (!this.ticking) {
                requestAnimationFrame(() => {
                    this.handleScroll();
                    this.ticking = false;
                });
                this.ticking = true;
            }
        });
        
        // 点击事件
        this.button.addEventListener('click', (e) => {
            e.preventDefault();
            this.scrollToTop();
        });
        
        // 键盘无障碍支持
        this.button.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.scrollToTop();
            }
        });
        
        // 初始状态检查
        this.handleScroll();
    },
    
    handleScroll() {
        const scrollY = window.scrollY || document.documentElement.scrollTop;
        const windowHeight = window.innerHeight;
        const documentHeight = document.documentElement.scrollHeight;
        
        const nearBottom = documentHeight - scrollY - windowHeight < 100;
        
        if (scrollY > this.scrollThreshold && !nearBottom) {
            this.button.classList.add('show');
        } else {
            this.button.classList.remove('show');
        }
    },
    
    scrollToTop() {
        if (this.isScrolling) return;
        
        this.isScrolling = true;
        this.button.style.pointerEvents = 'none';
        
        const startPosition = window.scrollY || document.documentElement.scrollTop;
        
        if ('scrollBehavior' in document.documentElement.style) {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
            
            const checkComplete = () => {
                const currentPosition = window.scrollY || document.documentElement.scrollTop;
                if (currentPosition === 0) {
                    this.scrollComplete();
                } else {
                    requestAnimationFrame(checkComplete);
                }
            };
            setTimeout(() => requestAnimationFrame(checkComplete), 100);
        } else {
            const startTime = performance.now();
            const duration = Math.min(600, 300 + (startPosition / 3));
            
            const animateScroll = (currentTime) => {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const easeOutCubic = 1 - Math.pow(1 - progress, 3);
                
                window.scrollTo(0, startPosition * (1 - easeOutCubic));
                
                if (progress < 1) {
                    requestAnimationFrame(animateScroll);
                } else {
                    this.scrollComplete();
                }
            };
            
            requestAnimationFrame(animateScroll);
        }
    },
    
    scrollComplete() {
        this.isScrolling = false;
        this.button.style.pointerEvents = '';
        this.button.classList.remove('show');
    }
};

// ==================== 更新分页信息 ====================
function updatePaginationInfo(page) {
    paginationConfig.currentPage = page;
    paginationConfig.totalPages = Math.ceil(domainData.length / paginationConfig.itemsPerPage);
    
    const infoElement = document.getElementById('paginationInfo');
    if (infoElement) {
        const start = (page - 1) * paginationConfig.itemsPerPage + 1;
        const end = Math.min(page * paginationConfig.itemsPerPage, domainData.length);
        // 可选:显示分页信息文本
        // infoElement.textContent = `第 ${page} / ${paginationConfig.totalPages} 页`;
    }
}

// ==================== 【新增】智能生成页码数组 ====================
/**
 * 智能生成页码数组,根据屏幕尺寸和当前页动态调整显示范围
 * @param {number} currentPage - 当前页码
 * @param {number} totalPages - 总页数
 * @returns {Array} 页码数组,包含数字页码和省略号标识
 */
function generateSmartPageNumbers(currentPage, totalPages) {
    const pages = [];
    const isMobile = window.innerWidth <= 768;
    
    // 移动端更激进的策略
    if (isMobile) {
        // 总页数很少时,显示所有页码
        if (totalPages <= 5) {
            for (let i = 1; i <= totalPages; i++) {
                pages.push(i);
            }
            return pages;
        }
        
        // 移动端只显示: 首页 ... 当前页 ... 尾页 (最多3个数字按钮)
        pages.push(1);
        
        if (currentPage > 2) {
            pages.push('ellipsis-start');
        }
        
        if (currentPage !== 1 && currentPage !== totalPages) {
            pages.push(currentPage);
        }
        
        if (currentPage < totalPages - 1) {
            pages.push('ellipsis-end');
        }
        
        pages.push(totalPages);
        
        return pages;
    }
    
    // 桌面端逻辑: 当前页 ± 2
    const delta = 2;
    
    // 总是包含第一页
    pages.push(1);
    
    // 如果总页数很少,直接显示所有页码
    if (totalPages <= (delta * 2 + 3)) {
        for (let i = 2; i <= totalPages; i++) {
            pages.push(i);
        }
        return pages;
    }
    
    // 计算显示范围
    const rangeStart = Math.max(2, currentPage - delta);
    const rangeEnd = Math.min(totalPages - 1, currentPage + delta);
    
    // 如果范围起点不是2,添加省略号
    if (rangeStart > 2) {
        pages.push('ellipsis-start');
    }
    
    // 添加中间范围的页码
    for (let i = rangeStart; i <= rangeEnd; i++) {
        pages.push(i);
    }
    
    // 如果范围终点不是倒数第二页,添加省略号
    if (rangeEnd < totalPages - 1) {
        pages.push('ellipsis-end');
    }
    
    // 总是包含最后一页(如果总页数大于1)
    if (totalPages > 1) {
        pages.push(totalPages);
    }
    
    return pages;
}

// ==================== 【优化】渲染分页控件 ====================
/**
 * 渲染分页控件,使用智能页码算法
 * 移动端和桌面端显示不同数量的页码,避免换行
 */
function renderPaginationControls(currentPage) {
    const totalPages = paginationConfig.totalPages;
    const pagesElement = document.getElementById('paginationPages');
    const prevBtn = document.getElementById('prevPage');
    const nextBtn = document.getElementById('nextPage');
    
    // 如果只有一页或没有分页元素,隐藏分页
    if (!pagesElement || totalPages <= 1) {
        const container = document.getElementById('paginationContainer');
        if (container) {
            container.style.display = 'none';
        }
        return;
    }
    
    // 显示分页容器
    const container = document.getElementById('paginationContainer');
    if (container) {
        container.style.display = 'flex';
    }
    
    // 更新上一页/下一页按钮状态
    if (prevBtn) prevBtn.disabled = currentPage === 1;
    if (nextBtn) nextBtn.disabled = currentPage === totalPages;
    
    // 使用智能页码生成算法
    const pages = generateSmartPageNumbers(currentPage, totalPages);
    
    // 渲染页码按钮
    pagesElement.innerHTML = pages.map(page => {
        if (page === 'ellipsis-start' || page === 'ellipsis-end') {
            // 省略号,不可点击
            return `<span class="pagination-page ellipsis">...</span>`;
        }
        // 普通页码按钮
        return `
            <button class="pagination-page ${page === currentPage ? 'active' : ''}" 
                    onclick="goToPage(${page})" 
                    ${page === currentPage ? 'disabled' : ''}>
                ${page}
            </button>
        `;
    }).join('');
}

// ==================== 页面跳转 ====================
function goToPage(page) {
    if (page < 1 || page > paginationConfig.totalPages || page === paginationConfig.currentPage) {
        return;
    }
    
    // 滚动到域名区域顶部
    const domainGrid = document.getElementById('domainGrid');
    if (domainGrid) {
        const offsetTop = domainGrid.getBoundingClientRect().top + window.pageYOffset - 100;
        window.scrollTo({
            top: offsetTop,
            behavior: 'smooth'
        });
    }
    
    // 渲染新页面
    renderDomainCards(page);
}

// ==================== 上一页/下一页 ====================
function prevPage() {
    if (paginationConfig.currentPage > 1) {
        goToPage(paginationConfig.currentPage - 1);
    }
}

function nextPage() {
    if (paginationConfig.currentPage < paginationConfig.totalPages) {
        goToPage(paginationConfig.currentPage + 1);
    }
}

// ==================== 移动端触摸优化 ====================
function initTouchOptimization() {
    // 防止移动端双击缩放
    let lastTouchEnd = 0;
    document.addEventListener('touchend', function (event) {
        const now = (new Date()).getTime();
        if (now - lastTouchEnd <= 300) {
            event.preventDefault();
        }
        lastTouchEnd = now;
    }, false);
    
    // 改善按钮触摸反馈
    document.querySelectorAll('.btn, .pagination-btn, .pagination-page').forEach(button => {
        button.addEventListener('touchstart', function() {
            this.style.transition = 'transform 0.1s ease, opacity 0.1s ease';
        }, { passive: true });
        
        button.addEventListener('touchend', function() {
            this.style.transition = '';
        }, { passive: true });
    });
    
    // 改善卡片触摸反馈
    document.querySelectorAll('.domain-card').forEach(card => {
        card.addEventListener('touchstart', function() {
            this.style.transition = 'transform 0.1s ease, background-color 0.1s ease';
        }, { passive: true });
        
        card.addEventListener('touchend', function() {
            this.style.transition = '';
        }, { passive: true });
    });
}

// ==================== 全局事件监听 ====================
// ESC键关闭弹窗
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeAllModals();
    }
});

// 点击遮罩关闭弹窗
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        closeAllModals();
    }
});

// ==================== 【新增】窗口大小改变时重新渲染分页 ====================
/**
 * 监听窗口大小变化,动态调整分页显示
 * 确保移动端和桌面端切换时页码显示正确
 */
window.addEventListener('resize', () => {
    // 防抖处理,避免频繁触发
    clearTimeout(window.resizeTimer);
    window.resizeTimer = setTimeout(() => {
        if (paginationConfig.totalPages > 1) {
            renderPaginationControls(paginationConfig.currentPage);
        }
    }, 200);
});

// ==================== 页面加载完成后初始化 ====================
document.addEventListener('DOMContentLoaded', function() {
    // 初始化主题管理器
    themeManager.init();
    
    // 初始化返回顶部按钮  
    if (typeof backToTop !== 'undefined') {
        backToTop.init();
    }
    
    // 初始化触摸优化
    if (typeof initTouchOptimization === 'function') {
        initTouchOptimization();
    }
    
    // 初始化表单验证
    if (typeof initFormValidation === 'function') {
        initFormValidation();
    }

    // 初始化 welcome 轮播与筛选联动
    initializeWelcomeCarousel();
    initializeFilterInteractions();
    initPageTransitionCornerLoader();
    
    // 加载域名数据
    loadDomainData();

    // 兜底: 避免极端情况下遮罩未关闭
    setTimeout(hideInitialPageLoader, 5000);
});
