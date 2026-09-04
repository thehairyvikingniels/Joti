// js/admin_telegram.js — Interactive controls, simulation, and auto-refresh for admin/telegram.php

function copyToClipboard(elementId, btn) {
    const input = document.getElementById(elementId);
    if (!input) return;
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => { btn.innerHTML = originalHtml; }, 2000);
    });
}

function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isPass = input.type === 'password';
    input.type = isPass ? 'text' : 'password';
    btn.innerHTML = isPass ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
}

function setSimExample(text) {
    const area = document.getElementById('simMessage');
    if (area) {
        area.value = text;
        area.focus();
    }
}

async function runSimulation(e) {
    e.preventDefault();
    const area = document.getElementById('simMessage');
    const badge = document.getElementById('simResultBadge');
    const submitBtn = document.getElementById('simSubmitBtn');
    if (!area || !area.value.trim()) return;

    submitBtn.disabled = true;
    submitBtn.classList.add('opacity-50');

    try {
        const formData = new FormData();
        formData.append('message', area.value.trim());

        const res = await fetch('telegram_helper.php?action=simulate', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        badge.classList.remove('hidden', 'bg-green-100', 'text-green-800', 'bg-red-100', 'text-red-800');
        if (data.success) {
            badge.classList.add('bg-green-100', 'text-green-800');
            badge.textContent = `✓ ${data.summary} (${data.type})`;
            pollFeed();
        } else {
            badge.classList.add('bg-red-100', 'text-red-800');
            badge.textContent = `✗ ${data.error || 'Fout bij verwerken'}`;
        }
    } catch (err) {
        badge.classList.remove('hidden');
        badge.classList.add('bg-red-100', 'text-red-800');
        badge.textContent = `Netwerkfout: ${err.message}`;
    } finally {
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50');
    }
}

async function saveConfig(e) {
    e.preventDefault();
    const form = document.getElementById('configForm');
    const formData = new FormData(form);

    try {
        const res = await fetch('telegram_helper.php?action=update_config', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message || 'Configuratie succesvol opgeslagen!');
        } else {
            alert('Fout: ' + (data.error || 'Onbekende fout'));
        }
    } catch (err) {
        alert('Netwerkfout bij opslaan: ' + err.message);
    }
}

function getBadgeColor(type) {
    switch (type) {
        case 'fox_status': return 'bg-orange-100 text-orange-800';
        case 'hunt_status': return 'bg-green-100 text-green-800';
        case 'assignment_graded': return 'bg-blue-100 text-blue-800';
        case 'happy_hour': return 'bg-yellow-100 text-yellow-800';
        case 'tegenhunt': return 'bg-red-100 text-red-800 font-bold';
        case 'admin_broadcast': return 'bg-purple-100 text-purple-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

async function pollFeed() {
    const tbody = document.getElementById('feedTableBody');
    const refreshIcon = document.getElementById('refreshIcon');
    if (refreshIcon) refreshIcon.classList.add('fa-spin');

    try {
        const res = await fetch('telegram_helper.php?action=feed');
        const data = await res.json();
        if (data.success && Array.isArray(data.messages)) {
            if (data.messages.length === 0) {
                tbody.innerHTML = '<tr id="emptyFeedRow"><td colspan="5" class="px-6 py-8 text-center opacity-60 italic">Nog geen berichten ontvangen in de database.</td></tr>';
            } else {
                let html = '';
                for (const msg of data.messages) {
                    const badgeClass = getBadgeColor(msg.parsed_type);
                    const payloadStr = msg.parsed_payload ? JSON.stringify(msg.parsed_payload) : '';
                    const cleanMsg = escapeHtml(msg.message_text).replace(/[\r\n]+/g, ' ');
                    html += `
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-4 py-3 whitespace-nowrap text-xs opacity-80">${escapeHtml(msg.relative_time || msg.created_at)}</td>
                        <td class="px-4 py-3 whitespace-nowrap"><span class="px-2.5 py-1 rounded-full text-xs font-semibold ${badgeClass}">${escapeHtml(msg.parsed_type)}</span></td>
                        <td class="px-4 py-3 whitespace-nowrap text-xs font-mono opacity-80 truncate" title="${escapeHtml(msg.sender)}">${escapeHtml(msg.sender)}</td>
                        <td class="px-4 py-3 text-xs font-sans overflow-hidden"><div class="truncate max-w-full" title="${escapeHtml(msg.message_text)}">${cleanMsg}</div></td>
                        <td class="px-4 py-3 text-[11px] font-mono opacity-70 overflow-hidden"><div class="truncate max-w-full" title="${escapeHtml(payloadStr)}">${escapeHtml(payloadStr)}</div></td>
                    </tr>`;
                }
                tbody.innerHTML = html;
            }
        }
    } catch (err) {
        console.error('Feed polling error:', err);
    } finally {
        if (refreshIcon) refreshIcon.classList.remove('fa-spin');
    }
}

// Webhook Management
async function loadWebhookInfo() {
    const statusEl = document.getElementById('webhookStatusText');
    const btnTest = document.getElementById('btnTestBot');
    if (!statusEl) return;

    try {
        const res = await fetch('telegram_helper.php?action=webhook_info');
        const data = await res.json();
        if (data.success) {
            let html = '';
            if (data.me && data.me.ok) {
                html += `<div class="text-green-600 font-bold flex items-center gap-1.5"><i class="fas fa-check-circle"></i> Bot: @${data.me.result.username} (${data.me.result.first_name})</div>`;
            } else {
                html += `<div class="text-amber-600 font-bold flex items-center gap-1.5"><i class="fas fa-exclamation-triangle"></i> Bot Token: ${data.me?.description || 'Niet geautoriseerd of placeholder'}</div>`;
            }

            if (data.webhook && data.webhook.ok) {
                const wh = data.webhook.result;
                const whActive = wh.url && wh.url.length > 0;
                html += `<div class="mt-1 text-xs ${whActive ? 'text-green-700' : 'text-gray-500'}">
                    Webhook URL: <code class="font-mono">${wh.url || '(Geen webhook ingesteld)'}</code><br>
                    Wachtende updates: <strong>${wh.pending_update_count ?? 0}</strong>
                    ${wh.last_error_message ? `<br><span class="text-red-500">Laatste fout: ${wh.last_error_message}</span>` : ''}
                </div>`;
            }
            statusEl.innerHTML = html;
        } else {
            statusEl.innerHTML = `<span class="text-red-500">Fout: ${data.error || 'Onbekend'}</span>`;
        }
    } catch (err) {
        statusEl.innerHTML = `<span class="text-red-500">Netwerkfout: ${err.message}</span>`;
    }
}

async function registerWebhook() {
    const statusEl = document.getElementById('webhookStatusText');
    if (statusEl) statusEl.innerHTML = '<span class="opacity-60"><i class="fas fa-spinner fa-spin"></i> Webhook registreren bij Telegram...</span>';
    try {
        const res = await fetch('telegram_helper.php?action=set_webhook');
        const data = await res.json();
        if (data.success) {
            alert('Webhook succesvol geregistreerd bij Telegram!');
        } else {
            alert('Fout bij registreren webhook: ' + (data.response?.description || data.error || 'Onbekende fout'));
        }
        loadWebhookInfo();
    } catch (err) {
        alert('Netwerkfout: ' + err.message);
        loadWebhookInfo();
    }
}

async function removeWebhook() {
    if (!confirm('Weet je zeker dat je de webhook wilt verwijderen?')) return;
    try {
        const res = await fetch('telegram_helper.php?action=delete_webhook');
        const data = await res.json();
        if (data.success) {
            alert('Webhook verwijderd.');
        } else {
            alert('Fout: ' + (data.response?.description || 'Onbekende fout'));
        }
        loadWebhookInfo();
    } catch (err) {
        alert('Netwerkfout: ' + err.message);
    }
}

async function testBotToken() {
    const statusEl = document.getElementById('webhookStatusText');
    if (statusEl) statusEl.innerHTML = '<span class="opacity-60"><i class="fas fa-spinner fa-spin"></i> Token testen...</span>';
    try {
        const res = await fetch('telegram_helper.php?action=test_bot');
        const data = await res.json();
        if (data.success) {
            alert(`Succes! Bot is geautoriseerd als @${data.response.result.username}`);
        } else {
            alert(`Fout bij autorisatie: ${data.response?.description || 'Controleer token in Site Instellingen'}`);
        }
        loadWebhookInfo();
    } catch (err) {
        alert('Netwerkfout: ' + err.message);
        loadWebhookInfo();
    }
}

// Auto-refresh feed every 5 seconds and load webhook info on page load
setInterval(pollFeed, 5000);
document.addEventListener('DOMContentLoaded', () => {
    loadWebhookInfo();
});
