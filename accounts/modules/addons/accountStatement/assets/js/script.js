jQuery(document).ready(function() {
    jQuery('.ajax-response-error').html('');
    var value = '<?php echo $period; ?>';
    if (value == '') {
        value = 'monthly';
    }
    if (value == 'customdate') {
        jQuery('.monthlyInp').css('display', 'inherit');
    } else {
        jQuery('.monthlyInp').css('display', 'none');
    }

    jQuery('#acSendInvoicePeriod').change(function() {
        var value = jQuery('#acSendInvoicePeriod').val();
        if (value == 'customdate') {
            jQuery('.monthlyInp').css('display', 'inherit');
        } else {
            jQuery('.monthlyInp').css('display', 'none');
        }
    });

	jQuery('.change_ctm_color').on('input', changeColorHtmltemp3);
	jQuery('.change_ctm_color_temp1').on('input', changeColorHtmltemp1);
	jQuery('.change_ctm_color_temp2').on('input', changeColorHtmltemp2);
});    

/* function */
function changeColorHtmltemp1() {
   
    var selectedClass = jQuery(this).attr('name');
    var colorval = this.value;
   
    if(selectedClass == "color_box_bg_temp1"){
        jQuery(".box_section_temp1.col-md-12 .inner-box").css({ 'background-color':this.value});
    }else if(selectedClass == "color_box_txt_temp1"){
        jQuery(".box_section_temp1.col-md-12 .inner-box .acc").css({ 'color':this.value });
    }if(selectedClass == "color_fistline_temp1"){
        jQuery(".temp1_line").css("border-top-color", this.value);
        //jQuery(".temp1contact i").css("color", this.value);
    }if(selectedClass == "color_tbl_head_temp1"){
        jQuery(".tbl-layout-temp1 thead").css("color", this.value);
    }if(selectedClass == "color_tbl_odd_temp1"){
        jQuery(".tbl-layout-temp1 tbody .odd_temp1").css("background-color", this.value);
    }if(selectedClass == "color_tbl_even_temp1"){
        jQuery(".tbl-layout-temp1 tbody .even_temp1").css("background-color", this.value);
    }if(selectedClass == "color_tbl_txt_temp1"){
        jQuery(".tbl-layout-temp1 tbody").css("color", this.value);
    }
}

function rgb2hex(rgb){
 rgb = rgb.match(/^rgb((d+),s*(d+),s*(d+))$/);
 return "#" +
  ("0" + parseInt(rgb[1],10).toString(16)).slice(-2) +
  ("0" + parseInt(rgb[2],10).toString(16)).slice(-2) +
  ("0" + parseInt(rgb[3],10).toString(16)).slice(-2);
}

function changeColorHtmltemp2() {
   
    var selectedClass = jQuery(this).attr('name');
    var colorval = this.value;

     if(selectedClass == "color_head_bg_temp2"){
        jQuery(".template2design .line_div_temp2 .line_first").css({ 'background-color':this.value });
        jQuery(".temp2_head .h-title").css({ 'background-color':this.value });
     }else if(selectedClass == "color_subhead_bg_temp2"){
        jQuery(".template2design .line_div_temp2 .line_second").css({ 'background-color':this.value });
        jQuery(".temp2_head .inner_date").css({ 'background-color':this.value });
        jQuery(".template2design .current_bal").css({ 'background-color':this.value });
     }else if(selectedClass == "color_border_temp2"){
         jQuery(".temp2_head_customer .dyl-right.col-md-6").css({ 'border-color':this.value});
         jQuery("table.tbl-layout-tmp2 tr").css({ 'border-color':this.value});
         jQuery("table.tbl2-layout-tmp2 td").css({ 'border-color':this.value});
     } 
}

function changeColorHtmltemp3() {
   
    var selectedClass = jQuery(this).attr('name');
    var colorval = this.value;

    if(selectedClass == "color_hr_bg"){
        jQuery(".template3design .heading_temp3").css({ 'background-color':'#fff','background-color':this.value });
    }else if(selectedClass == "color_hr_txt"){
        jQuery(".heading_temp3").css({ 'color':this.value });
    }else if(selectedClass == "color_box_bg"){
        jQuery(".box_div.cls").css({ 'background-color':this.value});
    }else if(selectedClass == "color_box_txt"){
        jQuery(".box_div.cls").css({ 'color':this.value });
    }
    else if(selectedClass == "color_fistline"){
       $(".line_div .line_first").css({ 'background-color':this.value });
    }else if(selectedClass == "color_secondline"){
       $(".line_div .line_second").css({ 'background-color':this.value });
    }else if(selectedClass == "color_tbl_odd"){
       $(".tbl-layout .odd").css({ 'background-color':this.value });
    }else if(selectedClass == "color_tbl_evn"){
       $(".tbl-layout .even").css({ 'background-color':this.value });
    }else if(selectedClass == "color_tbl_txt"){
        $(".tbl-layout .tbl_txt").css({ 'color':this.value });
    } 
}

function updateCustomColor(element,template){
    if(template == "temp1"){
        var formData = $('#temp1_customcolor').serialize();
    }else if(template == "temp2"){
        var formData = $('#temp2_customcolor').serialize();
    }else if(template == "temp3"){
        var formData = $('#temp3_customcolor').serialize();
    }
   
    $(element).html('<span class="loader_updt"><i class="fas fa-spinner fa-pulse"></i>Loading..<span>');
    jQuery('.ajax-response-error').html('');
    $.ajax({
        type : 'POST',
        url : '',
        data : formData+ "&customColor=true&template="+template,
        success: function(res) {  
         
        $(element).html('Update');
        if(res.trim() == 'success'){
            $(element).closest(".modal").modal("hide");
            $('#ajax-response').html('<div class="alert alert-success" role="alert"> Custom theme update successfully!</div>');
        }else{
            $('.ajax-response-error').html('<div class="alert alert-danger" role="alert">Custom theme update error: '+res+'</div>');
        }              
      }  
    });
}