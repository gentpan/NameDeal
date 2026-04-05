<?php
$page_title = '联系我们';
$page_subtitle = '合作与咨询';
$page_content = <<<HTML
<div class="contact-shell">
    <div class="prose-intro">
    	<p>欢迎咨询域名收购、出售、合作展示与品牌命名建议。我们会基于你的业务目标提供清晰沟通与执行方案。</p>
    </div>

    <div class="page-panel-grid two-col">
    	<article class="page-panel">
    		<h3>邮件联系</h3>
    		<p>发送需求说明、预算范围与时间安排，我们会尽快回复。</p>
    		<p><a class="inline-link" href="mailto:contact@domain.ls">contact@domain.ls</a></p>
    	</article>
    	<article class="page-panel">
    		<h3>在线咨询</h3>
    		<p>可在首页卡片详情中点击“进一步咨询”，提交信息后进入人工沟通。</p>
    		<p><a class="inline-link" href="/">返回首页发起咨询</a></p>
    	</article>
    </div>

    <section class="page-section">
    	<h2>建议提交信息</h2>
    	<ul class="feature-list">
    		<li>目标域名 / 目标后缀（如 .ls / .com）</li>
    		<li>预算区间与预期完成时间</li>
    		<li>业务方向与品牌定位关键词</li>
    	</ul>
    </section>
</div>
HTML;

require __DIR__ . '/_layout.php';
