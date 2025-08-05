let skipFadeOut = false;
let secureFlag;

(function ($, Drupal) {
    Drupal.behaviors.layout = {
        attach: function (context, settings) {
            once('layout', 'body').forEach(function () {
                secureFlag = (location.protocol === 'https:') ? 'Secure;' : '';

                $.fn.DataTable.ext.errMode = 'none';

                if (!skipFadeOut) {
                    $('.loader').fadeOut();
                }

                let storage_year = localStorage.getItem('index-year');
                if (storage_year)
                {
                    if ($(`#index-year-select option[value="${storage_year}"]`).length) {
                        $('#index-year-select').val(storage_year);
                    }
                }

                $(document).on('ajaxStop', function () {
                    if (!skipFadeOut) {
                        $('.loader').fadeOut();
                    }
                });
                  
                $(document).on('mouseover', '[data-bs-toggle="tooltip"]', function (e) {
                    $(e.currentTarget).tooltip({
                        'container': $('.modal').hasClass('show') ? this : 'body',
                        'placement': $('.modal').hasClass('show') ? 'left' : 'top'
                    }).tooltip('show');
                });
                  
                $(document).on('mouseleave', '[data-bs-toggle="tooltip"]', function (e) {
                    $(e.currentTarget).tooltip('hide');
                });
                  
                $(document).on('show.bs.modal', '.modal', function () {
                    $('.tooltip').tooltip('hide');
                });

                $(document).on('click', '#globan .wt-link', function () {
                    let dropdown = $('#globan .globan-dropdown');
                    let isHidden = dropdown.attr('aria-hidden') === 'true';

                    $('#wt-link').attr('aria-expanded', isHidden ? 'true' : 'false');
                
                    dropdown.attr('aria-hidden', isHidden ? 'false' : 'true');
                    dropdown.toggleClass('hidden');
                
                    $('.wt-link span').toggleClass('dark-text');
                });
                
                // All checkbox - thead
                $(document).on('click', '.enisa-table-group #item-select-all', function () {
                    $('.enisa-table-group tbody .form-check-input:not(:disabled):not(.switch)').prop('checked', $(this).is(':checked'));
                });
  
                // All checkbox - thead
                $(document).on('update_select_all', '.enisa-table-group #item-select-all', function () {
                    $(this).prop('checked', (
                        getDataTableCheckboxesEnabledByPage() &&
                        getDataTableCheckboxesCheckedByPage() == getDataTableCheckboxesEnabledByPage())
                    );
                });
  
                // Each checkbox - tbody
                $(document).on('click', '.enisa-table-group tbody .form-check-input:not(.switch)', function () {
                    $('.enisa-table-group #item-select-all').prop('checked', (
                        getDataTableCheckboxesEnabledByPage() &&
                        getDataTableCheckboxesCheckedByPage() == getDataTableCheckboxesEnabledByPage())
                    );
                });
                  
                $(document).on('click', '.btn-top', function () {
                    scrollToTop();
                });
                  
                $(document).on('click', '.btn-bottom', function () {
                    scrollToBottom();
                });

                $(document).on('change', '#index-year-select, #survey-year-select', function () {
                    let selected_year = $('option:selected', this).attr('data-year');
                    
                    localStorage.setItem('index-year', selected_year);
                    document.cookie = `index-year=${selected_year}; path=/; SameSite=Lax; ${secureFlag}`;
                  
                    var changeEvent = new Event('yearChange');
                    window.dispatchEvent(changeEvent);
                });

                // Initialize scroll button behavior
                manageScrollButtons();
            });
        }
    }
})(jQuery, Drupal);

function setIndexYear()
{
    let storage_year = localStorage.getItem('index-year');
    let selected_year = jQuery('#index-year-select').val();
  
    if (!storage_year) {
        localStorage.setItem('index-year', selected_year);
    }

    if (document.cookie.indexOf('index-year=') === -1) {
        document.cookie = `index-year=${selected_year}; path=/; SameSite=Lax; ${secureFlag}`;
    }

    // If the selected year is not in the list, get the first one
    if (!jQuery(`#index-year-select option[value="${storage_year}"]`).length) {
        storage_year = jQuery('#index-year-select').find('option:first').val();
        
        localStorage.setItem('index-year', storage_year);
        document.cookie = `index-year=${storage_year}; path=/; SameSite=Lax; ${secureFlag}`;
    }

    return storage_year;
}

function getIndexCountry()
{
  return localStorage.getItem('index-country');
}

function addDataTableSearchInfoIcon()
{
    jQuery(function($) {
        $('.dataTables_filter').each(function() {
            let label = document.querySelector(`#${this.id} label`);
            label.style.display = 'flex';
            label.style.alignItems = 'center';
            
            if ($(this).find('.info-icon-black').length === 0) {
                $(this).find('input').before('<span class="info-icon-black" data-bs-toggle="tooltip" style="padding: 7px 0 0 0;" title="Use quotes &quot;&quot; to search exact phrases"></span>');
            }
        });
    });
}

// Manage button visibility during scrolling
function manageScrollButtons()
{
  const btnTop = document.querySelector('.btn-top');
  const btnBottom = document.querySelector('.btn-bottom');
  let scrollTimeout;

  window.addEventListener('scroll', () => {
      // Show buttons during scrolling
      if (btnTop) {
          btnTop.classList.remove('d-none');
      }
      if (btnBottom) {
          btnBottom.classList.remove('d-none');
      }

      // Clear the previous timeout
      clearTimeout(scrollTimeout);

      // Set a timeout to detect the end of scrolling
      scrollTimeout = setTimeout(() => {
          if (window.scrollY === 0 && btnTop) {
              btnTop.classList.add('d-none'); // Hide btnTop when at the top
          }
          if (window.scrollY + window.innerHeight >= document.documentElement.scrollHeight && btnBottom) {
              btnBottom.classList.add('d-none'); // Hide btnBottom when at the bottom
          }
      }, 50); // Delay to detect scroll end
  });
}

// Scroll to the bottom of the page
function scrollToBottom()
{
    window.scrollTo({
        top: document.documentElement.scrollHeight, // Scroll to the bottom
        behavior: 'smooth' // Smooth scrolling animation
    });
}
  
  // Scroll to the top of the page
function scrollToTop()
{
    window.scrollTo({
        top: 0, // Scroll to the top
        behavior: 'smooth' // Smooth scrolling animation
    });
}

function getDataTableCheckboxesCheckedByPage() {
    return jQuery('.enisa-table-group').DataTable().rows({ page: 'current' }).nodes().to$().find('.form-check-input:checked:not(:disabled):not(.switch)').length;
}
  
function getDataTableCheckboxesEnabledByPage() {
    return jQuery('.enisa-table-group').DataTable().rows({ page: 'current' }).nodes().to$().find('.form-check-input:not(:disabled):not(.switch)').length;
}

function getDataTableCheckboxesCheckedAllPages()
{
    return jQuery('.enisa-table-group').DataTable().rows().nodes().to$().find('.form-check-input:checked:not(:disabled):not(.switch)').length;
}

function getLocalTimestamp(timestamp, type)
{
    let [year, month, day] = timestamp.split(' ')[0].split('-');
    let [hours, minutes, seconds] = timestamp.split(' ')[1].split(':');

    let formatted_timestamp = `${year}-${month}-${day}T${hours}:${minutes}:${seconds}`;
    
    let utc_date = new Date(formatted_timestamp);
    
    let options = {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    };

    if (type == 'timestamp') {
        options.hour = '2-digit';
        options.minute = '2-digit';
        options.second = '2-digit';
    }

    return utc_date.toLocaleString('en-GB', options)
        .replace(/\//g, '-')
        .replace(/,/g, '');
}

function convertToLocalTimestamp()
{
    jQuery(function($) {
        $('.local-timestamp').each(function() {
            let timestamp = $(this).text();
            
            if (timestamp)
            {
                let type = ($(this).siblings('.local-timestamp-display').hasClass('timestamp')) ? 'timestamp' : 'date';

                $(this).siblings('.local-timestamp-display').text(
                    getLocalTimestamp(timestamp, type)
                );
            }
        });
    });
}
