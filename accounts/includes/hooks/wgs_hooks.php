<?php
use WHMCS\Database\Capsule;


add_hook('AdminAreaHeaderOutput', 1, function($vars)
{

    $exist_data = Capsule::table('tblhosting')->get();

    $script = "
                <script type=\"text/javascript\">
                $(document).ready(function ()
                {
                    var all_hosting_data = ".$exist_data.";

                    var productTable = $('#profileContent #clientsummarycontainer form > table:nth-child(2) .datatable > tbody tr');
                    if ( productTable.length > 1 ) {
                        row = productTable.filter(function()
                            {
                                var username, userid = '';
                                var description = $.trim($(this).find('td').eq(2).html());
                                var hostingId   = $.trim($(this).find('td').eq(1).text());
                                // Get Username
                                var domain_data = all_hosting_data.find((o, i) => {
                                    if ($.trim(o.id) == hostingId ) {
                                        console.log('id::' + o.id);
                                        console.log('hostingId::' + hostingId);
                                        console.log('username::' + o.username);
                                        username = o.username;
                                        userid   = o.userid;
                                    }
                                });

                                if ( description.indexOf('(No Domain)') > 0 )
                                {
                                    var res = description.split('-');
                                    if (username) {
                                        $(this).find('td').eq(2).html( res[0] + ' - (' + username + ')' );
                                    }
                                }
                        });
                    }

                });
</script>";

return $script;
});

add_hook('ClientAreaHeaderOutput', 1, function($vars)
{
    if ($vars['action'] == 'services') {
    $script = "
                <script type=\"text/javascript\">

                $(document).ready(function(e)
                {
                    $('#tableServicesList tr.test .text-center:not(:nth-child(10))').on('click', function(e){
                        e.preventDefault();
                        $('.cometuserDetails').remove();

                        if ($(this).siblings('.service_list').find('i.fa.fa-caret-right').hasClass('active')) {
                            $('.service_list i.fa.fa-caret-right').removeClass('active');
                            $('#service-data').slideUp('slow');
                            return false;
                        }
                        $('.myloader').css('display', 'block');
                        $('.table-container').addClass('loading');

                        $('#service-data').slideUp('slow');
                        $('.service_list i.fa.fa-caret-right').removeClass('active');
                        $(this).siblings('.service_list').find('i.fa.fa-caret-right').addClass('active');

                        var id = $(this).closest('tr').attr('data-serviceid');
                        $.ajax({
                            method: 'POST',
                            data: {
                              'id': id
                            },
                            url: 'modules/servers/comet/ajax/ajax.php',
                            success: function(data) {
                              var cometuserDetails = '<tr class=\"cometuserDetails\"><td colspan=\"8\"><div id=\"service-data\" style=\"display:none\"></div></td></tr>';
                              $(cometuserDetails).insertAfter('#serviceid-' + id);
                              $('#service-data').html(data);
                              $('.table-container').removeClass('loading');
                              $('.myloader').css('display', 'none');
                              $('#service-data').slideDown('slow');
                              $('.emailbackup-ajax').bootstrapSwitch('state');

                            },
                            error: function(data) {
                              $('#service-data').html(data);
                            }
                        });
                    });
                });
</script>";

return $script;
    }
});