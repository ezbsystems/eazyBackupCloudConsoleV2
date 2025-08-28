
    <div class="service-card table-container clearfix">
        <table id="tableServicesList" class="table table-list">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Domain/IP</th>
                    <th>Signup Date</th>
                    <th>Next Due Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                {foreach $services as $service}
                <tr>
                    <td>{$service->productname}<br>
                        <strong>Management Console:</strong> {$service->domain}<br>
                        <strong>Username:</strong> {$service->username}<br>
                        <strong>Password:</strong>
                        <span id="password-{$service->id}">******</span>
                        <button onclick="decryptPassword({$service->id})" class="btn btn-primary btn-sm">Decrypt</button>
                        <button onclick="copyToClipboard('password-{$service->id}')" class="btn btn-secondary btn-sm">Copy</button>
                    </td>
                    <td>{$service->domain}</td>
                    <td>{$service->regdate}</td>
                    <td>{$service->nextduedate}</td>
                    <td>{$service->amount}</td>
                    <td>{$service->domainstatus}</td>
                </tr>
                {/foreach}
            </tbody>
        </table>
        <div class="text-center" id="tableLoading" style="display:none;">
            <p><i class="fas fa-spinner fa-spin"></i> Loading...</p>
        </div>
    </div>

