<div class="bg-gray-700 flex justify-center items-center min-h-screen">
    <!-- Main Content -->
    <div class="bg-gray-800 shadow flex-1 rounded-lg m-10 p-4 max-w-3xl w-full">

        {if $invalid}

            {include file="$template/includes/alert-darkmode.tpl" type="error" msg="{lang key='clientareacancelinvalid'}" textcenter=true}
            <p class="text-center">
                <a href="clientarea.php?action=productdetails&amp;id={$id}" class="bg-sky-500 hover:bg-sky-600 text-white py-2 px-4 rounded">{lang key='clientareabacklink'}</a>
            </p>

        {elseif $requested}

            {include file="$template/includes/alert-darkmode.tpl" type="success" msg="{lang key='clientareacancelconfirmation'}" textcenter=true}

            <p class="mt-auto text-center">
                <a href="/clientarea.php?action=services" 
                class="bg-sky-500 hover:bg-sky-600 text-white py-2 px-4 rounded">
                {lang key='clientareabacklink'}
                </a>
            </p>

        {else}

            {if $error}
                {include file="$template/includes/alert.tpl" type="error" errorshtml="<li>{lang key='clientareacancelreasonrequired'}</li>"}
            {/if}

            {include file="$template/includes/alert.tpl" type="info" class="text-gray-300" textcenter=true msg="{lang key='clientareacancelproduct'}: <strong>{$username}</strong>{if $domain} ({$domain}){/if}"}

            <div>
                <div id="card-body" class="p-4">

                    <form method="post" action="{$smarty.server.PHP_SELF}?action=cancel&amp;id={$id}" class="space-y-6">
                        <input type="hidden" name="sub" value="submit" />

                        <fieldset>
                            <div class="mb-4">
                                <label for="cancellationreason" class="block text-sm font-medium text-gray-300 pb-2">{lang key='clientareacancelreason'}</label>
                                <textarea name="cancellationreason" id="cancellationreason" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600" rows="6"></textarea>
                            </div>

                            {if $domainid}
                                <div class="border-l-4 border-yellow-400 bg-yellow-100 p-4">
                                    <p class="font-semibold text-yellow-700">{lang key='cancelrequestdomain'}</p>
                                    <p class="text-sm text-gray-600">{"{lang key='cancelrequestdomaindesc'}"|sprintf2:$domainnextduedate:$domainprice:$domainregperiod}</p>
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600" name="canceldomain" id="canceldomain" />
                                        <span class="text-sm text-gray-300">{lang key='cancelrequestdomainconfirm'}</span>
                                    </label>
                                </div>
                            {/if}

                            <div class="text-center">
                                <label for="type" class="block text-sm font-medium text-gray-300 pb-2">{lang key='clientareacancellationtype'}</label>
                                <select name="type" id="type" class="block w-full px-3 py-2 border border-gray-600 text-gray-300 bg-gray-700 rounded focus:outline-none focus:ring-0 focus:border-sky-600 mb-4">
                                    <option value="Immediate">{lang key='clientareacancellationimmediate'}</option>
                                    <option value="End of Billing Period">{lang key='clientareacancellationendofbillingperiod'}</option>
                                </select>
                            </div>

                            <div class="flex justify-center space-x-4">
                                <button type="submit" class="bg-red-700 hover:bg-red-600 text-white py-2 px-4 rounded">{lang key='clientareacancelrequestbutton'}</button>
                                <a href="clientarea.php?action=productdetails&id={$id}" class="text-sm/6 font-semibold text-gray-300 py-2 px-4 rounded">{lang key='cancel'}</a>
                            </div>
                        </fieldset>

                    </form>

                </div>
            </div>

        {/if}
    </div>
</div>
