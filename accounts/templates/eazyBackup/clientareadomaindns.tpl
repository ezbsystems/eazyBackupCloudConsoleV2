{capture name=ebDomainDnsBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="clientarea.php?action=domains" class="eb-breadcrumb-link">{lang key='navdomains'}</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">{lang key='domaindnsmanagement'}</span>
    </div>
{/capture}

{capture name=ebDomainDnsContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$smarty.capture.ebDomainDnsBreadcrumb
        ebPageTitle={lang key='domaindnsmanagement'}
        ebPageDescription={lang key='domaindnsmanagementdesc'}
    }

    <div class="eb-subpanel">
        {include file="$template/includes/alert-darkmode.tpl" type="info" msg="{lang key='domaindnsmanagementdesc'}"}

        {if $error}
            {include file="$template/includes/alert-darkmode.tpl" type="error" msg=$error}
        {/if}

        {if $external}
            <div class="text-center px-4">
                {$code}
            </div>
        {else}
            <form method="post" action="{$smarty.server.PHP_SELF}?action=domaindns" class="space-y-6">
                <input type="hidden" name="sub" value="save" />
                <input type="hidden" name="domainid" value="{$domainid}" />

                <div class="eb-table-shell">
                    <table class="eb-table">
                        <thead>
                            <tr>
                                <th>{lang key='domaindnshostname'}</th>
                                <th>{lang key='domaindnsrecordtype'}</th>
                                <th>{lang key='domaindnsaddress'}</th>
                                <th>{lang key='domaindnspriority'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $dnsrecords as $dnsrecord}
                                <tr>
                                    <td>
                                        <input type="hidden" name="dnsrecid[]" value="{$dnsrecord.recid}" />
                                        <input type="text" name="dnsrecordhost[]" value="{$dnsrecord.hostname}" class="eb-input" />
                                    </td>
                                    <td>
                                        <select name="dnsrecordtype[]" class="eb-select">
                                            <option value="A"{if $dnsrecord.type eq "A"} selected="selected"{/if}>{lang key="domainDns.a"}</option>
                                            <option value="AAAA"{if $dnsrecord.type eq "AAAA"} selected="selected"{/if}>{lang key="domainDns.aaaa"}</option>
                                            <option value="MXE"{if $dnsrecord.type eq "MXE"} selected="selected"{/if}>{lang key="domainDns.mxe"}</option>
                                            <option value="MX"{if $dnsrecord.type eq "MX"} selected="selected"{/if}>{lang key="domainDns.mx"}</option>
                                            <option value="CNAME"{if $dnsrecord.type eq "CNAME"} selected="selected"{/if}>{lang key="domainDns.cname"}</option>
                                            <option value="TXT"{if $dnsrecord.type eq "TXT"} selected="selected"{/if}>{lang key="domainDns.txt"}</option>
                                            <option value="URL"{if $dnsrecord.type eq "URL"} selected="selected"{/if}>{lang key="domainDns.url"}</option>
                                            <option value="FRAME"{if $dnsrecord.type eq "FRAME"} selected="selected"{/if}>{lang key="domainDns.frame"}</option>
                                        </select>
                                    </td>
                                    <td><input type="text" name="dnsrecordaddress[]" value="{$dnsrecord.address}" class="eb-input" /></td>
                                    <td>
                                        {if $dnsrecord.type eq "MX"}
                                            <input type="text" name="dnsrecordpriority[]" value="{$dnsrecord.priority}" class="eb-input" />
                                        {else}
                                            <input type="hidden" name="dnsrecordpriority[]" value="N/A" />
                                            <span class="eb-choice-card-description">{lang key='domainregnotavailable'}</span>
                                        {/if}
                                    </td>
                                </tr>
                            {/foreach}
                            <tr>
                                <td><input type="text" name="dnsrecordhost[]" class="eb-input" /></td>
                                <td>
                                    <select name="dnsrecordtype[]" class="eb-select">
                                        <option value="A">{lang key="domainDns.a"}</option>
                                        <option value="AAAA">{lang key="domainDns.aaaa"}</option>
                                        <option value="MXE">{lang key="domainDns.mxe"}</option>
                                        <option value="MX">{lang key="domainDns.mx"}</option>
                                        <option value="CNAME">{lang key="domainDns.cname"}</option>
                                        <option value="TXT">{lang key="domainDns.txt"}</option>
                                        <option value="URL">{lang key="domainDns.url"}</option>
                                        <option value="FRAME">{lang key="domainDns.frame"}</option>
                                    </select>
                                </td>
                                <td><input type="text" name="dnsrecordaddress[]" class="eb-input" /></td>
                                <td><input type="text" name="dnsrecordpriority[]" class="eb-input" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-right eb-choice-card-description">
                    <small>* {lang key='domaindnsmxonly'}</small>
                </p>

                <div class="flex justify-center gap-3">
                    <button type="submit" class="eb-btn eb-btn-primary">
                        {lang key='clientareasavechanges'}
                    </button>
                    <button type="reset" class="eb-btn eb-btn-ghost">
                        {lang key='clientareacancel'}
                    </button>
                </div>
            </form>
        {/if}
    </div>
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageContent=$smarty.capture.ebDomainDnsContent
}
