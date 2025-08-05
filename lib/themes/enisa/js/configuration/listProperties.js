var pageModal = new bootstrap.Modal(document.getElementById('pageModal'));
var printSurveyModal = new bootstrap.Modal(document.getElementById('printSurveyModal'));
let area_table;
let subarea_table;
let indicator_table;
let publishedIndex;
let publishedSurvey;
let areas_loaded = false;
let subareas_loaded = false;
let indicators_loaded = false;
var max_identifier = 0;

(function ($, Drupal) {
    Drupal.behaviors.listProperties = {
        attach: function (context, settings) {
            once('listProperties', 'body').forEach(function () {
                publishedIndex = settings.configuration.publishedIndex;
                publishedSurvey = settings.configuration.publishedSurvey;

                skipFadeOut = true;
                skipClearErrors = true;

                initDataTables();

                $(document).on('click', '.preview-survey', function() {
                    $('.loader').fadeIn();
        
                    skipFadeOut = true;
        
                    $.ajax({
                        'url': '/country/survey/preview',
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

                $(document).on('click', '.import-properties', function() {
                    $('.loader').fadeIn();
            
                    $.ajax({
                        'url': '/index/configuration/import/show',
                        success: function(data) {
                            $('.loader').fadeOut();

                            let obj = {
                                'action': 'import',
                                'route': '/index/configuration/import/store',
                                'title': 'Import Properties',
                                'html': data,
                                'btn': 'Import'
                            };
                            setModal(obj);

                            pageModal.show();
                        },
                        error: function(req) {
                            $('.loader').fadeOut();

                            setAlert({
                                'status': 'error',
                                'msg': req.responseJSON.error
                            });
                        }
                    });
                });

                $(document).on('click', '.clone-index', function() {
                    $('.loader').fadeIn();
                    
                    $.ajax({
                        'url': '/index/configuration/clone/show',
                        success: function(data) {
                            $('.loader').fadeOut();
         
                            let obj = {
                                'action': 'clone',
                                'route': '/index/configuration/clone/store',
                                'title': 'Clone Index',
                                'html': data,
                                'btn': 'Clone'
                            };
                            setModal(obj);
        
                            pageModal.show();
                        },
                        error: function(req) {
                            $('.loader').fadeOut();
        
                            setAlert({
                                'status': 'error',
                                'msg': req.responseJSON.error
                            });
                        }
                    });
                });

                $(document).on('click', '.download', function() {
                    $('.loader').fadeIn();

                    skipClearErrors = false;
                    
                    $.ajax({
                        'url': `/index/configuration/excel/export/${$('#index-year-select').val()}`,
                        'type': 'POST',
                        success: function(data) {
                            let link = document.createElement('a');

                            link.href = `/index/configuration/excel/download/${encodeURIComponent(data.filename)}`;
                            link.setAttribute('download', '');

                            document.body.appendChild(link);

                            link.click();
                            link.remove();

                            $('.loader').fadeOut();
                        },
                        error: function(req) {
                            $('.loader').fadeOut();
                            
                            setAlert({
                                'status': 'error',
                                'msg': req.responseJSON.error
                            });
                        }
                    });
                });

                $(document).on('click', '.item-create', function() {
                    $('.loader').fadeIn();
        
                    let type = $(this).attr('item-type');
        
                    $.ajax({
                        'url': `/${type}/create`,
                        success: function(data) {
                            $('.loader').fadeOut();
        
                            let obj = {
                                'large': (type == 'indicator'),
                                'action': 'create',
                                'type': type,
                                'route': `/${type}/store`,
                                'title': `New ${getLabel(type)}`,
                                'html': data
                            };
                            setModal(obj);
                            
                            pageModal.show();
                        }
                    });
                });

                $(document).on('click', '.item-edit', function() {
                    $('.loader').fadeIn();
        
                    let id = $(this).attr('item-id');
                    let type = $(this).attr('item-type');
        
                    $.ajax({
                        'url': `/${type}/show/${id}`,
                        success: function(data) {
                            $('.loader').fadeOut();
        
                            let obj = {
                                'id': id,
                                'large': (type == 'indicator'),
                                'action': 'edit',
                                'type': type,
                                'route': `/${type}/update/${id}`,
                                'title': `Edit ${getLabel(type)}`,
                                'html': data
                            };
                            setModal(obj);
                            
                            pageModal.show();
                        }
                    });
                });

                $(document).on('click', '.item-delete', function() {
                    let id = $(this).attr('item-id');
                    let type = $(this).attr('item-type');
                    let label = getLabel(type);
                    let name = $(this).attr('item-name');
                    let data = `<form id="item-delete">
                                    <input hidden id="item-id"/>
                                    <input hidden id="item-type"/>
                                    <input hidden id="item-action"/>
                                    <input hidden id="item-route"/>
                                    <div class="warning-message">
                                        ${label} <b>${name}</b> will be deleted. Are you sure?
                                    </div>
                                </form>`;
        
                    let obj = {
                        'modal': 'warning',
                        'id': id,
                        'action': 'delete',
                        'type': type,
                        'route': `/${type}/delete/${id}`,
                        'title': `Delete ${getLabel(type)}`,
                        'html': data,
                        'btn': 'Delete'
                    };
                    setModal(obj);
        
                    pageModal.show();
                });

                $(document).on('click', '#process-data', function() {
                    $('.loader').fadeIn();
                    
                    skipClearErrors = false;
                    
                    let route = $('.modal #item-route').val();
                    let action = $('.modal #item-action').val();
                    let form = $('.modal').find('form').attr('id');
                    let data = getFormData(form);
                    if (max_identifier) {
                        data.append('max_identifier', max_identifier);
                    }
        
                    $.ajax({
                        'url': route,
                        'type': 'POST',
                        'data': data,
                        'contentType': false,
                        'processData': false,
                        success: function() {
                            if (action == 'import' ||
                                action == 'clone')
                            {
                                location.reload();
                            }
                            else
                            {
                                pageModal.hide();
        
                                area_table.ajax.reload(checkDataTablesReload, false);
                                subarea_table.ajax.reload(checkDataTablesReload, false);
                                indicator_table.ajax.reload(checkDataTablesReload, false);
                            }
                        },
                        error: function(req) {
                            $('.loader').fadeOut();
        
                            if (req.status == 400) {
                                showInputErrors(req.responseJSON.errors);
                            }
                            else if (req.status == 405 ||
                                     req.status == 500)
                            {
                                pageModal.hide();
        
                                setAlert({
                                    'status': 'error',
                                    'msg': req.responseJSON.error
                                });
                            }
                        }
                    });
                });
            });
        }
    }
})(jQuery, Drupal);

function initDataTables()
{
    jQuery(function($) {
        area_table = $('#area-table').DataTable({
            'ajax': '/area/list',
            'initComplete': function() {
                addDataTableSearchInfoIcon();

                areas_loaded = true;

                var loadEvent = new Event('loadEvent');
                window.dispatchEvent(loadEvent);
            },
            'drawCallback': function() {
                toggleDataTablePagination(this, '#area-table_paginate');

                let areas = this.api().rows().data();
                
                if (areas.length) {
                    $('.download').prop('disabled', false);
                }
            },
            'columns': [
                {
                    'data': 'name'
                },
                {
                    'data': 'description'
                },
                {
                    'data': 'actions',
                    render: function(data, type, row) {
                        return `<div class="d-flex justify-content-center">
                                    <button class="icon-edit btn-unstyle item-edit ${(publishedIndex ? 'icon-edit-deactivated' : '')}" data-bs-toggle="tooltip"
                                        title="Edit area" type="button" item-id="${row.id}" item-type="area">
                                    </button>
                                    <button class="icon-bin btn-unstyle item-delete ${(publishedIndex ? 'icon-bin-deactivated' : '')}" data-bs-toggle="tooltip"
                                        title="Delete area" item-id="${row.id}" item-name="${row.name}" item-type="area">
                                    </button>
                                </div>`;
                    },
                    'className': 'actions',
                    'orderable': false
                }
            ]
        });

        subarea_table = $('#subarea-table').DataTable({
            'ajax': '/subarea/list',
            'initComplete': function() {
                addDataTableSearchInfoIcon();

                subareas_loaded = true;

                var loadEvent = new Event('loadEvent');
                window.dispatchEvent(loadEvent);
            },
            'drawCallback': function() {
                toggleDataTablePagination(this, '#subarea-table_paginate');
            },
            'order': [
                [2, 'asc'], // Sort by area
                [0, 'asc']  // Sort by title
            ],
            'columns': [
                {
                    'data': 'name'
                },
                {
                    'data': 'description'
                },
                {
                    'data': 'area',
                    render: function(data, type, row) {
                        return (row.area) ? row.area.name : '';
                    }
                },
                {
                    'data': 'actions',
                    render: function(data, type, row) {
                        return `<div class="d-flex justify-content-center">
                                    <button class="icon-edit btn-unstyle item-edit ${(publishedIndex ? 'icon-edit-deactivated' : '')}" data-bs-toggle="tooltip"
                                        title="Edit subarea" type="button" item-id="${row.id}" item-type="subarea">
                                    </button>
                                    <button class="icon-bin btn-unstyle item-delete ${(publishedIndex ? 'icon-bin-deactivated' : '')}" data-bs-toggle="tooltip"
                                        title="Delete subarea" item-id="${row.id}" item-name="${row.name}" item-type="subarea">
                                    </button>
                                </div>`;
                    },
                    'className': 'actions',
                    'orderable': false
                }
            ]
        });

        indicator_table = $('#indicator-table').DataTable({
            'ajax': {
                'url': '/indicator/list',
                'data': function (data) {
                    data.category = null;
                }
            },
            'initComplete': function() {
                addDataTableSearchInfoIcon();

                indicators_loaded = true;

                var loadEvent = new Event('loadEvent');
                window.dispatchEvent(loadEvent);
            },
            'drawCallback': function() {
                toggleDataTablePagination(this, '#indicator-table_paginate');

                let indicators = this.api().rows().data();
                let survey_indicators = [];

                $.each(indicators, function (key, val) {
                    if (val.category == 'survey') {
                        survey_indicators.push(val.id);
                    }
                });

                if (!publishedSurvey &&
                    survey_indicators.length)
                {
                    $('.edit-survey').prop('disabled', false).attr('onclick', `location.href='/indicator/survey/management/${survey_indicators[0]}'`);
                }
                else {
                    $('.edit-survey').prop('disabled', true).removeAttr('onclick');
                }
            },
            'order': [
                [0, 'asc'] // Sort by order
            ],
            'columns': [
                {
                    'data': 'order',
                    'visible': false,
                    'searchable': false
                },
                {
                    'data': 'name'
                },
                {
                    'data': 'description'
                },
                {
                    'data': 'category',
                    render: function(data, type, row) {
                        return (row.category == 'manual') ? 'Other' : `<span style="text-transform: capitalize;">${row.category}</span>`;
                    }
                },
                {
                    'data': 'weight'
                },
                {
                    'data': 'subarea',
                    render: function(data, type, row) {
                        return (row.subarea) ? row.subarea.name : '';
                    }
                },
                {
                    'data': 'actions',
                    render: function(data, type, row) {
                        return `<div class="d-flex justify-content-center">
                                    <button class="icon-edit btn-unstyle item-edit ${(publishedIndex ? 'icon-edit-deactivated' : '')}" data-bs-toggle="tooltip"
                                        title="Edit indicator" type="button" item-id="${row.id}" item-type="indicator">
                                    </button>
                                    <button class="icon-overview btn-unstyle ${(publishedSurvey || row.category != 'survey' ? 'icon-overview-deactivated' : '')}" data-bs-toggle="tooltip"
                                        title="Edit survey" onclick="location.href=\'/indicator/survey/management/${row.id}\';">
                                    </button>
                                    <button class="icon-bin btn-unstyle item-delete ${(publishedSurvey ? 'icon-bin-deactivated' : '')}" data-bs-toggle="tooltip"
                                        title="Delete indicator" item-id="${row.id}" item-name="${row.name}" item-type="indicator">
                                    </button>
                                </div`;
                    },
                    'orderable': false
                }
            ]
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

function getLabel(type)
{
    switch (type) {
        case 'area':
            return 'Area';
        case 'subarea':
            return 'Subarea';
        case 'indicator':
            return 'Indicator';
        default:
            return '';
    }
}

function checkDataTablesReload()
{
    jQuery(function($) {
        if (areas_loaded &&
            subareas_loaded &&
            indicators_loaded)
        {
            $('.loader').fadeOut();
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

window.addEventListener('loadEvent', function() {
    checkDataTablesReload();
});

window.addEventListener('yearChange', function() {
    jQuery(function($) {
        $('.loader').fadeIn();

        location.reload();
    });
});

window.addEventListener('pagedRendered', function() {
    jQuery(function($) {
        $('.loader').fadeOut();

        skipFadeOut = false;
    });
});