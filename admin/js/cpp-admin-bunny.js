(function(window, document){
    'use strict';

    function onTestBunnyClick(e){
        e.preventDefault();
        var btn = e.currentTarget;
        var resultEl = document.getElementById('cpp-test-bunny-result');
        if (btn) {
            btn.disabled = true;
            btn.dataset.origText = btn.textContent;
            btn.textContent = cpp_admin_bunny.strings.testing || 'Testing...';
        }
        if (resultEl) resultEl.textContent = '';

        var form = new FormData();
        form.append('action', 'cpp_test_bunny_connection');
        form.append('nonce', cpp_admin_bunny.nonce);

        fetch(cpp_admin_bunny.ajax_url, {
            method: 'POST',
            body: form,
            credentials: 'same-origin'
        }).then(function(resp){
            return resp.json();
        }).then(function(json){
            if (json && json.success) {
                if (resultEl) resultEl.textContent = 'OK: ' + (json.data && json.data.message ? json.data.message : cpp_admin_bunny.strings.success || 'Connection successful');
            } else {
                if (resultEl) resultEl.textContent = 'Error: ' + (json && json.data && json.data.message ? json.data.message : JSON.stringify(json));
            }
            if (btn) {
                btn.disabled = false;
                if (btn.dataset.origText) btn.textContent = btn.dataset.origText;
            }
        }).catch(function(err){
            if (resultEl) resultEl.textContent = 'Error: ' + err;
            if (btn) {
                btn.disabled = false;
                if (btn.dataset.origText) btn.textContent = btn.dataset.origText;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function(){
        var btn = document.getElementById('cpp-test-bunny');
        if (btn) btn.addEventListener('click', onTestBunnyClick);

        // Refresh recent tests
        var refreshBtn = document.getElementById('cpp-refresh-bunny-tests');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function(e){
                e.preventDefault();
                fetchRecentBunnyTests();
            });
        }

        // Auto fetch at load if localized data exists
        if (typeof cpp_admin_bunny !== 'undefined' && document.getElementById('cpp-bunny-tests-list')) {
            fetchRecentBunnyTests();
        }
    });

    function fetchRecentBunnyTests() {
        var listEl = document.getElementById('cpp-bunny-tests-list');
        if (!listEl) return;
        listEl.innerHTML = '<em>' + (cpp_admin_bunny.strings.loading || 'Loading...') + '</em>';

        var form = new FormData();
        form.append('action', 'cpp_get_bunny_tests');
        form.append('nonce', cpp_admin_bunny.nonce);

        fetch(cpp_admin_bunny.ajax_url, {
            method: 'POST',
            body: form,
            credentials: 'same-origin'
        }).then(function(resp){ return resp.json(); }).then(function(json){
            if (json && json.success && json.data && json.data.events) {
                renderBunnyTests(json.data.events, listEl);
            } else {
                listEl.innerHTML = '<div style="color:#dc3232;">' + (json && json.data ? JSON.stringify(json.data) : 'No tests found') + '</div>';
            }
        }).catch(function(err){
            listEl.innerHTML = '<div style="color:#dc3232;">Error: ' + err + '</div>';
        });
    }

    function renderBunnyTests(data, container) {
        if (!data || !data.events || data.events.length === 0) {
            container.innerHTML = '<div style="color:#666;">' + (cpp_admin_bunny && cpp_admin_bunny.strings && cpp_admin_bunny.strings.no_tests ? cpp_admin_bunny.strings.no_tests : 'No recent tests found.') + '</div>';
            return;
        }

        var html = '';
        data.events.forEach(function(ev){
            var meta = {};
            try {
                meta = ev.metadata ? JSON.parse(ev.metadata) : {};
            } catch (e) {
                meta = {};
            }
            html += '<div style="border-bottom:1px solid #eee; padding:6px 0;">';
            html += '<strong>' + (ev.created_at || '') + '</strong><br/>';
            var message = 'No result';
            if (meta && meta.result) {
                if (typeof meta.result === 'string') message = meta.result;
                else if (meta.result.message) message = meta.result.message;
                else message = JSON.stringify(meta.result);
            }
            html += '<div>' + escapeHtml(message) + '</div>';
            html += '</div>';
        });
        container.innerHTML = html;
    }

    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

})(window, document);
