var pageModal = new bootstrap.Modal(document.getElementById('pageModal'));
var printSurveyModal = new bootstrap.Modal(document.getElementById('printSurveyModal'));
let table;
let table_loaded = false;
let indicator_id;
let indicator_survey_data;
let formBuilder;
let loadedInputs = {};
let updateTimer;
let debounceTime = 1000;
let canSaveSurvey = false;

(function ($, Drupal) {
    Drupal.behaviors.indicatorSurvey = {
        attach: function (context, settings) {
            once('indicatorSurvey', 'body').forEach(function () {
                indicator_id = settings.indicator_survey.indicator_data.id;
                indicator_survey_data = settings.indicator_survey.indicator_survey_data;
                
                skipFadeOut = true;

                table = $('#indicators-reorder-table').DataTable({
                    'ajax': {
                        'url': '/indicator/list',
                        'data': function (data) {
                            data.category = 'survey';
                        }
                    },
                    'initComplete': function() {
                        table_loaded = true;
                    },
                    createdRow: function(row, data, index) {
                        $(row).attr('id', 'row_' + data.id);
                        
                        if (!data.validated) {
                            $(row).addClass('not_validated');
                        }
                    },
                    'columns': [
                        {
                            'data': 'order',
                            'className': 'reorder'
                        },
                        {
                            'data': 'name'
                        },
                        {
                            'data': 'subarea',
                            render: function(data, type, row) {
                                return (row.subarea) ? row.subarea.name : '';
                            }
                        },
                        {
                            'data': 'validated',
                            render: function(data, type, row) {
                                let validated = (row.validated) ? 'Yes' : 'No';
        
                                return `<span style="text-transform: capitalize;">${validated}</span>`;
                            }
                        }
                    ],
                    'stripeClasses': [],
                    'ordering': false,
                    'paging': false,
                    'searching': false,
                    'rowReorder': true,
                    'responsive': true
                });

                table.on('row-reorder', function(e, diff, edit) {
                    let data = [];
                    let loaded_indicator = $('.form-indicator-order');
                    
                    diff.forEach(change => {
                        let id = change.node.id;
                        let order = change.newPosition + 1;
    
                        data.push({
                            'id': id,
                            'order': order
                        });
    
                        if (id.indexOf(loaded_indicator.data('id')) >= 0) {
                            loaded_indicator.text(order);
                        }
                    });
    
                    updateOrder(data);
                });

                let options = {
                    'disableFields': [
                        'autocomplete',
                        'button',
                        'date',
                        'file',
                        'hidden',
                        'number',
                        'paragraph',
                        'select',
                        'textarea'
                    ],
                    'disabledAttrs': [
                        'access',
                        'className',
                        'inline',
                        'maxlength',
                        'other',
                        'placeholder',
                        'subtype',
                        'toggle',
                        'value'
                    ],
                    'disabledActionButtons': ['clear', 'data', 'save'],
                    'controlOrder': [
                        'header',
                        'radio-group',
                        'checkbox-group',
                        'text'
                    ],
                    'replaceFields': [
                        {
                            'type': 'header',
                            'label': 'Section Title'
                        },
                        {
                            'type': 'radio-group',
                            'label': 'Single-choice Question',
                            'values': [
                                {'label': 'Option 1', 'value': ''},
                                {'label': 'Option 2', 'value': ''},
                                {'label': 'Option 3', 'value': ''}
                            ]
                        },
                        {
                            'type': 'checkbox-group',
                            'label': 'Multiple-choice Question',
                            'values': [
                                {'label': 'Option 1', 'value': ''},
                                {'label': 'Option 2', 'value': ''},
                                {'label': 'Option 3', 'value': ''}
                            ]
                        },
                        {
                            'type': 'text',
                            'label': 'Free-text Question'
                        }
                    ],
                    'typeUserAttrs': {
                        'radio-group': {
                            'compatible': {
                                'type': 'checkbox',
                                'label': 'Compatible',
                                'value': true
                            }
                        },
                        'checkbox-group': {
                            'master': {
                                'type': 'select',
                                'label': 'Master',
                                'options': {}
                            },
                            'compatible': {
                                'label': 'Compatible',
                                'value': true,
                                'type': 'checkbox'
                            }
                        },
                        'text': {
                            'compatible': {
                                'label': 'Compatible',
                                'value': true,
                                'type': 'checkbox'
                            }
                        }
                    },
                    'typeUserDisabledAttrs': {
                        'header': ['description']
                    },
                    'stickyControls': {
                        'enable': true,
                        'offset': {
                            'top': 20,
                            'right': 20,
                            'left': 'auto'
                        }
                    },
                    'fieldRemoveWarn': true,
                    onAddFieldAfter: function(fieldId, fieldData) {
                        // Check required checkbox on new question by default
                        if ((fieldData.type == 'radio-group' ||
                             fieldData.type == 'checkbox-group' ||
                             fieldData.type == 'text') &&
                            !('required' in fieldData))
                        {
                            $('#' + fieldId).find('.required-asterisk').show();
                            $('#' + fieldId).find('.fld-required').prop('checked', true);
                        }
    
                        setMaster();
    
                        if (canSaveSurvey) {
                            saveSurvey({'action': 'autosave'});
                        }
                    },
                    onOpenFieldEdit: function(editPanel) {
                        // Default: Label
                        $('.header-field .form-elements .label-wrap label').html('Section Title');
                        // Add required asterisk to Label/Options
                        $('.form-elements .label-wrap label, .form-elements .field-options label').addClass('required');
                        // Default: Required
                        $('.form-elements .required-wrap label').html('Mandatory');
                        // Default: Label
                        $('.radio-group-field .form-elements .label-wrap label, \
                           .checkbox-group-field .form-elements .label-wrap label, \
                           .text-field .form-elements .label-wrap label').html('Question');
                        // Default: Description
                        $('.form-elements .description-wrap label').html('Info bubble');
                        // Default: Options
                        $('.form-elements .field-options label').html('Answers');
                        // Hide Name - used only in validation
                        $('.form-elements .name-wrap').hide();
    
                        // Compatible Tooltip
                        let compatible_label = $(editPanel).find('.form-elements .compatible-wrap label');
    
                        if (!compatible_label.children('.info-icon-black').length)
                        {
                            let tooltip = 'MS can upload last year\'s answers for this indicator';
    
                            compatible_label.append('<span class="info-icon-black" data-bs-toggle="tooltip" data-bs-placement="right" title="" data-bs-original-title="' + tooltip + '" aria-label="' + tooltip + '"></span>');
                        }
                        
                        formatTitle(editPanel);
                        formatOptions();
    
                        loadedInputs[editPanel.id] = getSerializedInputs(editPanel.id);
                    }
                };
                
                formBuilder = $('#survey-editor').formBuilder(options);
    
                formBuilder.promise.then(function() {
                    if (indicator_survey_data)
                    {
                        formBuilder.actions.setData(indicator_survey_data);
    
                        formatTooltip();
                        setMaster();
                    }
    
                    $('.loader').fadeOut();
    
                    canSaveSurvey = true;
                });

                $(document).on('click', '#indicatorsListDropdown', function () {
                    $('.dropdown-menu-wrapper.indicators').toggleClass('indicator-list-toggle-open');
                });
        
                $(document).on('click', '#indicators-reorder-table tbody tr', function() {
                    $('.loader').fadeIn();
        
                    let rowData = table.row(this).data();
        
                    location.href = `/indicator/survey/management/${rowData.id}`;
                });
        
                $(document).on('click', '.preview-survey, .preview-indicator', function() {
                    $('.loader').fadeIn();
        
                    skipFadeOut = true;
        
                    let indicator = $(this).attr('data-id');
        
                    $.ajax({
                        'url': `/country/survey/preview/${indicator ? `${indicator}` : ''}`,
                        'data': {
                            'with_answers': false
                        },
                        success: function() {
                            printSurveyModal.show();
                        }
                    }).done(function (data) {
                        /*** setTimeout needed to give time for pajed.js to render page ***/
                        setTimeout(() => {
                            $('#surveyContainer').empty();
                                
                            let iframe = document.createElement('iframe');
        
                            iframe.setAttribute('id', 'surveyIframe');
                            iframe.setAttribute('srcdoc', data);
                            iframe.style.height = '70vh';
                            
                            document.getElementById('surveyContainer').appendChild(iframe);
        
                            iframe.onload = function () {
                                let iframe_doc = iframe.contentDocument || iframe.contentWindow.document;

                                let js_files = [
                                    '/core/assets/vendor/jquery/jquery.min.js',
                                    '/themes/custom/enisa/js/survey/surveyActions.js',
                                    '/themes/custom/enisa/js/main/paged.polyfill.js'
                                ];

                                (async () => {
                                    await loadScripts(iframe_doc, js_files);
                                })();

                                expandTextareas(iframe_doc);
                                toggleElements(iframe_doc);

                                let css_files = [
                                    '/themes/custom/enisa/css/style.css',
                                    '/themes/custom/enisa/css/bootstrap.min.css',
                                    '/themes/custom/enisa/css/survey-preview.css'
                                ];
        
                                css_files.forEach((href) => {
                                    let css_link = document.createElement('link');
                                    
                                    css_link.rel = 'stylesheet';
                                    css_link.href = href;
                                    css_link.type = 'text/css';
                                    
                                    iframe_doc.head.appendChild(css_link);
                                });
                            };
                        }, 1000);
                    });
                });
        
                $(document).on('input', '.form-elements input, .form-elements textarea, .form-elements select', function() {
                    clearTimeout(updateTimer);
        
                    let editPanel = $(this).closest('.frm-holder').attr('id');
                    
                    if (getSerializedInputs(editPanel) !== loadedInputs[editPanel]) {
                        updateTimer = setTimeout(function () {
                            loadedInputs[editPanel] = getSerializedInputs(editPanel);
        
                            saveSurvey({'action': 'autosave'});
                        }, debounceTime);
                    }
                });
        
                // Update question div value on textarea change
                $(document).on('input', '.form-elements .label-wrap textarea', function() {
                    $(this).siblings('.fld-label').html($(this).val());
                    $(this).closest('.frm-holder').siblings('.field-label').html($(this).val());
                });
        
                $(document).on('input keydown keyup', '.fld-description', function() {
                    let old_tooltip = $(this).closest('.frm-holder').siblings('.tooltip-element');
                    let new_tooltip = $(this).closest('.frm-holder').siblings('.info-icon-black');
                    let val = $(this).val();
        
                    if (val.length)
                    {
                        $(old_tooltip).hide();
        
                        if (!new_tooltip.length) {
                            addTooltipElement(old_tooltip);
                        }
        
                        $(new_tooltip).attr({'data-bs-original-title': val, 'aria-label': val});
                    }
                    else {
                        $(new_tooltip).remove();
                    }
                });
        
                $(document).on('click', '.delete-confirm', function() {
                    $('.form-builder-dialog .btn').addClass('formbuilder');
                });
        
                $(document).on('click', '.form-builder-dialog .yes', function() {
                    checkBuilderDataOnRemoval(formBuilder.actions.getData());
                });
        
                // Update option label value on textarea change
                $(document).on('input', '.form-elements .field-options textarea', function() {
                    $(this).siblings('.option-label').val($(this).val());
                });
        
                $(document).on('click', '.add-opt', function() {
                    formatOptions();
                });
        
                $(document).on('click', '.remove', function() {
                    let option_removed = $(this).siblings('.option-label').val();
                    let master = $(this).closest('.field-options').siblings('.master-wrap').find('.fld-master');
                    let masters_selected = master.val();
                    
                    // Remove option from select2 if option is the same with the one removed
                    if ($.inArray(option_removed, masters_selected) != -1) {
                        $(master).find('option[value="' + option_removed + '"]').remove();
                    }
        
                    checkBuilderDataOnRemoval(formBuilder.actions.getData());
                });
        
                $(document).on('select2:open', '.fld-master', function() {
                    let that = $(this);
                    let masters_selected = that.val();
                    
                    // Add existing or new options to master
                    that.children('option').remove();
                    that.closest('.master-wrap').siblings('.field-options').find('li input[data-attr="label"]').each(function(i) {
                        if ($(this).val()) {
                            that.append('<option value="' + $(this).val() + '"' + ($.inArray($(this).val(), masters_selected) != -1 ? ' selected' : '') + '>' + $(this).val() + '</option>');
                        }
                    });
                });
        
                $(document).on('click', '.load-indicator-survey', function() {
                    let data = `<input hidden id="item-action"/>
                                <input hidden id="item-route"/>
                                <div class="warning-message">
                                    Indicator survey will be loaded from last year. Are you sure?
                                </div>`;
        
                    let obj = {
                        'modal': 'warning',
                        'action': 'load',
                        'route': `/indicator/survey/load/${indicator_id}`,
                        'title': 'Load Indicator Survey',
                        'html': data,
                        'btn': 'Load'
                    };
                    setModal(obj);
        
                    pageModal.show();
                });
        
                $(document).on('click', '#process-data', function() {
                    $('.loader').fadeIn();
                                
                    let route = $('.modal #item-route').val();
                    let action = $('.modal #item-action').val();
                    
                    $.ajax({
                        'url': route,
                        'type': 'POST',
                        success: function() {
                            if (action == 'load') {
                                location.reload();
                            }
                        },
                        error: function(req) {
                            setAlert({
                                'status': 'error',
                                'msg': req.responseJSON.error
                            });
                        }
                    });
                });
        
                $(document).on('click', '.save', function() {
                    $('.loader').fadeIn();
        
                    clearTimeout(updateTimer);
        
                    saveSurvey({'action': 'save'});
                });
            });
        }
    };
})(jQuery, Drupal);

function updateOrder(data)
{
    var $ = jQuery;
    
    $('.loader').fadeIn();

    $.ajax({
        'url': '/indicator/order/update',
        'type': 'POST',
        'data': JSON.stringify(data),
        success: function() {
            table.ajax.reload(checkDataTableReload, false);
        }
    });
}

function checkDataTableReload()
{
    if (table_loaded) {
        jQuery('.loader').fadeOut();
    }
}

function saveReportPDF()
{
    let printIframe = document.getElementById('surveyIframe').contentWindow;

    printIframe.focus();
    printIframe.print();

    return false;
}

function getSerializedInputs(editPanel)
{
    var $ = jQuery;

	return $('#' + editPanel).find('input, textarea, select').serialize();
}

function addTooltipElement(old_tooltip)
{
    var $ = jQuery;

    $('<span class="info-icon-black" data-bs-toggle="tooltip" data-bs-placement="right" title="" data-bs-original-title="' + $(old_tooltip).attr('tooltip') + '" aria-label="' + $(old_tooltip).attr('tooltip') + '"></span>').insertAfter(old_tooltip);
}

function formatTooltip()
{
    var $ = jQuery;
    
    $('.tooltip-element').each(function() {
        if ($(this).is(':visible'))
        {
            $(this).hide();

            addTooltipElement($(this));
        }
    });
}

function formatTitle(editPanel)
{
    var $ = jQuery;
    
    // Add textarea after contenteditable div and hide contenteditable div
    let title_div = $(editPanel).find('.form-elements .label-wrap .fld-label');
    let type = '';
    if (title_div.parent().siblings('label').text().indexOf('Section') >= 0) {
        type = 'section';
    }
    else if (title_div.parent().siblings('label').text().indexOf('Question') >= 0) {
        type = 'question';
    }
            
    if (!title_div.next('textarea').length)
    {
        title_div.hide().after(`<textarea name="${type}-label"></textarea><div class="invalid-feedback"></div>`);
        if (title_div.html().trim() === '') {
            title_div.html('');
        }
        title_div.siblings('textarea').val(title_div.html());
    }

    $('.form-elements .label-wrap textarea').attr('placeholder', `${capitalizeFirstLetter(type)} Title`);
}

function formatOptions()
{
    var $ = jQuery;

    // Add textarea after option label and hide option label
    $('.form-elements .field-options li').each(function() {
        $(this).addClass('row');

        let option_label = $(this).find('.option-label');

        if (!option_label.next('textarea').length)
        {
            option_label.hide().after('<textarea name="option-label"></textarea>');
            option_label.siblings('textarea').val(option_label.val());
        }

        let option_score = $(this).find('.option-value');

        if (!option_score.next('div').length)
        {
            option_score.after('<div class="invalid-feedback score"></div>');
            option_score.after('<div class="invalid-feedback label"></div>');
        }
    });

    $('.form-elements .field-options textarea').attr('placeholder', 'Label');
    $('.form-elements .field-options input.option-value').attr({'name': 'option-value', 'placeholder': 'Score'});
}

function setMaster()
{
    var $ = jQuery;

    $('.fld-master').attr('multiple', 'multiple').val('').select2();
    $('.fld-master').each(function() {
        let that = $(this);

        $.each(JSON.parse(indicator_survey_data), function(key, val) {
            if (val.type == 'checkbox-group' &&
                val.name == that.attr('master'))
            {
                $.each(val.master_options, function(key, val) {
                    if (val.label) {
                        that.append(`<option value="${val.label}" ${(val.selected ? 'selected' : '')}>${val.label}</option>`);
                    }
                });

                return false;
            }
        });
    });
}

function saveSurvey(obj)
{
    var $ = jQuery;

	$.ajax({
        'url': `/indicator/survey/store/${indicator_id}`,
        'type': 'POST',
        'data': {
            'action': obj.action,
            'indicator_survey_data': JSON.stringify(formBuilder.actions.getData())
        },
        success: function(response) {
            table.ajax.reload(checkDataTableReload, false);
                    
            if (obj.action == 'save')
            {
                $('.preview-indicator').parent().removeClass('d-none');
                $('.preview-survey').parent().parent().removeClass('d-none');

                clearFieldErrors();
                        
                setAlert({
                    'status': 'success',
                    'msg': response.success
                });
            }
        },
        error: function(req) {
            table.ajax.reload(checkDataTableReload, false);
                    
            if (obj.action == 'save')
            {
                clearFieldErrors();

                formBuilder.actions.closeAllFieldEdit();

                let exception_alert = req.responseJSON.find(function(exception) {
                    return exception.data.element_type === 'alert';
                });
                        
                let first_invalid = null;
                        
                $.each(req.responseJSON, function(exception_key, exception) {
                    let error = exception.error;
                    let data = exception.data;

                    let edit = null;
                    if (data.element_type != 'alert')
                    {
                        let input_id = $(`input[value='${data.field_id}']`).attr('id');
                        edit = $(`a#${input_id.replace('name-', '')}-edit`);

                        if (!edit.hasClass('open')) {
                            edit.trigger('click');
                        }
                    }

                    // Section / Question
                    if (data.element_type == 'section' ||
                        data.element_type == 'question')
                    {
                        let elem = edit.parent('.field-actions').siblings('.frm-holder').find('.fld-label').next('textarea');

                        elem.addClass('is-invalid').siblings('.invalid-feedback').html(error).show();
                                
                        if (exception_alert === undefined &&
                            exception_key == 0)
                        {
                            first_invalid = elem;
                        }
                    }
                    // Options
                    else if (data.element_type == 'option')
                    {
                        edit.parent('.field-actions').siblings('.frm-holder').find('.form-elements .field-options li').each(function(key, elem) {
                            if (key == data.element_id)
                            {
                                if (error.indexOf('label') >= 0) {
                                    $(elem).find('.option-label').next('textarea').addClass('is-invalid').siblings('.invalid-feedback.label').html(error).show();
                                }
                                else if (error.indexOf('score') >= 0) {
                                    $(elem).find('.option-value').addClass('is-invalid').siblings('.invalid-feedback.score').html(error).show();
                                }
                                    
                                if (exception_alert === undefined &&
                                    exception_key == 0)
                                {
                                    first_invalid = elem;
                                }
                                    
                                return false;
                            }
                        });
                    }
                    // Alert
                    else if (data.element_type == 'alert') {
                        setAlert({
                            'status': 'error',
                            'msg': error
                        });
                    }
                });

                if (first_invalid) {
                    scrollToElement(first_invalid);
                }
            }
        }
    });
}

function checkBuilderDataOnRemoval(initialData)
{
    return new Promise((resolve, reject) => {
        // Recursive function to keep checking
        const check = () => {
            let currentData = formBuilder.actions.getData();

            // Check if builder getData has been updated with element removal
            if (JSON.stringify(initialData) != JSON.stringify(currentData)) {
                saveSurvey({'action': 'autosave'});
            }
            else {
                setTimeout(check, 100);
            }
        };

        check();
    });
}

function clearFieldErrors()
{
    var $ = jQuery;

    $('#survey-editor').find('.invalid-feedback').empty();
    $('#survey-editor').find(':input').removeClass('is-invalid');
}

function scrollToElement(element)
{
    var $ = jQuery;

    // Scroll all scrollable elements to the top
    $('*').each(function() {
        if ($(this).css('overflow') === 'auto' ||
            $(this).css('overflow') === 'scroll')
        {
            $(this).scrollTop(0);
        }
    });

    $('html, body').animate({
        scrollTop: $('#survey-editor').offset().top - 30
    }, 0);

    setTimeout(() => {
        $('#survey-editor').animate({
            scrollTop: $(element).offset().top - $('#survey-editor').offset().top - 30
        });
    }, 1000);
}

function capitalizeFirstLetter(string)
{
    return string.charAt(0).toUpperCase() + string.slice(1).toLowerCase();
}

function loadScripts(iframe_doc, files)
{
    return files.reduce((promise, src) => {
        return promise.then(() => new Promise((resolve, reject) => {
            let script = document.createElement('script');

            script.src = src;
            script.defer = true;
            script.onload = () => {
                if (src.indexOf('paged.polyfill') >= 0)
                {
                    var changeEvent = new Event('pagedRendered');
                    parent.window.dispatchEvent(changeEvent);
                }

                resolve();
            };
            script.onerror = (e) => {
                reject(e);
            };

            iframe_doc.head.appendChild(script);
        }));
    }, Promise.resolve());
}

function expandTextareas(iframe_doc)
{
    var $ = jQuery;
    
    $(iframe_doc).find('textarea').each(function (i, el) {
        el.style.height = 'auto';
        el.style.height = el.scrollHeight + 'px';
    });
}

function toggleElements(iframe_doc)
{
    var $ = jQuery;
    
    $(iframe_doc).find('.form-indicators .input-choice input:checked').each(function (i, el) {
        toggleAnswers(el);
        toggleReference(el);

        let choice = $(el).val();
        if (choice != 3)
        {
            let master = $(el).closest('.input-choice').siblings('.actual-answers').find('.form-input.master');
            if (master.length) {
                toggleOptions(master);
            }
        }
    });
}

window.addEventListener('pagedRendered', function() {
    var $ = jQuery;

    $('.loader').fadeOut();

    skipFadeOut = false;
});