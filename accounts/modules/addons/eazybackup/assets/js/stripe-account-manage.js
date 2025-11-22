// Stripe Connect embedded account management (CSP-safe)
(function(){
  document.addEventListener('DOMContentLoaded', async function(){
    try {
      var mount = document.getElementById('stripe-embedded-account');
      if (!mount) return;
      var t0 = (window.performance && performance.now) ? performance.now() : Date.now();
      var log = function(event, meta){
        try {
          var payload = { area: 'eb.stripeManage', event: event, meta: meta || {}, ts: Date.now() };
          if (window.console && console.log) console.log('[eb.stripeManage]', event, payload);
          window.EBStripeManage = window.EBStripeManage || {}; window.EBStripeManage.last = payload;
        } catch(_) {}
      };
      log('init');
      var cleanup = null;
      try { if (window.ebShowLoader) { cleanup = window.ebShowLoader(mount, 'Loading Stripe Account…'); log('loader_shown'); } } catch(_) {}

      var connectLink = mount.getAttribute('data-connect-link') || '#';
      var manageLink = mount.getAttribute('data-manage-link') || '#';
      async function ensureStripe(){
        if (window.Stripe) return window.Stripe;
        return new Promise(function(resolve, reject){
          var existing = document.querySelector('script[src="https://js.stripe.com/v3"]');
          if (existing) {
            existing.addEventListener('load', function(){ try { log('stripe_js_loaded'); } catch(_){} resolve(window.Stripe); });
            existing.addEventListener('error', function(){ try { log('stripe_js_error'); } catch(_){} reject(new Error('Stripe.js failed to load')); });
            return;
          }
          var s = document.createElement('script');
          s.src = 'https://js.stripe.com/v3';
          s.async = true;
          s.onload = function(){ try { log('stripe_js_loaded'); } catch(_){} resolve(window.Stripe); };
          s.onerror = function(){ try { log('stripe_js_error'); } catch(_){} reject(new Error('Stripe.js failed to load')); };
          document.head.appendChild(s);
        });
      }
      async function ensureConnectJs(){
        if (window.StripeConnect && typeof window.StripeConnect.init === 'function') return window.StripeConnect;
        return new Promise(function(resolve, reject){
          var existing = document.querySelector('script[src="https://connect-js.stripe.com/v1.0/connect.js"]');
          function done(){ resolve(window.StripeConnect); }
          function fail(){ reject(new Error('Connect.js failed to load')); }
          if (existing) {
            if (window.StripeConnect && typeof window.StripeConnect.init === 'function') { resolve(window.StripeConnect); return; }
            existing.addEventListener('load', done);
            existing.addEventListener('error', fail);
          } else {
            var s = document.createElement('script');
            s.src = 'https://connect-js.stripe.com/v1.0/connect.js';
            s.async = true;
            s.onload = done;
            s.onerror = fail;
            document.head.appendChild(s);
          }
        });
      }
      var endpoint = mount.getAttribute('data-endpoint');
      if (!endpoint) {
        if (cleanup) { try { if (typeof cleanup === 'function') cleanup(); else if (cleanup && cleanup.remove) cleanup.remove(); } catch(_) {} }
        mount.innerHTML = '<div class="rounded-xl bg-rose-500/10 ring-1 ring-rose-400/20 px-4 py-3 text-sm text-rose-300">Missing account session endpoint.</div>';
        log('session_endpoint_missing');
        return;
      }

      var resp, data = {};
      try {
        log('session_request_start');
        resp = await fetch(endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: '' });
        data = await resp.json();
        log('session_request_success');
      } catch (e) {
        if (cleanup) { try { if (typeof cleanup === 'function') cleanup(); else if (cleanup && cleanup.remove) cleanup.remove(); } catch(_) {} }
        mount.innerHTML = '<div class="rounded-xl bg-rose-500/10 ring-1 ring-rose-400/20 px-4 py-3 text-sm text-rose-300">Network error while contacting Stripe. Please try again.</div>';
        log('session_request_error', { message: (e && e.message) || 'network_error' });
        return;
      }

      if (!data || data.status !== 'success') {
        if (data && data.message === 'not_connected') {
          if (cleanup) { try { if (typeof cleanup === 'function') cleanup(); else if (cleanup && cleanup.remove) cleanup.remove(); } catch(_) {} }
          mount.innerHTML = '<div class="rounded-xl bg-amber-500/10 ring-1 ring-amber-400/20 px-4 py-3 text-sm text-amber-200">Your Stripe account is not connected. <a href="' + connectLink + '" class="underline">Go to Connect &amp; Status</a>.</div>';
          log('not_connected');
          return;
        }
        if (cleanup) { try { if (typeof cleanup === 'function') cleanup(); else if (cleanup && cleanup.remove) cleanup.remove(); } catch(_) {} }
        mount.innerHTML = '<div class="rounded-xl bg-rose-500/10 ring-1 ring-rose-400/20 px-4 py-3 text-sm text-rose-300">Unable to start account management. Please refresh and try again.</div>';
        log('session_invalid_response', { status: data && data.status });
        return;
      }
      if (!data.client_secret || !data.publishable) {
        if (cleanup) { try { if (typeof cleanup === 'function') cleanup(); else if (cleanup && cleanup.remove) cleanup.remove(); } catch(_) {} }
        mount.innerHTML = '<div class="rounded-xl bg-rose-500/10 ring-1 ring-rose-400/20 px-4 py-3 text-sm text-rose-300">Invalid session response from Stripe.</div>';
        log('session_missing_fields');
        return;
      }

      // Prefer Connect.js embedded component per Stripe docs
      var embeddedMounted = false;
      try {
        await ensureConnectJs();
        if (window.StripeConnect && typeof window.StripeConnect.init === 'function') {
          var connectInstance = window.StripeConnect.init({
            publishableKey: data.publishable,
            fetchClientSecret: async function(){
              try {
                var r = await fetch(endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: '' });
                var j = await r.json();
                if (j && j.status === 'success' && j.client_secret) { return j.client_secret; }
              } catch(_) {}
              throw new Error('Failed to refresh account session');
            },
          });
          // Try likely component names
          var comp = null;
          try { comp = connectInstance.create('account-management'); } catch(_) {}
          if (!comp) { try { comp = connectInstance.create('accountManagement'); } catch(_) {} }
          if (comp) {
            // Optionally wire load/error handlers if present
            try { if (typeof comp.setOnLoadError === 'function') comp.setOnLoadError(function(e){ log('connect_load_error', { type: e && e.error && e.error.type, message: e && e.error && e.error.message }); }); } catch(_) {}
            try { if (typeof comp.setOnLoaderStart === 'function') comp.setOnLoaderStart(function(){ log('connect_loader_start'); }); } catch(_) {}
            mount.appendChild(comp);
            embeddedMounted = true;
          }
        }
      } catch(_) { /* ignore and fallback */ }

      if (!embeddedMounted) {
        // Fallback to older Stripe.js approach (unlikely to work for Connect embedded components)
        try {
          await ensureStripe();
          var stripe = window.Stripe(data.publishable);
          var elm = null;
          if (stripe && typeof stripe.create === 'function') {
            elm = stripe.create('accountManagement', { clientSecret: data.client_secret });
          } else if (stripe && typeof stripe.accountManagement === 'function') {
            elm = stripe.accountManagement({ clientSecret: data.client_secret });
          }
          if (elm && typeof elm.mount === 'function') {
            elm.mount('#stripe-embedded-account');
            embeddedMounted = true;
          }
        } catch(_) { /* ignore */ }
      }

      if (!embeddedMounted) {
        if (cleanup) { try { if (typeof cleanup === 'function') cleanup(); else if (cleanup && cleanup.remove) cleanup.remove(); } catch(_) {} }
        log('component_missing');
        mount.innerHTML = '<div class="space-y-2">'
          + '<div class="rounded-xl bg-rose-500/10 ring-1 ring-rose-400/20 px-4 py-3 text-sm text-rose-300">Unable to load Stripe account management component.</div>'
          + '<div class="rounded-xl bg-white/5 ring-1 ring-white/10 px-4 py-3 text-sm text-white/70">You can manage your Stripe account in the Stripe Dashboard: '
          + '<a class="underline" href="' + manageLink + '">Open Stripe Dashboard</a>.</div>'
          + '</div>';
        return;
      }
      if (cleanup) { try { if (typeof cleanup === 'function') cleanup(); else if (cleanup && cleanup.remove) cleanup.remove(); } catch(_) {} }
      var t1 = (window.performance && performance.now) ? performance.now() : Date.now();
      log('mount_success', { elapsedMs: (t1 - t0) });
    } catch (e) {
      try {
        var mount = document.getElementById('stripe-embedded-account');
        if (mount) { mount.innerHTML = '<div class=\"rounded-xl bg-rose-500/10 ring-1 ring-rose-400/20 px-4 py-3 text-sm text-rose-300\">An unexpected error occurred. Please refresh the page.</div>'; }
        try { if (window.console && console.error) console.error('[eb.stripeManage] unexpected_error', e); } catch(_) {}
      } catch(_) {}
    }
  });
})();


