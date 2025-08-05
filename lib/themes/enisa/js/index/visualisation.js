var pageModal = new bootstrap.Modal(document.getElementById('indicator-modal'));
let mapChart;
let visualisationLoaded = false;
let sunburstLoaded = false;
let is_admin;

(function ($, Drupal) {
  Drupal.behaviors.visualisation = {
    attach: function (context, settings) {
      once('visualisation', 'body').forEach(function () {
        is_admin = settings.visualisation.is_admin;

        loadData();
      });
    }
  }
})(jQuery, Drupal);

function loadData()
{
  jQuery(function($) {
    visualisationLoaded = false;
    sunburstLoaded = false;

    let year = setIndexYear();

    $.ajax({
      'url': '/index/visualisation/data',
      success: function(data) {
        let has_data = (data.indexOf('no-data') < 0);
        let has_reports_visualisations = (data.indexOf('no-reports-visualisations') < 0);
        
        if (!has_data ||
            !has_reports_visualisations)
        {
          $('.visualisation-data').html('');

          let msg = 'No data available. Data collection for ' + year + ' is currently in progress.';
          if (is_admin &&
              has_data &&
              !has_reports_visualisations)
          {
            msg = 'EU/MS Reports/Visualisations for ' + year + ' are unpublished.\
              Browse to <a href="/index/management">Indexes</a> to publish them.';
          }

          setAlert({
            'status': 'warning',
            'msg': msg
          });

          localStorage.removeItem('index-country');
        }
        else
        {
          $('.visualisation-data').html(DOMPurify.sanitize(data));

          let js_files = [
            '/themes/custom/enisa/js/main/html2canvas.min.js',
            '/themes/custom/enisa/js/index/visualisationData.js',
            '/themes/custom/enisa/js/index/sunburst.js'
          ];

          (async () => {
            await loadScripts(js_files);
            
            Drupal.attachBehaviors(document);
          })();
        }
      }
    });
  });
}

function loadScripts(files)
{
  return files.reduce((promise, src) => {
    return promise.then(() => new Promise((resolve, reject) => {
      let script = document.createElement('script');
      
      script.src = src;
      script.defer = true;
      script.onload = () => {
        resolve();
      };
      script.onerror = (e) => {
        reject(e);
      };

      document.head.appendChild(script);
    }));
  }, Promise.resolve());
}

function checkCountry()
{
  jQuery(function($) {
    let countryIndex = getIndexCountry();

    if (countryIndex) {
      setTimeout(() => {
        loadCountry(countryIndex);
      }, 1000);
    }
    else {
      $('.loader').fadeOut();
    }
  });
}

function loadCountry(countryIndex)
{
  jQuery(function($) {
    mapChart.dispatchAction({
      type: 'select',
      dataIndex: countryIndex
    });

    localStorage.removeItem('index-country');

    $('.loader').fadeOut();
  });
}

window.addEventListener('yearChange', function() {
  jQuery(function($) {
    $('.loader').fadeIn();

    location.reload();
  });
});

window.addEventListener('visualisationLoaded', function() {
  jQuery(function($) {
    visualisationLoaded = true;

    if (sunburstLoaded) {
      checkCountry();
    }
  });
});

window.addEventListener('sunburstLoaded', function() {
  jQuery(function($) {
    sunburstLoaded = true;

    if (visualisationLoaded) {
      checkCountry();
    }
  });
});