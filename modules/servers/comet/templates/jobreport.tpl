<style>
    table {
        width: 100%;
    }
</style>

<div id="jobreport">

    <h2>Job Details</h2>

    <div class="row">
        <div class="col-md-3">
            <h4>Username</h4>
            <p>{$username}</p>

            <h4>Device</h4>
            <p>{$device}</p>

            <h4>Storage Vault</h4>
            <p>{$storagevault}</p>
        </div>

        <div class="col-md-3">
            <h4>Protected Item</h4>
            <p>{$protecteditem}</p>

            <h4>Type</h4>
            <p>{$type}</p>

            <h4>Stats</h4>
            <p>{$stats}</p>
        </div>

        <div class="col-md-3">
            <h4>Status</h4>
            <p>{$status}</p>

            <h4>Started</h4>
            <p>{$started}</p>

            <h4>Stopped</h4>
            <p>{$stopped}</p>
        </div>

        <div class="col-md-3">
            <h4>Total size</h4>
            <p>{$totalsize}</p>

            <h4>Uploaded</h4>
            <p>{$uploaded}</p>

            <h4>Downloaded</h4>
            <p>{$downloaded}</p>
        </div>
    </div>

    <hr>

    <h2>Job Report</h2>

    <table class="table-striped table-condensed">
        <thead>
        <th>Time</th>
        <th>Status</th>
        <th>Message</th>
        </thead>
        <tbody>
        {foreach $rows as $row}
            <tr>
                <td>{$row[0]}</td>
                <td>{$row[1]}</td>
                <td>{$row[2]}</td>
            </tr>
        {/foreach}
        </tbody>
    </table>
</div>

<p class="text-center">
    <a href="clientarea.php?action=productdetails&amp;id={$id}" class="btn btn-primary">{$LANG.clientareabacklink}</a>
</p>
