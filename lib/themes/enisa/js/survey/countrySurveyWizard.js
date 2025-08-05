(function ($, Drupal) {
    Drupal.behaviors.countrySurveyWizard = {
        attach: function (context, settings) {
            once('countrySurveyWizard', 'body').forEach(function () {
    			// Click on previous, next buttons
				$('.form-wizard-next-btn, .form-wizard-previous-btn').click(function() {
  					var el = $(this);
					
	  				if (isDirty)
  					{
		  				let url = '/country/survey/view';
  						let requested_indicator = '';

	  					if (el.hasClass('form-wizard-next-btn')) {
		  					requested_indicator = el.parents('.wizard-fieldset').next('.wizard-fieldset').attr('data-id');
			  			}
			  			else if (el.hasClass('form-wizard-previous-btn')) {
				  			requested_indicator = el.parents('.wizard-fieldset').prev('.wizard-fieldset').attr('data-id');
			  			}

			  			go_to_data['save'] = go_to_data['discard'] = {
				  			'url': url,
				  			'requested_indicator': requested_indicator
			  			};
													
    					dirtyModal.show();

			  			return;
      				}
				
					$('.wizard-fieldset').removeClass('show', '400');			

					if (el.hasClass('form-wizard-next-btn')) {
						el.parents('.wizard-fieldset').next('.wizard-fieldset').addClass('show', '400');
					}
					else if (el.hasClass('form-wizard-previous-btn')) 
					{
						let type = el.parents('.wizard-fieldset').prev('.wizard-fieldset').attr('data-type');
						if (type == 'info') {
							$('.wizard-fieldset[data-type="' + type + '"]').addClass('show', '400');
						}
						else {
							el.parents('.wizard-fieldset').prev('.wizard-fieldset').addClass('show', '400');
						}
					}
		
					$('.wizard-fieldset').each(function (i, el) {
						var el = $(this);

						if (el.hasClass('show'))
						{
							Drupal.countrySurvey.updateIndicatorStateSection(null, true, true);

							return false;
						}
					});		
				});	

				// Focus on input field check empty or not
				$('.form-control').on('focus', function() {
					var tmpThis = $(this).val();
					if (tmpThis == '' ) {
						$(this).parent().addClass('focus-input');
					}
					else if (tmpThis !='' ) {
						$(this).parent().addClass('focus-input');
					}
				}).on('blur', function() {
					var tmpThis = $(this).val();
					if (tmpThis == '' ) {
						$(this).parent().removeClass('focus-input');
						$(this).siblings('.wizard-form-error').slideDown('3000');
					}
					else if (tmpThis !='' ) {
						$(this).parent().addClass('focus-input');
						$(this).siblings('.wizard-form-error').slideUp('3000');
					}
				});
			});
		}
	};
})(jQuery, Drupal);