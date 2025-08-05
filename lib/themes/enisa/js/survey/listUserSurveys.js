var pageModal = new bootstrap.Modal(document.getElementById('pageModal'));
var explicitModal = new bootstrap.Modal(document.getElementById('explicitModal'));
var printSurveyModal = new bootstrap.Modal(document.getElementById('printSurveyModal'));
let table;
let user_group;
let is_operator;
let is_poc;
let is_primary_poc;
let surveys;
let surveys_assigned;
let view_survey_token;

(function ($, Drupal) {
    Drupal.behaviors.listUserSurveys = {
        attach: function (context, settings) {
            once('listUserSurveys', 'body').forEach(function () {
                user_group = settings.manage_user_surveys.user_group;
                is_operator = settings.manage_user_surveys.is_operator;
                is_poc = settings.manage_user_surveys.is_poc;
                is_primary_poc = settings.manage_user_surveys.is_primary_poc;
                surveys = settings.manage_user_surveys.surveys;
                surveys_assigned = settings.manage_user_surveys.surveys_assigned;
                view_survey_token = settings.manage_user_surveys.view_survey_token;
                
                if (!is_poc &&
                    surveys.length &&
                    !surveys_assigned.length)
                {
                    setAlert({
                        'status': 'warning',
                        'msg': 'You haven\'t been assigned any indicators.'
                    });

                    return;
                }

                initDataTable();

                $(document).on('click', '.download', function() {
                    $('.loader').fadeIn();

                    let survey_id = $('#survey-id').val();
                    let country_survey_id = $('#country-survey-id').val();

                    $.ajax({
                        'url': `/survey/excel/template/export/${survey_id}`,
                        'type': 'POST',
                        'data': {
                            'country_survey_id': country_survey_id
                        },
                        success: function(data) {
                            let link = document.createElement('a');

                            link.href = `/survey/excel/download/${encodeURIComponent(data.filename)}`;
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

                $(document).on('click', '.item-download', function() {
                    $('.loader').fadeIn();

                    let data = JSON.parse($(this).attr('item-data'));
                    
                    $.ajax({
                        'url': `/survey/excel/with-answers/export/${data.survey.id}`,
                        'type': 'POST',
                        'data': {
                            'country_survey_id': data.id
                        },
                        success: function(data) {
                            let link = document.createElement('a');

                            link.href = `/survey/excel/download/${encodeURIComponent(data.filename)}`;
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
            });
        }
    }
})(jQuery, Drupal);

function initDataTable()
{
    jQuery(function($) {
        table = $('#surveys-table').DataTable({
            'data': surveys_assigned,
            'initComplete': function() {
                addDataTableSearchInfoIcon();
            },
            'drawCallback': function() {
                convertToLocalTimestamp();
                
                toggleDataTablePagination(this, '#surveys-table_paginate');
            },
            'columns': getDataTableColumns()
        });
    });
}

function getDataTableColumns()
{
    let columns = [];

    columns.push(
    {
        'data': 'survey_title',
        render: function(data, type, row) {
            return `<span>${row.survey.title}</span>`;
        }
    },
    {
        'data': 'survey_year',
        render: function(data, type, row) {
            return `<span>${row.survey.year}</span>`;
        }
    },
    {
        'data': 'status',
        render: function(data, type, row) {
            let obj = getRowData(row);

            return `<div class="d-flex justify-content-center">
                        <button class="btn-${obj.status_style} btn-label btn-with-tooltip" data-bs-toggle="tooltip" title="${obj.status_info}" type="button">${obj.status_label}</button>
                    </div>`;
        }
    });

    if (is_poc) {
        columns.push({
            'data': 'submitted_by',
            render: function(data, type, row) {
                return `${(row.submitted_user) ? `<span>${row.submitted_user.name}</span>` : ''}`;
            }
        });
    }

    columns.push(
    {
        'data': 'survey_deadline',
        render: function(data, type, row) {
            return `<span class="local-timestamp-display date" style="width: 81px;">${row.survey.deadline}</span>
                    <span class="local-timestamp" hidden>${row.survey.deadline}</span>`;
        }
    },
    {
        'data': 'fill_in_actions',
        render: function(data, type, row) {
            let obj = jQuery.extend(true, {}, row);
            delete obj.survey.index.json_data;

            let can_fill_in = (!row.indicators_assigned_exact || ((row.indicators_submitted || row.completed) && !is_primary_poc) || row.submitted_user) ? false : true;
                    
            return `<div class="d-flex justify-content-center">
                        <button type="button" class="btn-unstyle p-0 ${(!can_fill_in ? 'view-btn-deactivated' : '')}" ${(!can_fill_in ? 'disabled' : '')}>
                            <a onclick='viewSurvey(${JSON.stringify(obj)});' href="javascript:;" class="pointer-event-none view-btn ${(!can_fill_in ? 'view-btn-deactivated' : '')}">
                                <span>Fill in online</span>
                            </a>
                        </button>
                        &nbsp;&nbsp;
                        <button type="button" class="btn-unstyle p-0 ${(!can_fill_in ? 'view-btn-deactivated' : '')}" ${(!can_fill_in ? 'disabled' : '')}>
                            <a onclick='fillInOffline(${JSON.stringify(obj)});' href="javascript:;" class="pointer-event-none view-btn ${(!can_fill_in ? 'view-btn-deactivated' : '')}">
                                <span>Fill in offline</span>
                            </a>
                        </button>
                    </div>`;
        },
        'orderable': false
    },
    {
        'data': 'survey_actions',
        render: function(data, type, row) {
            let obj = jQuery.extend(true, {}, row);
            delete obj.survey.index.json_data;

            let can_view = ((row.indicators_submitted && is_operator) || row.submitted_user) ? true : false;

            let view_dashboard_button = '';
            let view_summary_data_button = '';
            let download_pdf = '';
            let download_excel = '';

            if (is_poc)
            {
                view_dashboard_button =
                    `<button class="icon-overview btn-unstyle" data-bs-toggle="tooltip"
                        title="View survey dashboard" type="button" onclick="location.href='/country/survey/dashboard/management/${row.id}';">
                    </button>`;
                view_summary_data_button =
                    `<button class="icon-summary btn-unstyle" data-bs-toggle="tooltip" title="View survey summary data" type="button"
                        onclick="location.href=\'/country/survey/dashboard/summarydata/${row.id}\';">
                    </button>`;
                download_pdf =
                    `<button class="icon-pdf-download btn-unstyle" data-bs-toggle="tooltip"
                        title="Preview survey with answers" type="button" onclick="javascript:previewSurveyWithAnswers(${row.id});">
                    </button>`;
                download_excel =
                    `<button class="icon-xls-download btn-unstyle item-download"
                        title="Download survey with answers (excel)" data-bs-toggle="tooltip" item-data='${JSON.stringify(obj)}'>
                    </button>`;
            }

            return `<div class="d-flex justify-content-center">
                        <button class="icon-show btn-unstyle ${(!can_view ? 'icon-show-deactivated' : '')}" data-bs-toggle="tooltip"
                            title="View survey" type="button" ${(!can_view ? 'disabled' : '')} onclick='viewSurvey(${JSON.stringify(obj)});'>
                        </button>
                        ${view_dashboard_button}
                        ${view_summary_data_button}
                        ${download_pdf}
                        ${download_excel}
                    </div>`;
        },
        'orderable': false
    });

    return columns;
}

function viewSurvey(obj)
{
    jQuery(function($) {
        let form = `<form action="/country/survey/view/${obj.id}" method="POST">
                        <input type="hidden" name="csrf-token" value="${view_survey_token}">
                        <input type="hidden" name="action" value="view"/>
                    </form>`;

        $(form).appendTo($(document.body)).submit();
    });
}

function previewSurveyWithAnswers(country_survey_id)
{
    jQuery(function($) {
        $('.loader').fadeIn();
        
        skipFadeOut = true;
                
        $.ajax({
            'url': '/country/survey/preview',
            'data': {
                'with_answers': true,
                'country_survey_id': country_survey_id
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

    setTimeout(function() {
        $(iframe_doc).find('textarea').each(function (i, el) {
            el.style.height = 'auto';
            el.style.height = el.scrollHeight + 'px';
        });
    }, 1000);
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

function saveReportPDF()
{
    let printIframe = document.getElementById('surveyIframe').contentWindow;

    printIframe.focus();
    printIframe.print();

    return false;
}

function getRowData(row)
{
    let obj = {
        status_label: 'Pending',
        status_style: 'positive-invert-with-tooltip',
        status_info: 'Indicators have been assigned by the PPoC and are pending completion.'
    };
    
    if (((!row.indicators_assigned_exact || row.indicators_submitted || row.completed) && !is_primary_poc) ||
        row.submitted_user)
    {
        obj.status_label = (is_primary_poc) ? 'Submitted' : 'Completed';
        obj.status_style = 'positive-with-tooltip';
        obj.status_info = (is_primary_poc)
            ? `Survey has been submitted by the MS and is under review by ${user_group}. Clarifications or changes may be requested.`
            : 'The assignee has submitted their answers, pending approval by the PPoC.';
    }

    return obj;
}

window.addEventListener('pagedRendered', function() {
    var $ = jQuery;

    $('.loader').fadeOut();

    skipFadeOut = false;
});