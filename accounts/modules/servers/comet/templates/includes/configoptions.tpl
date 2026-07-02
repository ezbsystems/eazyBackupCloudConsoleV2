{for $i=$start to $count}
    <tr class="{if $i < $devices}disabled{/if}">
        <td data-count="{$i}" data-attr="devices">
            {$i} {if $i eq 1}device{else}devices{/if}
            {if $i eq $maxdevices}<span class="label label-primary">CURRENT PLAN</span>{/if}
        </td>
        <td data-attr="devicestorage">{$i}TB</td>
        <td>
            {assign var=selectedqty value=$configoptionstable["storage"]["selectedqty"]}
            {assign var=storagedelta value=($minstorageplan - $i)}
            {assign var=storageval value=(max($storagedelta, $selectedqty))}

            <i class="fas fa-minus-circle {if $storageval + $i <= $minstorageplan }disabled{/if}" data-action="remove"></i>
            <span data-count="{$storageval}" data-attr="storage">{$storageval}TB</span>
            <i class="fas fa-plus-circle" data-action="add"></i>
        </td>
        <td data-attr="totalstorage">{$i + $storageval}TB</td>
        <td data-attr="price">${(($i * $configoptionstable["devices"]["price"]) + ($storageval * $configoptionstable["storage"]["price"]))|string_format:"%.2f"}/mo</td>
        <td>
            <form method="post" action="{$smarty.server.PHP_SELF}">
                <input type="hidden" name="step" value="2" />
                <input type="hidden" name="type" value="{$type}" />
                <input type="hidden" name="id" value="{$id}" />
                <input type="hidden" name="configoption[{$configoptionstable["devices"]["id"]}]" value="{$i - 1}" size="5">
                <input type="hidden" name="configoption[{$configoptionstable["storage"]["id"]}]" value="{$storageval}" size="5">
                <input type="submit" value="{$LANG.ordercontinuebutton}" class="btn btn-success"/>
            </form>
        </td>
    </tr>
{/for}
