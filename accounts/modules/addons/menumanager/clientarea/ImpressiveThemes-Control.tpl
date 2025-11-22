{foreach from=$menu item=item}
    {assign var=firstItemActive value=$item.isactive}
    {if $item.children}
        {foreach from=$item.children item=level2}
            {if $level2.isactive}
            {assign var=firstItemActive value=true}
            {/if}
        {/foreach}
    {/if}
    <li{if $item.css_id} id="{$item.css_id}"{/if} class="{if $firstItemActive}active open{/if}{if $item.children} nav-dropdown{/if}{if $item.css_class} {$item.css_class}{/if}">
        <a{if $item.children} href="#"{else} href="{$item.fullurl}" target="{$item.targetwindow}"{/if}{if $item.attributes} {$item.attributes}{/if}>
            {if $item.css_icon}<i class="{$item.css_icon}"></i>&nbsp;{/if}
            {$item.title}
            {if $item.badge!=="none"}&nbsp;<span class="badge">{$item.badge}</span>{/if}
        </a>
        {* Level 2 *}
        {if $item.children}
            <ul class="nav-sub">
            {foreach from=$item.children item=level2}
                {if $level2.title=="-----"}
                <li class="nav-divider">
                    <a href="">-----</a>
                </li>
                {else}
                <li{if $level2.css_id} id="{$level2.css_id}"{/if} class="{if $level2.isactive}active{/if}{if $level2.css_class} {$level2.css_class}{/if}">
                    <a href="{$level2.fullurl}" target="{$level2.targetwindow}"{if $level2.attributes} {$level2.attributes}{/if}>
                        {$level2.title}
                        {if $level2.badge!=="none"}&nbsp;<span class="badge">{$level2.badge}</span>{/if}
                    </a>
                </li>
                {/if}
            {/foreach}
            </ul>
        {/if}
    </li>
{/foreach}