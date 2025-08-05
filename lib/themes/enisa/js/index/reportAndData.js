var pageModal = new bootstrap.Modal(document.getElementById('pageModal'));
let table;
let section = '/index/report-and-data';
let is_admin;
let loaded_index_data;
let data_available;
let data;
let year;

(function ($, Drupal) {
    Drupal.behaviors.reportAndData = {
        attach: function (context, settings) {
            once('reportAndData', 'body').forEach(function () {
                is_admin = settings.reports_and_data.is_admin;
                loaded_index_data = settings.reports_and_data.loaded_index_data;
                data_available = settings.reports_and_data.data_available;
                data = settings.reports_and_data.data;
                year = setIndexYear();

                $(document).on('change', '#country', function() {
                    table.clear().rows.add(data).draw();
                });

                $('#pageModal').on('hide.bs.modal', function (e) {
                    const button = document.getElementById('export-pdf');

                    button.disabled = true;
                    button.textContent = 'Loading...';
                });
                
                if (data_available)
                {
                    if (!loaded_index_data.eu_published &&
                        !loaded_index_data.ms_published)
                    {
                        if (is_admin)
                        {
                            setAlert({
                                'status': 'warning',
                                'msg': `EU/MS Reports/Visualisations for ${year} are unpublished.\
                                    Browse to <a href="/index/management">Indexes</a> to publish them.`
                            });

                            return;
                        }
                    }
                    else
                    {
                        skipFadeOut = true;

                        initDataTable();

                        return;
                    }
                }

                setAlert({
                    'status': 'warning',
                    'msg': `No reports available ${(year == 2022 ? 'for 2022.' : `. Data collection for ${year} is currently in progress.`)}`
                });
            });
        }
    };
})(jQuery, Drupal);

function initDataTable()
{
    jQuery(function($) {
        table = $('#reports-and-data-table').DataTable({
            'data': data,
            'initComplete': function() {
                addDataTableSearchInfoIcon();
            },
            'drawCallback': function () {
                toggleDataTablePagination(this, '#reports-and-data-table_paginate');

                $('.loader').fadeOut();

                skipFadeOut = false;
            },
            'columns': [
                {
                    'data': 'title',
                    render: function (data, type, row) {
                        return `<span> ${row.title} - ${(row.country ? `${getCountryName()} ` : '')} ${year}</span>`;
                    }
                },
                {
                    'data': 'actions',
                    render: function(data, type, row) {
                        let actions = '';

                        if (row.type == 'ms_report')
                        {
                            if (year == '2023') {
                                actions = `<button class="icon-pdf-download btn-unstyle" data-bs-toggle="tooltip"
                                                title="Download pdf report" type="button" onclick="javascript:downloadPDFReport('ms');">
                                            </button>`;
                            }
                            else {
                                actions = `<button class="icon-show btn-unstyle" data-bs-toggle="tooltip"
                                                title="View report" type="button" onclick="javascript:viewMSReport();">
                                            </button>
                                            <button class="icon-xls-download btn-unstyle"
                                                title="Download xls report" data-bs-toggle="tooltip" onclick="javascript:getReportExcel('ms');">
                                            </button>`;
                            }
                        }
                        else if (row.type == 'eu_report')
                        {
                            if (year == '2023') {
                                actions = `<button class="icon-pdf-download btn-unstyle" data-bs-toggle="tooltip"
                                                title="Download pdf report" type="button" onclick="javascript:downloadPDFReport('eu');">
                                           </button>`;
                            }
                            else {
                                actions = `<button class="icon-show btn-unstyle" data-bs-toggle="tooltip"
                                                title="View report" type="button" onclick="javascript:viewEUReport();">
                                           </button>
                                            <button class="icon-xls-download btn-unstyle"
                                                title="Download xls report" data-bs-toggle="tooltip" onclick="javascript:getReportExcel('eu');">
                                            </button>`;
                            }
                        }
                        else if (row.type == 'ms_raw_data') {
                            actions = `<button class="icon-xls-download btn-unstyle" data-bs-toggle="tooltip"
                                            title="Download xls data" type="button" onclick="javascript:getMSRawDataExcel();">
                                       </button>`;
                        }
                        else if (row.type == 'ms_results') {
                            actions = `<button class="icon-xls-download btn-unstyle" data-bs-toggle="tooltip"
                                            title="Download xls data" type="button" onclick="javascript:getMSResultsExcel();">
                                        </button>`;
                        }

                        return `<div class="d-flex justify-content-start">${actions}</div>`;
                    },
                    'width': '20%'
                }
            ],
            'paging': false,
            'info': false,
            'searching': false,
            'ordering': false
        });
    });
}

function viewMSReport()
{
    jQuery(function($) {
        $('.loader').fadeIn();

        skipFadeOut = true;

        $.ajax({
            'url': `${section}/ms-report/${getCountryIndexId()}`,
            success: function() {
                pageModal.show();
            },
            error: function(req) {
                $('.loader').fadeOut();

                if (req.status == 403) {
                    setAlert({
                        'status': 'error',
                        'msg': req.responseJSON.error
                    });
                }
            }
        }).done(function (data) {
            $('#frameContainer').empty();

            setTimeout(() => {
                let iframe = document.createElement('iframe');

                iframe.setAttribute('id', 'reportIframe');
                iframe.setAttribute('srcdoc', data);
                iframe.style.height = '70vh';
                iframe.style.paddingLeft = '0px';
                iframe.style.paddingRight = '0px';

                document.getElementById('frameContainer').appendChild(iframe);

                iframe.onload = function () {
                    let iframe_doc = iframe.contentDocument || iframe.contentWindow.document;

                    let js_files = [
                        '/core/assets/vendor/jquery/jquery.min.js',
                        'https://cdn.jsdelivr.net/npm/echarts@5.4.0/dist/echarts.min.js'
                    ];

                    (async () => {
                        await loadScripts(iframe_doc, js_files);

                        getReportChartMSData(iframe_doc);
                    })();

                    let css_files = [
                        '/themes/custom/enisa/css/style.css'
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

function viewEUReport()
{
    jQuery(function($) {
        $('.loader').fadeIn();

        skipFadeOut = true;

        $.ajax({
            'url': `${section}/eu-report`,
            success: function() {
                pageModal.show();
            }
        }).done(function (data) {
            $('#frameContainer').empty();

            setTimeout(() => {
                let iframe = document.createElement('iframe');

                iframe.setAttribute('id', 'reportIframe');
                iframe.setAttribute('srcdoc', data);
                iframe.style.height = '70vh';
                iframe.style.paddingLeft = '0px';
                iframe.style.paddingRight = '0px';
                
                document.getElementById('frameContainer').appendChild(iframe);

                iframe.onload = function () {
                    let iframe_doc = iframe.contentDocument || iframe.contentWindow.document;

                    let js_files = [
                        '/core/assets/vendor/jquery/jquery.min.js',
                        'https://cdn.jsdelivr.net/npm/echarts@5.4.0/dist/echarts.min.js'
                    ];

                    (async () => {
                        await loadScripts(iframe_doc, js_files);

                        getReportChartEUData(iframe_doc);
                    })();

                    let css_files = [
                        '/themes/custom/enisa/css/style.css'
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
                    let changeEvent = new Event('pagedRendered');
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

function getReportChartMSData(iframe_doc)
{
    jQuery(function($) {
        $.ajax({
            'url': `${section}/ms-report/chart-data/${getCountryIndexId()}`,
            success: function (response) {
                $.each(response.data, function(key, value) {
                    initRadarMSChart(iframe_doc, `radar-${key}`, value);
                });
                
                let button = parent.document.getElementById('export-pdf');
                setTimeout(() => {
                    loadScripts(iframe_doc, ['/themes/custom/enisa/js/main/paged.polyfill.js']);

                    button.disabled = false;
                    button.textContent = 'Export | PDF';
                }, 1000);
            }
        });
    });
}

function initRadarMSChart(iframe_doc, element, data)
{
    let chart_dom = iframe_doc.getElementById(element);
    let bar_chart = echarts.init(chart_dom, null, {
        renderer: 'svg'
    });

    let option;

    option = {
        color: ['#CE0F3E', '#004F9F', '#8B8B8E'],
        textStyle: {
            color: 'rgba(20, 20, 20, 0.6)',
            fontSize: 7
        },
        radar: {
            shape: 'circle',
            radius: ['0%', '80%'],
            textStyle: {
                color: '#141414',
                fontSize: 7
            },
            name: {
                textStyle: {
                    color: '#141414',
                    fontSize: 10
                }
            },
            indicator: data.indicator.map((indicator) => ({
                name: indicator.name.length > 44 ? indicator.name.substring(0, 44) + '...' : indicator.name,
                max: indicator.max
            }))
        },
        legend: {
            show: true,
            orient: 'vertical',
            left: 'right',
            top: 'top',
            data: [
                { name: data.name, icon: 'circle', itemStyle: { color: '#CE0F3E' } },
                { name: 'EU', icon: 'circle', itemStyle: { color: '#004F9F' } },
                { name: 'max values', icon: 'circle', itemStyle: { color: '#8B8B8E' } }
            ],
            selectedMode: false
        },
        series: [
            {
                name: 'EU',
                type: 'radar',
                data: [
                    {
                        value: data.country,
                        name: data.name
                    },
                    {
                        value: data.eu,
                        name: 'EU'
                    },
                    {
                        value: 100,
                        name: 'max values',
                        lineStyle: {
                            type: 'dashed'
                        }
                    }
                ]
            }
        ]
    };

    option && bar_chart.setOption(option);
}

function getReportChartEUData(iframe_doc)
{
    jQuery(function($) {
        $.ajax({
            'url': `${section}/eu-report/chart-data`,
            success: function (response) {
                $.each(response.data, function(key, value) {
                    initRadarEUChart(iframe_doc, `radar-${key}`, value);
                });

                let button = parent.document.getElementById('export-pdf');
                setTimeout(() => {
                    loadScripts(iframe_doc, ['/themes/custom/enisa/js/main/paged.polyfill.js']);

                    button.disabled = false;
                    button.textContent = 'Export | PDF';
                }, 1000);
            }
        });
    });
}

function initRadarEUChart(iframe_doc, element, data)
{
    let chart_dom = iframe_doc.getElementById(element);
    let bar_chart = echarts.init(chart_dom, null, {
        renderer: 'svg'
    });

    let option;

    option = {
        color: ['#004F9F', '#8B8B8E'],
        textStyle: {
            color: 'rgba(20, 20, 20, 0.6)',
            fontSize: 7
        },
        radar: {
            shape: 'circle',
            textStyle: {
                color: '#141414',
                fontSize: 7
            },
            name: {
                textStyle: {
                    color: '#141414',
                    fontSize: 10
                }
            },
            indicator: data.indicator.map((indicator) => ({
                name: indicator.name.length > 44 ? indicator.name.substring(0, 44) + '...' : indicator.name,
                max: indicator.max
            }))
        },
        legend: {
            show: true,
            orient: 'vertical',
            left: 'right',
            top: 'top',
            data: [
                { name: 'max values', icon: 'circle' },
                { name: 'EU', icon: 'circle' }
            ],
            selectedMode: false
        },
        series: [
            {
                name: 'EU',
                type: 'radar',
                data: [
                    {
                        value: data.eu,
                        name: 'EU'
                    },
                    {
                        value: 100,
                        name: 'max values',
                        lineStyle: {
                            type: 'dashed'
                        }
                    }
                ]
            }
        ]
    };

    option && bar_chart.setOption(option);
}

function saveReportPDF()
{
    let reportIframe = document.getElementById('reportIframe').contentWindow;

    reportIframe.focus();
    reportIframe.print();

    return false;
}

function resetSaveReportButton()
{
    let button = document.getElementById('export-pdf');
           
    button.disabled = true;
    button.textContent = 'Loading...';
}

function downloadPDFReport(type)
{
    if (type == 'ms') {
        location.href = `${section}/ms-report/pdf/${getCountryIndexId()}`;
    }
    else if (type == 'eu') {
        location.href = `${section}/eu-report/pdf`;
    }
}

function getReportExcel(type)
{
    jQuery(function($) {
        $('.loader').fadeIn();

        $.ajax({
            'url': `${section}/report/excel/export${(type == 'ms') ? `/${getCountryId()}` : ''}`,
            'type': 'POST',
            success: function(data) {
                let link = document.createElement('a');

                link.href = `${section}/report/excel/download/${encodeURIComponent(data.filename)}`;
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
}

function getMSRawDataExcel()
{
    jQuery(function($) {
        $('.loader').fadeIn();

        $.ajax({
            'url': `${section}/ms-raw-data/excel/export/${getCountryIndexId()}`,
            'type': 'POST',
            success: function(data) {
                let link = document.createElement('a');

                link.href = `${section}/ms-raw-data/excel/download/${encodeURIComponent(data.filename)}`;
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
}

function getMSResultsExcel()
{
    location.href = `${section}/ms-results/excel`;
}

function getCountryId()
{
    return jQuery('#country').find(':selected').val();
}

function getCountryName()
{
    return jQuery('#country').find(':selected').text();
}

function getCountryIndexId()
{
    return jQuery('#country').find(':selected').attr('item-id');
}

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