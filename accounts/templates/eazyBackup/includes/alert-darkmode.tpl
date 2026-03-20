{if $type eq "error"}
    {assign var=ebAlertType value="danger"}
{elseif $type eq "success"}
    {assign var=ebAlertType value="success"}
{elseif $type eq "warning"}
    {assign var=ebAlertType value="warning"}
{else}
    {assign var=ebAlertType value="info"}
{/if}

{include file="$template/includes/ui/eb-alert.tpl"
    ebAlertType=$ebAlertType
    ebAlertClass=$additionalClasses|default:''
    ebAlertTitle=$title|default:''
    ebAlertMessage=$msg|default:''
    ebAlertCentered=$textcenter|default:false
    errorshtml=$errorshtml|default:''
    idname=$idname|default:''
    hide=$hide|default:false
}
