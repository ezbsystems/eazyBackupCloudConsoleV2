/**
 * User Actions JS
 * 
 * This file previously contained the password reset modal logic.
 * That functionality has been moved to an Alpine.js-based slide drawer
 * in user-profile.tpl for better UX and consistency with the design system.
 * 
 * The eb-reset-password event is now handled by the resetPasswordDrawer()
 * Alpine component in the template.
 */

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

  // Note: Password reset is now handled by the Alpine drawer in user-profile.tpl
  // The eb-reset-password event is dispatched from the Actions menu and caught by the drawer

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

  // Export generatePassword for potential use elsewhere
  window.ebGeneratePassword = generatePassword;
});


