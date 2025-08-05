var pageModal = new bootstrap.Modal(document.getElementById('pageModal'));
let table;
let section = '/index';

(function ($, Drupal) {
    Drupal.behaviors.listIndexes = {
        attach: function (context, settings) {
            once('listIndexes', 'body').forEach(function () {
                initDataTable();
                
                $(document).on('click', '.item-create', function() {
                    $('.loader').fadeIn();
                    
                    $.ajax({
                        'url': `${section}/create`,
                        success: function(data) {
                            let obj = {
                                'large': true,
                                'action': 'create',
                                'route': `${section}/store`,
                                'title': 'New Index',
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
        
                    $.ajax({
                        'url': `${section}/show/${id}`,
                        success: function(data) {
                            let obj = {
                                'id': id,
                                'large': true,
                                'action': 'edit',
                                'route': `${section}/update/${id}`,
                                'title': 'Edit Index',
                                'html': data
                            };
                            setModal(obj);

                            pageModal.show();
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
                                        Index <b>${name}</b> will be deleted. Are you sure?
                                    </div>
                                </form>`;
                
                    let obj = {
                        'modal': 'warning',
                        'id': id,
                        'action': 'delete',
                        'route': `${section}/delete/${id}`,
                        'title': 'Delete Index',
                        'html': data,
                        'btn': 'Delete'
                    };
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
                            if (req.status == 400) {
                                showInputErrors(req.responseJSON.errors);
                            }
                            else if (req.status == 403 ||
                                     req.status == 405)
                            {
                                pageModal.hide();
            
                                type = Object.keys(req.responseJSON)[0];
                                message = req.responseJSON[ type ];
            
                                setAlert({
                                    'status': type,
                                    'msg': message
                                });
                            }
                        }
                    });
                });

                $(document).on('change', '#statusSwitch', function() {
                    $(this).toggleClass('unchecked-style').parent().siblings('.label');
                    $(this).checked = !$(this).checked;
                    
                    if ($(this).hasClass('unchecked-style')) {
                        $(this).parent().siblings('.label').text('Unpublished');
                    }
                    else {
                        $(this).parent().siblings('.label').text('Published');
                    }
                });
              
                $(document).on('change', '#euSwitch', function() {
                    $(this).toggleClass('unchecked-style').parent().siblings('.label');
                    $(this).checked = !$(this).checked;
              
                    if ($(this).hasClass('unchecked-style'))
                    {
                        $(this).parent().siblings('.label').text('Unpublished');
              
                        $('#msSwitch').addClass('switch-deactivated');
                    }
                    else
                    {
                        $(this).parent().siblings('.label').text('Published');
              
                        $('#msSwitch').removeClass('switch-deactivated');
                    }
                });
              
                $(document).on('change', '#msSwitch', function() {
                    $(this).toggleClass('unchecked-style').parent().siblings('.label');
                    $(this).checked = !$(this).checked;
              
                    if ($(this).hasClass('unchecked-style'))
                    {
                        $(this).parent().siblings('.label').text('Unpublished');
              
                        $('#euSwitch').removeClass('switch-deactivated');
                    }
                    else
                    {
                        $(this).parent().siblings('.label').text('Published');
              
                        $('#euSwitch').addClass('switch-deactivated');
                    }
                });
            });
        }
    };
})(jQuery, Drupal);

function initDataTable()
{
    jQuery(function($) {
        table = $('#indexes-table').DataTable({
            'ajax': `${section}/list`,
            'initComplete': function() {
                addDataTableSearchInfoIcon();
            },
            'drawCallback': function() {
                toggleDataTablePagination(this, '#indexes-table_paginate');
            },
            'order': [
                [1, 'asc'], // Sort by year
                [0, 'asc']  // Sort by title
            ],
            'columns': [
                {
                    'data': 'title'
                },
                {
                    'data': 'year'
                },
                {
                    'data': 'created_by'
                },
                {
                    'data': 'status',
                    render: function(data, type, row) {
                        return `<div class="d-flex justify-content-center">
                                    <button class="btn-${(row.status == 'Published' ? 'positive' : 'positive-invert')} btn-label pointer-events-none">${row.status}</button>
                                </div>`;
                    }
                },
                {
                    'data': 'actions',
                    render: function(data, type, row) {
                        return `<div class="d-flex justify-content-center">
                                    <button class="icon-edit btn-unstyle item-edit" data-bs-toggle="tooltip" data-bs-target="#pageModalLabel"
                                        title="Edit index" item-id="${row.id}"></button>
                                    <button class="icon-bin btn-unstyle item-delete ${(row.status == 'Published' ? 'icon-bin-deactivated' : '')}" data-bs-toggle="tooltip"
                                        title="Delete index" item-id="${row.id}" item-name="${row.title}" ${(row.status == 'Published' ? 'disabled' : '')}></button>
                               </div`;
                    },
                    'orderable': false
                }
            ]
        });
    });
}