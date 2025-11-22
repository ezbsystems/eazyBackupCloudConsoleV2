{if $affiliatesystemenabled}

    <div class="min-h-screen bg-slate-800 text-gray-300">
    <div class="container mx-auto px-4 pb-8">

      <div class="flex flex-col sm:flex-row h-16 mx-12 justify-between items-start sm:items-center">
        <!-- Navigation Horizontal -->
        <div class="flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
          </svg>      
          <h2 class="text-2xl font-semibold text-white ml-2">{lang key='affiliatesignuptitle'}</h2>
        </div> 
      </div> 
      
      <div class="mx-8 space-y-12">
        <div class="min-h-[calc(100vh-14rem)] h-full p-6 xl:p-12 bg-[#11182759] rounded-xl border border-gray-700 max-w-3xl">   

        <p class="text-xl text-white">{lang key='affiliatesignupintro'}</p>

        <ul class="list-disc pl-5 py-4 text-white">
            <li class="text-sm font-medium text-gray-300 mb-1">{lang key='affiliatesignupinfo1'}</li>
            <li class="text-sm font-medium text-gray-300 mb-1">{lang key='affiliatesignupinfo2'}</li>
            <li class="text-sm font-medium text-gray-300 mb-1">{lang key='affiliatesignupinfo3'}</li>
        </ul>
        
        <br />
        
        <form method="post" action="affiliates.php">
            <input type="hidden" name="activate" value="true" />
            
                <button id="activateAffiliate" type="submit" class="bg-sky-600 hover:bg-sky-700 text-white px-5 py-2 rounded">
                    {lang key='affiliatesactivate'}
                </button>
            
        </form>
        
                    </button>
                </p>
            </form>
        </div>
    </div>

{else}
    {include file="$template/includes/alert.tpl" type="warning" msg="{lang key='affiliatesdisabled'}" textcenter=true}
{/if}
