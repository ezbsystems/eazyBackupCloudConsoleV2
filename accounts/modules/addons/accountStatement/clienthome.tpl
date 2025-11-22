 {if $licence}
     <div class="alert alert-danger">{$message}</div>
 {else}

     <script type="text/javascript"
         src="{$baseurl}/modules/addons/accountStatement/assets/js/jquery-ui-1.12.1/jquery-ui.min.js"></script>

     <link href="{$baseurl}/modules/addons/accountStatement/assets/js/jquery-ui-1.12.1/jquery-ui.min.css" rel="stylesheet"
         type="text/css" />

     {if $template eq 'five'}

         <link href="{$BASE_PATH_CSS}/font-awesome.css" rel="stylesheet" type="text/css" />

         <link href="{$baseurl}/modules/addons/accountStatement/css/fivestyle.css" rel="stylesheet" type="text/css" />

     {/if}

     <script type="text/javascript">
         var datepickerformat = '{$Dateformat}';
         datepickerformat = datepickerformat.toLowerCase();
         jQuery(document).ready(function() {

             jQuery("#startdate").datepicker({
                 dateFormat: datepickerformat
             });

             jQuery("#enddate").datepicker({
                 dateFormat: datepickerformat
             });

             jQuery("#clientarea_filter_button").on("click", function() {
                 if ($("#startdate").val() == "") {
                     $("#startdate").focus();
                     return false;
                 }
                 if ($("#enddate").val() == "") {
                     $("#enddate").focus();
                     return false;
                 }
             })

         });

         function resetfrm() {
             jQuery("#startdate").val("");
             jQuery("#enddate").val("");
         }
     </script>

     <style>
         .page-header {
             text-align: center;
             border-bottom: none;
         }

         .customrow {
             width: 600px;
             margin: 0 auto;
             clear: both;
             margin-top: 28px;
         }

         .card.text-center {
             max-width: 700px;
             position: inherit;
             margin: auto;
         }

         #filterfrm{
            border: 1px solid rgba(0,0,0,.125);
         }

     </style>


     <div class="card text-center">
         <h5 class="card-header"><b>{$pagetitle}</b></h5>
         <div class="card-body">
             <div id="maincontent">
                 <div id="innercontainer">
                     <form class="form" action="" method="post" id="filterfrm" class="center95 form-stacked">
                         <input type="hidden" name="action" value="genrate_client_statement" />
                         <input type="hidden" name="userid" value="{$userid}" />
                         <div class="row">
                            <div class="customrow">
                                 <div class="form-group col-sm-12 row">
                                     <div class="col-sm-3">
                                         <label for="startdate" class="control-label">{$AddonLang.startDate}</label>
                                     </div>
                                     <div class="col-sm-9">
                                         <input type="text" name="startdate" id="startdate" value="{$statements.startdate}"
                                             class="form-control datepick" autocomplete="off">
                                     </div>
                                 </div>
                                 <div class="form-group col-sm-12 row">
                                     <div class="col-sm-3">
                                         <label for="enddate" class="control-label">{$AddonLang.endDate}</label>
                                     </div>
                                     <div class="col-sm-9">
                                         <input type="text" name="enddate" id="enddate" value="{$statements.enddate}"
                                             class="form-control datepick" autocomplete="off">
                                     </div>
                                 </div>
                            </div>
                         </div>
                         <div class="d-flex align-items-center justify-content-center flex-column">
                             <div class="" style="display:none;">
                                 <div class="form-group">
                                     <select name="invoicetype" class="form-control" style="width:100%;">
                                         <option value="all" {if $statements.invoicetype eq 'all'} selected="selected"
                                             {/if}>{$AddonLang.allinv}</option>
                                         <option value="paid" {if $statements.invoicetype eq 'paid'} selected="selected"
                                             {/if}>{$AddonLang.paidinv}</option>
                                         <option value="unpaid" {if $statements.invoicetype eq 'unpaid'} selected="selected"
                                             {/if}>{$AddonLang.unpaidinv}</option>
                                     </select>
                                 </div>
                             </div>
                             <div class="form-group">
                                     <input class="btn btn-primary" id="clientarea_filter_button" style="width:100%;" type="submit" value="{$AddonLang.searchMyStatement}">
                            </div>
                         </div>
                     </form>
                     <br />
                     {if $statements}

                         <div class="table-container clearfix">

                             {* <div id="tableInvoicesList_wrapper" class="dataTables_wrapper form-inline dt-bootstrap no-footer"> *}
                             <div id="tableInvoicesList_wrapper" class="dataTables_wrapper dt-bootstrap no-footer">

                                 <div class="listtable">

                                     <div class="dataTables_info" id="tableInvoicesList_info" role="status" aria-live="polite">
                                         {$AddonLang.showingEntries}</div>



                                     <table id="tableInvoicesList" class="table table-list dataTable no-footer dtr-inline"
                                         aria-describedby="tableInvoicesList_info" role="grid">

                                         <tr>

                                             <th width="5%">{$AddonLang.sno}</th>

                                             <th>{$AddonLang.title}</th>

                                             {*<th width="10%"></th>*}

                                             <th width="10%"></th>

                                         </tr>

                                         {if $statements.result eq 'success'}

                                             <tr class="odd" style="cursor: inherit;">

                                                 <td class="text-center">1</td>

                                                 <td class="text-center" style="word-break: break-all;"><a
                                                         href="{$statements.downloadlink}"
                                                         title="{$AddonLang.download}"><strong>{$statements.file}</strong></a></td>

                                                 {*<td class="text-center"><a href="{$statements.url}" title="{$AddonLang.view}" target="_blank"><img src="modules/addons/accountStatement/img/view.png" /></a></td>*}

                                                 <td class="text-center"><a href="{$statements.downloadlink}"
                                                         title="{$AddonLang.download}"><img
                                                             src="modules/addons/accountStatement/img/file-download.png" /></a></td>

                                             </tr>

                                         {else}

                                             <tr class="odd" style="cursor: inherit;">

                                                 <td class="text-center" colspan="4"><strong>{$statements.message}</strong></td>

                                             </tr>

                                         {/if}

                                     </table>
                                 </div>

                             </div>

                         </div>

                     {/if}
                 </div>
             </div>
         </div>
     </div>



{/if}