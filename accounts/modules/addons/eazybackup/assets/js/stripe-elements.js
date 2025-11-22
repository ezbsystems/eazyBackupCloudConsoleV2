// Minimal Stripe Elements integration for Add Card
(function(){
  if (!window.Stripe) return;
  async function addCard(opts){
    const form = opts.form;
    const customerId = opts.customerId;
    const resp = await fetch(opts.endpoint, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({ customer_id: String(customerId) })});
    const j = await resp.json();
    if (j.status !== 'success') throw new Error(j.message||'SetupIntent failed');
    const stripe = Stripe(j.publishable);
    const elements = stripe.elements({appearance:{theme:'night'}});
    const card = elements.create('card');
    const mountEl = document.getElementById('eb-card');
    if (!mountEl) throw new Error('Missing #eb-card mount');
    card.mount('#eb-card');
    form.addEventListener('submit', async function(e){
      e.preventDefault();
      const result = await stripe.confirmCardSetup(j.client_secret, { payment_method: { card } });
      if (result.error) {
        alert(result.error.message||'Card error');
        return;
      }
      // Server will see default PM via future invoice; we can refresh UI or redirect
      window.location.reload();
    });
  }
  window.EBStripe = { addCard };
})();
