<style>

    .header-lined {
        display: none;
    }

</style>

{if $invalid}

    <div class="alert alert-danger text-center">Invalid device ID</div>

    <p class="text-center">
        <a href="clientarea.php?action=productdetails&amp;id={$id}" class="btn btn-primary">{$LANG.clientareabacklink}</a>
    </p>

{elseif $success}

    <div class="max-w-xl mx-auto my-6 p-6 bg-blue-50 border border-blue-200 rounded-lg text-center text-blue-800">The device &quot;{$devicename}&quot; has been revoked</div>

    <p class="text-center">
        <a href="/clientarea.php?action=products" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700">{$LANG.clientareabacklink}</a>
    </p>

{else}

    {if $error}
        <div class="alert alert-danger text-center">Unable to revoke device. Please contact support.</div>
    {/if}

    <div class="text-xl font-semibold text-gray-700 text-center mb-4">Revoke device &quot;{$devicename}&quot;</div>

    <div class="max-w-xl mx-auto my-6 p-6 bg-sky-50 shadow rounded-lg flex items-start mb-6 text-gray-700 text-sm">
        <ul class="list-disc list-inside space-y-2 text-left">
            <li>This action will stop billing for <span class="text-sky-600">{$devicename}</span></li>
            <li>Revoking a device will not cancel you backup plan for {$username}.
        </ul>
    </div>

    <div class="max-w-xl mx-auto my-6 p-6 bg-amber-50 shadow rounded-lg flex items-start mb-6 text-gray-700 text-sm">
        <ul class="list-disc list-inside space-y-2 text-left">
            <li>Revoking a device will remove all of its Protected Items and the associated retention rules.</li>
            <li>Data from the revoked device {$devicename} will be safely retained for the length of time determined by your Storage Vault retention rules (default 30 days).</li>        
        </ul>    
    </div>


    <form method="post" action="{$smarty.server.PHP_SELF}?action=productdetails&amp;id={$id}" class="form-stacked">
        <input type="hidden" name="sub" value="submit" />
        <input type="hidden" name="a" value="revoke" />
        <input type="hidden" name="confirm" value="1" />
        <input type="hidden" name="deviceid" value="{$deviceid}" />

        <table class="min-w-full dataTable no-footer mb-8">
            <thead class="bg-white border-b">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-900 tracking-wider sorting_asc">Device Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-900 tracking-wider sorting_asc">Protected Items</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-900 tracking-wider sorting_asc">Protected Item Size</th>
                </tr>
            </thead>
            <tbody class="bg-white">
                <tr class="hover:bg-gray-50 cursor-pointer odd">
                    <td class="px-4 py-4 text-left text-sm font-medium text-gray-900 service_username service_list">{$devicename}</td>
                    <td class="px-4 py-4 text-left text-sm font-medium text-gray-900 service_username service_list">{$deviceprotecteditems}</td>
                    <td class="px-4 py-4 text-left text-sm font-medium text-gray-900 service_username service_list">{$devicestored}</td>
                </tr>
            </tbody>
        </table>

        <fieldset>
            <div class="form-group">
                <div class="form-inline text-center">
                    <input type="submit" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-700" value="Revoke Device" />
                    <a href="clientarea.php?action=products" class="text-sm/6 font-semibold text-gray-900 ml-2">{$LANG.cancel}</a>
                </div>
            </div>

        </fieldset>

    </form>

{/if}
