{*
  Branding & Hostname — styled similar to console/user-profile.tpl (dark UI)
*}
<div class="bg-gray-800">
  <div class="min-h-screen bg-gray-800 container mx-auto pb-8">
    <div class="flex justify-between items-center h-16 space-y-12 px-2">
      <nav aria-label="breadcrumb">
        <ol class="flex space-x-2 text-gray-300 items-center">
          <li>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
          </li>
          <li><h2 class="text-2xl font-semibold text-white">Branding & Hostname</h2></li>
        </ol>
      </nav>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4 px-2">
      <div class="md:col-span-2 bg-gray-900/50 p-6 rounded-lg">
        <h3 class="text-lg font-semibold text-white mb-4">Branding</h3>
        <form method="post">
          <div class="space-y-3 text-sm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label class="block text-gray-300 mb-1">Product Name</label>
                <input name="product_name" value="{$brand.ProductName|escape}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100"/>
              </div>
              <div>
                <label class="block text-gray-300 mb-1">Company Name</label>
                <input name="company_name" value="{$brand.CompanyName|escape}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100"/>
              </div>
            </div>
            <div>
              <label class="block text-gray-300 mb-1">Help URL</label>
              <input name="help_url" value="{$brand.HelpURL|escape}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100"/>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div>
                <label class="block text-gray-300 mb-1">Header Color</label>
                <input name="header_color" value="{$brand.HeaderColor|escape}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100"/>
              </div>
              <div>
                <label class="block text-gray-300 mb-1">Accent Color</label>
                <input name="accent_color" value="{$brand.AccentColor|escape}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100"/>
              </div>
              <div>
                <label class="block text-gray-300 mb-1">Tile Background</label>
                <input name="tile_background" value="{$brand.TileBackground|escape}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100"/>
              </div>
            </div>
          </div>

          <h3 class="text-lg font-semibold text-white mt-6 mb-3">Email Options</h3>
          <div class="space-y-3 text-sm">
            <label class="inline-flex items-center gap-2 text-slate-200"><input type="checkbox" name="use_parent_mail" value="1" {if $email.inherit|default:1}checked{/if}/> Use parent mail server</label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label class="block text-gray-300 mb-1">From Name</label>
                <input name="smtp_sendas_name" value="{$email.FromName|escape}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100"/>
              </div>
              <div>
                <label class="block text-gray-300 mb-1">From Email</label>
                <input name="smtp_sendas_email" value="{$email.FromEmail|escape}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100"/>
              </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
              <div>
                <label class="block text-gray-300 mb-1">SMTP Server</label>
                <input name="smtp_server" value="{$email.SMTP.server|escape}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100"/>
              </div>
              <div>
                <label class="block text-gray-300 mb-1">Port</label>
                <input name="smtp_port" value="{$email.SMTP.port|escape}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100"/>
              </div>
              <div>
                <label class="block text-gray-300 mb-1">Username</label>
                <input name="smtp_username" value="{$email.SMTP.username|escape}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100"/>
              </div>
              <div>
                <label class="block text-gray-300 mb-1">Password</label>
                <input name="smtp_password" value="{$email.SMTP.password|escape}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100"/>
              </div>
            </div>
            <div>
              <label class="block text-gray-300 mb-1">Security</label>
              <input name="smtp_security" value="{$email.SMTP.security|escape}" class="w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 py-2 text-slate-100"/>
            </div>
          </div>

          <div class="flex justify-end mt-6">
            <button type="submit" class="rounded bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Save</button>
          </div>
        </form>
      </div>

      <div class="space-y-6">
        <div class="bg-gray-900/50 p-6 rounded-lg">
          <h3 class="text-lg font-semibold text-white mb-3">Hostname</h3>
          <div class="text-sm text-slate-300">Primary: <span class="font-mono">{$tenant.fqdn}</span></div>
          {if $tenant.custom_domain}
            <div class="text-sm text-slate-300">Custom: <span class="font-mono">{$tenant.custom_domain}</span></div>
          {/if}
        </div>

        <div class="bg-gray-900/50 p-6 rounded-lg">
          <h3 class="text-lg font-semibold text-white mb-3">Status</h3>
          <div class="text-sm text-slate-300">{$tenant.status|escape}</div>
          <div id="wl-timeline" class="mt-3 space-y-1 text-xs text-slate-400"></div>
          <script>
          (async function(){
            try{
              const res = await fetch('{$modulelink}&a=whitelabel-status&id={$tenant.id}');
              const j = await res.json();
              if(!j||!j.ok) return;
              const el = document.getElementById('wl-timeline');
              if(!el) return;
              el.innerHTML = (j.timeline||[]).map(function(s){
                return '<div><span>' + (s.label||'') + '</span> — <span>' + (s.status||'') + '</span></div>';
              }).join('');
            }catch(e){}
          })();
          </script>
        </div>
      </div>
    </div>
  </div>
</div>


