<div class="row">
    <div class="col-lg-10">{$breadcrumbs}</div>
    <div class="col-lg-2">
        <a href="{$modurl}&action=additem&groupid={$groupinfo.id}" class="btn btn-sm btn-block btn-primary"><i class="fa fa-plus"></i> New Menu Item</a>
    </div>
</div>

<div class="clear-line-20"></div>

<div class="row">
    <div class="col-lg-12">
        
        {function name=parsemenuchildren menuitems=$items}
        <ol{if $menuid!=''} id="{$menuid}"{/if}{if $menuclass!=''} class="{$menuclass}"{/if}>
            {foreach item=item from=$menuitems}
            <li id="list_{$item.id}">
                <div class="menu-item">
                    <div class="text-left pull-left">
                        <span>{$item.title}</span>
                    </div>
                    <div class="row pull-right">
                        <div class="col-lg-9 text-left">
                            <span class="text-mute" title="{$item.fullurl}">
                                {if $item.urltype!='customurl'}../{/if}{$item.fullurl|truncate:60}
                            </span>
                        </div>
                        <div class="col-lg-1 text-center">
                            {if $item.accesslevel=='1'}
                            <span data-toggle="tooltip" title="{$item.accesslevelexplain}"><i class="fa fa-circle always-on"></i></span>
                            {else if $item.accesslevel=='0'}
                            <span data-toggle="tooltip" title="{$item.accesslevelexplain}"><i class="fa fa-circle always-off"></i></span>
                            {else}
                            <span data-toggle="tooltip" title="{$item.accesslevelexplain}"><i class="fa fa-circle condition-apply"></i></span>
                            {/if}
                        </div>
                        <div class="col-lg-2 text-center">
                            <a href="{$modurl}&action=edititem&itemid={$item.id}" class="btn btn-sm btn-warning" title="Update Menu Item"><i class="fa fa-pencil"></i></a>
                            <a href="#DeleteItem_{$item.id}" data-toggle="modal" class="btn btn-sm btn-danger" title="Delete Menu Item"><i class="fa fa-times"></i></a>
                        </div>
                    </div>
                </div>
                <div class="clear"></div>
                
                {parsemenuchildren menuitems=$item.children menuid="" menuclass=""}
            
            </li>            
            {/foreach}
        </ol>
        {/function}
        
        {* Loop Menu *}
        {parsemenuchildren menuitems=$items menuid="sortable-menu" menuclass="menu-items-list sortable"}
        
        {* No Menu Items Created Yet *}
        {if $countitems=='0'}
        <p class="text-center">No Menu Items In This Group Created Yet.</p>
        {/if}
        
</div>
</div>


{* Item Delete Modal *}
{foreach item=item from=$items}
<div id="DeleteItem_{$item.id}" class="modal fade modal-delete">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{$modurl}&action=deleteitem&id={$item.id}&groupid={$item.groupid}" method="post">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Delete "{$item.title}"</h4>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this item "<b>{$item.title}</b>"?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-sm btn-danger">Delete Menu Item</button>
            </div>
            </form>
        </div>
    </div>
</div>
    {foreach item=level2 from=$item.children}
    <div id="DeleteItem_{$level2.id}" class="modal fade modal-delete">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{$modurl}&action=deleteitem&id={$level2.id}&groupid={$item.groupid}" method="post">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Delete "{$level2.title}"</h4>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this item "<b>{$level2.title}</b>"?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-sm btn-danger">Delete Menu Item</button>
                </div>
                </form>
            </div>
        </div>
    </div>
        {foreach item=level3 from=$level2.children}
        <div id="DeleteItem_{$level3.id}" class="modal fade modal-delete">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{$modurl}&action=deleteitem&id={$level3.id}&groupid={$item.groupid}" method="post">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title">Delete "{$level3.title}"</h4>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this item "<b>{$level3.title}</b>"?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-sm btn-danger">Delete Menu Item</button>
                    </div>
                    </form>
                </div>
            </div>
        </div>
            {foreach item=level4 from=$level3.children}
            <div id="DeleteItem_{$level4.id}" class="modal fade modal-delete">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="{$modurl}&action=deleteitem&id={$level4.id}&groupid={$item.groupid}" method="post">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            <h4 class="modal-title">Delete "{$level4.title}"</h4>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to delete this item "<b>{$level4.title}</b>"?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-sm btn-danger">Delete Menu Item</button>
                        </div>
                        </form>
                    </div>
                </div>
            </div>
            {/foreach}
        {/foreach}
    {/foreach}
{/foreach}




{* Attributes Form Syntax *}
<div id="attributesformsyntax" style="display:none;">
    <div class="row attributeform">
        <div class="col-lg-4">
            <input type="text" name="attrnames[]" class="form-control input-sm" placeholder="Name">
        </div>
        <div class="col-lg-4">
            <input type="text" name="attrvalues[]" class="form-control input-sm" placeholder="Value">
        </div>
        <div class="col-lg-1">
            <button type="button" class="btn btn-sm btn-danger delete-attribute-form" onclick="deleteAttribute(this);"><i class="fa fa-times"></i></button>
        </div>
    </div>
</div>