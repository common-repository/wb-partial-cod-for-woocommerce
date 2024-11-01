jQuery(document).ready(function($) {
    
    var ajaxUrl = partial_cod_params.ajax_url;
    // Handle AJAX request when partial payment checkbox is checked or unchecked
    $('input[name="partial_payment"]').change(function() {
        var partial_payment = $(this).is(':checked') ? '1' : '0';
        $(document.body).trigger('update_checkout');
        $.ajax({
            type: 'POST',
            url: ajaxUrl,
            data: {
                action: 'update_partial_payment',
                partial_payment: partial_payment,
                security: partial_cod_params.nonce
            },
            success: function(response) {
                // Update cart totals and fees on the frontend
                $(document.body).trigger('update_checkout');
            }
        });
    });
});
  