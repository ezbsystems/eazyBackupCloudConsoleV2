<div class="row">
    <div class="col-lg-12">{$breadcrumbs}</div>
</div>

<div class="clear-line-10"></div>

<form action="{$modurl}&action=doadditem" method="post" autocomplete="off">
<input type="hidden" name="groupid" value="{$groupid}">
<div class="row">
    <div class="col-lg-8">
        <div class="menumanager-tabs">
            <ul class="nav nav-tabs" role="tablist">
                <li role="presentation" class="active">
                    <a href="#general" aria-controls="general" role="tab" data-toggle="tab">General</a>
                </li>
                <li role="presentation">
                    <a href="#translations" aria-controls="translations" role="tab" data-toggle="tab">Translations</a>
                </li>
                <li role="presentation">
                    <a href="#attributes" aria-controls="attributes" role="tab" data-toggle="tab">Attributes</a>
                </li>
            </ul>
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane active" id="general">
                    <div class="form-group">
                        <label for="parentid">Parent Menu</label>
                        <div class="row">
                            <div class="col-lg-6">
                                <select name="parentid" id="parentid" class="form-control input-sm">
                                    <option value="0">No Parent</option>
                                    {foreach from=$parentlist item=item}
                                    <option value="{$item.id}">{$item.title}</option>
                                        {foreach from=$item.sub item=level2}
                                        <option value="{$level2.id}">-- -- {$level2.title}</option>
                                            {foreach from=$level2.sub item=level3}
                                            <option value="{$level3.id}">-- -- -- -- {$level3.title}</option>
                                            {/foreach}
                                        {/foreach}
                                    {/foreach}
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="title">Title</label>
                        <div class="row">
                            <div class="col-lg-6">
                                <input type="text" name="title" id="title" class="form-control input-sm" placeholder="">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="accesslevel">Status</label>
                        <div class="row">
                            <div class="col-lg-6">
                                <select name="accesslevel" id="accesslevel" class="form-control input-sm">
                                    <optgroup label="Common:">
                                        <option value="1">Always enabled</option>
                                        <option value="0">Always disabled</option>
                                        <option value="2">Enabled for visitors only</option>
                                        <option value="3">Enabled for clients only</option>
                                        <option value="14">Enabled for Affiliates only</option>
                                    </optgroup>
                                    <optgroup label="Enabled for clients if they have:">
                                        <option value="4">Active Product(s)</option>
                                        <option value="5">Overdue Invoice(s)</option>
                                        <option value="6">Active Ticket(s)</option>
                                        <option value="7">Active Domain(s)</option>
                                    </optgroup>
                                    <optgroup label="Enabled if one of this settings is available:">
                                        <option value="8">Manage Credit Cards</option>
                                        <option value="9">Add Funds</option>
                                        <option value="10">Mass Pay</option>
                                        <option value="11">Domain Registration</option>
                                        <option value="12">Domain Transfer</option>
                                        <option value="13">Affiliates</option>
                                    </optgroup>
                                    <option value="15"{if $iteminfo.accesslevel=='15'} selected{/if}>Client is in on of the selected groups</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div id="client-groups-div" class="form-group" style="display:none">
                        <div class="row">
                            <div class="col-lg-6">
                                <label for="clientgroups">Client Groups</label>
                                <select name="clientgroups[]" id="clientgroups" class="form-control" multiple>
                                    {foreach item=group from=$clientgroups}
                                    <option value="{$group.id}">{$group.groupname}</option>
                                    {/foreach}
                                </select>
                                <div class="help-block small">Click <kbd>CTRL</kbd> + <kbd>Click</kbd> to select multiple groups.<br>if no groups selected it will be considered "Enabled for clients only" without restrictions.</div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="target">Target URL</label>
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <select name="urltype" id="urltype" class="form-control input-sm">
                                        <option value="clientarea-off">Core URL Available To Anyone</option>
                                        <option value="clientarea-on">Core URL Available To Logged-in Clients Only</option>
                                        <option value="support">Support Department</option>
                                        <option value="customurl">External/Custom URL</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <select name="clientarea-off" id="clientarea-off" class="form-control input-sm" style="display:;">
                                        <option value="index.php">Portal Home</option>
                                        <option value="downloads.php">Downloads</option>
                                        <option value="knowledgebase.php">Knowledgebase</option>
                                        <option value="domainchecker.php">Domain Checker</option>
                                        <option value="cart.php">Order</option>
                                        <option value="announcements.php">Announcements</option>
                                        <option value="submitticket.php">Open New Ticket</option>
                                        <option value="affiliates.php">Affiliates</option>
                                        <option value="contact.php">Contact Us</option>
                                        <option value="login.php">Login Page</option>
                                        <option value="register.php">Register Account</option>
                                        <option value="pwreset.php">Forgot Password</option>
                                        <option value="cart.php?a=add&domain=register">Register a New Domain</option>
                                        <option value="cart.php?a=add&domain=transfer">Transfer Domains to Us</option>
                                        <option value="cart.php?a=view">View Cart</option>
                                    </select>
                                    
                                    <select name="clientarea-on" id="clientarea-on" class="form-control input-sm" style="display:none;">
                                        <option value="clientarea.php">Client Summary</option>
                                        <option value="networkissues.php">Network Issues</option>
                                        <option value="serverstatus.php">Servers Status</option>
                                        <option value="supporttickets.php">Support Tickets</option>
                                        <option value="clientarea.php?action=products">My Services</option>
                                        <option value="cart.php?gid=addons">View Available Addons</option>
                                        <option value="clientarea.php?action=domains">My Domain</option>
                                        <option value="cart.php?gid=renewals">Renew Domains</option>
                                        <option value="clientarea.php?action=invoices">My Invoices</option>
                                        <option value="clientarea.php?action=creditcard">Manage Credit Card</option>
                                        <option value="clientarea.php?action=addfunds">Add Funds</option>
                                        <option value="clientarea.php?action=quotes">My Quotes</option>
                                        <option value="clientarea.php?action=masspay&all=true">Mass Payment</option>
                                        <option value="clientarea.php?action=details">Edit Account Details</option>
                                        <option value="clientarea.php?action=contacts">Contacts/Sub-Accounts</option>
                                        <option value="clientarea.php?action=emails">Email History</option>
                                        <option value="clientarea.php?action=changepw">Change Password</option>
                                        <option value="clientarea.php?action=security">Security Settings</option>
                                        <option value="logout.php">Logout</option>
                                    </select>
                                    
                                    <select name="support" id="support" class="form-control input-sm" style="display:none;">
                                        {foreach from=$supportdepartments item=department}
                                        <option value="{$department.id}">{$department.name}</option>
                                        {/foreach}
                                    </select>
                                    
                                    <input type="text" name="customurl" id="customurl" class="form-control input-sm" placeholder="Specify anything here to be used as the URL.." style="display:none;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="translations">
                    <div class="clear-line-20"></div>
                    <div class="panel-group" id="translationpanels" role="tablist" aria-multiselectable="true">
                        {foreach from=$languages item=language}
                        <input type="hidden" name="translation_languages[]" value="{$language}">
                        <div class="panel panel-default">
                            <div class="panel-heading" role="tab" id="heading_{$language}">
                                <h4 class="panel-title">
                                    <a role="button" data-toggle="collapse" data-parent="#translationpanels" href="#{$language}" aria-expanded="true" aria-controls="{$language}">
                                        <i class="fa fa-plus"></i> {$language|ucfirst}
                                    </a>
                                </h4>
                            </div>
                            <div id="{$language}" class="panel-collapse collapse" role="tabpanel" aria-labelledby="heading_{$language}">
                                <div class="panel-body">
                                    <div class="form-horizontal">
                                        <div class="form-group">
                                            <label for="translation_title[{$language}]" class="col-sm-2 control-label">Title</label>
                                            <div class="col-sm-10">
                                                <input type="text" name="translation_title[{$language}]" class="form-control" id="translation_title[{$language}]" value="" placeholder="">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {/foreach}
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="attributes">
                    <button type="button" class="btn btn-sm btn-info pull-right add-attribute-form" onclick="addAttribute();"><i class="fa fa-plus"></i> Add More</button>
                    <div class="clear"></div>
                    <div id="attributes-wrap">
                        <div class="row attributeform">
                            <div class="col-lg-4">
                                <input type="text" name="attrnames[]" value="" class="form-control input-sm" placeholder="Name">
                            </div>
                            <div class="col-lg-4">
                                <input type="text" name="attrvalues[]" value="" class="form-control input-sm" placeholder="Value">
                            </div>
                            <div class="col-lg-1">
                                <button type="button" class="btn btn-sm btn-danger delete-attribute-form" onclick="deleteAttribute(this);"><i class="fa fa-times"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="clear-line-20"></div>
                    <div class="alert alert-warning">
                    <small class="help-block">Use this section to define additional attributes for example <code> data-itemid="120" </code> or <code> id="menuitemid" </code>, this attributes will be added to <code>&lt;a&gt;</code>.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-lg-offset-1">
        <div class="panel-group" id="extraoptions" role="tablist" aria-multiselectable="true">
            <div class="panel panel-default">
                <div class="panel-heading" role="tab" id="headingTargetWindow">
                    <h4 class="panel-title">
                        <a role="button" data-toggle="collapse" data-parent="#extraoptions" href="#targetwindow" aria-expanded="true" aria-controls="targetwindow">
                            Target Window
                        </a>
                    </h4>
                </div>
                <div id="targetwindow" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="headingTargetWindow">
                    <div class="panel-body">
                        <small class="help-block">When someone click on that item URL, it will:</small>
                        <label class="label-radio">
                            <input type="radio" name="targetwindow" value="_self" checked="checked">
                            <span>Open in the same Tab/Window</span>
                        </label>
                        <label class="label-radio">
                            <input type="radio" name="targetwindow" value="_blank">
                            <span>Open in new Tab/Window</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading" role="tab" id="headingBadge">
                    <h4 class="panel-title">
                        <a role="button" data-toggle="collapse" data-parent="#extraoptions" href="#badge" aria-expanded="true" aria-controls="badge">
                            Badge
                        </a>
                    </h4>
                </div>
                <div id="badge" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingBadge">
                    <div class="panel-body">
                        <small class="help-block">You can show the number of Unpaid invoices as an example to draw attention:</small>
                        <label class="label-radio">
                            <input type="radio" name="badge" value="totalservices">
                            <span>Total Products/Services</span>
                        </label>
                        <label class="label-radio">
                            <input type="radio" name="badge" value="activeservices">
                            <span>Active Products/Services</span>
                        </label>
                        <label class="label-radio">
                            <input type="radio" name="badge" value="totaldomains">
                            <span>Total Domains</span>
                        </label>
                        <label class="label-radio">
                            <input type="radio" name="badge" value="activedomains">
                            <span>Active Domains</span>
                        </label>
                        <label class="label-radio">
                            <input type="radio" name="badge" value="dueinvoices">
                            <span>Due Invoices</span>
                        </label>
                        <label class="label-radio">
                            <input type="radio" name="badge" value="overdueinvoices">
                            <span>Overdue Invoices</span>
                        </label>
                        <label class="label-radio">
                            <input type="radio" name="badge" value="activetickets">
                            <span>Active Tickets</span>
                        </label>
                        <label class="label-radio">
                            <input type="radio" name="badge" value="creditbalance">
                            <span>Credit Balance</span>
                        </label>
                        <label class="label-radio">
                            <input type="radio" name="badge" value="shoppingcartitems">
                            <span>Shopping Cart Items</span>
                        </label>
                        <label class="label-radio">
                            <input type="radio" name="badge" value="none" checked="checked">
                            <span>None</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading" role="tab" id="headingCSS">
                    <h4 class="panel-title">
                        <a role="button" data-toggle="collapse" data-parent="#extraoptions" href="#css" aria-expanded="true" aria-controls="css">
                            CSS
                        </a>
                    </h4>
                </div>
                <div id="css" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingCSS">
                    <div class="panel-body">
                        <div class="form-group">
                            <label for="css_class">Class</label>
                            <input type="text" name="css_class" id="css_class" value="" placeholder="Specify class name(s).." class="form-control input-sm">
                        </div>
                        <div class="form-group">
                            <label for="css_id">ID</label>
                            <input type="text" name="css_id" id="css_id" value="" placeholder="Specify ID for this menu item.." class="form-control input-sm">
                        </div>
                    </div>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading" role="tab" id="headingIcon">
                    <h4 class="panel-title">
                        <a role="button" data-toggle="collapse" data-parent="#extraoptions" href="#icon" aria-expanded="true" aria-controls="icon">
                            Icon
                        </a>
                    </h4>
                </div>
                <div id="icon" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingIcon">
                    <div class="panel-body">
                        <small class="help-block">You can specify Icon class name here to be displayed in this item</small>
                        <input type="text" name="css_icon" id="css_icon" class="form-control input-sm" placeholder="ex: fa fa-times">
                    </div>
                </div>
            </div>
        </div>
        <div class="btn-group btn-group-sm btn-group-two" role="group" aria-label="...">
            <button type="submit" name="save" value="save" class="btn btn-info">Save</button>
            <button type="submit" name="close" value="close" class="btn btn-primary">Save & Close</button>
        </div>
    </div>
</div>
</form>


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