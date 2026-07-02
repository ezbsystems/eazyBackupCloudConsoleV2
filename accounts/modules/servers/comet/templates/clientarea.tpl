{include file="$template/includes/tablelist.tpl" tableName="DevicesList" filterColumn="0" noSortColumns="3"}
{include file="$template/includes/tablelist.tpl" tableName="ProtectedItemsList" filterColumn="0" noSortColumns="5"}
<script type="text/javascript">
    jQuery(document).ready( function ()
    {
        var table1 = jQuery('#tableDevicesList').removeClass('hidden').DataTable();
        table1.draw();

        var table2 = jQuery('#tableProtectedItemsList').removeClass('hidden').DataTable();
        table2.draw();
    });
</script>
{* <pre>
{$devices|@print_r}
</pre> *}
<div class="panel panel-default">
    <div class="panel-heading" style="display:flex; justify-content: space-between; align-items: center;">
        <h3 class="panel-title" style="display: flex; align-items: center;">Devices {if $hasdevices > 0}<span class="label label-success" style="margin-left: 8px; font-weight: 400;">{$hasdevices} available in quota</span>{/if}</h3>
        <div class="progress" style="width: 50%; margin-bottom: 0;">
            <div class="progress-bar progress-bar-{$deviceprogress} progress-bar-striped" role="progressbar" style="width: {$deviceusedpercent}%;"></div>
        </div>
    </div>
    <table id="tableDevicesList" class="table table-list hidden">
        <thead>
        <tr>
            <th>Device Name</th>
            <th>Protected Items</th>
            <th>Activity</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        {foreach $devices as $device}
            <tr>
                <td>{$device["name"]}</td>
                <td>{$device["protecteditems"]}</td>
                <td>{$device["activity"]}</td>
                <td>
                    <form method="post" action="{$smarty.server.PHP_SELF}?action=productdetails&amp;id={$id}">
                        <input type="hidden" name="a" value="revoke" />
                        <input type="hidden" name="deviceid" value="{$device["id"]}" />
                        <button type="submit" class="btn btn-sm btn-danger">
                            <i class="fa fa-trash"></i>
                            &nbsp;Revoke
                        </button>
                    </form>
                </td>
            </tr>
        {/foreach}
        </tbody>
    </table>
</div>

<hr>

<div class="panel panel-default">
    <div class="panel-heading" style="display:flex; justify-content: space-between; align-items: center;">
        <h3 class="panel-title" style="display: flex; align-items: center;">Protected Items <span class="label label-{$quotaprogress}" style="margin-left: 8px; font-weight: 400;">{$quotaused} used of {$quota} quota</span></h3>
        <div class="progress" style="width: 50%; margin-bottom: 0;">
            <div class="progress-bar progress-bar-{$quotaprogress} progress-bar-striped" role="progressbar" style="width: {$quotausedpercent}%;"></div>
        </div>
    </div>
    {* <pre>
    {$sources|@print_r}
    </pre> *}
    <table id="tableProtectedItemsList" class="table table-list hidden">
        <thead>
        <tr>
            <th>Status</th>
            <th>Description</th>
            <th>Type</th>
            <th>Last Backup Job</th>
            <th>Size</th>
            <th>Device</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        {foreach $sources as $source}
            <tr>
                <td><i class="fa {$source["laststatusicon"]}" data-toggle="tooltip" data-placement="top" title="{$source["laststatus"]}"></i></td>
                <td>{$source["description"]}</td>
                <td>{$source["type"]}</td>
                <td>{$source["lastbackupjob"]}</td>
                <td>{$source["size"]}</td>
                <td>{$source["device"]}</td>
                <td>


                    <div class="btn-group">
                        <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Actions <span class="caret"></span>
                        </button>
                        <ul class="dropdown-menu">
                            {if $source["laststatus"] != "Running" && $devices[$source["deviceguid"]]["activity"] == "Online"}
                            <li>
                                <form method="post" action="{$smarty.server.PHP_SELF}?action=productdetails&amp;id={$id}">
                                    <input type="hidden" name="a" value="run" />
                                    <input type="hidden" name="deviceid" value="{$source["deviceid"]}" />
                                    <input type="hidden" name="sourceid" value="{$source["id"]}" />
                                    <input type="hidden" name="destinationid" value="{$source["destinationid"]}" />
                                    <input type="submit" style="display: none;">
                                </form>
                                <a class="dropdown-submit" href="#">Run Backup</a>
                            </li>
                            {else}
                                <li class="disabled"><a href="#">Run Backup</a></li>
                            {/if}
                            <li role="separator" class="divider"></li>
                            {if strlen($source["jobid"]) == 36}
                                <li>
                                    <form method="post" action="{$smarty.server.PHP_SELF}?action=productdetails&amp;id={$id}">
                                        <input type="hidden" name="a" value="jobreport" />
                                        <input type="hidden" name="jobid" value="{$source["jobid"]}" />
                                        <input type="submit" style="display: none;">
                                    </form>
                                    <a class="dropdown-submit" href="#">Details</a>
                                </li>
                            {else}
                                <li class="disabled"><a href="#">Details</a></li>
                            {/if}
                        </ul>
                    </div>
                </td>
            </tr>
        {/foreach}
        </tbody>
    </table>
</div>
<script>
    $(document).ready(function(){
        $(".dropdown-submit").click(function(e){
            e.preventDefault();
            console.log($(this).closest("form"));
            $(this).siblings("form").submit();
        });
    });
</script>
<script type="text/javascript">
    jQuery(document).ready(function($) {
        // For the DevicesList table actions
        $('#tableDevicesList').on('click', '.btn-group, .btn-group *', function(e) {
            e.stopPropagation();
        });

        // For the ProtectedItemsList table actions
        $('#tableProtectedItemsList').on('click', '.btn-group, .btn-group *', function(e) {
            e.stopPropagation();
        });
    });
</script>