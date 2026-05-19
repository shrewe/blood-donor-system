// ============================================================
// assets/js/main.js  –  LifeLink client-side logic
// ============================================================

const APP_URL = document.querySelector('meta[name="app-url"]')?.content
              || window.location.origin + '/blood-donor-system';

// ── Availability Toggle ───────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {

  // availability buttons
  document.querySelectorAll('.avail-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const status = btn.dataset.status;
      const res    = await fetch(`${APP_URL}/api/update_availability.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status, csrf: getCsrf() })
      });
      const data = await res.json();
      if (data.success) {
        // update active state visually
        document.querySelectorAll('.avail-btn').forEach(b => b.classList.remove('active-available_now','active-available_later','active-not_available'));
        btn.classList.add('active-' + status);
        showToast('Availability updated!', 'success');
        // refresh eligibility note if present
        const note = document.getElementById('eligibility-note');
        if (note && status !== 'available_now') note.style.display = 'none';
      } else {
        showToast(data.error || 'Update failed', 'error');
      }
    });
  });

  // accept / reject notification
  document.querySelectorAll('.notif-accept, .notif-reject').forEach(btn => {
    btn.addEventListener('click', async () => {
      const action   = btn.classList.contains('notif-accept') ? 'accepted' : 'rejected';
      const notifId  = btn.dataset.id;
      const requestId = btn.dataset.requestId;
      const res = await fetch(`${APP_URL}/api/respond_notification.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ notif_id: notifId, action, request_id: requestId, csrf: getCsrf() })
      });
      const data = await res.json();
      if (data.success) {
        const item = btn.closest('.notif-item');
        if (item) {
          item.innerHTML = `<div class="notif-msg text-muted">${action === 'accepted' ? '✅ You accepted this request. Contact info shown below.' : '❌ Request declined.'}</div>`;
          if (action === 'accepted' && data.contact) {
            item.innerHTML += `<div class="notif-msg mt-2"><strong>Contact:</strong> ${escHtml(data.contact)}</div>`;
          }
        }
        // update badge
        const badge = document.querySelector('.navbar .badge');
        if (badge) {
          const cnt = parseInt(badge.textContent) - 1;
          if (cnt <= 0) badge.remove(); else badge.textContent = cnt;
        }
      } else {
        showToast(data.error || 'Action failed', 'error');
      }
    });
  });

  // confirm donation (hospital)
  document.querySelectorAll('.confirm-donation-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('Confirm this donation has been completed?')) return;
      const donationId = btn.dataset.id;
      btn.disabled = true;
      btn.textContent = 'Confirming...';
      try {
        const data = await postJson(`${APP_URL}/api/confirm_donation.php`, {
          donation_id: donationId,
          csrf: getCsrf()
        });
        if (data.success) {
          showToast('Donation confirmed! Donor score updated.', 'success');
          const row = btn.closest('tr');
          const status = row?.querySelector('.donation-status');
          if (status) {
            status.classList.add('text-green');
            status.textContent = 'Confirmed';
          }
          btn.closest('td').textContent = '—';
          updateStatTiles(data.stats);
        } else {
          throw new Error(data.error || 'Failed');
        }
      } catch (err) {
        showToast(err.message || 'Failed to confirm donation', 'error');
        btn.disabled = false;
        btn.textContent = 'Confirm';
      }
    });
  });



  // open donor profile when a donor card is clicked
  document.querySelectorAll('.donor-card-clickable').forEach(card => {
    card.addEventListener('click', (e) => {
      if (e.target.closest('a, button, input, select, textarea')) return;
      const href = card.dataset.href;
      if (href) window.location.href = href;
    });
  });

  // auto-dismiss alerts
  document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }, 4000);
  });

  // poll for new notifications every 30s (donor only)
  if (document.body.dataset.role === 'donor') {
    setInterval(pollNotifications, 30000);
  }

});


async function postJson(url, payload) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  const text = await res.text();
  let data;
  try { data = JSON.parse(text); }
  catch (_) { throw new Error('Server returned invalid response. Check APP_URL and login session.'); }
  if (!res.ok) throw new Error(data.error || `Request failed (${res.status})`);
  return data;
}

function updateStatTiles(stats) {
  if (!stats) return;
  const map = { total: 'stat-total', confirmed: 'stat-confirmed', pending: 'stat-pending' };
  Object.entries(map).forEach(([key, id]) => {
    const el = document.getElementById(id);
    if (el && stats[key] !== undefined) el.textContent = stats[key];
  });
}

// ── Polling ───────────────────────────────────────────────────

async function pollNotifications() {
  try {
    const res  = await fetch(`${APP_URL}/api/poll_notifications.php`);
    const data = await res.json();
    if (data.count > 0) {
      const badge = document.querySelector('.navbar .badge');
      if (badge) { badge.textContent = data.count; }
      else {
        const link = document.querySelector('.nav-links a[href*="notifications"]');
        if (link) {
          const b = document.createElement('span');
          b.className = 'badge'; b.textContent = data.count;
          link.appendChild(b);
        }
      }
    }
  } catch(e) {}
}

// ── Toast notifications ───────────────────────────────────────

function showToast(msg, type = 'info') {
  const t = document.createElement('div');
  t.className = `alert alert-${type} fade-in`;
  t.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;max-width:320px;';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .4s'; setTimeout(() => t.remove(), 400); }, 3000);
}

// ── Utility ───────────────────────────────────────────────────

function getCsrf() {
  return document.querySelector('input[name="csrf_token"]')?.value || '';
}

function escHtml(str) {
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Search form live filter ───────────────────────────────────

const searchInput = document.getElementById('live-search');
if (searchInput) {
  searchInput.addEventListener('input', () => {
    const q = searchInput.value.toLowerCase();
    document.querySelectorAll('.donor-card').forEach(card => {
      const text = card.textContent.toLowerCase();
      card.style.display = text.includes(q) ? '' : 'none';
    });
  });
}
