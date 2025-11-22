(function(){
  function q(id){ return document.getElementById(id); }
  function toast(msg){ try{ var t=q('toast-container'); if(!t){ t=document.createElement('div'); t.id='toast-container'; t.style.position='fixed'; t.style.top='1rem'; t.style.right='1rem'; t.style.zIndex='9999'; document.body.appendChild(t);} var el=document.createElement('div'); el.className='mt-2 rounded-xl bg-white/5 ring-1 ring-white/10 px-4 py-3 text-sm text-white/80'; el.textContent=msg; t.appendChild(el); setTimeout(function(){ try{ el.remove(); }catch(_){ } }, 3000);}catch(_){}}
  function val(id){ var el=q(id); return el ? (el.value||'') : ''; }
  function bool(id){ var el=q(id); return !!(el && el.checked); }

  function validate(){
    var ok=true;
    var p=val('tx-prefix'); if(p && !/^[A-Za-z0-9\-_.]{0,16}$/.test(p)){ ok=false; }
    var btn=q('tax-btn-save'); if(btn){ btn.disabled=!ok; }
    return ok;
  }

  function payload(){
    var cents=Math.round(parseFloat(val('tx-writeoff')||'0')*100)||0;
    return {
      tax_mode: {
        stripe_tax_enabled: bool('tx-stripe-tax'),
        default_tax_behavior: val('tx-tax-behavior')||'exclusive',
        respect_exemption: bool('tx-respect-exemption')
      },
      registrations: {
        business_address: { line1:'', line2:'', city:'', state:'', postal:'', country:'CA' }
      },
      invoice_presentation: {
        invoice_prefix: val('tx-prefix')||'',
        footer_md: val('tx-footer-md')||'',
        show_logo: bool('tx-show-logo'),
        show_legal_override: bool('tx-show-legal'),
        legal_name_override: val('tx-legal-name')||'',
        payment_terms: val('tx-terms')||'due_immediately',
        show_qty_x_price: bool('tx-show-qtyxprice')
      },
      credit_notes: {
        allow_partial: bool('tx-allow-partial'),
        allow_negative_lines: bool('tx-allow-negative'),
        default_reason: val('tx-credit-reason')||'customer_request'
      },
      rounding: {
        rounding_mode: val('tx-rounding')||'bankers_rounding',
        writeoff_threshold_cents: cents
      }
    };
  }

  function save(){
    if(!validate()){ toast('Fix validation errors.'); return; }
    var token=(q('eb-token')?.value)||'';
    var body=new URLSearchParams();
    body.set('token', token);
    body.set('payload', JSON.stringify(payload()));
    fetch('index.php?m=eazybackup&a=ph-settings-tax-save', { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body: body.toString() })
      .then(function(r){ return r.json(); })
      .then(function(j){ if(j && j.status==='success'){ toast('Settings saved.'); setTimeout(function(){ location.reload(); }, 400); } else { toast((j && (j.message||'Save failed')) || 'Save failed'); } })
      .catch(function(){ toast('Network error.'); });
  }

  // Registrations UI
  function openReg(){ q('tx-reg-id').value=''; q('tx-reg-country').value=''; q('tx-reg-region').value=''; q('tx-reg-number').value=''; q('tx-reg-legal').value=''; q('tx-reg-modal').classList.remove('hidden'); }
  function closeReg(){ q('tx-reg-modal').classList.add('hidden'); }
  function refreshRegs(){ fetch('index.php?m=eazybackup&a=ph-tax-registrations').then(function(r){ return r.json(); }).then(function(j){ if(!j||j.status!=='success') return; var tb=document.getElementById('tx-reg-tbody'); if(!tb) return; tb.innerHTML=''; (j.data||[]).forEach(function(r){ var tr=document.createElement('tr'); tr.className='hover:bg-white/5'; tr.setAttribute('data-id', r.id); tr.innerHTML='<td class="px-4 py-3">'+(r.country||'')+'</td><td class="px-4 py-3">'+(r.region||'-')+'</td><td class="px-4 py-3">'+(r.registration_number||'')+'</td><td class="px-4 py-3">'+(r.legal_name||'-')+'</td><td class="px-4 py-3 text-right"><button class="tx-del rounded-lg px-3 py-1.5 ring-1 ring-white/10 hover:bg-white/10">Delete</button></td>'; tb.appendChild(tr); }); }); }
  function upsertReg(){
    var token=(q('eb-token')?.value)||'';
    var body=new URLSearchParams();
    body.set('token', token);
    body.set('id', q('tx-reg-id').value||'');
    body.set('country', (q('tx-reg-country').value||'').toUpperCase());
    body.set('region', (q('tx-reg-region').value||'').toUpperCase());
    body.set('registration_number', q('tx-reg-number').value||'');
    body.set('legal_name', q('tx-reg-legal').value||'');
    fetch('index.php?m=eazybackup&a=ph-tax-registration-upsert', { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body: body.toString() })
      .then(function(r){ return r.json(); }).then(function(j){ if(j && j.status==='success'){ toast('Saved.'); closeReg(); refreshRegs(); } else { toast((j && (j.message||'Save failed')) || 'Save failed'); } })
      .catch(function(){ toast('Network error.'); });
  }
  function deleteReg(id){ var token=(q('eb-token')?.value)||''; var b=new URLSearchParams(); b.set('token', token); b.set('id', id); fetch('index.php?m=eazybackup&a=ph-tax-registration-delete', { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body: b.toString() }).then(function(r){ return r.json(); }).then(function(j){ if(j && j.status==='success'){ toast('Deleted.'); refreshRegs(); } else { toast((j && (j.message||'Delete failed')) || 'Delete failed'); } }).catch(function(){ toast('Network error.'); }); }

  // Events
  q('tax-btn-save')?.addEventListener('click', save);
  q('tax-btn-preview')?.addEventListener('click', function(){ q('tx-preview-modal').classList.remove('hidden'); });
  document.querySelectorAll('[data-tx-prev-close]')?.forEach(function(el){ el.addEventListener('click', function(){ q('tx-preview-modal').classList.add('hidden'); }); });
  q('tx-prefix')?.addEventListener('input', validate);
  q('tx-btn-add-reg')?.addEventListener('click', function(){ openReg(); });
  q('tx-reg-save')?.addEventListener('click', upsertReg);
  document.getElementById('tx-reg-tbody')?.addEventListener('click', function(e){ var t=e.target; if(t && t.classList.contains('tx-del')){ var tr=t.closest('tr'); var id=tr && tr.getAttribute('data-id'); if(id){ deleteReg(id); } } });
  document.querySelectorAll('[data-tx-close]')?.forEach(function(el){ el.addEventListener('click', closeReg); });

  validate();
})();


