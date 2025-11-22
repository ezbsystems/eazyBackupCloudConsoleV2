<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>

<div class="nave-top-wgs">
    <nav class="navbar navbar-default">
        <div id="navbarCollapse" class="collapse navbar-collapse">
            <ul class="nav navbar-nav">
                <li class="nav-home nav-item">
                    <a href="{$whmcs}/admin/{$modulelink}" class="home"><i class="wgs-flat-icon fa fa-home"></i> Homepage</a>
                </li>
            </ul>
        </div>
    </nav>
</div>

<div class="content">
    <div class="tablebg">
        <table id="products" class="datatable" width="100%" cellspacing="1" cellpadding="3">
            <thead>
                <tr>
                    <th scope="col">Sr. No.</th>
                    <th scope="col">Product Group</th>
                    <th scope="col">Product Name</th>
                    <th scope="col">Pay Type</th>
                    <th scope="col">Action</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$products item=product key=sno}
                    <tr>
                        <td>{$sno + 1}</td>
                        <td>{$product->productgroup}</td>
                        <td>{$product->productname}</td>
                        <td>{ucwords($product->paytype)}</td>
                        <td>
                            {if $product->pid eq $defaultPlanId}
                                <a class="btn btn-success" href="{$modulelink}&action=setdefault&id={$product->pid}"><i class="fas fa-bookmark"></i> Default Plan</a>
                            {else}
                                <a class="btn btn-primary" href="{$modulelink}&action=setdefault&id={$product->pid}"><i class="far fa-bookmark"></i> Set as default plan</a>
                            {/if}
                        </td>
                    </tr>
                {foreachelse}
                    <tr>
                        <td colspan="5">No Records Found</td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function () {
    jQuery('#products').DataTable();
});
</script>