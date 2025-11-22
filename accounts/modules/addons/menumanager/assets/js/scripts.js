"use strict";

$(document).ready(function(){
    var URITYPE = $('select[name="urltype"]');
        URITYPE.on("change", function(){
            $('[name="clientarea-on"],[name="clientarea-off"],[name="knowledgebase"],[name="downloads"],[name="support"],[name="customurl"]').hide();
            $('[name="'+this.value+'"]').show();
        });
    
    $('#accesslevel').on('change', function(){
        if ($(this).val()==15) {
            $('#client-groups-div').show();
        }
        else {
            $('#client-groups-div').hide();
        }
    });

    $('[data-toggle="tooltip"]').tooltip();
});

// Add Attribute Form
function addAttribute(){
    $('#attributesformsyntax .row').clone().appendTo('#attributes-wrap');
}

// Delete Attribute Form
function deleteAttribute(element){
    $(element).parent().parent().remove();
}

// Sortable
$(document).ready(function () {
    var sortableMenu = $('.sortable');
    sortableMenu.nestedSortable({
        handle: 'div',
        items: 'li',
        toleranceElement: '> div',
        listType: 'ol',
        maxLevels: 4,
        relocate: function(){
            var reorder = sortableMenu.nestedSortable('toHierarchy');
            
            $.ajax({
                type: "POST",
                url: "addonmodules.php?module=menumanager&action=reorder",
                data: {items:reorder}
            });
            
        }
    });
});


