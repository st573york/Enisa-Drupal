var pageModal = new bootstrap.Modal(document.getElementById('pageModal'));
var dirtyModal = new bootstrap.Modal(document.getElementById('dirtyModal'));
let user_group;
let country_survey_id;
let current_user_data;
let action;
let requested_indicator;
let requested_action;
let view_all;
let is_assignee_indicators_submitted;
let is_country_survey_completed;
let is_country_survey_submitted;
let is_pending_requested_changes;
let is_admin;
let is_primary_poc;
let is_primary_poc_active;
let is_poc;
let is_dropdown_menu_opened = false;
let view_survey_token;
let isDirty = false;
let go_to_data = {
  'save': null,
  'discard': null
};
let loadedInputs = '';
let scroll_to_error = false;
let country_survey_answers_copy = {};
let indicator_answers_copy = {};
let indicators_list_unique = [];
let assigned_indicators_unique = [];
let incomplete_indicators_unique = [];
let incomplete_indicators_assigned_unique = [];
let indicator_questions_not_answered_unique = [];
let unsubmitted_indicators_unique = [];
let requested_approval_indicators_unique = [];
let current_state = null;
let submitted_requested_changes_content = '';

(function ($, Drupal) {
	Drupal.countrySurvey = Drupal.countrySurvey || {};

	Drupal.countrySurvey.updateIndicatorStateSection = function (state, reset, update) {
  		clearPageErrors();
  		clearInputErrors();
  
  		if (reset) 
  		{
	    	$('.indicator-state').removeClass('show');
    		$('.indicator-state').children().children().removeClass('show');
  		}
		
  		let active_indicator = findActiveIndicator();
  		if (active_indicator)
  		{
	    	$('.submitted-requested-changes-history').removeClass('show');
	    	let history_elem = $(`.submitted-requested-changes-history[data-id="${active_indicator}"]`);
    		if (history_elem.hasClass('history')) {
      			history_elem.addClass('show');
    		}

    		let state_elem = $(`.indicator-state[data-id="${active_indicator}"]`);
    		let wizard_elem = $(`.wizard-fieldset[data-id="${active_indicator}"]`);

    		let is_assigned = wizard_elem.hasClass('assigned');
    		let latest_requested_change_state = state_elem.attr('data-latest-requested-change-state');
    		let editor = tinymce.get(`request-requested-changes-${active_indicator}`);

    		let state_class = '';
		
    		current_state = wizard_elem.attr('data-state');
    		state = (state) ? state : current_state;
			
    		if ((state == '6' && !is_country_survey_submitted) || // Approved
        		state == '7')                                     // Final approved
    		{
      			state_elem.find('.approved-title-author').text(
        			($(`#approved-title-author-${active_indicator}`).text() ? $(`#approved-title-author-${active_indicator}`).text() : `${current_user_data.name} (you)`)
      			);

      			if (update) {
        			wizard_elem.attr('data-state', state);
      			}

	    		state_class = '.approved-wrap';

      			if (state == '7' && is_country_survey_submitted) {
        			state_elem.find(state_class).find('.unapprove').removeClass('d-none');
      			}
    		}
    		else if (state == '3' || // Submitted
            	 	 state == '6')   // Approved
	    	{
    	  		if (((is_poc && !is_primary_poc) ||
        	   		 (is_primary_poc && is_country_survey_completed) ||
           			 (is_admin && is_country_survey_submitted)) &&
          			!is_assigned)
      			{
        			state_class = '.request-approval-wrap';
      			}
      
      			if (update) {
        			wizard_elem.attr('data-state', state);
      			}
      
      			if (latest_requested_change_state == 2) {
        			Drupal.countrySurvey.updateIndicatorStateSection('5', false, false);
      			}
    		}
    		else if (state == '4') // Request changes
    		{
      			state_elem.find('.requested-changes-title, .requested-changes-discard-edit').addClass('d-none');
      			state_elem.find('.request-changes-title, .request-changes-deadline, .request-changes-actions').removeClass('d-none');

      			if (latest_requested_change_state == 2)
      			{
        			submitted_requested_changes_content = $(`#request-requested-changes-${active_indicator}`).text();
        			editor.setContent('');
      			}

				editor.mode.set('design');

		    	state_class = '.request-requested-changes-wrap';
    		}
    		else if (state == '5') // Requested changes
    		{
      			let latest_requested_change_author_name = $(`#requested-changes-title-author-${active_indicator}`).text();
      			let latest_requested_change_author_role = state_elem.find('.requested-changes-discard-edit').attr('data-latest-requested-change-author-role');
				
      			state_elem.find('.requested-changes-title').removeClass('d-none');
      			state_elem.find('.requested-changes-title-author').text(
        			(latest_requested_change_author_name.length ? latest_requested_change_author_name : `${current_user_data.name} (you)`)
      			);
      			state_elem.find('.requested-changes-title-deadline').text($(`#request-requested-changes-deadline-${active_indicator}`).val());
	      		if (((is_poc && latest_requested_change_author_role == 7) ||
    	       		 (is_primary_poc && latest_requested_change_author_role == 6) ||
        	   		 (is_admin && latest_requested_change_author_role == 5)) &&
          			is_pending_requested_changes &&
          			latest_requested_change_state == 1)
      			{
        			if (!is_assigned) {
          				state_elem.find('.requested-changes-discard-edit').removeClass('d-none');
        			}
					
        			setAlert({
          				'status': 'warning',
          				'msg': `Requested changes have NOT been sent to the assignee yet.\
            				Browse to <a href="/country/survey/dashboard/management/${country_survey_id}">Survey Dashboard</a> to submit the requested changes for ALL indicators.`
        			});
				}
      			state_elem.find('.request-changes-title, .request-changes-deadline, .request-changes-actions').addClass('d-none');
				
      			if (submitted_requested_changes_content.length) {
        			editor.setContent(submitted_requested_changes_content);
      			}
				editor.mode.set('readonly');

      			if (update) {
        			wizard_elem.attr('data-state', state);
      			}

      			state_class = '.request-requested-changes-wrap';
    		}

    		state_elem.find(state_class).addClass('show')
      			.closest('.indicator-state').addClass('show');
  		}
	}

    Drupal.behaviors.countrySurvey = {
        attach: function (context, settings) {
            once('countrySurvey', 'body').forEach(function () {
				user_group = settings.view_country_survey.user_group;
				country_survey_id = settings.view_country_survey.assignee_country_survey_data.id;
				current_user_data = settings.view_country_survey.current_user_data;
				action = settings.view_country_survey.action;
				requested_indicator = settings.view_country_survey.requested_indicator;
				requested_action = settings.view_country_survey.requested_action;
				view_all = settings.view_country_survey.view_all;
				is_assignee_indicators_submitted = settings.view_country_survey.assignee_country_survey_data.indicators_submitted;
				is_country_survey_completed = settings.view_country_survey.assignee_country_survey_data.completed;
				is_country_survey_submitted = (Object.keys(settings.view_country_survey.assignee_country_survey_data.submitted_user).length) ? true : false;
				is_pending_requested_changes = (Object.keys(settings.view_country_survey.pending_requested_changes).length) ? true : false;
				is_admin = settings.view_country_survey.is_admin;
				is_primary_poc = settings.view_country_survey.is_primary_poc;
				is_primary_poc_active = settings.view_country_survey.assignee_country_survey_data.default_assignee.is_active;
				is_poc = settings.view_country_survey.is_poc;
				view_survey_token = settings.view_country_survey.view_survey_token;
				
				skipFadeOut = true;
				
            	initDatePicker();
				Drupal.tinymce.initTinyMCE({
                	'height': 150
            	});
				
            	$('.wizard-fieldset.not_assigned :input, \
               	   .wizard-fieldset.assigned[data-state="3"] :input, \
               	   .wizard-fieldset.assigned[data-state="6"] :input, \
               	   .wizard-fieldset.assigned[data-state="7"] :input').not(':checked').not(':button').prop('disabled', true);

            	if (is_assignee_indicators_submitted ||
                	is_country_survey_submitted)
            	{
                	$('.wizard-fieldset :input').not(':checked').not(':button').prop('disabled', true);
				}

				findAssignedIndicators();
  				findIncompleteIndicators();
  				findQuestionsAnswered();

				if (is_primary_poc)
  				{
    				findUnsubmittedIndicators();
    				findRequestedApprovalIndicators();
  				}

  				updateStepStatus();

  				if (!view_all)
  				{
    				$('.wizard-fieldset.assigned').each(function (i, el) {      
      					let state = $(el).attr('data-state');
            
      					if ($.inArray(parseInt(state), [3, 6, 7]) == -1)
      					{
	        				$(el).find('.form-indicators .input-choice input:checked').each(function (i, e) {
    	      					toggleAnswers(e);
        	  					toggleReference(e);

          						let choice = $(e).val();          
          						if (choice != 3)
          						{
            						let master = $(e).closest('.input-choice').siblings('.actual-answers').find('.form-input.master');
            						if (master.length) {
              							toggleOptions(master);
            						}
          						}
        					});        
      					}
    				});

    				if (requested_indicator)
    				{
      					let indicator_id = $('.wizard-fieldset[data-id="' + requested_indicator + '"]').prop('id');
      					if (indicator_id)
						{
        					jumpToPage(indicator_id.replace('page-', ''));
							findCurrentStep();
      					}
    				}

    				resetDirty();
					
    				if (requested_action == 'load_answers')
    				{
      					isDirty = true;
						
      					$('.wizard-fieldset.show').find('.step-save').prop('disabled', !isDirty);
    				}
					else if (requested_action == 'save')
  					{
    					let elem = $('.wizard-fieldset.show').find('.step-save');

    					$(window).scrollTop(elem.offset().top);
  					}
					else if (requested_action == 'review') {
      					showReviewModal();
    				}
  				}

				if ($.inArray(requested_action, ['save', 'requested_changes', 'discard_requested_changes', 'approve', 'final_approve', 'unapprove', 'review']) == -1) {
    				scrollToWizard();
  				}

				$('.loader').fadeOut(); 

				$(document).on('click', '.close-alert', function () {
            		$('.alert-section').addClass('d-none');
        		});

        		$(document).on('click', function (e) {
            		if ($(e.target).closest('#surveyNavigationDropdown').length === 0 &&
                		$(e.target).closest('.icon-dropdown-menu').length === 0)
            		{
                		is_dropdown_menu_opened = false;
            		}
        		});

        		$(document).on('click', '.icon-dropdown-menu', function (e) {
            		if (is_dropdown_menu_opened)
            		{
                		$('#surveyNavigationDropdown').dropdown('hide');

                		is_dropdown_menu_opened = false;
            		}
            		else
            		{
                		$('#surveyNavigationDropdown').dropdown('show');
						
                		is_dropdown_menu_opened = true;
            		}
        		});

        		$(document).on('click', '#surveyNavigationDropdown', function (e) {
            		if ($(this).hasClass('show')) {
                		is_dropdown_menu_opened = true;
            		}
            		else {
                		is_dropdown_menu_opened = false;
            		}
        		});

        		$(document).on('click', '.form-check-input', function (e) {
            		let state = $(this).closest('fieldset').attr('data-state');
            
            		if ($(this).hasClass('not_assigned') ||
                		$.inArray(parseInt(state), [3, 6, 7]) != -1 ||
                		is_assignee_indicators_submitted ||
                		is_country_survey_submitted)
            		{
                		e.preventDefault();
            		}
        		});

				$(window).on('beforeunload', function() {
  					if (isDirty &&
      					!go_to_data['save'] &&
      					!go_to_data['discard']) 
  					{  
    					return 'This page has unsaved changes that will be lost. Save or discard the answers before leaving this page.';
  					}
				});

				$(document).on('hide.bs.modal', '#dirtyModal', function() {  
  					resetGoToData();
				});

				$(document).on('shown.bs.collapse', '.accordion-collapse', function() {  
  					if (scroll_to_error) {
    					scrollToError();
  					}
				});

				$(document).ajaxStop(function () {
  					findIncompleteIndicators();
  					findQuestionsAnswered();
  					if (is_primary_poc)
  					{
    					findUnsubmittedIndicators();
    					findRequestedApprovalIndicators();
  					}
  					updateStepStatus();
				});

				$(document).on('click', 'a:not([href^="#"])', function(e) {   
  					if (isDirty) 
  					{
    					go_to_data['save'] = go_to_data['discard'] = {
      						'url': this.href
    					};
						
    					e.preventDefault();
    
    					dirtyModal.show();
  					}
				});

				$(document).on('input', '.wizard-fieldset.show', function() {  
  					isDirty = (getSerializedInputs() !== loadedInputs && action != 'preview') ? true : false;
					
  					$(this).find('.step-save').prop('disabled', !isDirty);
				});

				$(document).on('click', '.btn-approve', function () {
  					approveIndicator();
				});

				$(document).on('click', '.btn-request-changes', function () {
  					if (!isIndicatorAssigneeActive()) {
    					return;
  					}
					
  					Drupal.countrySurvey.updateIndicatorStateSection('4', true, true);
				});

				$(document).on('click', '.btn-cancel-request-changes', function () {
  					Drupal.countrySurvey.updateIndicatorStateSection(current_state, true, false);
				});

				$(document).on('click', '.btn-send-request-changes', function () {
  					requestChangesIndicator();
				});

				$(document).on('click', '.btn-discard-requested-changes', function () {
  					discardRequestedChangesIndicator();
				});

				$(document).on('click', '.btn-edit-requested-changes', function () {
  					if (!isIndicatorAssigneeActive()) {
    					return;
  					}

  					Drupal.countrySurvey.updateIndicatorStateSection('4', true, true);
				});

				$(document).on('click', '.btn-unapprove', function () {
  					unapproveIndicator();
				});

				$(document).on('click', '#step-save-goto', function () {
  					saveCountrySurvey('save');
				});

				$(document).on('click', '#step-discard-goto', function () {
  					$('.loader').fadeIn();
  
  					if (requested_action == 'load_answers') {
    					loadResetCountrySurveyIndicatorAnswers('reset');
  					}
  					else {
    					goTo(go_to_data['discard']);
  					}
				});

				$(document).on('click', '#country-survey-review', function () {
  					if (isDirty) 
  					{
    					go_to_data['save'] = go_to_data['discard'] = {
      						'url': '/country/survey/view',
      						'requested_indicator': $('.wizard-fieldset.show').attr('data-id'),
      						'requested_action': 'review'
    					};
      
    					dirtyModal.show();

    					return;
  					}

  					showReviewModal();
				});

				$(document).on('click', '#country-survey-submit', function () {
  					saveCountrySurvey('submit');
				});

				$(document).on('click', '.step-save', function () {
  					saveCountrySurvey('save');
				});

				$(document).on('click', '.validate', function () {
  					findIncompleteIndicators();
  					if (is_primary_poc)
  					{
    					findUnsubmittedIndicators();
    					findRequestedApprovalIndicators();
  					}
  					updateStepStatus();
				});

				$(document).on('click', '.step-choice:not(.disabled), .go-to-page', function () {
  					let page = $(this).attr('id').replace('step-page-', '').replace('to-page-', '');
  					let indicator = $(this).attr('data-id');
  					let indicator_assigned = ($.inArray(parseInt(indicator), assigned_indicators_unique) != -1) ? true : false;

  					if (isDirty) 
  					{
    					go_to_data['save'] = go_to_data['discard'] = {
      						'url': '/country/survey/view',
      						'requested_indicator': indicator
    					};
      
    					dirtyModal.show();

    					return;
  					}

  					if($(this).hasClass('step-choice') || 
     				   ($(this).hasClass('go-to-page') && !indicator_assigned))
  					{
    					clearPageErrors();
    					clearInputErrors();
  					}
  
  					jumpToPage(page);
					findCurrentStep();

  					resetDirty();
  
  					if ($(this).hasClass('go-to-page'))
  					{
    					pageModal.hide();

    					if (indicator_assigned)
    					{
      						validateCountrySurveyIndicator();

      						return;
    					}
  					}
  
  					scrollToWizard();  
				});

				$(document).on('click', '.step-change', function () {  
  					if (isDirty) {   
    					return;
  					}
    
  					findCurrentStep();
					
  					resetDirty();

  					scrollToWizard();
				});

				$(document).on('input', '.input-choice input, .actual-answers input', function () {
  					$(this).closest('.form-indicators').siblings('.form-indicator-question-answer').addClass('d-none');
  					$(this).closest('.form-indicators').siblings('.form-indicator-question-load-reset').remove();
				});

				$(document).on('input', '.form-references select, .form-references textarea', function () {
  					$(this).closest('.form-references').siblings('.form-indicator-question-answer').addClass('d-none');
  					$(this).closest('.form-references').siblings('.form-indicator-question-load-reset').remove();
				});

				$(document).on('input', '.form-comments textarea', function () {
  					$(this).closest('.form-comments').find('.form-indicator-question-load-reset').remove();
				});

				$(document).on('input', '.form-rating input', function () {
  					$(this).closest('.form-rating').siblings('.form-indicator-question-load-reset').remove();
				});

				$(document).on('click', '.clear-answers', function (e) {
  					e.preventDefault();

  					let default_choice = $(this).parent().siblings('.form-check').find('input[type="radio"][value="1"], input[type="radio"][value="2"]');

  					default_choice.prop('checked', true);
  					$(this).closest('.input-choice').siblings('.actual-answers').find('input[type="checkbox"], input[type="radio"]').prop('checked', false);
  					$(this).closest('.input-choice').siblings('.actual-answers').find('input[type="text"]').val('');
  					$(this).closest('.form-indicators').siblings('.form-indicator-question-answer').addClass('d-none');
  					$(this).closest('.form-indicators').siblings('.form-indicator-question-load-reset').remove();

  					toggleAnswers(default_choice);
  					toggleReference(default_choice);
  
  					// Update isDirty
  					$('.wizard-fieldset.show').trigger('input');
				});
            });
        }
    }
})(jQuery, Drupal);

function goTo(go_to)
{
  if (go_to.url.indexOf('/') === -1)
  {
    jumpToPage(go_to);
	findCurrentStep();
    
    scrollToWizard();
  }
  else if (go_to.url.indexOf('/country/survey/view') >= 0) {
    viewSurvey({
      'country_survey_id': country_survey_id,
      'requested_indicator': go_to.requested_indicator,
      'requested_action': go_to.requested_action
    });
  }
  else {
    window.location.href = go_to.url;
  }
}

function getSerializedInputs()
{
	var $ = jQuery;

	return $('.wizard-fieldset.show').find('.form-indicators input, select, textarea, .rating input').serialize();
}

function getModalErrorData(id)
{
	var $ = jQuery;

  	return {
    	'indicatorPage': $(`.step-choice[data-id="${id}"]`).attr('id').replace('step-page-', ''),
    	'indicatorId': $(`.step-choice[data-id="${id}"]`).attr('data-id'),
    	'indicatorText': $(`.step-choice[data-id="${id}"]`).text()
  	};
}

function resetDirty()
{
	var $ = jQuery;

  	loadedInputs = getSerializedInputs(); 

	// Update isDirty
  	$('.wizard-fieldset.show').trigger('input');
}

function resetGoToData()
{
  	go_to_data = {
    	'save': null,
    	'discard': null
  	};
}

function scrollToWizard() 
{
	var $ = jQuery;

  	$('html, body').animate({
    	scrollTop: $('.country-survey-title').offset().top - 10
  	}, 0);
}

function isAccordionOpen()
{
	var $ = jQuery;

  	if (!$('.wizard-fieldset.show .is-invalid:first').closest('.accordion-collapse').hasClass('show') &&
    	!$('.wizard-fieldset.show .is-invalid:first').closest('.rating').length)
  	{
    	scroll_to_error = true;
    
    	$('.wizard-fieldset.show .is-invalid:first').closest('.accordion-collapse').prev('.accordion-item').find('.accordion-button').trigger('click');

    	return false;
  	}

  	return true;
}

function scrollToError() 
{
	var $ = jQuery;

  	let elem = null;
  	if ($('.wizard-fieldset.show .is-invalid:first').closest('.form-indicators, .form-references').length) {
    	elem = $('.wizard-fieldset.show .is-invalid:first').closest('.form-indicators, .form-references').siblings('.form-question-text');
  	}
  	else if ($('.wizard-fieldset.show .is-invalid:first').closest('.rating').length) {
    	elem = $('.wizard-fieldset.show .is-invalid:first').closest('.rating').siblings('label');
  	}

  	$('html, body').animate({
    	scrollTop: elem.offset().top - 10
  	}, 0);

  	scroll_to_error = false;
}

function isIndicatorAssigneeActive()
{
	var $ = jQuery;

  	let warning = '';

  	if (is_admin)
  	{
    	if (!is_primary_poc_active) {
      		warning = 'Request changes are not allowed as Primary PoC is inactive.';
    	}
  	}
  	else if (is_primary_poc)
  	{
    	if ($('.wizard-fieldset.show .form-indicator-assignee').text().indexOf('inactive') >= 0) {
      		warning = 'Request changes are not allowed as indicator assignee is inactive. Please re-assign indicator to an active user.';
    	}
  	}

  	if (warning) 
  	{
    	setAlert({
      		'status': 'warning',
      		'msg': warning,
    	});

    	return false;
  	}

  	return true;
}

function findActiveIndicator()
{
	var $ = jQuery;

  	return ($('.wizard-fieldset.show').attr('data-type').includes('form-indicator')) ? $('.wizard-fieldset.show').attr('data-id') : null;
}

function findAssignedIndicators()
{
	var $ = jQuery;

  	let assigned_indicators = [];
  
  	$('.wizard-fieldset.assigned').each(function (i, el) {
    	assigned_indicators.push(parseInt($(el).attr('data-id')));    
  	});
  
  	assigned_indicators_unique = [...new Set(assigned_indicators)];
}

function findAnswers(el)
{
	var $ = jQuery;

  	if ($(el).hasClass('multiple-choice')) 
  	{
    	let values = [];    

    	$(el)
      		.find('.form-input:checked')
      		.each(function (i, e) {
        		values.push($(e).val());
      		});
      
    	if (values.length) {
      		return values;
    	}
  	}
  	else if ($(el).hasClass('single-choice')) 
  	{          
    	let value = $(el).find('.form-input:checked').val();

    	if (value) {
      		return value;
    	}
  	} 
  	else if ($(el).hasClass('free-text')) 
  	{          
    	let value = $(el).find('.form-input').val();

    	if (value) {
      		return $.trim(value);
    	}
  	}

  	return undefined;
}

function findIncompleteIndicators() 
{
	var $ = jQuery;

  	let incomplete_indicators = [];
  	let incomplete_indicators_assigned = [];
  	let indicator_questions_not_answered = [];
  
  	$('.form-indicators').each(function (i, e) {    
    	let indicator_id = $(e).attr('id').replace('form-indicator-', '');
    	let question_id = $(e).closest('.question-body').attr('id').split('survey_indicator_question_')[1];
    	let answer_choice = $(e).find('.input-choice').find('.form-input:checked').val();
    
    	if ($(e).find('.input-choice').hasClass('required') &&
        	answer_choice === undefined)
    	{
      		incomplete_indicators.push(parseInt(indicator_id));

      		return;
    	}

    	if ($(e).find('.actual-answers').hasClass('required') &&
        	$(e).siblings('.form-references').hasClass('required') &&
        	answer_choice != 3)
    	{
      		let answers_provided = true;
      		let reference_year_provided = true;
      		let reference_source_provided = true;

      		$(e)
        		.children('.actual-answers')
        		.each(function (idx, el) {      
          			let answers = findAnswers(el);
          			if (answers === undefined)
          			{
            			incomplete_indicators.push(parseInt(indicator_id));

            			answers_provided = false;

            			return;
          			}
        		});

      		let reference_year = $(e).siblings('.form-references').find('select').val();
      		if (!reference_year)
      		{
	        	incomplete_indicators.push(parseInt(indicator_id));
  
    	    	reference_year_provided = false;
      		}

      		let reference_source = $(e).siblings('.form-references').find('textarea').val();
      		if (!$.trim(reference_source).length)
      		{
        		incomplete_indicators.push(parseInt(indicator_id));

        		reference_source_provided = false;
      		}

      		if (!answers_provided &&
          		!reference_year_provided &&
          		!reference_source_provided)
      		{
        		indicator_questions_not_answered.push(parseInt(question_id));
      		}
    	}

    	if (!$(`.form-indicator-${indicator_id}-rating:checked`).length) {
      		incomplete_indicators.push(parseInt(indicator_id));
    	}

    	if ($.inArray(parseInt(indicator_id), assigned_indicators_unique) != -1 &&
        	$.inArray(parseInt(indicator_id), incomplete_indicators) != -1)
    	{
      		incomplete_indicators_assigned.push(parseInt(indicator_id));
    	}
  	});
  
  	incomplete_indicators_unique = [...new Set(incomplete_indicators)];
  	incomplete_indicators_assigned_unique = [...new Set(incomplete_indicators_assigned)];
  	indicator_questions_not_answered_unique = [...new Set(indicator_questions_not_answered)];
}

function findUnsubmittedIndicators()
{
	var $ = jQuery;

  	let unsubmitted_indicators = [];
  
  	$('.form-indicators').each(function (i, e) {    
    	let id = $(e).attr('id').replace('form-indicator-', '');

    	if ($.inArray(parseInt(id), assigned_indicators_unique) == -1 &&
        	$.inArray(parseInt(id), incomplete_indicators_unique) == -1)
    	{
      		let state = $(`.wizard-fieldset[data-id="${id}"]`).attr('data-state');

      		if (state == '2') {
        		unsubmitted_indicators.push(parseInt(id));
      		}
    	}
  	});
  
  	unsubmitted_indicators_unique = [...new Set(unsubmitted_indicators)];
}

function findRequestedApprovalIndicators()
{
	var $ = jQuery;

  	let requested_approval_indicators = [];
  
  	$('.form-indicators').each(function (i, e) {    
    	let id = $(e).attr('id').replace('form-indicator-', '');

    	if ($.inArray(parseInt(id), assigned_indicators_unique) == -1 &&
        	$.inArray(parseInt(id), incomplete_indicators_unique) == -1)
    	{
      		let state = $(`.wizard-fieldset[data-id="${id}"]`).attr('data-state');

      		if ($.inArray(state, ['3', '4', '5']) != -1) {
        		requested_approval_indicators.push(parseInt(id));
      		}
    	}
  	});
  
  	requested_approval_indicators_unique = [...new Set(requested_approval_indicators)];
}

function findCurrentStep() 
{
	var $ = jQuery;

  	let page_id = $('.wizard-fieldset.show').attr('id').replace('page-', '');

  	$('.step-choice').each(function (idx, el) {
    	let step_choice_id = $(el).attr('id').replace('step-page-', '');
    
    	$(el).toggleClass('current', (step_choice_id == page_id));    
  	});
}

function findQuestionsAnswered()
{
	var $ = jQuery;

  	$('.wizard-fieldset').find('.question-body').each(function (i, e) {
    	let question_id = $(e).attr('id').split('survey_indicator_question_')[1];
    	let question = $(`#survey_indicator_question_${question_id}`);
    	let answers_loaded = question.find('.answers-loaded');
    
    	if ($.inArray(parseInt(question_id), indicator_questions_not_answered_unique) == -1 &&
        	!answers_loaded.length)
    	{
      		question.find('.form-indicator-question-answer').removeClass('d-none');
    	}
  	});
}

function findData()
{
	var $ = jQuery;

  	let indicators_list = [];
  	let country_survey_answers = [];
  	let indicator_answers = [];

  	$('.form-indicators').each(function (i, e) {
    	let indicator = {
      		id: null,
      		type: null,
      		inputs: {
        		'choice': null,
        		'answers': null,
        		'reference_year': null,
        		'reference_source': null,
        		'rating': null
      		},
      		accordion: null,
      		question: null,
      		choice: null,
      		answers: [],
      		reference_year: null,
      		reference_source: null,
      		comments: null,
      		rating: null
    	};
    
    	indicator.id = $(e).attr('id').replace('form-indicator-', '');
    	indicator.number = $(e).attr('data-number').replace('form-indicator-', '');
    	indicator.type = $(e).attr('data-type').replace('form-indicator-', '');
    	indicator.accordion = $(e).closest('.accordion-collapse').attr('data-order');
    	indicator.question = $(e).find('.input-choice').attr('data-order');
    	indicator.choice = $(e).find('.input-choice').find('.form-input:checked').val();
    	indicator.inputs.choice = $(e).find('.input-choice').find('.form-input:first').attr('name');
    	indicator.reference_year = $(e).siblings('.form-references').find('select').val();
    	indicator.inputs.reference_year = $(e).siblings('.form-references').find('select').attr('name');
    	indicator.reference_source = $(e).siblings('.form-references').find('textarea').val();
    	indicator.inputs.reference_source = $(e).siblings('.form-references').find('textarea').attr('name');
    	indicator.comments = $(`#form-indicator-${indicator.id}-comments`).val();
    	indicator.rating = $(`.form-indicator-${indicator.id}-rating:checked`).length > 0 ? $(`.form-indicator-${indicator.id}-rating:checked`).val() : 0;
    	indicator.inputs.rating = $(`.form-indicator-${indicator.id}-rating`).attr('name');
    	if ($(e).siblings('.form-indicator-question-load-reset').length > 0) {
      		indicator.answers_loaded = $(e).siblings('.form-indicator-question-load-reset').find('.answers-loaded').length > 0 ? true : false;
    	}
    
    	$(e)
      		.children('.actual-answers')
      		.each(function (idx, el) {
        		indicator.inputs.answers = $(el).find('.form-input:first').attr('name');
        
        		let answers = findAnswers(el);
        		if (answers !== undefined) 
        		{
          			if ($.isArray(answers)) {
            			indicator.answers = answers;
          			}
          			else {
            			indicator.answers.push(answers);
          			}
        		}
      		});
      
      	indicators_list.push(indicator.id);
      	country_survey_answers.push(indicator);
      	if ($(e).closest('fieldset').hasClass('show')) {
        	indicator_answers.push(indicator);
      	}
  	});

  	indicators_list_unique = [...new Set(indicators_list)];
  	country_survey_answers_copy = $.extend(true, {}, country_survey_answers);
  	indicator_answers_copy = $.extend(true, {}, indicator_answers);
}

function updateStepStatus()
{
	var $ = jQuery;

  	$('.step-choice.indicators').each(function (i, element) {
    	let id = $(element).attr('data-id');

    	$(`.step-choice[data-id="${id}"]`).parent()
      		.toggleClass('incomplete', ($.inArray(parseInt(id), incomplete_indicators_unique) != -1))
      		.toggleClass('complete', ($.inArray(parseInt(id), incomplete_indicators_unique) == -1));
  	});
}

function approveIndicator()
{
	var $ = jQuery;

  	$('.loader').fadeIn();

  	skipFadeOut = true;

  	let active_indicator = findActiveIndicator();
  	let requested_indicator = parseInt(active_indicator) + (($('#country-survey-review').is(':visible') || !$('.form-wizard-next-btn').is(':visible')) ? 0 : 1);
  	let action = (is_admin) ? 'final_approve' : 'approve';
  
  	$.ajax({
    	'url': `/country/survey/dashboard/management/indicator/single/update/${active_indicator}`,
    	'type': 'POST',
    	'data': {
      		'action': action,
      		'country_survey_id': country_survey_id
    	},
    	success: function () {
      		viewSurvey({
        		'country_survey_id': country_survey_id,
        		'requested_indicator': requested_indicator,
        		'requested_action': action
      		});
    	},
    	error: function (req) {    
      		$('.loader').fadeOut();

      		setAlert({
        		'status': 'error',
        		'msg': req.responseJSON.error
      		});              
    	} 
  	});
}

function requestChangesIndicator()
{
	var $ = jQuery;

  	$('.loader').fadeIn();

  	skipFadeOut = true;

  	let active_indicator = findActiveIndicator();
  	let deadline = $(`#request-requested-changes-deadline-${active_indicator}`).val();
  	let editor = tinymce.get(`request-requested-changes-${active_indicator}`);
  	let text_content = $.trim(editor.getContent({format: 'text'}));
  	let raw_content = editor.getContent({format: 'raw'});

  	$.ajax({
    	'url': `/country/survey/indicator/request_changes/${active_indicator}`,
    	'type': 'POST',
    	'data': {
      		'country_survey_id': country_survey_id,
      		'deadline': deadline,
      		'changes': (text_content) ? encodeURIComponent(raw_content) : text_content
    	},    
    	success: function () {
      		viewSurvey({
        		'country_survey_id': country_survey_id,
        		'requested_indicator': active_indicator,
        		'requested_action': 'requested_changes'
      		});
    	},
    	error: function (req) {
      		$('.loader').fadeOut();

      		let type = 'error';
      		let message = '';

      		if (req.status == 400) 
      		{
        		let messages = [];
        		$.each(req.responseJSON.errors, function (key, val) {
          			messages.push(val);
        		});
        		message = messages.join(' ');
      		}
      		else if (req.status == 403 ||
               		 req.status == 405)
      		{
        		type = Object.keys(req.responseJSON)[0];
        		message = req.responseJSON[ type ];
      		}
			
      		setAlert({
        		'status': type,
        		'msg': message
      		});
    	} 
  	});
}

function discardRequestedChangesIndicator()
{
	var $ = jQuery;

  	$('.loader').fadeIn();

  	skipFadeOut = true;

  	let active_indicator = findActiveIndicator();

  	$.ajax({
    	'url': `/country/survey/indicator/discard_requested_changes/${active_indicator}`,
    	'type': 'POST',
    	'data': {
      		'country_survey_id': country_survey_id
    	},    
    	success: function () {
      		viewSurvey({
        		'country_survey_id': country_survey_id,
        		'requested_indicator': active_indicator,
        		'requested_action': 'discard_requested_changes'
      		});
    	},
    	error: function (req) { 
      		$('.loader').fadeOut();

      		setAlert({
        		'status': 'error',
        		'msg': req.responseJSON.error
      		});              
    	} 
  	});
}

function unapproveIndicator()
{
	var $ = jQuery;

  	$('.loader').fadeIn();

  	skipFadeOut = true;

  	let active_indicator = findActiveIndicator();
  	let action = 'unapprove';

  	$.ajax({
		'url': `/country/survey/dashboard/management/indicator/single/update/${active_indicator}`,
    	'type': 'POST',
    	'data': {
      		'action': action,
      		'country_survey_id': country_survey_id
    	},    
    	success: function () {
      		viewSurvey({
        		'country_survey_id': country_survey_id,
        		'requested_indicator': active_indicator,
        		'requested_action': action
      		});
    	},
    	error: function (req) {    
      		$('.loader').fadeOut();

      		setAlert({
        		'status': 'error',
        		'msg': req.responseJSON.error
      		});              
    	} 
  	});
}

function validateCountrySurveyIndicator()
{
	var $ = jQuery;

  	$('.loader').fadeIn();

  	skipFadeOut = false;

  	findData();

  	$.ajax({
    	'url': `/country/survey/indicator/validate/${country_survey_id}`,
    	'type': 'POST',
    	'data': {
      		'indicator_answers': JSON.stringify(indicator_answers_copy)
    	},    
    	success: function () {
      		scrollToWizard();
    	},
    	error: function (req) {
			showInputErrors(req.responseJSON.errors).then(() => {
				if (isAccordionOpen()) {
        			scrollToError();
	      		}
			});
    	} 
  	});
}

function loadResetCountrySurveyIndicatorAnswers(action)
{
	var $ = jQuery;

  	$('.loader').fadeIn();

  	skipFadeOut = true;
  	isDirty = false;

  	let active_indicator = findActiveIndicator();
	
  	$.ajax({
		'url': `/country/survey/indicator/answers/${action}/${country_survey_id}`,
    	'type': 'POST',
    	'data': {
      		'active_indicator': active_indicator
    	},    
    	success: function () {
      		if (go_to_data['discard']) {
        		goTo(go_to_data['discard']);
      		}
      		else {
        		viewSurvey({
          			'country_survey_id': country_survey_id,
          			'requested_indicator': active_indicator,
          			'requested_action': `${action}_answers`
        		});
      		}
    	},
    	error: function (req) {     
      		$('.loader').fadeOut();

      		setAlert({
        		'status': 'error',
        		'msg': req.responseJSON.error
      		});
    	} 
  	});
}

function saveCountrySurvey(action)
{
	var $ = jQuery;

  	$('.loader').fadeIn();

  	skipFadeOut = true;

  	let active_indicator = findActiveIndicator();
  
  	findData();
  
  	$.ajax({
    	'url': `/country/survey/save/${country_survey_id}`,
    	'type': 'POST',
    	'data': { 
      		'action': action,
      		'indicators_list': indicators_list_unique,
      		'active_indicator': active_indicator,
      		'country_survey_answers': JSON.stringify(country_survey_answers_copy),
      		'indicator_answers': JSON.stringify(indicator_answers_copy)
    	},
    	success: function () {
      		resetDirty();

      		if (action == 'save')
      		{
        		if (go_to_data['save'])
        		{
          			goTo(go_to_data['save']);
    
          			dirtyModal.hide();
        		}
        		else {
          			viewSurvey({
	            		'country_survey_id': country_survey_id,
    	        		'requested_indicator': active_indicator,
        	    		'requested_action': action
          			});
        		}
      		}
      		else if (action == 'submit')
      		{
        		pageModal.hide();

        		if (is_primary_poc) {
          			viewSurvey({
            			'country_survey_id': country_survey_id
          			});
        		}
        		else {
          			window.location.href = '/survey/user/management';
        		}
      		}
    	},
    	error: function (req) {
      		$('.loader').fadeOut();
      
      		dirtyModal.hide();  
      		pageModal.hide();

      		let type = Object.keys(req.responseJSON)[0];
      
      		if (req.status == 400) 
      		{
        		if (type == 'errors') 
        		{
					showInputErrors(req.responseJSON.errors).then(() => {
						if (isAccordionOpen()) {
        					scrollToError();
	      				}
					});

          			return;
        		}
      		}      
      		else if (req.status == 403 || 
            		 req.status == 405) 
      		{        
        		if (req.responseJSON.indicators_assigned) {
          			$('.alert-section').removeClass('d-none');
        		}
        		$('.wizard-fieldset :input').prop('disabled', true);
        		$('.step-choice').addClass('disabled');
      		}
      
      		setAlert({
        		'status': type,
        		'msg': req.responseJSON[ type ]
      		});    
      
      		resetDirty();
    	}
  	});
}

function jumpToPage(page)
{
	var $ = jQuery;

  	$('.wizard-fieldset').removeClass('show', '400');

  	if (page == 0 || 
    	page == 1) 
  	{
    	$('.wizard-fieldset[data-type="info"]').addClass('show', '400');
  	}
  	else 
  	{
    	let el = $(`.wizard-fieldset#page-${page}`);
    	el.addClass('show', '400');
  	}

  	Drupal.countrySurvey.updateIndicatorStateSection(null, true, true);
}

function showReviewModal() 
{
	var $ = jQuery;

  	let final_submit = ((!incomplete_indicators_assigned_unique.length && !is_primary_poc) ||
                      	(!incomplete_indicators_unique.length &&
                       	!unsubmitted_indicators_unique.length && 
                       	!requested_approval_indicators_unique.length && 
                       	is_primary_poc)) ? true : false;
  	let data = '';
  
  	if (final_submit)
  	{
    	data += '<div>';
    	data += `<h6 id="status-complete-message" class="success">You can now submit the survey to ${(is_primary_poc ? user_group : 'the PoC')} for review!</h6>`;
    	data += '</div>';
  	}
  	else 
  	{
    	$('.modal-body').addClass('indicators');

    	if (!is_primary_poc) {
      		incomplete_indicators_unique = incomplete_indicators_assigned_unique;
    	}

    	if (incomplete_indicators_unique.length)
    	{
      		data += '<div>';
      		data += "<h6 id='status-incomplete-message' class='ms-span error' style='color: var(--state-error);'>Incomplete sections</h6>";
      		data += '<ul>';
      		$.each(incomplete_indicators_unique, function (idx, id) { 
        		data += '<li class="incomplete">';   
        		let indicatorClass = ($.inArray(parseInt(id), assigned_indicators_unique) != -1) ? 'assigned' : 'not_assigned';
        		let obj = getModalErrorData(id);
        		data += `<div class="go-to-page ${indicatorClass}" id="to-page-${obj.indicatorPage}" data-id="${obj.indicatorId}">${obj.indicatorText}</div>`;
        		data += '</li>';        
      		});
      		data += '</ul>';
      		data += '</div>';
    	}
    
    	if (is_primary_poc)
    	{
      		if (unsubmitted_indicators_unique.length) 
      		{
        		data += '<div>';
        		data += "<h6 id='status-unsubmitted-message' class='ms-span error'>Unsubmitted sections</h6>";
        		data += '<ul>';
        		$.each(unsubmitted_indicators_unique, function (idx, id) {             
          			data += '<li class="complete">';
          			let obj = getModalErrorData(id);
          			data += `<div class="go-to-page not_assigned" id="to-page-${obj.indicatorPage}" data-id="${obj.indicatorId}">${obj.indicatorText}</div>`;
          			data += '</li>';          
        		});
        		data += '</ul>';
        		data += '</div>';
      		}

      		if (requested_approval_indicators_unique.length) 
      		{ 
        		data += '<div>';
        		data += "<h6 id='status-request-acceptance-message' class='ms-span error'>Request acceptance sections</h6>";
        		data += '<ul>';
        		$.each(requested_approval_indicators_unique, function (idx, id) {             
          			data += '<li class="complete">';
          			let obj = getModalErrorData(id);
          			data += `<div class="go-to-page not_assigned" id="to-page-${obj.indicatorPage}" data-id="${obj.indicatorId}">${obj.indicatorText}</div>`;
          			data += '</li>';          
        		});
        		data += '</ul>';
        		data += '</div>';
      		}
    	}
  	}

  	$('#country-survey-submit').prop('disabled', !final_submit);
  
  	let obj = {
    	'large': !final_submit,
    	'action': 'submit',
    	'title': $('.country-survey-title').text(), 
    	'html': data,
    	'btn': 'Submit'
  	};

  	setModal(obj);

  	pageModal.show();
}

function viewSurvey(obj)
{
	var $ = jQuery;
	
    let requested_indicator = (obj.requested_indicator) ? `<input type="hidden" name="requested_indicator" value="${obj.requested_indicator}"/>` : '';
    let requested_action = (obj.requested_action) ? `<input type="hidden" name="requested_action" value="${obj.requested_action}"/>` : '';
    let form = `<form action="/country/survey/view/${obj.country_survey_id}" method="POST">
                    <input type="hidden" name="csrf-token" value="${view_survey_token}">
                    <input type="hidden" name="action" value="view"/>
                    ${requested_indicator}
                    ${requested_action}
                </form>`;

    $(form).appendTo($(document.body)).submit();
}