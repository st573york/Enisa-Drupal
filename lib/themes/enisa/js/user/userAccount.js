let is_blocked;

(function ($, Drupal) {
    Drupal.behaviors.userAccount = {
        attach: function (context, settings) {
            once('userAccount', 'body').forEach(function () {
                is_blocked = settings.userData.is_blocked;
                
                if (is_blocked) {
                    setAlert({
                        'status': 'error',
                        'msg': 'Access blocked. An administrator will need to unblock your account.'
                    });
                }
                
                $(document).on('click', '#process-data', function() {
                    $('.loader').fadeIn();
                    
                    let data = getFormData('user-account');
                    if ($('#country').is(':disabled')) {
                        data.append('country', $('#country').val());
                    }
                    
                    $.ajax({
                        'url': '/user-account/update',
                        'type': 'POST',
                        'data': data,
                        'contentType': false,
                        'processData': false,
                        success: function(response) {
                            $('#country').prop('disabled', true);
        
                            setAlert({
                                'status': 'success',
                                'msg': response.success
                            });
                        },
                        error: function(req) {
                            showInputErrors(req.responseJSON.errors);
                        }
                    });
                });
            });
        }
    }
})(jQuery, Drupal);