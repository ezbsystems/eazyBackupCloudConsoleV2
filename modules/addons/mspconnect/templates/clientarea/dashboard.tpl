<div class="row">
    <div class="col-md-12">
        <h2>MSP Dashboard</h2>
        
        {if !$stripe_connected}
            <div class="alert alert-warning">
                <h4><i class="fa fa-exclamation-triangle"></i> Stripe Account Required</h4>
                <p>To start accepting payments from your customers, you need to connect your Stripe account.</p>
                <a href="index.php?m=mspconnect&page=stripe-connect" class="btn btn-primary">
                    <i class="fa fa-credit-card"></i> Connect Stripe Account
                </a>
            </div>
        {/if}
    </div>
</div>

<div class="row">
    <!-- Statistics Cards -->
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-body text-center">
                <h3 class="text-primary">{$stats.total_customers}</h3>
                <p>Total Customers</p>
                <a href="index.php?m=mspconnect&page=customers" class="btn btn-sm btn-default">
                    <i class="fa fa-users"></i> Manage
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-body text-center">
                <h3 class="text-success">{$stats.total_services}</h3>
                <p>Active Services</p>
                <a href="index.php?m=mspconnect&page=customers" class="btn btn-sm btn-default">
                    <i class="fa fa-cogs"></i> View
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-body text-center">
                <h3 class="text-info">${$stats.monthly_revenue}</h3>
                <p>This Month's Revenue</p>
                <a href="index.php?m=mspconnect&page=invoices" class="btn btn-sm btn-default">
                    <i class="fa fa-chart-line"></i> Details
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-body text-center">
                <h3 class="text-warning">{$stats.pending_invoices}</h3>
                <p>Pending Invoices</p>
                <a href="index.php?m=mspconnect&page=invoices" class="btn btn-sm btn-default">
                    <i class="fa fa-file-invoice"></i> Review
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Quick Actions -->
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-bolt"></i> Quick Actions</h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-6">
                        <a href="index.php?m=mspconnect&page=customers&sub_action=add" class="btn btn-success btn-block">
                            <i class="fa fa-user-plus"></i><br>
                            Add Customer
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="index.php?m=mspconnect&page=plans&sub_action=add" class="btn btn-primary btn-block">
                            <i class="fa fa-plus-circle"></i><br>
                            Create Plan
                        </a>
                    </div>
                </div>
                <div class="row" style="margin-top: 15px;">
                    <div class="col-md-6">
                        <a href="index.php?m=mspconnect&page=settings" class="btn btn-default btn-block">
                            <i class="fa fa-cog"></i><br>
                            Settings
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="index.php?m=mspconnect&page=email-templates" class="btn btn-info btn-block">
                            <i class="fa fa-envelope"></i><br>
                            Email Templates
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-clock"></i> Recent Activity</h3>
            </div>
            <div class="panel-body">
                {if $recent_activity}
                    <div class="activity-feed" style="max-height: 300px; overflow-y: auto;">
                        {foreach $recent_activity as $activity}
                            <div class="activity-item" style="border-bottom: 1px solid #eee; padding: 10px 0;">
                                <small class="text-muted pull-right">
                                    {$activity->created_at|date_format:"%M %d, %Y %H:%M"}
                                </small>
                                <div>
                                    <strong>{$activity->action|replace:"_":" "|ucwords}</strong>
                                </div>
                                <div class="text-muted">
                                    {$activity->description}
                                </div>
                            </div>
                        {/foreach}
                    </div>
                {else}
                    <p class="text-muted">No recent activity to display.</p>
                {/if}
            </div>
        </div>
    </div>
</div>

{if $stripe_connected}
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-credit-card"></i> Payment Processing Status</h3>
            </div>
            <div class="panel-body">
                <div class="alert alert-success">
                    <i class="fa fa-check-circle"></i> Your Stripe account is connected and ready to process payments.
                    <div class="pull-right">
                        <a href="index.php?m=mspconnect&page=stripe-connect" class="btn btn-sm btn-default">
                            <i class="fa fa-external-link"></i> Manage Stripe Account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{/if}

<!-- Navigation Menu -->
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-body">
                <nav class="navbar navbar-default" style="margin-bottom: 0;">
                    <div class="container-fluid">
                        <ul class="nav navbar-nav">
                            <li class="active">
                                <a href="index.php?m=mspconnect&page=dashboard">
                                    <i class="fa fa-dashboard"></i> Dashboard
                                </a>
                            </li>
                            <li>
                                <a href="index.php?m=mspconnect&page=customers">
                                    <i class="fa fa-users"></i> Customers
                                </a>
                            </li>
                            <li>
                                <a href="index.php?m=mspconnect&page=plans">
                                    <i class="fa fa-list"></i> Service Plans
                                </a>
                            </li>
                            <li>
                                <a href="index.php?m=mspconnect&page=invoices">
                                    <i class="fa fa-file-invoice"></i> Invoices
                                </a>
                            </li>
                            <li class="dropdown">
                                <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                    <i class="fa fa-cog"></i> Settings <span class="caret"></span>
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a href="index.php?m=mspconnect&page=settings">General Settings</a></li>
                                    <li><a href="index.php?m=mspconnect&page=branding">Company Branding</a></li>
                                    <li><a href="index.php?m=mspconnect&page=email-templates">Email Templates</a></li>
                                    <li><a href="index.php?m=mspconnect&page=stripe-connect">Stripe Integration</a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>
        </div>
    </div>
</div>

<style>
.activity-item:last-child {
    border-bottom: none !important;
}

.btn-block {
    padding: 15px;
    margin-bottom: 10px;
}

.panel-body .btn-block i {
    display: block;
    font-size: 18px;
    margin-bottom: 5px;
}

.navbar {
    border: none;
    background: none;
}

.navbar-nav > li > a {
    padding: 10px 15px;
}

.stats-card {
    text-align: center;
    padding: 20px;
}

.stats-card h3 {
    margin: 0 0 10px 0;
    font-size: 2.5em;
}
</style> 