(function(){
  function q(id){ return document.getElementById(id); }
  function toast(msg, type){
    try{
      type = type || 'info';
      var variant = 'info';
      if(type === 'success') variant = 'success';
      else if(type === 'error' || type === 'danger') variant = 'danger';
      else if(type === 'warning') variant = 'warning';
      var t=q('toast-container');
      if(!t){
        t=document.createElement('div');
        t.id='toast-container';
        t.className='pointer-events-none fixed right-4 top-4 z-[9999] flex flex-col items-end gap-2';
        document.body.appendChild(t);
      }
      var el=document.createElement('div');
      el.className='pointer-events-auto eb-toast eb-toast--' + variant;
      el.setAttribute('role','status');
      el.textContent=msg;
      t.appendChild(el);
      setTimeout(function(){ try{ el.remove(); }catch(_){ } }, 3000);
    }catch(_){}
  }
  function val(id){ var el=q(id); return el ? (el.value||'') : ''; }
  function bool(id){ var el=q(id); return !!(el && el.checked); }

  function validate(){
    var ok=true;
    var email=/^[^@\s]+@[^@\s]+\.[^@\s]+$/;
    var fa=val('eml-from-address'); if(fa && !email.test(fa)){ ok=false; }
    var rt=val('eml-reply-to'); if(rt && !email.test(rt)){ ok=false; }
    var mode=(q('eml-smtp-mode')?.value)||'builtin';
    if(mode!=='builtin'){
      if(!val('eml-smtp-host')) ok=false;
      var p=parseInt(val('eml-smtp-port')||'0',10)||0; if(p<=0) ok=false;
    }
    var btn=q('eml-btn-save'); if(btn){ btn.disabled=!ok; }
    return ok;
  }

  function collectTemplates(){
    var out={};
    document.querySelectorAll('.eml-subject').forEach(function(inp){ var k=inp.getAttribute('data-key'); if(!out[k]) out[k]={}; out[k].subject=inp.value||''; });
    document.querySelectorAll('.eml-body').forEach(function(inp){ var k=inp.getAttribute('data-key'); if(!out[k]) out[k]={}; out[k].body_md=inp.value||''; });
    return out;
  }

  function payload(){
    var cc=(val('eml-cc-finance')||'').split(',').map(function(s){ return s.trim(); }).filter(Boolean);
    return {
      sender: {
        from_name: val('eml-from-name')||'',
        from_address: val('eml-from-address')||'',
        reply_to: val('eml-reply-to')||'',
        cc_finance: cc,
        brand: { header_image: val('eml-header-img')||'', primary_color: val('eml-primary-color')||'#1B2C50' }
      },
      smtp: {
        mode: (q('eml-smtp-mode')?.value)||'builtin',
        host: val('eml-smtp-host')||'',
        port: parseInt(val('eml-smtp-port')||'587',10)||587,
        username: val('eml-smtp-user')||'',
        password_enc: val('eml-smtp-pass')||'',
        allow_unencrypted: false
      },
      templates: collectTemplates(),
      stripe_emails: {
        send_invoices: bool('eml-stripe-invoices'),
        send_receipts: bool('eml-stripe-receipts'),
        bcc_msp_on_invoices: bool('eml-bcc-msp')
      }
    };
  }

  function save(){
    if(!validate()){ toast('Fix validation errors.', 'warning'); return; }
    var token=(q('eb-token')?.value)||'';
    var body=new URLSearchParams(); body.set('token', token); body.set('payload', JSON.stringify(payload()));
    fetch('index.php?m=eazybackup&a=ph-settings-email-save', { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body: body.toString() })
      .then(function(r){ return r.json(); }).then(function(j){ if(j && j.status==='success'){ toast('Settings saved.', 'success'); setTimeout(function(){ location.reload(); }, 400); } else { toast((j && (j.message||'Save failed')) || 'Save failed', 'danger'); } })
      .catch(function(){ toast('Network error.', 'danger'); });
  }

  function mdPreview(md){
    var h=md.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    h=h.replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>').replace(/\*(.+?)\*/g,'<em>$1</em>').replace(/\[(.+?)\]\((https?:[^\)]+)\)/g,'<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
    h=h.replace(/\n/g,'<br/>');
    return h;
  }

  function bindPreviews(){
    document.querySelectorAll('.eml-body').forEach(function(ta){
      var key=ta.getAttribute('data-key');
      var pv=document.querySelector('.eml-preview[data-key="'+key+'"]');
      var update=function(){ pv.innerHTML=mdPreview(ta.value||''); };
      ta.addEventListener('input', update); update();
    });
  }

  // Test modal
  function openTest(){ q('eml-test-modal').classList.remove('hidden'); }
  function closeTest(){ q('eml-test-modal').classList.add('hidden'); }
  function sendTest(){
    var token=(q('eb-token')?.value)||'';
    var tpl=(q('eml-test-template')?.value)||'welcome';
    var to=(q('eml-test-to')?.value)||'';
    var b=new URLSearchParams(); b.set('token', token); b.set('template', tpl); b.set('to', to);
    fetch('index.php?m=eazybackup&a=ph-email-test', { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body: b.toString() })
      .then(function(r){ return r.json(); }).then(function(j){ if(j && j.status==='success'){ toast('Test sent.', 'success'); closeTest(); } else { toast((j && (j.message||'Send failed')) || 'Send failed', 'danger'); } })
      .catch(function(){ toast('Network error.', 'danger'); });
  }

  // Restore default
  document.addEventListener('click', function(e){ var t=e.target; if(t && t.classList.contains('eml-restore')){ var key=t.getAttribute('data-key'); var token=(q('eb-token')?.value)||''; var b=new URLSearchParams(); b.set('token', token); b.set('template', key); fetch('index.php?m=eazybackup&a=ph-email-restore-default', { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body: b.toString() }).then(function(r){ return r.json(); }).then(function(j){ if(j && j.status==='success'){ toast('Template restored.', 'success'); setTimeout(function(){ location.reload(); }, 300); } else { toast('Restore failed', 'danger'); } }); }});

  // Events
  q('eml-btn-save')?.addEventListener('click', save);
  q('eml-btn-test')?.addEventListener('click', openTest);
  document.querySelectorAll('[data-eml-close]')?.forEach(function(el){ el.addEventListener('click', closeTest); });
  ['eml-from-address','eml-reply-to','eml-smtp-host','eml-smtp-port'].forEach(function(id){ q(id)?.addEventListener('input', validate); });
  document.addEventListener('eml-settings-validate', validate);
  bindPreviews();
  validate();
})();


