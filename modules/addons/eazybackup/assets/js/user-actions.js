document.addEventListener('DOMContentLoaded', () => {
  const endpoint = window.EB_USER_ENDPOINT;
  const serviceId = document.body.getAttribute('data-eb-serviceid');
  const username = document.body.getAttribute('data-eb-username');

  function toast(msg, kind){ try { window.showToast?.(msg, kind || 'info'); } catch (_) {} }

  async function call(action, extra){
    const body = Object.assign({ action, serviceId, username }, (extra || {}));
    const res = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    return res.json();
  }

  async function doResetPassword(detail) {
    try {
      // Open a dedicated password reset modal with input + generate button.
      const chosen = await passwordResetModal();
      if (!chosen) { return; } // closed without action
      const inputPassword = chosen.password || '';
      if (!inputPassword || inputPassword.length < 8) { toast('Password must be at least 8 characters.', 'warning'); return; }

      const modalCard = document.querySelector('.min-h-screen .container') || document.body;
      window.ebShowLoader?.(modalCard, 'Resetting password…');

      // Determine service ID from event detail or fallback to data attribute
      const sid = (detail && (detail.serviceid || detail.serviceId)) || serviceId;
      if (!sid) { throw new Error('Missing service ID'); }

      // Use Comet server AJAX endpoint directly; no dependency on services.js
      let r;
      try {
        const form = new URLSearchParams();
        form.append('serviceId', String(sid));
        form.append('newpassword', String(inputPassword));
        const res = await fetch('modules/servers/comet/ajax/changepassword.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: form.toString()
        });
        r = await res.json();
      } catch (_) {
        r = { result: 'error', message: 'Network error' };
      }

      if (r && r.result === 'success') {
        const newPassword = String(inputPassword);

        const actionPanel = `
          <div class="mt-4 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3">
            <div class="flex items-start gap-3">
              <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 4h.01M5.07 19h13.86A2 2 0 0021 17.2L13.93 4.8a2 2 0 00-3.86 0L3 17.2A2 2 0 005.07 19z"/>
              </svg>
              <div>
                <div class="font-medium text-amber-300">Action needed</div>
                <p class="mt-1 text-sm text-slate-300">On each computer, <strong>close and reopen the eazyBackup client</strong>, then sign in with your <strong>new backup account password</strong> so future backups do not fail.</p>
              </div>
            </div>
          </div>`;

        // Immediately show the password view with copy button (no intermediate prompt)
        await alertDialog(
          'New Password',
          `
            <div class="space-y-3">
              <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3">
                <div class="text-xs uppercase tracking-wide text-emerald-400">New password</div>
                <div class="mt-1 flex items-center gap-2">
                  <div id="ua-password-text" class="select-all font-mono text-lg">${escapeHtml(newPassword)}</div>
                  <button id="ua-copy-btn" type="button" class="inline-flex items-center p-2 rounded border border-slate-600 hover:bg-slate-700" title="Copy">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                    </svg>
                  </button>
                </div>
                <div class="mt-2 text-xs text-slate-400">Password updated for your backup account.</div>
              </div>
              ${actionPanel}
            </div>
          `
        );

      } else {
        const msg = (r && (r.message || r.error)) || 'Password reset failed.';
        toast(msg, 'error');
      }

    } catch (_) {
      toast('Network error while resetting password.', 'error');
    } finally {
      const modalCard = document.querySelector('.min-h-screen .container') || document.body;
      window.ebHideLoader?.(modalCard);
    }
  }
  
  

  // Alpine custom event from Actions menu
  document.addEventListener('eb-reset-password', (e) => { doResetPassword(e && e.detail); });

  function escapeHtml(s){ return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }

  function confirmDialog(title, message){
    return new Promise(resolve => {
      const overlay = document.createElement('div');
      overlay.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60';
      overlay.innerHTML = `
        <div class="bg-slate-800/95 border border-slate-700 rounded-lg shadow-xl w-full max-w-md">
          <div class="px-5 py-4 border-b border-slate-700"><h3 class="text-slate-200 text-base font-semibold">${title}</h3></div>
          <div class="px-5 py-4 text-slate-300 text-sm">${message}</div>
          <div class="px-5 py-3 border-t border-slate-700 flex justify-end gap-2">
            <button class="px-4 py-2 text-slate-300 hover:text-white" data-cmd="cancel">Cancel</button>
            <button class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded" data-cmd="ok">OK</button>
          </div>
        </div>`;
      function cleanup(){ try { overlay.remove(); } catch(_){} }
      overlay.addEventListener('click', (e)=>{
        const cmd = e.target.getAttribute('data-cmd');
        if (cmd === 'ok') { cleanup(); resolve(true); }
        if (cmd === 'cancel' || e.target === overlay) { cleanup(); resolve(false); }
      });
      document.body.appendChild(overlay);
    });
  }

  // Dedicated password reset modal: input + Generate button, X close, no Cancel
  function passwordResetModal(){
    return new Promise(resolve => {
      const overlay = document.createElement('div');
      overlay.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60';
      overlay.innerHTML = `
        <div class="bg-slate-800/95 border border-slate-700 rounded-lg shadow-xl w-full max-w-md">
          <div class="px-5 py-4 border-b border-slate-700 flex items-center justify-between">
            <h3 class="text-slate-200 text-base font-semibold">Reset Password</h3>
            <button class="text-slate-400 hover:text-slate-200" data-cmd="close" title="Close">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <div class="px-5 py-4 text-slate-300 text-sm space-y-3">
            <div>Enter a new password, or generate a secure password.</div>
            <div class="flex items-stretch gap-2">
              <input id="ua-reset-input" type="password" class="flex-1 px-3 py-2 rounded border border-slate-600 focus:border-sky-600 focus:outline-none focus:ring-0 bg-slate-800 text-slate-200" placeholder="Enter new password (min 8 chars)">
              <button id="ua-gen-btn" type="button" class="px-3 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded">Generate</button>
            </div>
            <div class="text-xs text-slate-400">Minimum 8 characters. Use a unique password not used elsewhere.</div>
          </div>
          <div class="px-5 py-3 border-t border-slate-700 flex justify-end gap-2">
            <button class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded" data-cmd="submit">Reset password</button>
          </div>
        </div>`;
      function cleanup(){ try { overlay.remove(); } catch(_){} }
      function generate(){ const input = overlay.querySelector('#ua-reset-input'); input.value = generatePassword(); input.type = 'text'; setTimeout(()=>{ try { input.focus(); input.select(); } catch(_){} }, 0); }
      overlay.addEventListener('click', (e)=>{
        const cmdEl = e.target.closest && e.target.closest('[data-cmd]');
        const cmd = cmdEl ? cmdEl.getAttribute('data-cmd') : null;
        if (cmd === 'close' || e.target === overlay) { cleanup(); resolve(null); return; }
        if (cmd === 'submit') {
          const val = overlay.querySelector('#ua-reset-input').value || '';
          cleanup(); resolve({ password: val });
          return;
        }
      });
      overlay.querySelector('#ua-gen-btn')?.addEventListener('click', generate);
      document.body.appendChild(overlay);
      overlay.querySelector('#ua-reset-input').focus();
    });
  }

  function alertDialog(title, html){
    return new Promise(resolve => {
      const overlay = document.createElement('div');
      overlay.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60';
      overlay.innerHTML = `
        <div class="bg-slate-800/95 border border-slate-700 rounded-lg shadow-xl w-full max-w-md">
          <div class="px-5 py-4 border-b border-slate-700 flex items-center justify-between"><h3 class="text-slate-200 text-base font-semibold">${title}</h3>
            <button class="text-slate-400 hover:text-slate-200" data-cmd="ok" title="Close">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <div class="px-5 py-4 text-slate-300 text-sm">${html}</div>
          <div class="px-5 py-3 border-t border-slate-700 flex justify-end gap-2">
            <button class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded" data-cmd="ok">OK</button>
          </div>
        </div>`;
      function cleanup(){ try { overlay.remove(); } catch(_){} }
      overlay.addEventListener('click', async (e)=>{ const cmd = e.target.getAttribute && e.target.getAttribute('data-cmd'); if (cmd === 'ok' || e.target === overlay) { cleanup(); resolve(); } });
      // Wire copy button inside, if present
      setTimeout(() => {
        const btn = overlay.querySelector('#ua-copy-btn');
        const tgt = overlay.querySelector('#ua-password-text');
        if (btn && tgt) {
          btn.addEventListener('click', async () => {
            try { await navigator.clipboard.writeText(tgt.textContent || ''); toast('Password copied to clipboard.', 'success'); } catch (_) { toast('Copy failed.', 'error'); }
          });
        }
      }, 0);
      document.body.appendChild(overlay);
    });
  }

  function generatePassword(){
    // 16-char: mix of upper, lower, digits; avoid ambiguous chars
    const lowers = 'abcdefghijkmnopqrstuvwxyz';
    const uppers = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const digits = '23456789';
    const all = lowers + uppers + digits;
    let out = '';
    function pick(set){ return set[Math.floor(Math.random() * set.length)]; }
    // ensure at least one of each
    out += pick(lowers); out += pick(uppers); out += pick(digits);
    for (let i = 0; i < 13; i++) out += pick(all);
    return out;
  }
});


