var pageModal = new bootstrap.Modal(document.getElementById('pageModal'));
let table;
let publish_table;
let section = '/survey';

(function ($, Drupal) {
    Drupal.behaviors.listSurveys = {
        attach: function (context, settings) {
            once('listSurveys', 'body').forEach(function () {
                initDataTable();
                
                $(document).on('click', '.item-create', function() {
                    $('.loader').fadeIn();
                    
                    $.ajax({
                        'url': `${section}/management/create`,
                        success: function(data) {
                            let obj = {
                                'large': true,
                                'action': 'create',
                                'route': `${section}/management/store`,
                                'title': 'New Survey',
                                'html': data
                            };
                            setModal(obj);

                            initDatePicker();

                            pageModal.show();
                        }
                    });
                });

                $(document).on('click', '.item-edit', function() {
                    $('.loader').fadeIn();

                    let id = $(this).attr('item-id');
        
                    $.ajax({
                        'url': `${section}/management/show/${id}`,
                        success: function(data) {
                            $('.loader').fadeOut();
                            let obj = {
                                'id': id,
                                'large': true,
                                'action': 'edit',
                                'route': `${section}/management/update/${id}`,
                                'title': 'Edit Survey',
                                'html': data
                            };
                            setModal(obj);

                            initDatePicker();
                            
                            pageModal.show();
                        }
                    });
                });

                $(document).on('click', '.item-publish', function() {
                    $('.loader').fadeIn();

                    let id = $(this).attr('item-id');
        
                    $.ajax({
                        'url': `${section}/management/publish/show/${id}`,
                        success: function(data) {
                            $('.loader').fadeOut();
                            let obj = {
                                'id': id,
                                'large': true,
                                'action': 'publish',
                                'route': `${section}/management/publish/create/${id}`,
                                'title': 'Publish Survey',
                                'html': data,
                                'btn': 'Publish'
                            };
                            setModal(obj);

                            initPublishUsersDataTable(id);

                            pageModal.show();
                        }
                    });
                });

                $(document).on('click', '.item-download', function() {
                    $('.loader').fadeIn();

                    let id = $(this).attr('item-id');

                    $.ajax({
                        'url': `${section}/excel/template/export/${id}`,
                        'type': 'POST',
                        success: function(data) {
                            let link = document.createElement('a');

                            link.href = `${section}/excel/download/${encodeURIComponent(data.filename)}`;
                            link.setAttribute('download', '');

                            document.body.appendChild(link);

                            link.click();
                            link.remove();
                        },
                        error: function(req) {
                            setAlert({
                                'status': 'error',
                                'msg': req.responseJSON.error
                            });
                        }
                    });
                });
        
                $(document).on('click', '.item-delete', function() {
                    let id = $(this).attr('item-id');
                    let name = $(this).attr('item-name');
                    let data = `<form id="item-delete">
                                    <input hidden id="item-id"/>
                                    <input hidden id="item-action"/>
                                    <input hidden id="item-route"/>
                                    <div class="warning-message">
                                        Survey <b>${name}</b> will be deleted. Are you sure?
                                    </div>
                                </form>`;
                
                    let obj = {
                        'modal': 'warning',
                        'id': id,
                        'action': 'delete',
                        'route': `${section}/management/delete/${id}`,
                        'title': 'Delete Survey',
                        'html': data,
                        'btn': 'Delete'
                    }
                    setModal(obj);

                    pageModal.show();
                });
                
                $(document).on('click', '#process-data', function() {
                    $('.loader').fadeIn();

                    let route = $('.modal #item-route').val();
                    let form = $('.modal').find('form').attr('id');
                    let data = getFormData(form);
                    if ($('.modal #item-id').length &&
                        $('.modal #item-id').val())
                    {
                        data.append('id', $('.modal #item-id').val());
                    }
                        
                    $.ajax({
                        'url': route,
                        'type': 'POST',
                        'data': data,
                        'contentType': false,
                        'processData': false,
                        success: function() {
                            pageModal.hide();
                            table.ajax.reload(null, false);
                        },
                        error: function(req) {
                            if (req.status == 400)
                            {
                                if (req.responseJSON.type == 'pageModalForm') {
                                    showInputErrors(req.responseJSON.errors);
                                }
                            }
        
                            if (req.responseJSON.type &&
                                req.responseJSON.error)
                            {
                                if (req.responseJSON.type == 'pageAlert') {
                                    pageModal.hide();
                                }
        
                                setAlert({
                                    'type': req.responseJSON.type,
                                    'status': 'error',
                                    'msg': req.responseJSON.error
                                });
                            }
                        }
                    });
                });

                $(document).on('change', '.publish_users', function() {
                    $('#publish-users-table_wrapper').parent('div').toggle(this.id == 'radio-specific');
                    $('.close-alert').trigger('click');
                });
            });
        }
    }
})(jQuery, Drupal);

function initDataTable()
{
    jQuery(function($) {
        table = $('#surveys-table').DataTable({
            'ajax': `${section}/management/list`,
            'initComplete': function() {
                addDataTableSearchInfoIcon();
            },
            'drawCallback': function() {
                convertToLocalTimestamp();

                toggleDataTablePagination(this, '#surveys-table_paginate');
            },
            'columns': [
                {
                    'data': 'title'
                },
                {
                    'data': 'year'
                },
                {
                    'data': 'deadline',
                    render: function(data, type, row) {
                        if (type === 'sort') {
                            return data;
                        }
                        
                        return `<span class="local-timestamp-display date" style="width: 81px;">${row.deadline}</span>
                                <span class="local-timestamp" hidden>${row.deadline}</span>`;
                    }
                },
                {
                    'data': 'created_by'
                },
                {
                    'data': 'status',
                    render: function(data, type, row) {
                        return `<div class="d-flex justify-content-center">
                                    <button class="btn-${(row.status == 'Published' ? 'positive' : 'positive-invert')} btn-label pointer-events-none" type="button">${row.status}</button>
                                </div>`;
                    }
                },
                {
                    'data': 'actions',
                    render: function(data, type, row) {
                        let is_survey_published = (row.status == 'Published') ? true : false;
                        let is_survey_submitted = (row.not_submitted) ? false : true;

                        let survey_dashboard_click = (is_survey_published) ?
                            ' onclick="location.href=\'/survey/dashboard/management/' + row.id + '\';"' : '';

                        return `<div class="d-flex justify-content-center">
                                    <button class="icon-edit btn-unstyle item-edit" data-bs-toggle="tooltip"
                                        title="Edit survey" type="button" item-id="${row.id}">
                                    </button>
                                    <button class="icon-publish btn-unstyle item-publish" data-bs-toggle="tooltip"
                                        title="Publish survey" type="button" item-id="${row.id}">
                                    </button>
                                    <button class="icon-reminder btn-unstyle item-remind ${(is_survey_submitted) ? 'icon-reminder-deactivated' : ''}"
                                        data-bs-toggle="tooltip" title="Send notifications" type="button" item-id="${row.id}" ${(is_survey_submitted ? 'disabled' : '')}>
                                    </button>
                                    <button class="icon-overview btn-unstyle ${(!is_survey_published) ? 'icon-overview-deactivated' : ''}"
                                        data-bs-toggle="tooltip" title="View survey dashboard" type="button" ${survey_dashboard_click} ${(!is_survey_published ? 'disabled' : '')}>
                                    </button>
                                    <button class="icon-xls-download btn-unstyle item-download"
                                        data-bs-toggle="tooltip" title="Download survey template" type="button" item-id="${row.id}">
                                    </button>
                                    <button class="icon-bin btn-unstyle item-delete ${(is_survey_published) ? 'icon-bin-deactivated' : ''}"
                                        data-bs-toggle="tooltip" title="Delete survey" type="button" item-id="${row.id}" item-name="${row.title}" ${(is_survey_published ? 'disabled' : '')}>
                                    </button>
                                </div>`;
                    },
                    'orderable': false
                }
            ]
        });
    });
}

function initPublishUsersDataTable(id)
{
    jQuery(function($) {
        publish_table = $('#publish-users-table').DataTable({
            'ajax': `${section}/management/publish/users/list/${id}`,
            'initComplete': function() {
                addDataTableSearchInfoIcon();
            },
            'drawCallback': function() {
                toggleDataTablePagination(this, '#publish-users-table_paginate');
            },
            'order': [
                [4, 'asc']
            ],
            'createdRow': function(row, data, dataIndex) {
                if (data.notified) {
                    $(row).addClass('row-disabled');
                }
            },
            'columns': [
                {
                    'data': 'id',
                    render: function(data, type, row) {
                        return `<input class="item-select form-check-input" type="checkbox" id="item-${row.id}" ${(row.notified ? 'disabled checked' : '')}/>`;
                    },
                    'orderable': false
                },
                {
                    'data': 'name'
                },
                {
                    "data": 'email'
                },
                {
                    'data': 'role',
                    render: function(data, type, row) {
                        return `<span>${row.role.name}</span>`;
                    }
                },
                {
                    'data': 'country',
                    render: function(data, type, row) {
                        return `<span>${row.country.name}</span>`;
                    }
                }
            ]
        });

        $('#publish-users-table_wrapper').parent('div').hide();
    });
}