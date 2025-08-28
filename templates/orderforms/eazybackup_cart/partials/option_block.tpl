{* accounts/templates/orderforms/eazybackup_cart/option_block.tpl *}
<div 
    class="group flex items-start space-x-4 p-4 border rounded-md transition-colors duration-200"
    id="configOptionBlock{$configoption.id}"
>
    <!-- Icon Placeholder -->
    <div class="flex items-center justify-center w-12 h-12 bg-gray-50 rounded-lg">
        {if $configoption.id == 67}
            <!-- Icon for Additional Storage -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 1.332-7.257 3 3 0 0 0-3.758-3.848 5.25 5.25 0 0 0-10.233 2.33A4.502 4.502 0 0 0 2.25 15Z" />
            </svg>          
        {elseif $configoption.id == 98}
            <!-- Icon for Synology endpoint -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="m6.75 7.5 3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0 0 21 18V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v12a2.25 2.25 0 0 0 2.25 2.25Z" />
            </svg>
          
        {elseif $configoption.id == 88}
            <!-- Icon for Workstation Device -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
            </svg>          
        {elseif $configoption.id == 89}
            <!-- Icon for Server Device -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 0 0-.12-1.03l-2.268-9.64a3.375 3.375 0 0 0-3.285-2.602H7.923a3.375 3.375 0 0 0-3.285 2.602l-2.268 9.64a4.5 4.5 0 0 0-.12 1.03v.228m19.5 0a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3m19.5 0a3 3 0 0 0-3-3H5.25a3 3 0 0 0-3 3m16.5 0h.008v.008h-.008v-.008Zm-3 0h.008v.008h-.008v-.008Z" />
            </svg>
        {elseif $configoption.id == 97}
            <!-- Icon for Hyper-V Guest VM -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
            </svg>          
        {elseif $configoption.id == 91}
            <!-- Icon for Disk Image -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
            </svg>
          
        {else}
            <!-- Default Icon -->
            <svg class="w-6 h-6 text-gray-400 group-hover:text-[#fe5000] 
                       transition-colors duration-200" fill="none" 
                 stroke="currentColor" viewBox="0 0 24 24" 
                 aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" 
                      d="M12 2a10 10 0 1 1-10 10A10 10 0 0 1 12 2z" />
            </svg>
        {/if}
    </div>

    <!-- Right Column: Title, Description, Number Field -->
    <div class="flex flex-col h-full">
        <!-- Option Name -->
        <h3 
            id="titleConfigOption{$configoption.id}"
            class="font-semibold text-gray-800"
        >
            {$configoption.optionname}
        </h3>

        <!-- Description or Features -->
        <ul class="text-xs text-gray-500 list-disc ml-5 mt-2 mb-3 space-y-1">
            {if $configoption.id == 67} 
                <li>1TB additional cloud storage</li>
                <li class="italic">Base plan includes 1TB cloud storage</li>
            {elseif $configoption.id == 88}
                <li>Supports Windows 10/11/Server, Linux and macOS devices</li>
                <li>Protect unlimited files and folders</li>
                <li>Configurable data retention, default 30-days</li>
                <li>Protect NAS devices and network drives</li>
            {elseif $configoption.id == 89}
                <li>Supports Windows 10/11/Server, Linux and macOS devices</li>
                <li>Protect unlimited files and folders</li>
                <li>Configurable data retention, default 30-days</li>
                <li>Protect NAS devices and network drives</li>
                <li>Protect Hyper-V and VMware guests</li>                                                  
                <li>Backup SQL, MySQL, and MariaDB</li>                
            {elseif $configoption.id == 98}
                <li>Support for for Synology DSM 6 and 7</li>    
                <li>Protect files, folders and settings</li>  
                <li>Command line client controlled from web dashboard</li>  
            {elseif $configoption.id == 97}
                <li>Protect your Hyper-V infrastructure</li>
            {elseif $configoption.id == 91}
                <li>Protect entire disks and partitions on Windows and Linux</li>
                <li>Bare metal system recovery</li>
            {else}
                <li>No additional description available</li>
            {/if}
        </ul>

        <!-- Number Input Field with - and + Buttons -->
        <div class="mt-auto">
            <label for="inputConfigOption{$configoption.id}" class="sr-only">
                {$configoption.optionname}
            </label>
            <div class="flex items-center">
                <!-- Decrease Button -->
                <button 
                    type="button" 
                    class="decrease-btn text-gray-700 font-semibold px-2 py-1 rounded-l-md border-gray-300 border-t border-b border-l focus:outline-none active:bg-gray-300"
                    onclick="decreaseValue('inputConfigOption{$configoption.id}')"
                    aria-label="Decrease quantity"
                >
                    &minus;
                </button>

                <!-- Number Input -->
                <input 
                    type="number" 
                    name="configoption[{$configoption.id}]" 
                    id="inputConfigOption{$configoption.id}"
                    value="{if $configoption.selectedqty}{$configoption.selectedqty}{else}{$configoption.qtyminimum}{/if}" 
                    min="{$configoption.qtyminimum}" 
                    onchange="recalctotals()" 
                    onkeyup="recalctotals()" 
                    class="w-16 text-center py-1 border-t border-b border-gray-300 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-sky-600 disabled:text-gray-400"
                />

                <!-- Increase Button -->
                <button 
                    type="button" 
                    class="decrease-btn text-gray-700 font-semibold px-2 py-1 rounded-r-md border-gray-300 border-t border-r border-b focus:outline-none active:bg-gray-300"
                    onclick="increaseValue('inputConfigOption{$configoption.id}')"
                    aria-label="Increase quantity"
                >
                    +
                </button>
            </div>
        </div>

    </div>
</div>
