<div class="row">
    <div class="col-md-12">
        <div class="page-header">
            <h2>Customer Management</h2>
            <div class="pull-right">
                <a href="index.php?m=mspconnect&page=customers&sub_action=add" class="btn btn-success">
                    <i class="fa fa-user-plus"></i> Add New Customer
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-users"></i> Your Customers ({$customers|@count})
                </h3>
                <div class="panel-tools">
                    <div class="input-group" style="width: 200px; float: right; margin-top: -5px;">
                        <input type="text" class="form-control" id="customerSearch" placeholder="Search customers...">
                        <span class="input-group-addon">
                            <i class="fa fa-search"></i>
                        </span>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                {if $customers}
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="customersTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Company</th>
                                    <th>Services</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach $customers as $customer}
                                    <tr>
                                        <td>
                                            <strong>{$customer->first_name} {$customer->last_name}</strong>
                                            {if $customer->last_login}
                                                <br><small class="text-muted">
                                                    Last login: {$customer->last_login|date_format:"%M %d, %Y"}
                                                </small>
                                            {/if}
                                        </td>
                                        <td>
                                            <a href="mailto:{$customer->email}">
                                                {$customer->email}
                                            </a>
                                        </td>
                                        <td>
                                            {$customer->company|default:"—"}
                                        </td>
                                        <td>
                                            <span class="badge badge-primary">{$customer->service_count}</span>
                                            {if $customer->service_count > 0}
                                                <br><small>
                                                    <a href="index.php?m=mspconnect&page=customer&id={$customer->id}">
                                                        View services
                                                    </a>
                                                </small>
                                            {/if}
                                        </td>
                                        <td>
                                            {if $customer->status == 'active'}
                                                <span class="label label-success">Active</span>
                                            {elseif $customer->status == 'inactive'}
                                                <span class="label label-default">Inactive</span>
                                            {elseif $customer->status == 'suspended'}
                                                <span class="label label-warning">Suspended</span>
                                            {/if}
                                        </td>
                                        <td>
                                            {$customer->created_at|date_format:"%M %d, %Y"}
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="index.php?m=mspconnect&page=customer&id={$customer->id}" 
                                                   class="btn btn-default" title="View Details">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                                <a href="index.php?m=mspconnect&page=customers&sub_action=edit&id={$customer->id}" 
                                                   class="btn btn-primary" title="Edit">
                                                    <i class="fa fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-info" 
                                                        onclick="sendPasswordReset({$customer->id})" title="Send Password Reset">
                                                    <i class="fa fa-key"></i>
                                                </button>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-default dropdown-toggle" 
                                                            data-toggle="dropdown" title="More Actions">
                                                        <i class="fa fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-right">
                                                        <li>
                                                            <a href="#" onclick="loginAsCustomer({$customer->id})">
                                                                <i class="fa fa-sign-in"></i> Login as Customer
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a href="index.php?m=mspconnect&page=invoices&customer_id={$customer->id}">
                                                                <i class="fa fa-file-invoice"></i> View Invoices
                                                            </a>
                                                        </li>
                                                        <li class="divider"></li>
                                                        <li>
                                                            <a href="#" onclick="suspendCustomer({$customer->id})" 
                                                               class="text-warning">
                                                                <i class="fa fa-pause"></i> Suspend
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a href="#" onclick="deleteCustomer({$customer->id})" 
                                                               class="text-danger">
                                                                <i class="fa fa-trash"></i> Delete
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                {else}
                    <div class="text-center" style="padding: 40px;">
                        <i class="fa fa-users fa-3x text-muted"></i>
                        <h4>No Customers Yet</h4>
                        <p class="text-muted">Get started by adding your first customer.</p>
                        <a href="index.php?m=mspconnect&page=customers&sub_action=add" class="btn btn-success">
                            <i class="fa fa-user-plus"></i> Add First Customer
                        </a>
                    </div>
                {/if}
            </div>
        </div>
    </div>
</div>

<!-- Customer Actions Modal -->
<div class="modal fade" id="customerActionModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
                <h4 class="modal-title">Confirm Action</h4>
            </div>
            <div class="modal-body">
                <p id="modalMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmAction">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize search functionality
    $('#customerSearch').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('#customersTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
    
    // Make table sortable
    $('#customersTable').DataTable({
        "pageLength": 25,
        "order": [[ 5, "desc" ]], // Sort by registration date
        "columnDefs": [
            { "orderable": false, "targets": 6 } // Disable sorting on actions column
        ]
    });
});

function sendPasswordReset(customerId) {
    $('#modalMessage').text('Are you sure you want to send a password reset email to this customer?');
    $('#confirmAction').off('click').on('click', function() {
        $.post('/modules/addons/mspconnect/api/customer_actions.php', {
            action: 'send_password_reset',
            customer_id: customerId
        }, function(response) {
            if (response.success) {
                showAlert('success', 'Password reset email sent successfully.');
                $('#customerActionModal').modal('hide');
            } else {
                showAlert('danger', 'Error: ' + response.message);
            }
        });
    });
    $('#customerActionModal').modal('show');
}

function loginAsCustomer(customerId) {
    window.open('/modules/addons/mspconnect/portal/login.php?auto_login=' + customerId, '_blank');
}

function suspendCustomer(customerId) {
    $('#modalMessage').text('Are you sure you want to suspend this customer? They will not be able to access their account.');
    $('#confirmAction').off('click').on('click', function() {
        $.post('/modules/addons/mspconnect/api/customer_actions.php', {
            action: 'suspend',
            customer_id: customerId
        }, function(response) {
            if (response.success) {
                showAlert('success', 'Customer suspended successfully.');
                location.reload();
            } else {
                showAlert('danger', 'Error: ' + response.message);
            }
        });
    });
    $('#customerActionModal').modal('show');
}

function deleteCustomer(customerId) {
    $('#modalMessage').text('Are you sure you want to delete this customer? This action cannot be undone and will remove all their data, services, and invoices.');
    $('#confirmAction').removeClass('btn-primary').addClass('btn-danger').text('Delete');
    $('#confirmAction').off('click').on('click', function() {
        $.post('/modules/addons/mspconnect/api/customer_actions.php', {
            action: 'delete',
            customer_id: customerId
        }, function(response) {
            if (response.success) {
                showAlert('success', 'Customer deleted successfully.');
                location.reload();
            } else {
                showAlert('danger', 'Error: ' + response.message);
            }
        });
    });
    $('#customerActionModal').modal('show');
}

function showAlert(type, message) {
    var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible" role="alert">' +
        '<button type="button" class="close" data-dismiss="alert">' +
        '<span>&times;</span></button>' + message + '</div>';
    
    $('.page-header').after(alertHtml);
    
    // Auto-hide success alerts after 5 seconds
    if (type === 'success') {
        setTimeout(function() {
            $('.alert-success').fadeOut();
        }, 5000);
    }
}
</script>

<style>
.page-header {
    position: relative;
}

.panel-tools {
    position: absolute;
    right: 15px;
    top: 10px;
}

.btn-group-sm > .btn {
    margin-right: 2px;
}

.badge {
    background-color: #337ab7;
}

.table > tbody > tr:hover {
    background-color: #f5f5f5;
}

.dropdown-menu-right {
    right: 0;
    left: auto;
}

@media (max-width: 768px) {
    .panel-tools {
        position: static;
        float: none !important;
        margin-top: 10px !important;
        width: 100% !important;
    }
    
    .panel-tools .input-group {
        width: 100% !important;
    }
}
</style> 