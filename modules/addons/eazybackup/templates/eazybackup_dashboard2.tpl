<script>
    var whmcsBaseUrl = "{\WHMCS\Utility\Environment\WebHelper::getBaseUrl()}";
</script>



<link href="/resources/css/bootstrap.min.css" rel="stylesheet">
<script src="/resources/js/bootstrap.bundle.min.js"></script>

<link href="/modules/addons/eazybackup/templates/assets/css/dashboard_styles.css" rel="stylesheet">





<div class="dashboard-content-inner">                
<div class="plan-list-box">
    <div class="row">
        <div class="col-md-12">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="my-2">Usage Summary</h4>
                    <span>Current Period:</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Current Usage Column -->
                        <div class="col mb-2">
                            <div class="d-flex align-items-start"> <!-- Use 'd-flex' to create a flex container and 'align-items-start' to align items at the top -->
                                <div class="icon-box me-2"> <!-- 'me-2' adds margin to the right of the icon box -->
                                    <i class="bi bi-bar-chart bucket-icon"></i>
                                </div>
                                <div>
                                    <h5>Total Accounts</h5>
                                    <span class="inner-numbers">{$summaryData.totalAccounts}</span><br />   
                                    <span>as of </span>
                                </div>
                            </div>
                        </div>
                        <!-- Total Buckets Column -->
                        <div class="col mb-2">
                            <div class="d-flex align-items-start">
                                <div class="icon-box me-2">
                                    <i class="bi bi-bucket bucket-icon"></i>
                                </div>
                                <div>
                                    <h5>Total Devices</h5>                                            
                                    <span class="inner-numbers">{$summaryData.totalDevices}</span>
                                </div>     
                            </div>                                                                              
                        </div>
                        <!-- Projected Usage Column -->
                        <div class="col mb-2">
                            <div class="d-flex align-items-start">
                                <div class="icon-box me-2">
                                    <i class="bi bi-boxes bucket-icon"></i>
                                </div>
                                <div>
                                    <h5>Total Objects</h5>                                            
                                    <span class="inner-numbers">Usage</span><br />   
                                    <span>as of </span>   
                                </div>
                            </div>                       
                        </div>
                    </div>                                    
                </div>
            </div>
        </div>    
    </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card h-100">                                   
                    <div class="card-body">
                        <div class="row">                                        
                            <div class="col-md-12 mb-4">
                                <div class="d-flex align-items-start mb-2">
                                    <div class="icon-box me-2">
                                        <i class="bi bi-bucket bucket-icon"></i>
                                    </div>
                                    <div>
                                        <h4>Buckets by Size</h4>
                                        <span class="inner-numbers">Top 10</span>
                                    </div>
                                </div>
                            </div>                                        
                            <!-- bucket list -->
                            <div>                                            
                                <ul class="list-group">

                                </ul>
                            </div>
                        </div> 
                    </div> 
                </div> 
            </div>

            <div class="col-md-8 mb-3">
                <div class="card h-100">
                    <!-- <div class="card-header">
                    Data Egress
                    </div> -->
                    <div class="card-body">
                        <div class="row">
                            <!-- Column 1 for existing bucket information -->
                            <div class="col-md-4">
                                <div class="d-flex align-items-start mb-2">
                                    <div class="icon-box me-2">                                                    
                                        <i class="bi bi-bar-chart bucket-icon"></i>
                                    </div>
                                    <div>
                                        <h4>Usage</h4>
                                        <span class="inner-numbers">Usage Numbers</span>
                                    </div>
                                </div>
                            </div>                                         
                            <div id="sizeStatsChart"></div>                                       
                        </div> 
                    </div> 
                </div>
            </div>                      
        </div>

        <!-- Start Second Row-->

        <div class="row">
            <div class="col-md-6 mb-3">
            <div class="card h-100">                            
                <div class="card-body">
                    <div class="row">
                        <!-- Column 1 -->
                        <div class="col-md-12 mb-4">
                            <div class="d-flex align-items-start">
                                <div class="icon-box me-2">              
                                    <i class="bi bi-cloud-upload bucket-icon"></i>
                                </div>
                                <div>
                                    <h4>Data Ingress</h4>
                                    <span class="inner-numbers">Usage</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <select onchange="updateChart()">
                                <option value="hourly">24 Hours</option>
                                <option value="daily">7 Days</option>
                                <option value="weekly">2 Weeks</option>
                                <option value="monthly">Month</option>
                            </select>
                            <div class="recent-report__chart">
                                <div id="bytesReceivedChart"></div>
                            </div>
                        </div>
                        
                        
                    </div> <!-- End of row -->
                </div> <!-- End of card-body -->
            </div> <!-- End of card -->

            </div>
            <div class="col-md-6 mb-3">
            <div class="card h-100">                    
                <div class="card-body">
                    <div class="row">
                        <!-- Column 1 -->
                        <div class="col-md-12 mb-4">
                            <div class="d-flex align-items-start">
                                <div class="icon-box me-2">       
                                    <i class="bi bi-cloud-download bucket-icon"></i>
                                </div>
                                <div>
                                    <h4>Data Egress</h4>
                                    <span class="inner-numbers">Usage</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <select onchange="updateChart()">
                                <option value="hourly">24 Hours</option>
                                <option value="daily">7 Days</option>
                                <option value="weekly">2 Weeks</option>
                                <option value="monthly">Month</option>
                            </select>
                            <div class="recent-report__chart">
                                <div id="BytesSentChart"></div>
                            </div>
                        </div>
                        
                        
                    </div> <!-- End of row -->
                </div> 
            </div>
            </div>                      
        </div>                

</div>          