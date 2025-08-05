var pageModal = new bootstrap.Modal(document.getElementById('pageModal'));
let table;
let section = '/invitation';

(function ($, Drupal) {
    Drupal.behaviors.listInvitations = {
        attach: function (context, settings) {
            once('listInvitations', 'body').forEach(function () {
                initDataTable();

                $(document).on('click', '.invite-user', function() {
                    $('.loader').fadeIn();
            
                    $.ajax({
                        'url': `${section}/create`,
                        success: function(data) {
                            let obj = {
                                'action': 'create',
                                'route': `${section}/store`,
                                'title': 'Invite new user',
                                'html': data,
                                'btn': 'Invite'
                            };
                            setModal(obj);
        
                            pageModal.show();
                        }
                    });
                });
                
                $(document).on('click', '#process-data', function() {
                    $('.loader').fadeIn();

                    let route = $('.modal #item-route').val();
                    let data = getFormData('invitation-create');
        
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
            });
        }
    }
})(jQuery, Drupal);

function initDataTable()
{
    jQuery(function($) {
        table = $('#invitations-table').DataTable({
            'ajax': `${section}/list`,
            'initComplete': function() {
                addDataTableSearchInfoIcon();
            },
            'drawCallback': function() {
                convertToLocalTimestamp();

                toggleDataTablePagination(this, '#invitations-table_paginate');
            },
            'order': [
                [5, 'desc']
            ],
            'columns': [
                {
                    'data': 'title'
                },
                {
                    'data': 'email',
                    render: function(data, type, row) {
                        return `<span class="word-break">${data}</span>`;
                    }
                },
                {
                    'data': 'country',
                    render: function(data, type, row) {
                        if (row.country) {
                            return `<span>${row.country.name}</span>`;
                        }
                        
                        return `<span class="d-flex justify-content-center">-</span>`;
                    }
                },
                {
                    'data': 'role',
                    render: function(data, type, row) {
                        if (row.role) {
                            return `<span>${row.role.name}</span>`;
                        }
                        
                        return `<span class="d-flex justify-content-center">-</span>`;
                    }
                },
                {
                    'data': 'inviter_user'
                },
                {
                    'data': 'invited_date',
                    render: function(data, type, row) {
                        if (type === 'sort') {
                            return data;
                        }

                        if (row.invited_date) {
                            return `<span class="local-timestamp-display date" style="width: 81px;">${row.invited_date}</span>
                                    <span class="local-timestamp" hidden>${row.invited_date}</span>`;
                        }
                        
                        return `<span class="d-flex justify-content-center">-</span>`;
                    }
                },
                {
                    'data': 'registered_date',
                    render: function(data, type, row) {
                        if (type === 'sort') {
                            return data;
                        }

                        if (row.registered_date) {
                            return `<span class="local-timestamp-display date" style="width: 81px;">${row.registered_date}</span>
                                    <span class="local-timestamp" hidden>${row.registered_date}</span>`;
                        }
                        
                        return `<span class="d-flex justify-content-center">-</span>`;
                    }
                },
                {
                    'data': 'status',
                    render: function(data, type, row) {
                        let style = getStatusStyle(row);
                        
                        return `<div class="d-flex justify-content-center">
                                    <button class="btn-${style} btn-label pointer-events-none" type="button">${row.state.name}</button>
                                </div>`;
                    }
                }
            ]
        });
    });
}

function getStatusStyle(row)
{           
    switch (row.state.id)
    {
        case 1:
            return 'positive-invert';
        case 2:
            return 'positive';
        case 3:
            return 'negative';
        default:
            return '';
    }
}