(function(){
  function q(id){ return document.getElementById(id); }
  function toast(msg){
    try{
      var t=q('toast-container');
      if(!t){ t=document.createElement('div'); t.id='toast-container'; t.style.position='fixed'; t.style.top='1rem'; t.style.right='1rem'; t.style.zIndex='9999'; document.body.appendChild(t); }
      var el=document.createElement('div'); el.className='mt-2 rounded-xl bg-white/5 ring-1 ring-white/10 px-4 py-3 text-sm text-white/80'; el.textContent=msg; t.appendChild(el); setTimeout(function(){ try{ el.remove(); }catch(_){ } }, 3000);
    }catch(_){ }
  }
  function dbg(msg){ try{ console.debug('[EB] '+msg); }catch(_){ } }

  function init(){
    var form = q('eb-profile');
    var btn  = q('eb-profile-save');
    if(!form || !btn){ dbg('profile init missing nodes'); return; }
    var endpoint = (function(){
      // Prefer server-provided endpoint; fallback build from modulelink embedded in Back link
      var hidden = form.querySelector('input[name="eb_endpoint"]');
      if (hidden && hidden.value) return hidden.value;
      try{
        var back = document.querySelector('a[href*="&a=ph-clients"]');
        if(back){
          var ml = back.getAttribute('href').split('&a=')[0];
          return ml + '&a=ph-client-profile-update';
        }
      }catch(_){ }
      return 'index.php?m=eazybackup&a=ph-client-profile-update';
    })();

    function doSave(){
      dbg('doSave start ' + endpoint);
      try{
        var body = new URLSearchParams(new FormData(form));
        fetch(endpoint, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body
        }).then(function(r){ dbg('resp '+r.status); return r.json(); })
          .then(function(j){ dbg('json ' + JSON.stringify(j||{})); if(j && j.status==='success'){ toast('Profile saved.'); setTimeout(function(){ location.reload(); }, 400); } else { toast((j && j.message) || 'Save failed'); } })
          .catch(function(e){ dbg('error '+(e && e.message || '')); toast('Save failed'); });
      }catch(e){ dbg('exception '+(e && e.message || '')); toast('Save failed'); }
    }

    btn.addEventListener('click', function(e){ e.preventDefault(); doSave(); });
    form.addEventListener('submit', function(e){ e.preventDefault(); doSave(); });
    dbg('profile init ok');
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
  else { init(); }
})();


