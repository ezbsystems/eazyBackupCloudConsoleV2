(function(){
  const $ = (s,ctx)=> (ctx||document).querySelector(s);
  const $$ = (s,ctx)=> Array.from((ctx||document).querySelectorAll(s));
  const open = el => el.classList.remove('hidden');
  const close = el => el.classList.add('hidden');

  const modulelink = (function(){
    const a = document.querySelector('a[href*="index.php?m=eazybackup"]');
    return a ? a.href.split('&a=')[0] : 'index.php?m=eazybackup';
  })();

  const createModal = $('#eb-create-plan-modal');
  const addComponentModal = $('#eb-add-component-modal');
  const assignModal = $('#eb-assign-plan-modal');

  const openCreate = $('#eb-open-create-plan');
  if (openCreate) openCreate.addEventListener('click', ()=> open(createModal));
  $$('#eb-create-plan-modal [data-eb-close]').forEach(b=>b.addEventListener('click', ()=> close(createModal)));

  $$('[data-eb-open-add-component]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      $('#eb-component-plan-id').value = btn.getAttribute('data-eb-open-add-component');
      open(addComponentModal);
    });
  });
  $$('#eb-add-component-modal [data-eb-close]').forEach(b=>b.addEventListener('click', ()=> close(addComponentModal)));

  $$('[data-eb-open-assign]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      $('#eb-assign-plan-id').value = btn.getAttribute('data-eb-open-assign');
      open(assignModal);
    });
  });
  $$('#eb-assign-plan-modal [data-eb-close]').forEach(b=>b.addEventListener('click', ()=> close(assignModal)));

  const post = async (action, form) => {
    const body = new FormData(form);
    const res = await fetch(`${modulelink}&a=${action}`, { method:'POST', body });
    return await res.json();
  };

  const createForm = $('#eb-create-plan-form');
  if (createForm) createForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    try {
      const out = await post('ph-plan-template-create', createForm);
      if (out.status === 'success') { window.location.reload(); }
      else { console.warn('[eb.catalog] create plan error', out); alert('Failed to create plan'); }
    } catch (e) { console.error(e); alert('Network error'); }
  });

  const componentForm = $('#eb-add-component-form');
  if (componentForm) componentForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    try {
      const out = await post('ph-plan-component-add', componentForm);
      if (out.status === 'success') { window.location.reload(); }
      else { console.warn('[eb.catalog] add component error', out); alert('Failed to add component'); }
    } catch (e) { console.error(e); alert('Network error'); }
  });

  const assignForm = $('#eb-assign-plan-form');
  if (assignForm) assignForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    try {
      const out = await post('ph-plan-assign', assignForm);
      if (out.status === 'success') { window.location.reload(); }
      else { console.warn('[eb.catalog] assign plan error', out); alert('Failed to create subscription'); }
    } catch (e) { console.error(e); alert('Network error'); }
  });
})();


