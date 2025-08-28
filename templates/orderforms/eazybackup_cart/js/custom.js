// function recalctotals() {
//     console.log("Starting recalctotals function");

//     // if (!jQuery("#orderSummaryLoader").is(":visible")) {
//     //     jQuery("#orderSummaryLoader").fadeIn('fast');
//     // }

//     var thisRequestId = Math.floor((Math.random() * 1000000) + 1);
//     window.lastSliderUpdateRequestId = thisRequestId;

//     console.log("Request ID:", thisRequestId);

//     // Placeholder for instant feedback
//     jQuery("#producttotal").html('<p>Updating prices...</p>');

//     var post = WHMCS.http.jqClient.post(
//         whmcsBaseUrl + '/cart.php',
//         'ajax=1&a=confproduct&calctotal=true&' + jQuery("#frmConfigureProduct").serialize()
//     );

//     post.done(function (data) {
//         console.log("AJAX request completed. Data received:", data);

//         if (thisRequestId == window.lastSliderUpdateRequestId) {
//             jQuery("#producttotal").html(data);

//             console.log("Applying client discount...");
//             jQuery("#producttotal .text-gray-900.font-medium").each(function () {
//                 const priceText = jQuery(this).text().trim();
//                 const priceMatch = priceText.match(/\$?(\d+(\.\d{1,2})?)CAD/);
//                 if (priceMatch) {
//                     const originalPrice = parseFloat(priceMatch[1]);
//                     const discountedPrice = originalPrice * (1 - clientGroupDiscount / 100);
//                     jQuery(this).text(`$${discountedPrice.toFixed(2)}CAD`);
//                 }
//             });

//             const totalElement = jQuery("#producttotal .text-lg.font-semibold");
//             // or the specific .text-sm.font-semibold that shows "Total Due Today"
//             if (totalElement.length > 0) {
//                 const totalText = totalElement.text();
//                 const totalMatch = totalText.match(/\$?(\d+(\.\d{2})?)CAD/);
//                 if (totalMatch) {
//                     const originalTotal = parseFloat(totalMatch[1]);
//                     const discountedTotal = originalTotal * (1 - clientGroupDiscount / 100);
//                     totalElement.text(`$${discountedTotal.toFixed(2)}CAD`);
//                 }
//             }
//         }
            

//     });

//     post.fail(function (jqXHR, textStatus, errorThrown) {
//         console.error("AJAX request failed:", textStatus, errorThrown);
//     });


    

    // post.always(function () {
    //     console.log("Hiding order summary loader");
    //     jQuery("#orderSummaryLoader").fadeOut('fast');
    // });
// }
