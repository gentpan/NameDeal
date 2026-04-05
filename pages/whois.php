<?php
$page_title = 'WHOIS 查询';
$page_subtitle = '基于 RDAP 的域名信息查询';
$page_content_class = 'page-content page-content-wide';
$page_inner_class = 'page-content-inner page-content-inner-wide';
$page_content = <<<HTML
<div class="whois-shell">
    <div class="whois-search">
        <label for="whoisDomain" class="whois-label">输入域名</label>
        <div class="whois-input-row">
            <input id="whoisDomain" class="whois-input" type="text" placeholder="例如 domain.ls" autocomplete="off">
            <button id="whoisSearchBtn" class="whois-btn" type="button">查询</button>
        </div>
        <p class="whois-hint">数据来源：RDAP（注册信息标准协议），支持主流国际域名查询。</p>
    </div>

    <div id="whoisState" class="whois-state">请输入域名后开始查询。</div>

    <div id="whoisResult" class="whois-result hidden">
        <div class="whois-grid">
            <div class="whois-card"><span>域名</span><strong id="rDomain"></strong></div>
            <div class="whois-card"><span>注册商</span><strong id="rRegistrar"></strong></div>
            <div class="whois-card"><span>注册时间</span><strong id="rRegDate"></strong></div>
            <div class="whois-card"><span>到期时间</span><strong id="rExpDate"></strong></div>
            <div class="whois-card"><span>状态</span><strong id="rStatus"></strong></div>
            <div class="whois-card"><span>WHOIS 服务器</span><strong id="rPort43"></strong></div>
        </div>

        <div class="whois-block">
            <h3>Nameservers</h3>
            <div id="rNs" class="whois-list"></div>
        </div>

        <details class="whois-block">
            <summary>查看原始 RDAP 数据</summary>
            <pre id="rRaw" class="whois-raw"></pre>
        </details>
    </div>
</div>
HTML;

$page_extra_script = '<script src="/assets/js/whois-page.js"></script>';

require __DIR__ . '/_layout.php';
