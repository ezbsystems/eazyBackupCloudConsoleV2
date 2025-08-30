;(function(){
  if (window.ebShowLoader && window.ebHideLoader) return;
  function ensureStyles(){
    // No-op: Tailwind classes used; keep hook if we need custom CSS later
  }
  function makeOverlay(message){
    const wrap = document.createElement('div');
    wrap.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/60';
    wrap.setAttribute('data-eb-loader', '1');
    wrap.innerHTML = `
      <div class="flex flex-col items-center gap-3 px-6 py-4 rounded-lg border border-slate-700 bg-slate-900/90 shadow-xl">
        <span class="inline-flex h-10 w-10 rounded-full border-2 border-sky-500 border-t-transparent animate-spin"></span>
        ${message ? `<div class="text-slate-200 text-sm">${message}</div>` : ''}
      </div>
    `;
    return wrap;
  }
  window.ebShowLoader = function(targetEl, message){
    try {
      ensureStyles();
      const host = targetEl || document.body;
      let overlay = host.querySelector(':scope > [data-eb-loader="1"]');
      if (!overlay) {
        overlay = makeOverlay(message);
        // Position relative container if needed
        const computed = getComputedStyle(host);
        if (computed.position === 'static') {
          host.classList.add('relative');
        }
        host.appendChild(overlay);
      } else if (message) {
        const msg = overlay.querySelector('div.text-slate-200');
        if (msg) msg.textContent = message;
      }
      return overlay;
    } catch (_) {}
  };
  window.ebHideLoader = function(targetEl){
    try {
      const host = targetEl || document.body;
      const overlay = host.querySelector(':scope > [data-eb-loader="1"]');
      if (overlay) overlay.remove();
    } catch (_) {}
  };
})();


