var pageModal = new bootstrap.Modal(document.getElementById('pageModal'));
let auditTable;
let section = '/audit';
let span = document.createElement('span');

(function ($, Drupal) {
    Drupal.behaviors.listAudit = {
        attach: function (context, settings) {
            once('listAudit', 'body').forEach(function () {
                initDatePicker();
                initDataTable();

                $(document).on('show', '#minDate', function() {
                    if (datepickerLimit)
                    {
                        $(this).datepicker('setEndDate', $('#maxDate').datepicker('getDate'));

                        datepickerLimit = false;
                    }
                });

                $(document).on('show', '#maxDate', function() {
                    if (datepickerLimit)
                    {
                        $(this).datepicker('setStartDate', $('#minDate').datepicker('getDate'));
                        $(this).datepicker('setEndDate', new Date());

                        datepickerLimit = false;
                    }
                });
        
                $(document).on('change', '.datepicker, #action', function() {
                    auditTable.draw();
                });
            });
        }
    }
})(jQuery, Drupal);

function initDataTable()
{
    jQuery(function($) {
        auditTable = $('#audit-table').DataTable({
            'processing': true,
            'serverSide': true,
            'ajax': {
                'url': `${section}/list`,
                'data': function (data) {
                    data.minDate = $('#minDate').val();
                    data.maxDate = $('#maxDate').val();
                    data.action = $('#action').val();
                }
            },
            'initComplete': function() {
                addDataTableSearchInfoIcon();

                let api = this.api();
                let requestTimer = false;
                let searchDelay = 1000;
                    
                $('#audit-table_filter input[type="search"]')
                    .unbind() // Unbind previous default bindings
                    .bind('input propertychange', function(e) { // Bind the timeout search
                        let elem = $(this);

                        if (requestTimer)
                        {
                            window.clearTimeout(requestTimer);

                            requestTimer = false;
                        }

                        requestTimer = setTimeout(function () {
                            api.search($(elem).val()).draw();
                        }, searchDelay);
                    });
            },
            'drawCallback': function() {
                toggleDataTablePagination(this, '#audit-table_paginate');

                scrollToTop();
            },
            'order': [
                [0, 'desc']
            ],
            'columns': [
                {
                    'data': 'id'
                },
                {
                    'data': 'date',
                    render: function(data) {
                        return `<span class="local-timestamp">${data}</span>`;
                    }
                },
                {
                    "data": 'ip_address'
                },
                {
                    'data': 'action',
                    render: function(data) {
                        return `<span style="text-transform: capitalize;">${data}</span>`;
                    }
                },
                {
                    'data': 'user'
                },
                {
                    'data': 'description'
                },
                {
                    'data': 'affected_entity'
                },
                {
                    'data': 'new_values',
                    render: function(data) {
                        let changes = '';

                        $.each(JSON.parse(data), function(key, val) {
                            if (key != 'data' &&
                                key != 'json_data')
                            {
                                span.innerHTML = val;
                                val = escapeHtml(span.innerText);
                                                                       
                                let max = 30;
                                let tooltip = (val && val.length > max) ? true : false;

                                val = (tooltip) ?
                                    `${val.substring(0, max)} <span class="info-icon-black" data-bs-toggle="tooltip" data-bs-placement="right" title="${val}"></span>` : val;

                                if (key == 'status') {
                                    val = getValueStyle(val);
                                }

                                changes += `<b style="text-transform: capitalize;">${key.replace(/_/g, ' ')}</b>: ${val}<br \>`;
                            }
                        });

                        return changes;
                    },
                    'orderable': false
                }
            ]
        });
    });
}

function getValueStyle(val)
{
    switch (val)
    {
        case 'Blocked':
        case 'Unapproved':
            return `<span class="text-danger">${val}</span>`;
        case 'Approved':
            return `<span style="color: #3C58CF;">${val}</span>`;
        case 'Published':
            return `<span style="color: #004087;">${val}</span>`;
        case 'Calculated':
        case 'Submitted':
        case 'Finalised':
            return `<span class="text-success">${val}</span>`;
        default:
            return val;
    }
}

function escapeHtml(text)
{
    let map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
  
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}
