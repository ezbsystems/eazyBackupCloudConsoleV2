{if !defined('WHMCS')} {* guard *}{/if}

<div class="container-fluid">
  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" href="#">Storage</a></li>
    <li class="nav-item"><a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true">Devices</a></li>
    <li class="nav-item"><a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true">Protected Items</a></li>
  </ul>

  <form method="get" class="mb-3 form-inline">
    <input type="hidden" name="module" value="eazybackup"/>
    <input type="hidden" name="action" value="powerpanel"/>
    <input type="hidden" name="view" value="storage"/>

    <div class="form-group mr-2 mb-2">
      <label for="filter-username" class="mr-2">Username</label>
      <input id="filter-username" type="text" class="form-control" name="username" value="{$filters.username|escape}" placeholder="Contains…"/>
    </div>

    <div class="form-group mr-2 mb-2">
      <label for="filter-server" class="mr-2">Comet Server</label>
      <select id="filter-server" class="form-control" name="server">
        <option value="">All</option>
        {foreach from=$servers item=server}
          <option value="{$server|escape}" {if $filters.server eq $server}selected{/if}>{$server|escape}</option>
        {/foreach}
      </select>
    </div>

    <div class="form-group mr-2 mb-2">
      <label for="perPage" class="mr-2">Per Page</label>
      <select id="perPage" class="form-control" name="perPage">
        {foreach from=[25,50,100,250] item=pp}
          <option value="{$pp}" {if (int)$perPage === $pp}selected{/if}>{$pp}</option>
        {/foreach}
      </select>
    </div>

    <button type="submit" class="btn btn-primary mb-2 mr-2">Filter</button>
    <a href="addonmodules.php?module=eazybackup&action=powerpanel&view=storage" class="btn btn-default mb-2">Reset</a>
  </form>

  <div class="table-responsive">
    <table class="table table-striped table-condensed">
      <thead>
        <tr>
          <th><a href="{$sortLinks.username|escape}">Username{if $sort eq 'username'} <span class="text-muted">({$dir|upper})</span>{/if}</a></th>
          <th><a href="{$sortLinks.server|escape}">Comet Server{if $sort eq 'server'} <span class="text-muted">({$dir|upper})</span>{/if}</a></th>
          <th class="text-right"><a href="{$sortLinks.bytes|escape}">Storage Size{if $sort eq 'bytes'} <span class="text-muted">({$dir|upper})</span>{/if}</a></th>
          <th class="text-right"><a href="{$sortLinks.units|escape}">Storage Billing Units{if $sort eq 'units'} <span class="text-muted">({$dir|upper})</span>{/if}</a></th>
        </tr>
      </thead>
      <tbody>
        {if $rows|@count gt 0}
          {foreach from=$rows item=r}
            <tr>
              <td>{$r.username|escape}</td>
              <td>{$r.comet_server_url|escape}</td>
              <td class="text-right">
                {$r.total_bytes_hr|escape}
                <div class="text-muted small">{$r.total_bytes} bytes</div>
              </td>
              <td class="text-right">{$r.billed_units}</td>
            </tr>
          {/foreach}
        {else}
          <tr>
            <td colspan="4" class="text-center text-muted">No results</td>
          </tr>
        {/if}
      </tbody>
    </table>
  </div>

  <div class="mt-2">{$pagination nofilter}</div>
</div>


