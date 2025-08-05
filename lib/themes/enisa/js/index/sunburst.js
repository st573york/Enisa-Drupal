let sunburst_data;

(function ($, Drupal) {
  Drupal.behaviors.sunburst = {
    attach: function (context, settings) {
      once('sunburst', 'body').forEach(function () {
        $.ajax({
          'url': '/index/visualisation/sunburst/data'
        }).done(function (response) {
          sunburst_data = response.data;
          
          drawSunburst();

          let loadEvent = new Event('sunburstLoaded');
          window.dispatchEvent(loadEvent);
        });
      });
    }
  };
})(jQuery, Drupal);

function drawSunburst()
{
  jQuery(function($) {
    let country = $('#sunburst-form-country-0').val();
    let data = sunburst_data[country];

    if (!data) {
      return;
    }

    let chart_dom = document.getElementById('sunburst');
    let sun_chart = echarts.init(chart_dom);

    let option = {
      visualMap: {
        type: 'piecewise',
        top: 'top',
        left: 'left',
        dimension: 1,
        seriesIndex: 1,
        splitNumber: 5,
        pieces: [
          { min: 0, max: 19.999 },
          { min: 20.0, max: 39.999 },
          { min: 40.0, max: 59.999 },
          { min: 60.0, max: 79.999 },
          { min: 80.0, max: 100 }
        ],
        inRange: {
          color: [
            '#f5f7fd',
            '#d6dff6',
            '#8ea7e6',
            '#3a65d3',
            '#254AA5'
          ]
        },
        borderColor: '#141414',
        borderWidth: 0.5,
        borderRadius: 2,
        backgroundColor: '#ffffff'
      },
      toolbox: {
        show: true,
        right: '20%',
        top: 'top',
        feature: {
          saveAsImage: {
            name: 'Sunburst',
            emphasis: {
              iconStyle: {
                color: '#141414',
                textAlign: 'right'
              }
            },
            icon: "image://data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none'%3E%3Cpath d='M20.75 20.75C20.75 20.0625 20.1875 19.5 19.5 19.5L4.5 19.5C3.8125 19.5 3.25 20.0625 3.25 20.75C3.25 21.4375 3.8125 22 4.5 22L19.5 22C20.1875 22 20.75 21.4375 20.75 20.75ZM17.7375 9.5L15.75 9.5L15.75 3.25C15.75 2.5625 15.1875 2 14.5 2L9.5 2C8.8125 2 8.25 2.5625 8.25 3.25L8.25 9.5L6.2625 9.5C5.15 9.5 4.5875 10.85 5.375 11.6375L11.1125 17.375C11.2281 17.4909 11.3655 17.5828 11.5167 17.6455C11.6679 17.7083 11.83 17.7406 11.9938 17.7406C12.1575 17.7406 12.3196 17.7083 12.4708 17.6455C12.622 17.5828 12.7594 17.4909 12.875 17.375L18.6125 11.6375C19.4 10.85 18.85 9.5 17.7375 9.5V9.5Z' fill='black'/%3E%3C/svg%3E"
          }
        }
      },
      tooltip: {
        formatter: function (params) {
          let tooltip = '';

          if (params.data.fullName) {
            tooltip = `<b>Name:</b> ${params.data.fullName}<br><b>Value:</b> ${params.data.val}<br>`;
          }

          return tooltip;
        },
        axisPointer: {
          type: 'shadow'
        }
      },
      series: {
        type: 'sunburst',
        emphasis: {
          focus: 'descendant'
        },
        data: [data],
        levels: [
          {},
          {
            'r0': '0%',
            'r': '15%',
            'itemStyle': {
              'borderWidth': 1
            },
            'label': {
              'rotate': 'tangential',
              'align': 'center',
            }
          },
          {
            'r0': '15%',
            'r': '30%',
            'label': {
              'align': 'center',
              'rotate': 'tangential'
            }
          },
          {
            'r0': '30%',
            'r': '57%',
            'label': {
              'position': 'inside',
              'padding': 0,
              'silent': false
            },
            'itemStyle': {
              'borderWidth': 2
            }
          },
          {
            'r0': '57%',
            'r': '60%',
            'label': {
              'position': 'outside',
              'padding': 0,
              'silent': false
            },
            'itemStyle': {
              'borderWidth': 2
            }
          }
        ],
        radius: [0, '60%']
      }
    };
  
    option && sun_chart.setOption(option, true);
    sun_chart.on('click', function (params)
    {
      if (typeof params.treePathInfo.length !== 'undefined' && params.treePathInfo.length == 5)
      {
        if (typeof sunburst_data['algorithms'][params.data.fullName] !== 'undefined')
        {
          $('#indicator-modal .modal-title').text(params.data.fullName);
          $('#algorithm-container').html(DOMPurify.sanitize(sunburst_data['algorithms'][params.data.fullName]));

          pageModal.show();
        }
      }
    });
  });
}