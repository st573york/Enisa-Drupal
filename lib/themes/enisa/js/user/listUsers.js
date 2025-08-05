var pageModal = new bootstrap.Modal(document.getElementById('pageModal'));
let table;
let section = '/user';
let user_id;
let is_admin;

(function ($, Drupal) {
    Drupal.behaviors.listUsers = {
        attach: function (context, settings) {
            once('listUsers', 'body').forEach(function () {
                user_id = settings.userData.user_id;
                is_admin = settings.userData.is_admin;
                
                initDataTable();

                $(document).on('change update_buttons', '#item-select-all, .item-select', function() {
                    $('.multiple-delete, .multiple-edit').prop('disabled', (!getDataTableCheckboxesCheckedAllPages()));
                });

                $(document).on('change', 'input[name="blockSwitch"]', function() {
                    $('.loader').fadeIn();
    
                    $.ajax({
                        'url': `${section}/block/toggle/${$(this).attr('item-id')}`,
                        'type': 'POST',
                        success: function() {
                            table.ajax.reload(null, false);
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
    
                $(document).on('change', '.form-switch input:checkbox', function() {
                    if ($(this).is(':checked')) {
                        $(this).removeClass('unchecked-blocked-style');
                    }
                    else {
                        $(this).addClass('unchecked-blocked-style');
                    }
                });
    
                $(document).on('click', '.multiple-delete', function() {
                    let data = `<input hidden id="item-route"/>
                                <div class="warning-message">
                                    Selected users will be deleted. Are you sure?
                                </div>`;
    
                    let obj = {
                        'modal': 'warning',
                        'action': 'delete',
                        'route': `${section}/multiple/delete`,
                        'title': 'Delete Users',
                        'html': data,
                        'btn': 'Delete'
                    };
                    setModal(obj);
    
                    pageModal.show();
                });

                $(document).on('click', '.multiple-edit', function() {
                    $('.loader').fadeIn();
                    let data = getFormData('user-edit');
                    let users = data.get('datatable-selected').split(',');
                    if (users.length == 1)
                    {
                        let row = {
                            'id': users[0],
                            'name': $('.name[data-id="' + users[0] + '"]').text()
                        };
                        
                        editUser(row);
                        
                        return;
                    }
    
                    $.ajax({
                        'url': `${section}/multiple/get`,
                        'data': {
                            'users': data.get('datatable-selected')
                        },
                        success: function(data) {
                            let obj = {
                                'action': 'edit',
                                'route': `${section}/multiple/update`,
                                'title': 'Edit Users',
                                'html': data
                            };
                            setModal(obj);
    
                            pageModal.show();
                        }
                    });
                });
    
                $(document).on('click', '#process-data', function() {
                    $('.loader').fadeIn();
                
                    skipFadeOut = true;
                
                    let route = $('.modal #item-route').val();
                    let data = getFormData('user-edit');
    
                    $.ajax({
                        'url': route,
                        'type': 'POST',
                        'data': data,
                        'contentType': false,
                        'processData': false,
                        success: function() {
                            skipFadeOut = false;
    
                            pageModal.hide();
                            table.ajax.reload(null, false);
                        },
                        error: function(req) {
                            if (req.status == 302)
                            {
                                pageModal.hide();

                                if (req.responseJSON.action == 'redirect') {
                                    window.location.href = req.responseJSON.url;
                                }
                                else if (req.responseJSON.action == 'reload') {
                                    window.location.href = window.location.href;
                                }
                            }
                            else if (req.status == 400)
                            {
                                skipFadeOut = false;
                                
                                if (req.responseJSON.type == 'pageModalForm') {
                                    showInputErrors(req.responseJSON.errors);
                                }
                            }
                            else if (req.status == 403 ||
                                     req.status == 405)
                            {
                                skipFadeOut = false;
    
                                pageModal.hide();
                                table.ajax.reload(null, false);
    
                                type = Object.keys(req.responseJSON)[0];
                                message = req.responseJSON[ type ];
    
                                setAlert({
                                    'status': type,
                                    'msg': message
                                });
                            }
                        }
                    }).done(function () {
                        $('#item-select-all').prop('checked', false);
                    });
                });
            });
        }
    }
})(jQuery, Drupal);

function initDataTable()
{
    jQuery(function($) {
        table = $('#users-table').DataTable({
            'ajax': `${section}/list`,
            'initComplete': function() {
                addDataTableSearchInfoIcon();
            },
            'drawCallback': function() {
                convertToLocalTimestamp();

                toggleDataTablePagination(this, '#users-table_paginate');

                $('#item-select-all')
                    .trigger('update_select_all')
                    .trigger('update_buttons');
            },
            'order': [
                [1, 'asc'], // Sort by role weight
                [5, 'asc']  // Sort by country
            ],
            'columns': [
                {
                    'data': 'id',
                    render: function(data, type, row) {
                        let can_select = (row.id == user_id) ? false : true;

                        return `<input class="item-select form-check-input" type="checkbox" id="item-${data}" ${(can_select ? '' : 'disabled')}\>`;
                    },
                    'orderable': false
                },
                {
                    'data': 'role',
                    render: function(data, type, row) {
                        if (row.role) {
                            return row.role.weight;
                        }

                        return null;
                    },
                    'visible': false,
                    'searchable': false
                },
                {
                    'data': 'name',
                    render: function(data, type, row) {
                        return `<span class="name" data-id="${row.id}">${row.name}</span>`;
                    }
                },
                {
                    'data': 'email',
                    render: function(data, type, row) {
                        if (row.email) {
                            return `<span class="word-break">${data}</span>`;
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
                    'data': 'country',
                    render: function(data, type, row) {
                        if (row.country) {
                            return `<span>${row.country.name}</span>`;
                        }
                        
                        return `<span class="d-flex justify-content-center">-</span>`;
                    }
                },
                {
                    'data': 'last_login_at',
                    render: function(data, type, row) {
                        if (type === 'sort') {
                            return data;
                        }

                        if (row.last_login_at) {
                            return `<span class="local-timestamp-display date" style="width: 81px;">${row.last_login_at}</span>
                                    <span class="local-timestamp" hidden>${row.last_login_at}</span>`;
                        }
            
                        return `<span class="d-flex justify-content-center">-</span>`;
                    }
                },
                {
                    'data': 'status',
                    render: function(data, type, row) {
                        let can_toggle = (row.id == user_id) ? false : true;
                        
                        return (row.status == 'Blocked') ?
                            `<div class="d-flex gap-2">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input switch unchecked-blocked-style ${(can_toggle ? '' : 'switch-deactivated')} "name="blockSwitch" id="blockSwitch" item-id="${row.id}">
                                </div>
                                <span class="text-danger pt-1">Blocked</span>
                            </div>` :
                            `<div class="d-flex gap-2">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input switch ${(can_toggle ? '' : 'switch-deactivated')} "name="blockSwitch" id="blockSwitch" checked item-id="${row.id}">
                                </div>
                                <span class="pt-1">Enabled</span>
                            </div>`;
                    }
                },
                {
                    'data': 'actions',
                    render: function(data, type, row) {
                        let can_delete = (row.id == user_id) ? false : true;

                        let edit_click = " onclick='editUser(" + JSON.stringify(row) + ");'";
                        let delete_click = " onclick='deleteUser(" + JSON.stringify(row) + ");'";

                        let delete_button = '';
                        if (is_admin) {
                            delete_button =
                                `<button class="icon-bin btn-unstyle ${(can_delete ? '' : 'icon-bin-deactivated')} "data-bs-toggle="tooltip"
                                    title="Delete user" type="button" ${delete_click} ${(!can_delete ? 'disabled' : '')}>
                                </button>`;
                        }

                        return `<div class="d-flex justify-content-center">
                                    <button class="icon-edit btn-unstyle "data-bs-toggle="tooltip"
                                        title="Edit user" type="button" ${edit_click}>
                                    </button>
                                    ${delete_button}
                                </div>`;
                    },
                    'orderable': false
                }
            ]
        });
    });
}

function editUser(row)
{
    jQuery(function($) {
        $('.loader').fadeIn();

        $.ajax({
            'url': `${section}/single/get/${row.id}`,
            success: function(data) {
                let obj = {
                    'id': row.id,
                    'action': 'edit',
                    'route': `${section}/single/update/${row.id}`,
                    'title': 'Edit User ' + row.name,
                    'html': data
                };
                setModal(obj);

                pageModal.show();
            }
        });
    });
}

function deleteUser(row)
{
    jQuery(function($) {
        let data = `<input hidden id="item-route"/>
                    <div class="warning-message">
                        User '${row.name}' will be deleted. Are you sure?
                    </div>`;

        let obj = {
            'modal': 'warning',
            'id': row.id,
            'action': 'delete',
            'route': `${section}/single/delete/${row.id}`,
            'title': 'Delete User',
            'html': data,
            'btn': 'Delete'
        };
        setModal(obj);

        pageModal.show();
    });
}