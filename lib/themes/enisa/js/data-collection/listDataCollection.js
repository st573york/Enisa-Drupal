var pageModal = new bootstrap.Modal(document.getElementById('pageModal'));
let table;
let section = '/index/data-collection';

(function ($, Drupal) {
    Drupal.behaviors.listDataCollection = {
        attach: function (context, settings) {
            once('listDataCollection', 'body').forEach(function () {
                skipFadeOut = true;
                skipClearErrors = true;

                setIndexYear();

                if (localStorage.getItem('data-collection-error'))
                {
                    setAlert({
                        'status': 'error',
                        'msg': localStorage.getItem('data-collection-error')
                    });

                    localStorage.removeItem('data-collection-error');
                }

                initDataTable();

                $(document).on('click', '.import-data', function() {
                    $('.loader').fadeIn();
            
                    $.ajax({
                        'url': `${section}/import-data/show`,
                        success: function(data) {
                            $('.loader').fadeOut();

                            let obj = {
                                'action': 'import',
                                'route': `${section}/import-data/store`,
                                'title': 'Import Data',
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

                $(document).on('click', '.download', function() {
                    $('.loader').fadeIn();

                    skipClearErrors = false;
                    
                    $.ajax({
                        'url': `${section}/data/excel/export`,
                        'type': 'POST',
                        success: function(data) {
                            let link = document.createElement('a');

                            link.href = `${section}/data/excel/download/${encodeURIComponent(data.filename)}`;
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

                $(document).on('click', '#process-data', function() {
                    $('.loader').fadeIn();

                    skipClearErrors = false;

                    let route = $('.modal #item-route').val();
                    let form = $('.modal').find('form').attr('id');
                    let data = getFormData(form);
            
                    $.ajax({
                        'url': route,
                        'type': 'POST',
                        'data': data,
                        'contentType': false,
                        'processData': false,
                        success: function() {
                            location.reload();
                        },
                        error: function(req) {
                            if (req.status == 400)
                            {
                                $('.loader').fadeOut();

                                showInputErrors(req.responseJSON.errors);
                            }
                            else if (req.status == 403 ||
                                     req.status == 405)
                            {
                                $('.loader').fadeOut();

                                pageModal.hide();

                                setAlert({
                                    'status': 'error',
                                    'msg': req.responseJSON.error
                                });
                            }
                            else if (req.status == 500)
                            {
                                localStorage.setItem('data-collection-error', req.responseJSON.error);

                                location.reload();

                                scrollToTop();
                            }
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
        table = $('#data-collection-table').DataTable({
            'processing': true,
            'ajax': `${section}/list`,
            'initComplete': function() {
                addDataTableSearchInfoIcon();
            },
            'drawCallback': function() {
                toggleDataTablePagination(this, '#data-collection-table_paginate');
                
                let data = this.api().rows().data();

                if (data.length)
                {
                    let countries_percentage = 0;
                    let country_percentage = 0;

                    for (var i = 0; i < data.length; i++)
                    {
                        country_percentage =
                            (data[i].imported_indicators_approved + data[i].survey_indicators_final_approved + data[i].eurostat_indicators_approved) /
                                (data[i].imported_indicators + data[i].survey_indicators + data[i].eurostat_indicators);
                        countries_percentage += country_percentage * 100;
                    }

                    $('.overall').removeClass('d-none').find('h2').html(Math.floor(countries_percentage / data.length || 0) + '%');
                }

                $('.loader').fadeOut();
            },
            'columns': [{
                    'data': 'country_name',
                    render: function(data, type, row) {
                        return `<span>${row.country.name}</span>`;
                    }
                },
                {
                    'data': 'other_sources',
                    render: function(data, type, row) {
                        let progress = '-';

                        if (row.imported_indicators != undefined &&
                            row.imported_indicators_approved != undefined)
                        {
                            let imported_indicators_percentage_approved = Math.floor((row.imported_indicators_approved / row.imported_indicators || 0) * 100);

                            progress = '<div class="progress-ratio">' + row.imported_indicators_approved + '/' + row.imported_indicators + '</div>' +
                                       '<div class="progress">' +
                                            '<div class="progress-bar bg-positive" role="progressbar" style="width: ' +
                                                imported_indicators_percentage_approved + '%"' +
                                                'aria-valuenow="50" aria-valuemin="0" aria-valuemax="100">' +
                                                imported_indicators_percentage_approved + '%' +
                                            '</div>' +
                                       '</div>';
                        }

                        return '<div class="d-flex justify-content-center">' + progress + '</div>';
                    }
                },
                {
                    'data': 'survey',
                    render: function(data, type, row) {
                        let progress = '-';

                        if (row.survey_indicators != undefined &&
                            row.survey_indicators_final_approved != undefined)
                        {
                            progress = '<div class="progress-ratio">' + row.survey_indicators_final_approved + '/' + row.survey_indicators + '</div>' +
                                       '<div class="progress">' +
                                            '<div class="progress-bar bg-positive" role="progressbar" style="width: ' +
                                                row.survey_indicators_percentage_final_approved + '%"' +
                                                'aria-valuenow="50" aria-valuemin="0" aria-valuemax="100">' +
                                                row.survey_indicators_percentage_final_approved + '%' +
                                            '</div>' +
                                       '</div>';
                        }

                        return '<div class="d-flex justify-content-center">' + progress + '</div>';
                    }
                },
                {
                    'data': 'external_sources',
                    render: function(data, type, row) {
                        let progress = '-';

                        if (row.eurostat_indicators != undefined &&
                            row.eurostat_indicators_approved != undefined)
                        {
                            let eurostat_indicators_percentage_approved = Math.floor((row.eurostat_indicators_approved / row.eurostat_indicators || 0) * 100);

                            progress = '<div class="progress-ratio">' + row.eurostat_indicators_approved + '/' + row.eurostat_indicators + '</div>' +
                                       '<div class="progress">' +
                                            '<div class="progress-bar bg-positive" role="progressbar" style="width: ' +
                                                eurostat_indicators_percentage_approved + '%"' +
                                                'aria-valuenow="50" aria-valuemin="0" aria-valuemax="100">' +
                                                eurostat_indicators_percentage_approved + '%' +
                                            '</div>' +
                                       '</div>';
                        }

                        return '<div class="d-flex justify-content-center">' + progress + '</div>';
                    }
                }
            ]
        });
    });
}

window.addEventListener('yearChange', function() {
    jQuery(function($) {
        $('.loader').fadeIn();

        location.reload();
    });
});