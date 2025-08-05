var pageModal = new bootstrap.Modal(document.getElementById('pageModal'));
let table;
let section = '/country/survey';
let user_group;
let country_survey_id;
let survey_deadline;
let is_poc;
let is_admin;
let is_assigned;
let is_finalised;
let requested_changes = false;
let view_survey_token;

(function ($, Drupal) {
    Drupal.behaviors.countrySurveyDashboard = {
        attach: function (context, settings) {
            once('countrySurveyDashboard', 'body').forEach(function () {
                user_group = settings.country_dashboard.user_group;
                country_survey_id = settings.country_dashboard.assignee_country_survey_data.id;
                survey_deadline = getLocalTimestamp(settings.country_dashboard.assignee_country_survey_data.survey.deadline, 'date');
                is_poc = settings.country_dashboard.is_poc;
                is_admin = settings.country_dashboard.is_admin;
                is_assigned = (Object.keys(settings.country_dashboard.assignee_country_survey_data.indicators_assigned).length) ? true : false;
                is_finalised = (Object.keys(settings.country_dashboard.assignee_country_survey_data.approved_user).length) ? true : false;
                view_survey_token = settings.country_dashboard.view_survey_token;
                
                initDataTable();

                $(document).on('change update_buttons', '#item-select-all, .item-select', function() {
                    $('.multiple-edit, .multiple-approve').prop('disabled', (!getDataTableCheckboxesCheckedAllPages()));
                });

                $(document).on('show', '#formDate', function() {
                    if (datepickerLimit)
                    {
                        if (!requested_changes) {
                            $(this).datepicker('setEndDate', survey_deadline);
                        }
                        datepickerLimit = false;
                    }
                });

                $(document).on('click', '.multiple-edit', function() {
                    $('.loader').fadeIn();

                    let data = getDataTableData(new FormData());
                    
                    $.ajax({
                        'url': `${section}/dashboard/management/indicator/multiple/edit`,
                        'data': {
                            'indicators': data.get('datatable-selected'),
                            'country_survey_id': country_survey_id
                        },
                        success: function(data) {
                            let obj = {
                                'action': 'edit',
                                'route': `${section}/dashboard/management/indicator/multiple/update`,
                                'title': 'Edit Indicators',
                                'html': data
                            };
                            setModal(obj);

                            initDatePicker();

                            pageModal.show();
                        }
                    })
                });

                $(document).on('click', '.submit-requested-changes', function() {
                    $('.loader').fadeIn();
    
                    $.ajax({
                        'url': `${section}/dashboard/management/submit_requested_changes`,
                        'type': 'POST',
                        'data': {
                            'country_survey_id': country_survey_id
                        },
                        success: function() {
                            table.ajax.reload(null, false);

                            $('.submit-requested-changes').prop('disabled', true);
                        },
                        error: function(req) {
                            setAlert({
                                'status': 'error',
                                'msg': req.responseJSON.error
                            });
                        }
                    });
                });

                $(document).on('click', '.item-approve', function() {
                    $('.loader').fadeIn();

                    let id = $(this).attr('item-id');
            
                    $.ajax({
                        'url': `${section}/dashboard/management/indicator/single/update/${id}`,
                        'type': 'POST',
                        'data': {
                            'action': 'final_approve',
                            'country_survey_id': country_survey_id
                        },
                        success: function(response) {
                            table.ajax.reload(null, false);

                            if (response.approved) {
                                $('.finalise-survey').prop('disabled', false);
                            }
                        },
                        error: function(req) {
                            table.ajax.reload(null, false);

                            setAlert({
                                'status': 'error',
                                'msg': req.responseJSON.error
                            });
                        }
                    }).done(function () {
                        $('#item-select-all').prop('checked', false);
                    });
                });

                $(document).on('click', '.multiple-approve', function() {
                    $('.loader').fadeIn();

                    let data = getDataTableData(new FormData());
                    data.append('action', 'final_approve');
                    data.append('country_survey_id', country_survey_id);
    
                    $.ajax({
                        'url': `${section}/dashboard/management/indicator/multiple/update`,
                        'type': 'POST',
                        'data': data,
                        'contentType': false,
                        'processData': false,
                        success: function(response) {
                            table.ajax.reload(null, false);

                            if (response.approved) {
                                $('.finalise-survey').prop('disabled', false);
                            }
                        },
                        error: function(req) {
                            table.ajax.reload(null, false);

                            setAlert({
                                'status': 'error',
                                'msg': req.responseJSON.error
                            });
                        }
                    }).done(function () {
                        $('#item-select-all').prop('checked', false);
                    });
                });

                $(document).on('click', '.finalise-survey', function() {
                    let data = `<input hidden id="item-action"/>
                                <input hidden id="item-route"/>
                                <div class="warning-message">
                                    By clicking Finalise, the Survey of this Member State will accept no further changes.<br>
                                    The PPoC of the Member State will be notified by email that the Survey has been finalised and accepted.
                                </div>`;

                    let obj = {
                        'large': true,
                        'modal': 'warning',
                        'action': 'finalise',
                        'route': `${section}/dashboard/management/finalise`,
                        'title': 'Finalise Survey',
                        'html': data,
                        'btn': 'Finalise'
                    };
                    setModal(obj);

                    pageModal.show();
                });

                $(document).on('click', '#process-data', function() {
                    $('.loader').fadeIn();

                    let action = $('.modal #item-action').val();
            
                    if (action == 'edit' ||
                        action == 'finalise')
                    {
                        let route = $('.modal #item-route').val();
                        let data = getFormData('indicator-manage');
                        data.append('action', action);
                        data.append('country_survey_id', country_survey_id);
                        data.append('requested_changes', requested_changes);

                        $.ajax({
                            'url': route,
                            'type': 'POST',
                            'data': data,
                            'contentType': false,
                            'processData': false,
                            success: function() {
                                pageModal.hide();

                                if (action == 'finalise')
                                {
                                    $('.finalise-survey').prop('disabled', true);

                                    is_finalised = true;
                                }

                                table.ajax.reload(null, false);
                            },
                            error: function(req) {
                                if (req.status == 400) {
                                    showInputErrors(req.responseJSON.errors);
                                }

                                if (req.responseJSON.error)
                                {
                                    pageModal.hide();

                                    if (action == 'edit') {
                                        table.ajax.reload(null, false);
                                    }

                                    setAlert({
                                        'status': 'error',
                                        'msg': req.responseJSON.error
                                    });
                                }
                            }
                        }).done(function () {
                            $('#item-select-all').prop('checked', false);
                        });
                    }
                    else if (action == 'review')
                    {
                        let data = JSON.parse($('.modal #item-data').val());

                        viewSurvey(data);
                    }
                });
            });
        }
    }
})(jQuery, Drupal);

function initDataTable()
{
    jQuery(function($) {
        table = $('#country-survey-dashboard-management-table').DataTable({
            'ajax': `${section}/dashboard/management/list/${country_survey_id}`,
            'initComplete': function() {
                addDataTableSearchInfoIcon();
            },
            'drawCallback': function() {
                convertToLocalTimestamp();

                toggleDataTablePagination(this, '#country-survey-dashboard-management-table_paginate');

                $('#item-select-all')
                    .trigger('update_select_all')
                    .trigger('update_buttons');
            },
            'order': [
                [1, 'asc']
            ],
            'columns': [
                {
                    'data': 'id',
                    render: function(data, type, row) {
                        let can_select = ((is_poc && row.state < 6) || (is_admin && Object.keys(row.country_survey.submitted_user).length && row.state == 6)) ? true : false;

                        return `<input class="item-select form-check-input" type="checkbox" id="item-${data}" ${(can_select) ? '' : 'disabled'}\>`;
                    },
                    'orderable': false
                },
                {
                    'data': 'number',
                    'visible': false,
                    'searchable': false
                },
                {
                    'data': 'name',
                    render: function(data, type, row) {
                        let obj = jQuery.extend(true, {}, row);
                        delete obj.country_survey.survey.index.json_data;

                        return `<a href="javascript:;" onclick='showIndicatorInfo(${JSON.stringify(obj)});'>${row.number}. ${row.name}</a>`;
                    }
                },
                {
                    'data': 'assignee',
                    render: function(data, type, row) {
                        return `<span>${row.assignee.name}</span>`;
                    }
                },
                {
                    'data': 'status',
                    render: function(data, type, row) {
                        let obj = getRowData(row);

                        return `<div class="d-flex justify-content-center">
                                    <button class="btn-${obj.status_style} btn-label btn-with-tooltip" data-bs-toggle="tooltip" title="${obj.status_info}" type="button">${obj.status_label}<span style="display: none;">${obj.status_style}</span></button>
                                </div>`;
                    }
                },
                {
                    'data': 'deadline',
                    render: function(data, type, row) {
                        return `<span class="local-timestamp-display date" style="width: 81px;">${row.deadline}</span>
                                <span class="local-timestamp" hidden>${row.deadline}</span>`;
                        }
                },
                {
                    'data': 'actions',
                    render: function(data, type, row) {
                        let obj = jQuery.extend(true, {}, row);
                        delete obj.country_survey.survey.index.json_data;

                        let review_click = `onclick=${(is_assigned) ? `'viewSurvey(${JSON.stringify(obj)})';` : "location.href='/survey/user/management'"}`;

                        let edit_button = '';
                        let approve_button = '';

                        if (is_poc)
                        {
                            let can_edit = (row.state > 5) ? false : true;

                            let edit_click = (can_edit) ? `onclick='editIndicator(${JSON.stringify(obj)});'` : '';

                            edit_button =
                                `<button class="icon-edit btn-unstyle ${(can_edit ? '' : 'icon-edit-deactivated')} "data-bs-toggle="tooltip"
                                    title="Edit indicator" type="button" ${edit_click} ${(!can_edit ? 'disabled' : '')}>
                                </button>`;
                        }
                        else if (is_admin)
                        {
                            let can_approve = (Object.keys(row.country_survey.submitted_user).length && row.state == 6) ? true : false;

                            approve_button =
                                `<button class="icon-verify btn-unstyle item-approve ${(can_approve ? '' : 'icon-verify-deactivated')} "data-bs-toggle="tooltip"
                                    title="Approve indicator" type="button" item-id="${row.id}" ${(!can_approve ? 'disabled' : '')}>
                                </button>`;
                        }
                            
                        return `<div class="d-flex justify-content-center">
                                    ${edit_button}
                                    ${approve_button}
                                    <button class="icon-show btn-unstyle" data-bs-toggle="tooltip"
                                        title="Review indicator" type="button" ${review_click}>
                                    </button>
                                </div>`;
                    },
                    'orderable': false
                }
            ]
        });
    });
}

function viewSurvey(obj)
{
    jQuery(function($) {
        let country_survey_id = '';
        let requested_indicator = '';
        if (obj.country_survey)
        {
            country_survey_id = obj.country_survey.id;
            requested_indicator = ((is_poc && Object.keys(obj.country_survey.submitted_user).length) || Object.keys(obj.country_survey.approved_user).length)
                ? '' : `<input type="hidden" name="requested_indicator" value="${obj.id}"/>`;
        }
        else {
            country_survey_id = obj.country_survey_id;
        }
        let form = `<form action="/country/survey/view/${country_survey_id}" method="POST">
                        <input type="hidden" name="csrf-token" value="${view_survey_token}">
                        <input type="hidden" name="action" value="view"/>
                        ${requested_indicator}
                    </form>`;

        $(form).appendTo($(document.body)).submit();
    });
}

function showIndicatorInfo(row)
{
    jQuery(function($) {
        $('.loader').fadeIn();

        $.ajax({
            'url': `${section}/dashboard/management/indicator/info/${row.id}`,
            'data': {
                'country_survey_id': country_survey_id
            },
            success: function(data) {
                let obj = {
                    'large': true,
                    'data': JSON.stringify(row),
                    'action': 'review',
                    'title': `${row.number}. ${row.name}`,
                    'html': data,
                    'btn': 'Review Indicator'
                };
                setModal(obj);

                pageModal.show();
            }
        });
    });
}

function editIndicator(row)
{
    jQuery(function($) {
        $('.loader').fadeIn();
            
        requested_changes = row.requested_changes;
            
        $.ajax({
            'url': `${section}/dashboard/management/indicator/single/edit/${row.id}`,
            'data': {'country_survey_id': country_survey_id},
            success: function(data) {
                let obj = {
                    'action': 'edit',
                    'route': `${section}/dashboard/management/indicator/single/update/${row.id}`,
                    'title': `Edit Indicator ${row.number}`,
                    'html': data
                };
                setModal(obj);

                initDatePicker();

                pageModal.show();
            }
        });
    });
}

function getRowData(row)
{
    let obj = {};
            
    switch (row.dashboard_state)
    {
        case 1:
        case 5:
            obj.status_label = 'Assigned';
            obj.status_style = `positive-invert-with-tooltip ${(row.requested_changes) ? 'icon-requested-changes-positive-invert' : ''}`;
            obj.status_info = (row.requested_changes)
                ? 'Changes have been requested for this indicator. The MS has not yet started revising the indicator.'
                : 'Indicator is assigned but has not yet been edited/revised.';

            break;
        case 2:
            obj.status_label = 'In progress';
            obj.status_style = `positive-invert-with-tooltip ${(row.requested_changes) ? 'icon-requested-changes-positive-invert' : ''}`;
            obj.status_info = (row.requested_changes)
                ? 'Changes have been requested for this indicator. Indicator is currently under revision by the MS.'
                : 'The assignee is currently working on this indicator.';

            break;
        case 3:
            obj.status_label = 'Completed';
            obj.status_style = `approved-with-tooltip ${(row.requested_changes) ? 'icon-requested-changes-approved' : ''}`;
            obj.status_info = (row.requested_changes)
                ? 'The assigned indicators have been completed, after requested changes, and submitted to the PPoC for approval.'
                : 'The assignee has submitted their answers, pending approval by the PPoC.';

            break;
        case 4:
            obj.status_label = 'Under review';
            obj.status_style = 'positive-invert-with-tooltip';
            obj.status_info = `${user_group} has requested changes for the indicator, but the request has not yet been sent to the MS.`;

            break;
        case 6:
            obj.status_label = 'Approved';
            obj.status_style = `positive-with-tooltip ${(row.requested_changes) ? 'icon-requested-changes-approved' : ''}`;
            obj.status_info = (row.requested_changes)
                ? `Following request by ${user_group}, the indicator has been revised and approved by the MS (PPoC). The indicator is under review by ${user_group}.`
                : 'Indicator has been approved by the MS (PPoC).';

            break;
        case 7:
            obj.status_label = 'Approved';
            obj.status_style = `positive-with-tooltip ${(row.requested_changes) ? 'icon-requested-changes-approved-and-approved' : 'icon-approved'}`;

            let requested_changes_text = (is_poc || is_finalised)
                ? 'The indicator can no longer be edited.'
                : 'The indicator can be unapproved.';
            let approved_text = (is_poc || is_finalised)
                ? `The indicator has been approved by ${user_group} and can no longer be edited.`
                : `The indicator has been approved by ${user_group} but it can be unapproved.`

            obj.status_info = (row.requested_changes)
                ? `Changes made to the indicator by the MS have been approved by ${user_group}. ${requested_changes_text}`
                : approved_text;

            break;
        default:
            obj.status_label = 'Unassigned';
            obj.status_style = 'positive-invert-with-tooltip';
            obj.status_info = '';

            break;
    }
            
    return obj;
}