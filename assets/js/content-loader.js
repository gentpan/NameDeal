/**
 * 前端内容动态加载脚本
 * 文件: /assets/js/content-loader.js
 * 用途: 从API加载所有动态内容，替换前端硬编码内容
 * 注意: 保持所有HTML结构、CSS类名、样式100%不变
 */

// ==================== 全局内容数据 ====================
let siteContent = null;

// ==================== 页面加载时初始化 ====================
document.addEventListener('DOMContentLoaded', async function() {
    // 先加载内容数据
    await loadSiteContent();
    
    // 如果内容加载成功，应用到页面
    if (siteContent) {
        applySiteContent();
    }
});

// ==================== 从API加载网站内容 ====================
async function loadSiteContent() {
    try {
        const response = await fetch('/api/content.php?_t=' + Date.now());
        
        if (!response.ok) {
            console.error('内容API响应失败');
            return false;
        }
        
        const result = await response.json();
        
        if (result.success && result.data) {
            siteContent = result.data;
            console.log('✅ 网站内容加载成功');
            return true;
        } else {
            console.error('❌ 内容数据格式错误');
            return false;
        }
        
    } catch (error) {
        console.error('❌ 加载网站内容失败:', error);
        return false;
    }
}

// ==================== 应用内容到页面 ====================
function applySiteContent() {
    // 1. 应用基本设置
    applyBasicSettings();
    
    // 2. 应用公告
    applyAnnouncements();
    
    // 3. 应用导航链接
    applyNavigationLinks();
    
    // 4. 应用优势卡片
    applyAdvantages();
    
    // 5. 应用友情链接
    applyFriendLinks();
    
    // 6. 应用Footer链接
    applyFooterLinks();
    
    // 7. 应用区域设置
    applyContactSection();
}

// ==================== 1. 基本设置 ====================
function applyBasicSettings() {
    if (!siteContent.site_settings) return;
    
    const settings = siteContent.site_settings;
    
    // 更新页面标题
    if (settings.site_title) {
        document.title = settings.site_title;
    }
    
    // 更新meta描述
    if (settings.site_description) {
        let metaDesc = document.querySelector('meta[name="description"]');
        if (metaDesc) {
            metaDesc.content = settings.site_description;
        }
    }
    
    // 更新顶部站点名称
    if (settings.site_name) {
        const topBrandTitle = document.querySelector('.top-brand-title');
        if (topBrandTitle) {
            topBrandTitle.textContent = settings.site_name;
        }
    } else if (settings.logo_text) {
        const topBrandTitle = document.querySelector('.top-brand-title');
        if (topBrandTitle) {
            topBrandTitle.textContent = settings.logo_text;
        }
    }

    // 更新顶部副标题（右侧）
    if (settings.logo_subtitle) {
        const topSubtitle = document.querySelector('.top-subtitle-right');
        if (topSubtitle) {
            topSubtitle.textContent = settings.logo_subtitle;
        }
    }

    // Footer中的Logo（优先使用 site_name，避免被旧的 logo_text 覆盖为大写）
    if (settings.site_name) {
        const footerLogoText = document.querySelector('.footer-logo-text');
        if (footerLogoText) {
            footerLogoText.textContent = settings.site_name;
        }
    } else if (settings.logo_text) {
        const footerLogoText = document.querySelector('.footer-logo-text');
        if (footerLogoText) {
            footerLogoText.textContent = settings.logo_text.split(' ')[0];
        }
    }
    
    // 更新版权信息
    if (settings.footer_copyright) {
        const copyright = document.querySelector('.copyright');
        if (copyright) {
            copyright.textContent = settings.footer_copyright;
        }
    }
}

// ==================== 2. 公告 ====================
function applyAnnouncements() {
    // 公告位已移除，保留空函数避免影响调用链。
}

// ==================== 3. 导航链接 ====================
function applyNavigationLinks() {
    if (!siteContent.navigation_links || siteContent.navigation_links.length === 0) return;
    
    const topNav = document.querySelector('.top-nav');
    if (!topNav) return;
    
    // 清空现有链接
    topNav.innerHTML = '';
    
    // 添加新链接 - 保持原始HTML结构
    siteContent.navigation_links.forEach(link => {
        if (link.position === 'top') {
            const a = document.createElement('a');
            a.href = link.url;
            a.target = link.target;
            a.textContent = link.title;
            topNav.appendChild(a);
        }
    });
}

// ==================== 4. 优势卡片 ====================
function applyAdvantages() {
    if (!siteContent.advantages || siteContent.advantages.length === 0) return;
    
    const advantagesGrid = document.querySelector('.advantages-grid');
    if (!advantagesGrid) return;
    
    // 清空现有卡片
    advantagesGrid.innerHTML = '';
    
    // 添加新卡片 - 完全保持原始HTML结构
    siteContent.advantages.forEach(adv => {
        const card = document.createElement('div');
        card.className = 'advantage-item';
        card.innerHTML = `
            <svg class="advantage-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                ${adv.icon_svg}
            </svg>
            <h3 class="advantage-title">${escapeHtml(adv.title)}</h3>
            <p class="advantage-description">${escapeHtml(adv.description)}</p>
        `;
        advantagesGrid.appendChild(card);
    });
}

// ==================== 5. 友情链接 ====================
function applyFriendLinks() {
    if (!siteContent.friend_links || siteContent.friend_links.length === 0) return;
    
    const friendLinkWrapper = document.querySelector('.friends-link-wrapper');
    if (!friendLinkWrapper) return;
    
    // 清空现有链接
    friendLinkWrapper.innerHTML = '';
    
    // 添加新链接 - 保持原始HTML结构
    siteContent.friend_links.forEach((link, index) => {
        // 添加链接
        const a = document.createElement('a');
        a.href = link.url;
        a.target = link.target;
        a.rel = link.rel;
        a.className = 'friend-link';
        a.textContent = link.title;
        friendLinkWrapper.appendChild(a);
        
        // 添加分隔符(最后一个不添加)
        if (index < siteContent.friend_links.length - 1) {
            const separator = document.createElement('span');
            separator.className = 'link-separator';
            separator.textContent = '·';
            friendLinkWrapper.appendChild(separator);
        }
    });
}

// ==================== 6. Footer链接 ====================
function applyFooterLinks() {
    if (!siteContent.footer_links) return;
    
    const footerMain = document.querySelector('.footer-main');
    if (!footerMain) return;
    
    // Footer列映射
    const columnMap = {
        'quick': 0,
        'service': 1,
        'project': 2,
        'follow': 3
    };
    
    // 获取所有footer-column元素
    const columns = footerMain.querySelectorAll('.footer-column');
    
    // 更新每一列
    Object.keys(columnMap).forEach(columnKey => {
        const columnIndex = columnMap[columnKey];
        const column = columns[columnIndex];
        
        if (!column) return;
        
        const links = siteContent.footer_links[columnKey];
        if (!links || links.length === 0) return;
        
        // 更新列标题
        const columnTitle = column.querySelector('.footer-column-title');
        if (columnTitle && links[0]) {
            // 检查是否有链接标题
            if (links[0].column_title && columnTitle.querySelector('a')) {
                // 如果标题本身是链接
                const titleLink = columnTitle.querySelector('a');
                if (titleLink) {
                    titleLink.textContent = links[0].column_title;
                }
            } else {
                columnTitle.textContent = links[0].column_title;
            }
        }
        
        // 更新链接列表
        const linksList = column.querySelector('.footer-links');
        if (linksList) {
            linksList.innerHTML = '';
            
            links.forEach(link => {
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = link.url;
                a.target = link.target;
                a.textContent = link.title;
                li.appendChild(a);
                linksList.appendChild(li);
            });
        }
    });
}

// ==================== 7. 联系区域设置 ====================
function applyContactSection() {
    if (!siteContent.contact_section) return;
    
    // 联系我们区域
    if (siteContent.contact_section.contact) {
        const contactSection = document.querySelector('.contact-section');
        if (contactSection) {
            const title = contactSection.querySelector('.section-title');
            const subtitle = contactSection.querySelector('.section-subtitle');
            const button = contactSection.querySelector('.btn');
            
            if (title) title.textContent = siteContent.contact_section.contact.title;
            if (subtitle) subtitle.textContent = siteContent.contact_section.contact.subtitle;
            if (button) button.textContent = siteContent.contact_section.contact.button_text;
        }
    }
    
    // 选择优势区域
    if (siteContent.contact_section.advantages) {
        const advantagesSection = document.querySelector('.advantages-section');
        if (advantagesSection) {
            const title = advantagesSection.querySelector('.section-title');
            const subtitle = advantagesSection.querySelector('.section-subtitle');
            
            if (title) title.textContent = siteContent.contact_section.advantages.title;
            if (subtitle) subtitle.textContent = siteContent.contact_section.advantages.subtitle;
        }
    }
}

// ==================== 辅助函数 ====================
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ==================== 导出供外部使用 ====================
window.siteContentLoader = {
    reload: loadSiteContent,
    apply: applySiteContent,
    getContent: () => siteContent
};

console.log('✅ 内容动态加载器已初始化');
