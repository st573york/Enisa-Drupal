var pageModal = new bootstrap.Modal(document.getElementById('pageModal'));
let table;
let section = '/survey';
let survey_id;
let view_survey_token;

(function ($, Drupal) {
    Drupal.behaviors.surveyDashboard = {
        attach: function (context, settings) {
            once('surveyDashboard', 'body').forEach(function () {
                survey_id = settings.dashboard.survey_data.id;
                view_survey_token = settings.dashboard.view_survey_token;
                
                initDataTable();
            });
        }
    }
})(jQuery, Drupal);

function initDataTable()
{
    jQuery(function($) {
        table = $('#survey-dashboard-table').DataTable({
            'ajax': `${section}/dashboard/management/list/${survey_id}`,
            'initComplete': function() {
                addDataTableSearchInfoIcon();
            },
            'drawCallback': function() {
                convertToLocalTimestamp();

                toggleDataTablePagination(this, '#survey-dashboard-table_paginate');
            },
            'columns': [
                {
                    'data': 'country_name',
                    render: function(data, type, row) {
                        return `<span>${row.country.name}</span>`;
                    }
                },
                {
                    'data': 'in_progress',
                    render: function(data, type, row) {
                        if (row.percentage_in_progress != null &&
                            row.percentage_approved != null)
                        {
                            return `<div style="width: max-content;">
                                        <div class="d-flex">
                                            <div class="d-flex justify-content-end progress-percentage pe-1">${((Object.keys(row.submitted_user).length)) ? '-' : row.percentage_in_progress + '%'}</div>
                                            <div class="progress-percentage-label">In progress</div>
                                        </div>
                                        <div class="d-flex">
                                            <div class="d-flex justify-content-end progress-percentage pe-1">${row.percentage_approved}%</div>
                                            <div class="progress-percentage-label">Approved</div>
                                        </div>
                                    </div>`;
                        }
                        else {
                            return `<div class="d-flex justify-content-center">-</div>`;
                        }
                    }
                },
                {
                    'data': 'status',
                    render: function(data, type, row) {
                        let status = (row.style && row.status)
                            ? `<button class="btn-${row.style} btn-label btn-with-tooltip" data-bs-toggle="tooltip" title="${row.info}" type="button">${row.status}</button>`
                            : '-';

                        return `<div class="d-flex justify-content-center">${status}</div>`;
                    }
                },
                {
                    'data': 'primary_poc',
                    render: function(data, type, row) {
                        return `<div class="d-flex justify-content-center">${(row.primary_poc) ? row.primary_poc : '-'}</div>`;
                    }
                },
                {
                    'data': 'submitted_date',
                    render: function(data, type, row) {
                        if (type === 'sort') {
                            return data;
                        }
                        
                        if (row.submitted_date) {
                            return `<span class="local-timestamp-display timestamp" style="width: 81px;">${row.submitted_date}</span>
                                    <span class="local-timestamp" hidden>${row.submitted_date}</span>`;
                        }
                        
                        return `<div class="d-flex justify-content-center">-</div>`;
                    }
                },
                {
                    'data': 'rc_submitted_date',
                    render: function(data, type, row) {
                        if (type === 'sort') {
                            return data;
                        }
                                                    
                        if (row.rc_submitted_date) {
                            return `<span class="local-timestamp-display timestamp" style="width: 81px;">${row.rc_submitted_date}</span>
                                    <span class="local-timestamp" hidden>${row.rc_submitted_date}</span>`;
                        }
                        
                        return `<div class="d-flex justify-content-center">-</div>`;
                    }
                },
                {
                    'data': 'latest_requested_change',
                    render: function(data, type, row) {
                        if (type === 'sort') {
                            return data;
                        }

                        if (row.latest_requested_change) {
                            return `<span style="width: 81px;">${row.latest_requested_change.deadline}</span>`;
                        }
                        
                        return `<div class="d-flex justify-content-center">-</div>`;
                    }
                },
                {
                    'data': 'actions',
                    render: function(data, type, row) {
                        if (row.country_data)
                        {
                            let obj = jQuery.extend(true, {}, row);
                            delete obj.latest_requested_change;
                            delete obj.survey.index.json_data;
                            
                            return `<div class="d-flex justify-content-center">
                                        <button class="icon-show btn-unstyle" data-bs-toggle="tooltip"
                                            title="Review survey" type="button" onclick='viewSurvey(${JSON.stringify(obj)});'>
                                        </button>
                                        <button class="icon-overview btn-unstyle" data-bs-toggle="tooltip" title="View indicators dashboard" type="button"
                                            onclick="location.href=\'/country/survey/dashboard/management/${row.id}\';">
                                        </button>
                                        <button class="icon-summary btn-unstyle" data-bs-toggle="tooltip" title="View survey summary data" type="button"
                                            onclick="location.href=\'/country/survey/dashboard/summarydata/${row.id}\';">
                                        </button>
                                    </div>`;
                        }

                        return '';
                    },
                    'orderable': false
                }
            ]
        });
    });
}

function viewSurvey(obj)
{
	var $ = jQuery;
	
    let form = `<form action="/country/survey/view/${obj.id}" method="POST">
                    <input type="hidden" name="csrf-token" value="${view_survey_token}">
                    <input type="hidden" name="action" value="view"/>
                </form>`;

    $(form).appendTo($(document.body)).submit();
}

window.addEventListener('yearChange', function() {
    jQuery(function($) {
        $('.loader').fadeIn();

        window.location = `${section}/dashboard/management/${$('.loaded-survey').val()}`;
    });
});