(function(){
  function q(id){ return document.getElementById(id); }
  function val(id){ var el=q(id); return el ? (el.value||'') : ''; }
  function bool(id){ var el=q(id); return !!(el && el.checked); }
  function setClass(el, add, cls){ if(!el) return; if(add){ el.classList.add(cls); } else { el.classList.remove(cls); } }
  function toast(msg){
    try{
      var t=q('toast-container');
      if(!t){ t=document.createElement('div'); t.id='toast-container'; t.style.position='fixed'; t.style.top='1rem'; t.style.right='1rem'; t.style.zIndex='9999'; document.body.appendChild(t); }
      var el=document.createElement('div'); el.className='mt-2 rounded-xl bg-white/5 ring-1 ring-white/10 px-4 py-3 text-sm text-white/80'; el.textContent=msg; t.appendChild(el); setTimeout(function(){ try{ el.remove(); }catch(_){ } }, 3000);
    }catch(_){ }
  }

  function uppercaseDescriptor(){
    var el=q('sc-descriptor'); if(!el) return;
    var s=(el.value||''); var up=s.toUpperCase(); if(up!==s){ el.value=up; }
  }

  function validate(){
    var ok=true;
    var desc=q('sc-descriptor'); var help=q('sc-descriptor-help');
    if(desc){
      var s=(desc.value||'').trim().toUpperCase(); desc.value=s;
      var re=/^[A-Z0-9 ]{0,22}$/;
      var valid=re.test(s);
      ok = ok && valid;
      if(help){ help.classList.toggle('text-rose-300', !valid); help.classList.toggle('text-white/50', valid); }
    }
    var cur=q('sc-default-currency'); if(cur){ cur.value=(cur.value||'').trim().toUpperCase(); if(cur.value.length!==3){ ok=false; } }
    var retry=q('sc-retry-schedule'); if(retry){ var parts=(retry.value||'').split(',').map(function(v){return v.trim();}).filter(Boolean); if(parts.length<3||parts.length>5){ ok=false; } }
    var btn=q('sc-btn-save'); if(btn){ btn.disabled=!ok; }
    return ok;
  }

  function buildPayload(){
    // Retry schedule parse to int array
    var retryRaw=(val('sc-retry-schedule')||'');
    var retry=retryRaw.split(',').map(function(v){ return parseInt(v.trim(),10); }).filter(function(n){ return !isNaN(n); });
    return {
      checkout_experience: {
        require_billing_address: val('sc-address-require')||'postal_code',
        collect_tax_id: bool('sc-collect-tax-id'),
        statement_descriptor: (val('sc-descriptor')||'').toUpperCase().slice(0,22),
        support_url: val('sc-support-url')||'',
        default_currency: (val('sc-default-currency')||'CAD').toUpperCase()
      },
      payment_methods: {
        cards: bool('sc-accept-cards'),
        bank_debits: bool('sc-accept-bank-debits'),
        apple_google_pay: bool('sc-apple-google-pay'),
        retry_mandate_bank_debits: bool('sc-retry-mandate-bank-debits')
      },
      trials_proration: {
        default_trial_days: Math.max(0, parseInt(val('sc-trial-days')||'0',10)||0),
        proration_behavior: val('sc-proration')||'prorate_now',
        end_trial_on_usage: bool('sc-end-trial-on-usage')
      },
      dunning_collections: {
        retry_schedule_days: retry.length?retry:[0,3,7,14],
        send_payment_failed_email: bool('sc-send-failed-email'),
        auto_pause_after_attempts: (function(){ var v=val('sc-auto-pause-attempts').trim(); return v===''?null:(parseInt(v,10)||0); })(),
        auto_cancel_after_days: (function(){ var v=val('sc-auto-cancel-days').trim(); return v===''?null:(parseInt(v,10)||0); })(),
        take_past_due_on_next_success: bool('sc-take-past-due-on-success')
      },
      customer_portal: {
        enabled: bool('sc-portal-enabled'),
        allow_update_payment: bool('sc-portal-update-payment'),
        allow_view_invoices: bool('sc-portal-view-invoices'),
        allow_cancel: bool('sc-portal-cancel'),
        allow_resume: bool('sc-portal-resume'),
        return_url: val('sc-portal-return-url')||''
      }
    };
  }

  function showCurrencyConfirm(){ var m=q('sc-currency-modal'); if(m){ m.classList.remove('hidden'); } }
  function hideCurrencyConfirm(){ var m=q('sc-currency-modal'); if(m){ m.classList.add('hidden'); } }

  function submit(force){
    if(!validate()) { toast('Please fix validation errors.'); return; }
    var token=(q('eb-token')?.value)||'';
    var body=new URLSearchParams();
    body.set('token', token);
    body.set('payload', JSON.stringify(buildPayload()));
    if(force===true){ body.set('force','1'); }
    fetch('index.php?m=eazybackup&a=ph-settings-checkout-save', {
      method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body: body.toString()
    }).then(function(r){ return r.json(); }).then(function(j){
      if(j && j.status==='success'){ toast('Settings saved.'); setTimeout(function(){ location.reload(); }, 400); return; }
      if(j && j.status==='confirm_required' && j.code==='currency_conflict'){ showCurrencyConfirm(); return; }
      toast((j && (j.message||'Save failed')) || 'Save failed');
    }).catch(function(){ toast('Network error.'); });
  }

  // Events
  q('sc-descriptor')?.addEventListener('input', function(){ uppercaseDescriptor(); validate(); });
  q('sc-default-currency')?.addEventListener('input', validate);
  q('sc-retry-schedule')?.addEventListener('input', validate);
  q('sc-btn-save')?.addEventListener('click', function(){ submit(false); });
  q('sc-currency-confirm')?.addEventListener('click', function(){ hideCurrencyConfirm(); submit(true); });
  document.querySelectorAll('[data-sc-modal-close]')?.forEach(function(el){ el.addEventListener('click', hideCurrencyConfirm); });

  // Bank debit capability warn toggle
  var warn=q('sc-warn-bank');
  var capBank = warn ? (String(warn.getAttribute('data-cap-bank')||'0')==='1') : false;
  q('sc-accept-bank-debits')?.addEventListener('change', function(){
    if(!warn) return; var on=this.checked===true; warn.classList.toggle('hidden', !(on && !capBank));
  });

  // Initial validation
  uppercaseDescriptor(); validate();
})();


