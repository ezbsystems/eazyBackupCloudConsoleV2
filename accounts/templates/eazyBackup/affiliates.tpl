<style>
/* 1. Remove native arrow & chrome on modern browsers */
#tableAffiliatesList_length select {
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
  background-color: #1f2937;  
  color: #d1d5db;             
  border: none; 
}

/* 2. Hide the default “expand” arrow in IE/Edge */
#tableAffiliatesList_length select::-ms-expand {
  display: none;
}

/* 3. Style the individual options */
#tableAffiliatesList_length option {
  background-color: #1f2937 !important;
  color: #d1d5db !important;
}

/* 4. Strip inner focus rings/paddings in Firefox */
#tableAffiliatesList_length select::-moz-focus-inner {
  border: 0;
  padding: 0;
}
</style>

<div class="min-h-screen bg-[#11182759] text-slate-300">
  <div class="container mx-auto px-4 pb-8">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row h-16 mx-12 justify-between items-start sm:items-center mb-6">
      <div class="flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6 text-white">
          <path d="M4.5 6.375a4.125 4.125 0 1 1 8.25 0 4.125 4.125 0 0 1-8.25 0ZM14.25 8.625a3.375 3.375 0 1 1 6.75 0 3.375 3.375 0 0 1-6.75 0ZM1.5 19.125a7.125 7.125 0 0 1 14.25 0v.003l-.001.119a.75.75 0 0 1-.363.63 13.067 13.067 0 0 1-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 0 1-.364-.63l-.001-.122ZM17.25 19.128l-.001.144a2.25 2.25 0 0 1-.233.96 10.088 10.088 0 0 0 5.06-1.01.75.75 0 0 0 .42-.643 4.875 4.875 0 0 0-6.957-4.611 8.586 8.586 0 0 1 1.71 5.157v.003Z" />
        </svg>
    
        <h2 class="text-2xl font-semibold text-white ml-2">Affiliate Program</h2>
      </div>
    </div>

    {if $inactive}
      <div class="bg-red-600 bg-opacity-25 border border-red-500 text-red-200 p-4 rounded text-center mx-12 mb-6">
        {lang key='affiliatesdisabled'}
      </div>
    {else}
      {include file="$template/includes/flashmessage.tpl"}

      {if $withdrawrequestsent}
        <div class="border border-green-500 text-green-100 p-4 rounded text-center mx-12 mb-6">
          <i class="fas fa-check fa-fw mr-2"></i>
          {lang key='affiliateswithdrawalrequestsuccessful'}
        </div>
      {/if}

      <!-- Stats Tiles -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mx-12 mb-6">
        <!-- Clicks -->
        <div class="bg-black bg-opacity-10 border border-slate-700 rounded-lg p-4 flex items-center space-x-4">
          <div class="flex-shrink-0 border-2 border-sky-600 p-3 rounded-md">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-8 text-sky-600">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672 13.684 16.6m0 0-2.51 2.225.569-9.47 5.227 7.917-3.286-.672ZM12 2.25V4.5m5.834.166-1.591 1.591M20.25 10.5H18M7.757 14.743l-1.59 1.59M6 10.5H3.75m4.007-4.243-1.59-1.59" />
            </svg>        
          </div>    
          <div>
            <div class="text-3xl font-bold text-white">{$visitors}</div>
            <div class="text-sm text-slate-300">{lang key='affiliatesclicks'}</div>
          </div>
        </div>
        <!-- Signups -->
        <div class="bg-black bg-opacity-10 border border-slate-700 rounded-lg p-4 flex items-center space-x-4">
          <div class="flex-shrink-0 border-2 border-emerald-600 p-3 rounded-md">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-8 text-emerald-600">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
            </svg>
          </div>
      
          <div>
            <div class="text-3xl font-bold text-white">{$signups}</div>
            <div class="text-sm text-slate-300">{lang key='affiliatessignups'}</div>
          </div>
        </div>
        <!-- Conversion Rate -->
        <div class="bg-black bg-opacity-10 border border-slate-700 rounded-lg p-4 flex items-center space-x-4">
          <div class="flex-shrink-0 border-2 border-yellow-600 p-3 rounded-md">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-8 text-yellow-600">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
            </svg>
          </div>
      
          <div>
            <div class="text-3xl font-bold text-white">{$conversionrate}%</div>
            <div class="text-sm text-slate-300">{lang key='affiliatesconversionrate'}</div>
          </div>
        </div>
      </div>

      <!-- Referral Link -->
      <div class="mx-12 mb-6">
      <div class="bg-black bg-opacity-10 border border-slate-700 rounded-lg p-6 relative">
        <p class="text-lg font-semibold mb-2 text-gray-100">{lang key='affiliatesreferallink'}</p>
        <div class="relative">
          <input
            id="referralLinkInput"
            type="text"
            class="w-full border bg-black bg-opacity-0 border-slate-700 rounded-md p-2 pr-10 text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            readonly
            value="{$referrallink}"
          >
          <button
            id="copyReferralLinkBtn"
            type="button"
            class="absolute inset-y-0 right-2 flex items-center p-1 text-gray-400 hover:text-gray-200"
            title="{lang key='copytoclipboard'}"
          >
            <!-- your SVG icon -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 
                       0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 
                       9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 
                       1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 
                       0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 
                       10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 
                       6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 
                       1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
            </svg>
          </button>
        </div>
        <div id="copyTooltip" class="hidden absolute top-0 right-0 mt-2 mr-2 bg-gray-800 text-white text-xs rounded px-2 py-1">
          Copied!
        </div>
      </div>
    </div>
    

      <!-- Commissions Summary -->
      <div class="mx-12 mb-6">
        <div class="bg-black bg-opacity-10 border border-slate-700 rounded-lg overflow-hidden">
          <dl class="grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-slate-700">
            <div class="p-4 text-center">
              <dt class="text-sm text-gray-400">{lang key='affiliatescommissionspending'}:</dt>
              <dd class="mt-1 text-xl font-semibold text-white">{$pendingcommissions}</dd>
            </div>
            <div class="p-4 text-center">
              <dt class="text-sm text-gray-400">{lang key='affiliatescommissionsavailable'}:</dt>
              <dd class="mt-1 text-xl font-semibold text-white">{$balance}</dd>
            </div>
            <div class="p-4 text-center">
              <dt class="text-sm text-gray-400">{lang key='affiliateswithdrawn'}:</dt>
              <dd class="mt-1 text-xl font-semibold text-white">{$withdrawn}</dd>
            </div>
          </dl>
        </div>
      </div>

      <!-- Withdrawal Button -->
      {if !$withdrawrequestsent}
        <div class="text-center mx-12 mb-6">
          <form method="POST" action="{$smarty.server.PHP_SELF}">
            <input type="hidden" name="action" value="withdrawrequest" />
            <button
              type="submit"
              class="inline-flex items-center px-6 py-3 bg-green-600 hover:bg-green-700 disabled:bg-opacity-30 rounded-lg text-white text-lg font-medium transition"
              {if !$withdrawlevel} disabled {/if}
            >
              {lang key='affiliatesrequestwithdrawal'}
            </button>
          </form>
          {if !$withdrawlevel}
            <p class="text-slate-200 mt-2">{lang key="affiliateWithdrawalSummary" amountForWithdrawal=$affiliatePayoutMinimum}</p>
          {/if}
        </div>
      {/if}

      <!-- Referrals Table -->
      <div class="mx-12 mb-8">
        <h2 class="text-xl font-semibold text-slate-300 mb-4">{lang key='affiliatesreferals'}</h2>
        {include file="$template/includes/tablelist.tpl" tableName="AffiliatesList"}
        <div class="bg-black bg-opacity-10 border border-slate-700 shadow rounded-md p-4 mb-4">
          <table id="tableAffiliatesList" class="min-w-full">
            <thead class="border-b border-gray-600">
              <tr>
                <th class="px-4 py-4 text-left text-sm font-normal text-slate-300 sorting_asc">{lang key='affiliatessignupdate'}</th>
                <th class="px-4 py-4 text-left text-sm font-normal text-slate-300">{lang key='orderproduct'}</th>
                <th class="px-4 py-4 text-left text-sm font-normal text-slate-300">{lang key='affiliatesamount'}</th>
                <th class="px-4 py-4 text-left text-sm font-normal text-slate-300">{lang key='affiliatescommission'}</th>
                <th class="px-4 py-4 text-left text-sm font-normal text-slate-300">{lang key='affiliatesstatus'}</th>
              </tr>
            </thead>
            <tbody class="">
              {foreach $referrals as $referral}
                <tr class="hover:bg-[#1118272e] cursor-pointer">
                  <td class="px-4 py-4 text-left text-sm text-gray-400"><span class="hidden">{$referral.datets}</span>{$referral.date}</td>
                  <td class="px-4 py-4 text-left text-sm text-gray-400">{$referral.service}</td>
                  <td class="px-4 py-4 text-left text-sm text-gray-400" data-order="{$referral.amountnum}">{$referral.amountdesc}</td>
                  <td class="px-4 py-4 text-left text-sm text-gray-400" data-order="{$referral.commissionnum}">{$referral.commission}</td>
                  <td class="px-4 py-4 text-left text-sm text-gray-400">
                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded 
                      status-{$referral.rawstatus|strtolower}">
                      {$referral.status}
                    </span>
                  </td>
                </tr>
              {/foreach}
            </tbody>
          </table>
          <div id="tableLoading" class="p-6 text-center">
            <i class="fas fa-spinner fa-spin text-indigo-400 text-2xl"></i>
            <p class="mt-2">{lang key='loading'}</p>
          </div>
        </div>
      </div>

      <!-- Link to Us -->
      {if $affiliatelinkscode}
        <div class="mx-12 mb-8">
          <h2 class="text-xl font-semibold text-white mb-4">{lang key='affiliateslinktous'}</h2>
          <div class="bg-slate-700 border border-slate-600 rounded-lg p-6 text-center">
            {$affiliatelinkscode}
          </div>
        </div>
      {/if}


<script>
jQuery(document).ready(function($) {  
  $('#tableAffiliatesList').show();
  $('.myloader, #tableLoading').hide();
 
  var table = $('#tableAffiliatesList').DataTable();

  $('#tableAffiliatesList_info').hide();

  var $filter = $('#tableAffiliatesList_filter');
  $filter.find('label').contents().filter(function(){
    return this.nodeType === 3;
  }).each(function(){
    var txt = $.trim($(this).text());
    if(txt) $(this).replaceWith('<span class="text-gray-400">'+txt+'</span>');
  });
  $filter.find('input')
    .removeClass()
    .addClass(
      'block w-full px-3 py-2 border border-slate-700 text-slate-300 ' +
      'bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600'
    )
    .css('border','1px solid #4b5563');

   var $length = $('#tableAffiliatesList_length');
  $length.find('label').contents().filter(function(){
    return this.nodeType === 3;
  }).each(function(){
    var txt = $.trim($(this).text());
    if(txt) $(this).replaceWith('<span class="text-gray-400">'+txt+'</span>');
  });  
$length.find('select')
  .removeClass()
  .addClass(
    'block w-16 px-3 py-2 border border-slate-700 text-slate-300 ' +
    'bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600'
  )
  .css({
    'border': '1px solid #4b5563',
  });
  
  {if $orderby == 'regdate'}
    table.order(0, '{$sort}');
  {elseif $orderby == 'product'}
    table.order(1, '{$sort}');
  {elseif $orderby == 'amount'}
    table.order(2, '{$sort}');
  {elseif $orderby == 'status'}
    table.order(4, '{$sort}');
  {/if}
 
  table.draw();
});
</script>


<script>
document.addEventListener('DOMContentLoaded', function() {
  const btn = document.getElementById('copyReferralLinkBtn');
  const input = document.getElementById('referralLinkInput');
  const tooltip = document.getElementById('copyTooltip');

  btn.addEventListener('click', function() {
    // select and copy
    input.select();
    input.setSelectionRange(0, 99999); // mobile
    navigator.clipboard.writeText(input.value)
      .then(() => {
        // show tooltip
        tooltip.classList.remove('hidden');
        // hide after 1.5s
        setTimeout(() => tooltip.classList.add('hidden'), 1500);
      })
      .catch(console.error);
  });
});
</script>



    {/if}

  </div>
</div>
