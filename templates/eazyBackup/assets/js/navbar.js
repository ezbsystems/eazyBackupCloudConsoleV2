(function(){
  function applyTheme(isDark){
    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.classList.toggle('light', !isDark);
    try { localStorage.theme = isDark ? 'dark':'light'; } catch(e){}
    document.querySelectorAll('.js-theme-toggle').forEach(function(btn){
      btn.setAttribute('aria-pressed', String(isDark));
    });
  }
  var initDark = (function(){
    try {
      if (localStorage.theme) { return localStorage.theme==='dark'; }
    } catch(e){}
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  })();
  applyTheme(initDark);

  document.addEventListener('click', function(e){
    var t = e.target.closest('.js-theme-toggle');
    if(!t) return;
    applyTheme(!document.documentElement.classList.contains('dark'));
  });

  // Command-K registry (extend as needed)
  window.NAV_ROUTES = window.NAV_ROUTES || [
    {label:'Dashboard', href: (window.WEB_ROOT || '') + '/index.php?m=eazybackup&a=dashboard'},
    {label:'My Services', href: (window.WEB_ROOT || '') + '/clientarea.php?action=services'},
    {label:'Billing', href: (window.WEB_ROOT || '') + '/clientarea.php?action=invoices'},
    {label:'Support Tickets', href: (window.WEB_ROOT || '') + '/supporttickets.php'}
  ];

  // Roving tabindex helper
  function rove(container){
    var items = Array.prototype.slice.call(container.querySelectorAll('[role="menuitem"], a, button')).filter(function(el){ return !el.hasAttribute('disabled'); });
    var idx = 0;
    container.addEventListener('keydown', function(e){
      if(e.key==='ArrowDown') { e.preventDefault(); idx=(idx+1)%items.length; items[idx].focus(); }
      else if(e.key==='ArrowUp') { e.preventDefault(); idx=(idx-1+items.length)%items.length; items[idx].focus(); }
    });
  }
  document.querySelectorAll('[data-rove]').forEach(rove);
})();


