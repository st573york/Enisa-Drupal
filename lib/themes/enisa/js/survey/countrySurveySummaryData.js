let user_group;
let is_poc;
let requested_changes_data;
let data_not_available;
let references;
let comments;

(function ($, Drupal) {
    Drupal.behaviors.countrySurveySummaryData = {
        attach: function (context, settings) {
            once('countrySurveySummaryData', 'body').forEach(function () {
                user_group = settings.country_summary_data.user_group;
                is_poc = settings.country_summary_data.is_poc;
                requested_changes_data = settings.country_summary_data.requested_changes_data;
                data_not_available = settings.country_summary_data.data_not_available;
                references = settings.country_summary_data.references;
                comments = settings.country_summary_data.comments;
                
                initRequestedChangesDataTable();
                initDataNotAvailableDataTable();
                initReferencesDataTable();
                initCommentsDataTable();
            });
        }
    }
})(jQuery, Drupal);

function initRequestedChangesDataTable()
{
    jQuery(function($) {
        $('#country-survey-requested-changes-table').DataTable({
            'data': requested_changes_data,
            'initComplete': function() {
                addDataTableSearchInfoIcon();
            },
            'drawCallback': function() {
                convertToLocalTimestamp();

                toggleDataTablePagination(this, '#country-survey-requested-changes-table_paginate');
            },
            'order': [
                [1, 'asc']
            ],
            'columns': [
                {
                    'data': 'order',
                    render: function(data, type, row) {
                        return `<span>${row.indicator.order}</span>`;
                    }
                },
                {
                    'data': 'requested_date',
                    render: function(data, type, row) {
                        if (type === 'sort') {
                            return data;
                        }
                                                        
                        if (row.requested_date) {
                            return `<span class="local-timestamp-display date" style="width: 81px;">${row.requested_date}</span>
                                    <span class="local-timestamp" hidden>${row.requested_date}</span>`;
                        }
                        
                        return '<div class="d-flex justify-content-center">-</div>';
                    }
                },
                {
                    'data': 'requested_user_name',
                    render: function(data, type, row) {
                        if (is_poc &&
                            row.requested_user.role.weight == 5)
                        {
                            return user_group;
                        }

                        return row.requested_user.name;
                    }
                },
                {
                    'data': 'answered_date',
                    render: function(data, type, row) {
                        if (type === 'sort') {
                            return data;
                        }
                                                        
                        if (row.answered_date) {
                            return `<span class="local-timestamp-display date" style="width: 81px;">${row.answered_date}</span>
                                    <span class="local-timestamp" hidden>${row.answered_date}</span>`;
                        }
                        
                        return '<div class="d-flex justify-content-center">-</div>';
                    }
                },
                {
                    'data': 'assignee_name',
                    render: function(data, type, row) {
                        return `<span>${row.assignee.name}</span>`;
                    }
                },
                {
                    'data': 'changes',
                    render: function(data, type, row) {
                        return `<span>${row.changes}</span>`;
                    }
                }
            ]
        });
    });
}

function initDataNotAvailableDataTable()
{
    jQuery(function($) {
        $('#country-survey-data-not-available-table').DataTable({
            'data': data_not_available,
            'initComplete': function() {
                addDataTableSearchInfoIcon();
            },
            'drawCallback': function() {
                toggleDataTablePagination(this, '#country-survey-data-not-available-table_paginate');
            },
            'order': [
                [0, 'asc']
            ],
            'columns': [
                {
                    'data': 'order'
                },
                {
                    'data': 'title'
                },
                {
                    'data': 'number'
                },
                {
                    'data': 'question'
                }
            ]
        });
    });
}

function initReferencesDataTable()
{
    jQuery(function($) {
        $('#country-survey-references-table').DataTable({
            'data': references,
            'initComplete': function() {
                addDataTableSearchInfoIcon();
            },
            'drawCallback': function() {
                toggleDataTablePagination(this, '#country-survey-references-table_paginate');
            },
            'order': [
                [0, 'asc']
            ],
            'columns': [
                {
                    'data': 'order'
                },
                {
                    'data': 'title'
                },
                {
                    'data': 'number'
                },
                {
                    'data': 'reference_year'
                },
                {
                    'data': 'reference_source'
                }
            ]
        });
    });
}

function initCommentsDataTable()
{
    jQuery(function($) {
        $('#country-survey-comments-table').DataTable({
            'data': comments,
            'initComplete': function() {
                addDataTableSearchInfoIcon();
            },
            'drawCallback': function() {
                toggleDataTablePagination(this, '#country-survey-comments-table_paginate');
            },
            'order': [
                [0, 'asc']
            ],
            'columns': [
                {
                    'data': 'order'
                },
                {
                    'data': 'title'
                },
                {
                    'data': 'comments'
                }
            ]
        });
    });
}