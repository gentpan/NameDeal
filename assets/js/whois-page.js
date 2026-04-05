(function () {
    const input = document.getElementById('whoisDomain');
    const btn = document.getElementById('whoisSearchBtn');
    const state = document.getElementById('whoisState');
    const result = document.getElementById('whoisResult');

    if (!input || !btn || !state || !result) {
        return;
    }

    const setState = (text, type) => {
        state.textContent = text;
        state.className = 'whois-state' + (type ? ' ' + type : '');
    };

    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value || '-';
    };

    const formatDate = (v) => {
        if (!v) return '-';
        const d = new Date(v);
        if (Number.isNaN(d.getTime())) return v;
        return d.toISOString().slice(0, 10);
    };

    const query = async () => {
        const domain = input.value.trim().toLowerCase();
        if (!domain) {
            setState('请输入要查询的域名。', 'error');
            result.classList.add('hidden');
            return;
        }

        setState('查询中，请稍候...', 'loading');
        result.classList.add('hidden');
        btn.disabled = true;

        try {
            const resp = await fetch('/api/rdap.php?domain=' + encodeURIComponent(domain));
            const data = await resp.json();
            if (!data.success) {
                setState(data.message || '查询失败', 'error');
                return;
            }

            const rdap = data.data || {};
            setText('rDomain', rdap.domain || domain);
            setText('rRegistrar', rdap.registrar || '-');
            setText('rRegDate', formatDate(rdap.events?.registration));
            setText('rExpDate', formatDate(rdap.events?.expiration));
            setText('rStatus', (rdap.status || []).join(' | '));
            setText('rPort43', rdap.port43 || '-');

            const nsEl = document.getElementById('rNs');
            if (nsEl) {
                const list = rdap.nameservers || [];
                nsEl.innerHTML = list.length
                    ? list.map((n) => '<span class="whois-tag">' + n + '</span>').join('')
                    : '<span class="whois-empty">无 Nameserver 数据</span>';
            }

            const raw = document.getElementById('rRaw');
            if (raw) {
                raw.textContent = JSON.stringify(rdap.raw || {}, null, 2);
            }

            result.classList.remove('hidden');
            const source = data.source === 'local' ? '本地样本数据' : 'RDAP 实时数据';
            setState('查询成功（' + source + '）。', 'success');
        } catch (e) {
            setState('网络错误，稍后重试。', 'error');
        } finally {
            btn.disabled = false;
        }
    };

    btn.addEventListener('click', query);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') query();
    });
})();
