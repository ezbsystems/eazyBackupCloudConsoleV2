{if $overdueinvoice}
    {include file="$template/includes/alert.tpl" type="warning" msg=$LANG.upgradeerroroverdueinvoice}
{elseif $existingupgradeinvoice}
    {include file="$template/includes/alert.tpl" type="warning" msg=$LANG.upgradeexistingupgradeinvoice}
{elseif $upgradenotavailable}
    {include file="$template/includes/alert.tpl" type="warning" msg=$LANG.upgradeNotPossible textcenter=true}
{/if}

{if $overdueinvoice}

    <p>
        <a href="clientarea.php?action=productdetails&id={$id}" class="btn btn-default">{$LANG.clientareabacklink}</a>
    </p>

{elseif $existingupgradeinvoice}

    <p>
        <a href="clientarea.php?action=productdetails&id={$id}" class="btn btn-default btn-lg">{$LANG.clientareabacklink}</a>
        <a href="submitticket.php" class="btn btn-default btn-lg">{$LANG.submitticketdescription}</a>
    </p>

{elseif $upgradenotavailable}

    <p>
        <a href="clientarea.php?action=productdetails&id={$id}" class="btn btn-default btn-lg">{$LANG.clientareabacklink}</a>
        <a href="submitticket.php" class="btn btn-default btn-lg">{$LANG.submitticketdescription}</a>
    </p>

{else}

    {if $type eq "package"}

        <p>{$LANG.upgradechoosepackage}</p>

        <p>{$LANG.upgradecurrentconfig}:<br/><strong>{$groupname} - {$productname}</strong>{if $domain} ({$domain}){/if}</p>

        <p>{$LANG.upgradenewconfig}:</p>

        <table class="table table-striped">
            {foreach key=num item=upgradepackage from=$upgradepackages}
                <tr>
                    <td>
                        <strong>
                            {$upgradepackage.groupname} - {$upgradepackage.name}
                        </strong>
                        <br />
                        {$upgradepackage.description}
                    </td>
                    <td width="300" class="text-center">
                        <form method="post" action="{$smarty.server.PHP_SELF}">
                            <input type="hidden" name="step" value="2">
                            <input type="hidden" name="type" value="{$type}">
                            <input type="hidden" name="id" value="{$id}">
                            <input type="hidden" name="pid" value="{$upgradepackage.pid}">
                            <div class="form-group">
                                {if $upgradepackage.pricing.type eq "free"}
                                    {$LANG.orderfree}<br />
                                    <input type="hidden" name="billingcycle" value="free">
                                {elseif $upgradepackage.pricing.type eq "onetime"}
                                    {$upgradepackage.pricing.onetime} {$LANG.orderpaymenttermonetime}
                                    <input type="hidden" name="billingcycle" value="onetime">
                                {elseif $upgradepackage.pricing.type eq "recurring"}
                                    <select name="billingcycle" class="form-control">
                                        {if $upgradepackage.pricing.monthly}<option value="monthly">{$upgradepackage.pricing.monthly}</option>{/if}
                                        {if $upgradepackage.pricing.quarterly}<option value="quarterly">{$upgradepackage.pricing.quarterly}</option>{/if}
                                        {if $upgradepackage.pricing.semiannually}<option value="semiannually">{$upgradepackage.pricing.semiannually}</option>{/if}
                                        {if $upgradepackage.pricing.annually}<option value="annually">{$upgradepackage.pricing.annually}</option>{/if}
                                        {if $upgradepackage.pricing.biennially}<option value="biennially">{$upgradepackage.pricing.biennially}</option>{/if}
                                        {if $upgradepackage.pricing.triennially}<option value="triennially">{$upgradepackage.pricing.triennially}</option>{/if}
                                    </select>
                                {/if}
                            </div>
                            <input type="submit" value="{$LANG.upgradedowngradechooseproduct}" class="btn btn-primary btn-block" />
                        </form>
                    </td>
                </tr>
            {/foreach}
        </table>

    {elseif $type eq "configoptions"}
        <style>
            tr {
                height: 48px;
            }

            tr.disabled {
                cursor: not-allowed;
                opacity: 0.2;
            }

            tr.disabled input,
            tr.disabled [data-action] {
                pointer-events: none;
            }

            tbody tr {
                height: 64px;
            }

            th, td {
                font-size: 16px;
            }

            th {
                font-weight: 600;
                text-transform: uppercase;
                text-align: center;
                vertical-align: middle !important;
                background: #efedf4;
                border-bottom: none !important;
            }

            td {
                text-align: center;
                vertical-align: middle !important;
            }

            td:first-child, td:nth-last-child(2) {
                font-weight: 600;
            }

            .table-hover > tbody > tr:hover {
                background-color: #f6f5f9 !important;
            }

            .fas {
                font-size: 16px;
                padding: 0 8px;
                color: #4357AD;
                cursor: pointer;
            }

            .fas.disabled {
                cursor: not-allowed;
                opacity: 0.4;
            }

            .optionstable input[type="submit"] {
                visibility: hidden;
            }

            .optionstable tr:not(.disabled):hover input[type="submit"] {
                visibility: visible;
            }

            .lead strong {
                font-weight: 600;
            }

            .optionstable .label {
                font-weight: 600;
                position: relative;
                border-radius: 4px;
                background-color: #f60 !important;
            }

            @keyframes flash {
                0% {
                    -webkit-transform: scale(1);
                    transform: scale(1);
                }
                50% {
                    -webkit-transform: scale(1.5);
                    transform: scale(1.5);
                }
                100% {
                    -webkit-transform: scale(1);
                    transform: scale(1);
                }
            }

            [data-attr=totalstorage].flash {
                transition: .2s ease-in;
                animation: flash .2s;
                animation-iteration-count: 1;
            }
        </style>

        {if $errormessage}
            {include file="$template/includes/alert.tpl" type="error" errorshtml=$errormessage}
        {/if}

        <p class="lead">Your current plan: <strong>{$maxdevices} {if $maxdevices eq 1}device{else}devices{/if} + {$configoptionstable["storage"]["selectedqty"]}TB addon storage</strong> ({$maxdevices + $configoptionstable["storage"]["selectedqty"]}TB total storage)</p>
        <p class="lead"><small>Your usage: <strong>{$devices} {if $devices eq 1}device{else}devices{/if}</strong>/<strong>{$humanminstorageplan} total storage</strong></small></p>

        <table class="table optionstable table-hover" data-minstorageplan="{$minstorageplan}">
            <thead>
                <tr>
                    <th>Devices</th>
                    <th>Storage Included</th>
                    <th>Addon Storage</th>
                    <th>Total Storage</th>
                    <th>Price</th>
                    <th>&nbsp;</th>
                </tr>
            </thead>

            <tbody>
                {include file="$template/includes/configoptions.tpl" start=1 count=12}
            </tbody>
        </table>

        <div class="row">
            <div class="text-center">
                <button class="btn btn-default" data-action="add-row">More Plans</button>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                const storageConfigOptionId = {$configoptionstable["storage"]["id"]};
                const deviceConfigOptionId = {$configoptionstable["devices"]["id"]};
                const devicePrice = {($configoptionstable["devices"]["price"] * 100)|intval};
                const storagePrice = {($configoptionstable["storage"]["price"] * 100)|intval};
                const minStorage = {$minstorageplan};
                const humanMinStoragePlan = "{$humanminstorageplan}";
                const minDevices = {$devices};
                const deviceLabel = minDevices > 1 ? " devices" : " device";

                {literal}
                $(document).on("click", "[data-action=add]", function(e) {
                    updateRow(this, getAttribute(this, "storage", "count") + 1);
                });

                $(document).on("click", "[data-action=remove]", function(e) {
                    updateRow(this, getAttribute(this, "storage", "count") - 1);
                });

                $(document).on(
                    "webkitAnimationEnd oanimationend msAnimationEnd animationend",
                    "[data-attr=totalstorage]",
                    function() {
                        $(this).removeClass("flash");
                    }
                );

                $("[data-action=add-row]").click(function(e) {
                    const rows = $(".optionstable tr").length + 1;
                    for (var i = rows + 1; i < rows + 12; i++) {
                        addRow(i);
                    }
                });

                $('.fas.disabled').popover({
                    container: "body",
                    content: "You need at least " + humanMinStoragePlan + " of total storage",
                    placement: "bottom",
                    trigger: "hover",
                });

                $('tr.disabled').popover({
                    container: "body",
                    content: "You're backing up " + minDevices + deviceLabel,
                    placement: "left",
                    trigger: "hover"
                });

                function getAttribute(_this, name, key) {
                    return parseInt($(_this).parents("tr").find("[data-attr=" + name + "]").attr("data-" + key));
                }

                function updateRow(_this, storage) {
                    const row = $(_this).parents("tr");
                    const devices = getAttribute(_this, "devices", "count");
                    const price = ((devices * devicePrice) + (storage * storagePrice)) / 100;
                    const totalStorage = devices + storage;

                    if (totalStorage >= minStorage && storage >= 0) {
                        row.find("[data-attr=storage]").text(storage + " TB");
                        row.find("[data-attr=storage]").attr("data-count", storage);
                        row.find("[data-attr=totalstorage]").text((storage + devices) + " TB");
                        row.find("[data-attr=price]").text(price.toLocaleString("en-US", {style: "currency", currency: "USD"}) + "/mo");
                        row.find("[name='configoption[" + storageConfigOptionId + "]']").val(storage);
                        row.find("[data-attr=totalstorage]").addClass("flash");
                    }

                    if (storage <= 0 || totalStorage <= minStorage) {
                        var button = row.find("[data-action=remove]");

                        if (!button.hasClass("disabled")) {
                            button.addClass("disabled");

                            if (storage > 0 && totalStorage < minStorage) {
                                button.popover({
                                    container: "body",
                                    content: "You need at least " + humanMinStoragePlan + " of total storage",
                                    placement: "bottom",
                                    trigger: "hover",
                                });
                            }
                        }
                    } else {
                        var button = row.find("[data-action=remove]");
                        button.removeClass("disabled");
                        button.popover("destroy");
                    }
                }

                function addRow(i) {
                    const row = $(".optionstable tr").last().clone();

                    row.find("[data-attr=devices]").attr("data-count", i);
                    row.find("[data-attr=devices]").text(i + " devices");
                    row.find("[data-attr=devicestorage]").text(i + " TB");
                    row.find("[name='configoption[" + deviceConfigOptionId + "]']").val(i);

                    row.appendTo($(".optionstable tbody"));
                    updateRow($(".optionstable [data-attr=storage]").last(), 0);
                }

                {/literal}
            });
        </script>
    {/if}
{/if}
