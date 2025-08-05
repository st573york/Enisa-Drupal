let skipClearErrors = false;

(function ($, Drupal) {
    Drupal.behaviors.main = {
        attach: function (context, settings) {
            once('main', 'body').forEach(function () {
                $(document).on('ajaxSend', function (event, jqxhr, settings) {
                    if (settings.url.includes('/history/')) {
                        return;
                    }

                    if (!skipClearErrors)
                    {
                        clearInputErrors();
                        clearPageErrors();
                    }
                });
            });
        }
    }
})(jQuery, Drupal);

function getFormData(id)
{
    // Get values from all form inputs, exclude radio buttons - processed below
    let form = jQuery(`form#${id}`).not(':input[type=radio]')[0];
    
    // FormData object
    let fd = new FormData(form);
    
    // Get values from DataTable, tinymce
    fd = getDataTableData(fd);
    fd = getTinymceData(fd);

    // Get values from all checkboxes - convert 'on' value to integer
    jQuery(`form#${id} input[type=checkbox]`).each(function() { 
        fd.append(this.name, (this.checked ? 1 : 0));    
    });

    // Get value from selected radio
    jQuery(`form#${id} input[type=radio]:checked`).each(function() {
        fd.append(this.name, this.id);    
    });

    // Get values from uploaded files
    jQuery(`form#${id} input[type=file]`).each(function() {
        if (this.files.length) {
            fd.append('file', this.files[0]);
        }
    });

    return fd;
}

function getDataTableData(fd)
{
    if (jQuery.fn.DataTable.isDataTable('.enisa-table-group') &&
        jQuery('.enisa-table-group').find('.item-select').length)
    {
        let rows = jQuery('.enisa-table-group').DataTable().rows().nodes().to$().find('.item-select');

        let selected = [];
        let all = [];
        
        rows.each(function() {
            let id = this.id.replace('item-', '');
            if (this.checked) {
                selected.push(id);
            }
            all.push(id);
        });

        fd.append('datatable-selected', selected);
        fd.append('datatable-all', all);
    }

    return fd;
}

function getTinymceData(fd)
{
    jQuery('.tinymce').each(function() {
        let editor = tinymce.get(this.id);
        let text_content = jQuery.trim(editor.getContent({format: 'text'}));
        let raw_content = editor.getContent({format: 'raw'}).replace(/&nbsp;/g, '');
        let content = (text_content) ? encodeURIComponent(raw_content) : text_content;
        
        fd.append(this.name, content);
    });

    return fd;
}

function clearInputErrors()
{
    jQuery(function($) {
        $('form, .wizard-fieldset.show').find(':input').removeClass('is-invalid');
        $('form, .wizard-fieldset.show').find('.invalid-feedback').empty();
    });
}

function clearPageErrors()
{
    jQuery(function($) {
        $('.close-alert').trigger('click');
    });
}

function showInputErrors(data)
{
    return new Promise((resolve) => {
        jQuery(function($) {
            for (var key in data)
            {
                let input = $('form, .wizard-fieldset.show').find(`:input[name="${key}"]`);
                input.addClass('is-invalid');
                if (input.nextAll('.invalid-feedback:first').length) {
                    input.nextAll('.invalid-feedback:first').html(data[key]).show();
                }
                else if (input.parents().nextAll('.invalid-feedback:first').length) {
                    input.parents().nextAll('.invalid-feedback:first').html(data[key]).show();
                }
            }

            resolve();
        });
    });
}

function toggleDataTablePagination(that, selector)
{
    jQuery(function($) {
        let api = that.api();
        let pages = api.page.info().pages;
    
        $(selector).toggle((pages > 1));
    });
}

function toggleRoles(user_group)
{
    jQuery(function($) {
        let country = $('option:selected', 'select[name="country"]').val();
        let role = $('option:selected', 'select[name="role"]').val();
        
        switch (country)
        {
            case user_group:
                if (role != 'enisa_administrator' &&
                    role != 'viewer')
                {
                    $('select[name="role"]').val($('select[name="role"] option:first').val());
                }

                break;
            default:
                if (role == 'enisa_administrator') {
                    $('select[name="role"]').val($('select[name="role"] option:first').val());
                }

                break;
        }
    });
}

function toggleCountries(user_group)
{
    jQuery(function($) {
        let country = $('option:selected', 'select[name="country"]').val();
        let role = $('option:selected', 'select[name="role"]').val();
        
        switch (role)
        {
            case 'enisa_administrator':
                if (country != user_group) {
                    $('select[name="country"]').val($('select[name="country"] option:first').val());
                }

                break;
            case 'primary_poc':
            case 'poc':
            case 'operator':
                if (country == user_group) {
                    $('select[name="country"]').val($('select[name="country"] option:first').val());
                }

                break;
            default:
                break;
        }
    });
}