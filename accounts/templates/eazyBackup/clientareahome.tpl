{include file="$template/includes/flashmessage-darkmode.tpl"}

{capture name=ebClientHomeContent}
    {include file="$template/includes/ui/page-header.tpl"
        ebPageTitle={lang key='clientareatitle'}
        ebPageDescription="Use the client area to review services, domains, invoices, quotes, and support activity."
    }

    <div class="eb-home-grid mb-6">
        <a href="clientarea.php?action=services" class="eb-home-tile">
            <span class="eb-home-tile-icon"><i class="fas fa-cube"></i></span>
            <div class="eb-home-tile-stat">{$clientsstats.productsnumactive}</div>
            <div class="eb-home-tile-title">{lang key='navservices'}</div>
        </a>

        {if $clientsstats.numdomains || $registerdomainenabled || $transferdomainenabled}
            <a href="clientarea.php?action=domains" class="eb-home-tile">
                <span class="eb-home-tile-icon"><i class="fas fa-globe"></i></span>
                <div class="eb-home-tile-stat">{$clientsstats.numactivedomains}</div>
                <div class="eb-home-tile-title">{lang key='navdomains'}</div>
            </a>
        {elseif $condlinks.affiliates && $clientsstats.isAffiliate}
            <a href="affiliates.php" class="eb-home-tile">
                <span class="eb-home-tile-icon"><i class="fas fa-shopping-cart"></i></span>
                <div class="eb-home-tile-stat">{$clientsstats.numaffiliatesignups}</div>
                <div class="eb-home-tile-title">{lang key='affiliatessignups'}</div>
            </a>
        {else}
            <a href="clientarea.php?action=quotes" class="eb-home-tile">
                <span class="eb-home-tile-icon"><i class="far fa-file-alt"></i></span>
                <div class="eb-home-tile-stat">{$clientsstats.numquotes}</div>
                <div class="eb-home-tile-title">{lang key='quotes'}</div>
            </a>
        {/if}

        <a href="supporttickets.php" class="eb-home-tile">
            <span class="eb-home-tile-icon"><i class="fas fa-comments"></i></span>
            <div class="eb-home-tile-stat">{$clientsstats.numactivetickets}</div>
            <div class="eb-home-tile-title">{lang key='navtickets'}</div>
        </a>

        <a href="clientarea.php?action=invoices" class="eb-home-tile">
            <span class="eb-home-tile-icon"><i class="fas fa-credit-card"></i></span>
            <div class="eb-home-tile-stat">{$clientsstats.numunpaidinvoices}</div>
            <div class="eb-home-tile-title">{lang key='navinvoices'}</div>
        </a>
    </div>

    {foreach $addons_html as $addon_html}
        <div class="mb-4">
            {$addon_html}
        </div>
    {/foreach}

    {function name=outputHomePanels}
        <div menuItemName="{$item->getName()}" class="eb-home-panel{if $item->getExtra('colspan')} xl:col-span-2{/if}{if $item->getClass()} {$item->getClass()}{/if}"{if $item->getAttribute('id')} id="{$item->getAttribute('id')}"{/if}>
            <div class="eb-home-panel-header">
                <h3 class="eb-home-panel-title">
                    {if $item->hasIcon()}<i class="{$item->getIcon()}"></i>&nbsp;{/if}
                    {$item->getLabel()}
                    {if $item->hasBadge()}&nbsp;<span class="eb-badge eb-badge--neutral">{$item->getBadge()}</span>{/if}
                </h3>
                {if $item->getExtra('btn-link') && $item->getExtra('btn-text')}
                    <a href="{$item->getExtra('btn-link')}" class="eb-btn eb-btn-secondary eb-btn-xs">
                        {if $item->getExtra('btn-icon')}<i class="{$item->getExtra('btn-icon')}"></i>{/if}
                        {$item->getExtra('btn-text')}
                    </a>
                {/if}
            </div>
            {if $item->hasBodyHtml()}
                <div class="eb-home-panel-body">
                    {$item->getBodyHtml()}
                </div>
            {/if}
            {if $item->hasChildren()}
                <div class="eb-home-panel-list{if $item->getChildrenAttribute('class')} {$item->getChildrenAttribute('class')}{/if}">
                    {foreach $item->getChildren() as $childItem}
                        {if $childItem->getUri()}
                            <a menuItemName="{$childItem->getName()}" href="{$childItem->getUri()}" class="eb-home-panel-item{if $childItem->getClass()} {$childItem->getClass()}{/if}{if $childItem->isCurrent()} is-active{/if}"{if $childItem->getAttribute('dataToggleTab')} data-toggle="tab"{/if}{if $childItem->getAttribute('target')} target="{$childItem->getAttribute('target')}"{/if} id="{$childItem->getId()}">
                                <span>
                                    {if $childItem->hasIcon()}<i class="{$childItem->getIcon()}"></i>&nbsp;{/if}
                                    {$childItem->getLabel()}
                                </span>
                                {if $childItem->hasBadge()}<span class="eb-badge eb-badge--neutral">{$childItem->getBadge()}</span>{/if}
                            </a>
                        {else}
                            <div menuItemName="{$childItem->getName()}" class="eb-home-panel-item{if $childItem->getClass()} {$childItem->getClass()}{/if}" id="{$childItem->getId()}">
                                <span>
                                    {if $childItem->hasIcon()}<i class="{$childItem->getIcon()}"></i>&nbsp;{/if}
                                    {$childItem->getLabel()}
                                </span>
                                {if $childItem->hasBadge()}<span class="eb-badge eb-badge--neutral">{$childItem->getBadge()}</span>{/if}
                            </div>
                        {/if}
                    {/foreach}
                </div>
            {/if}
            {if $item->hasFooterHtml()}
                <div class="eb-home-panel-body">
                    {$item->getFooterHtml()}
                </div>
            {/if}
        </div>
    {/function}

    <div class="eb-home-panels">
        {foreach $panels as $item}
            {outputHomePanels}
        {/foreach}
    </div>
{/capture}

{include file="$template/includes/ui/page-shell.tpl"
    ebPageContent=$smarty.capture.ebClientHomeContent
}
