let default_nodes;
let selected_nodes;
let selected_node = 'Index';
let chart_data;
let map_data;
let eu_average_name;
let year;
let eu_published;
let ms_published;
let isAdmin;
let isEnisa;
let range_plot_html = '';
let current_tab = 'map-tab';
let cyprus_idx;
let northern_cyprus_idx;
let plotly_colors = [
  '#2f4554',
  '#61a0a8',
  '#d48265',
  '#91c7ae',
  '#749f83',
  '#ca8622',
  '#bda29a',
  '#6e7074',
  '#546570',
  '#c4ccd3'
];

(function ($, Drupal) {
  Drupal.behaviors.visualisationData = {
    attach: function (context, settings) {
      once('visualisationData', 'body').forEach(function () {
        default_nodes = getNodes();
        
        $('#form-country-0').select2();
        $('#sunburst-form-country-0').select2();
        $('#areas-subareas').select2({
          templateResult: formatItem
        });

        $(document).on('change', '#areas-subareas', function () {
          $('.loader').fadeIn();

          selected_node = $('#areas-subareas :selected').text();
              
          getMapData();
        });

        $(document).on('change', '#sunburst-form-country-0', function () {
          drawSunburst();
        });

        $(document).on('click', '#tab-select #map-tab', function () {
          current_tab = $(this).attr('id');

          $('#map-wrapper, #map, #all-forms-wrap, .indicators .btn-wrapper').removeClass('hidden');
          $('#sunburst, .label-section, #sunburst-form-wrap, #chart, #sliderChart, #downloadTreeImage, .indicators, .sunburst-label').addClass('hidden');
          $('.indicators').removeClass('sunburst-operate tree-operate');
          $('.width-expand').addClass('w-100');
        });

        $(document).on('click', '#tab-select #sunburst-tab', function () {
          current_tab = $(this).attr('id');

          $('#sunburst, .label-section, #sunburst-form-wrap, .indicators, .sunburst-label').removeClass('hidden');
          $('#all-forms-wrap, #map-wrapper, #map, #chart, #sliderChart, #downloadTreeImage, #indicators-tree, .indicators .btn-wrapper').addClass('hidden');
          $('.indicators').addClass('sunburst-operate');
          $('.width-expand').removeClass('w-100');
          $('.indicators').removeClass('tree-operate');

          $('#revert').trigger('click');

          if (!ms_published) {
            $('.indicators').addClass('hidden');
          }
        });

        $(document).on('click', '#tab-select #tree-tab', function () {
          current_tab = $(this).attr('id');
              
          $('#sliderChart, #downloadTreeImage, #all-forms-wrap, .indicators, .indicators .btn-wrapper').removeClass('hidden');
          $('#map-wrapper, #map, #sunburst, .label-section, #sunburst-form-wrap, #chart, #indicators-tree, .sunburst-label').addClass('hidden');
          $('.indicators').removeClass('sunburst-operate');
          $('.indicators').addClass('tree-operate');
          $('.width-expand').removeClass('w-100');

          $('#revert').trigger('click');

          if (!ms_published) {
            $('.indicators').addClass('hidden');
          }
        });

        $(document).on('click', '#tab-select #barchart-tab', function () {
          current_tab = $(this).attr('id');
              
          setTimeout(() => {
            updateCompareButton();
            buildBarPlot();
          }, 50);

          $('#chart, #all-forms-wrap, .indicators, #indicators-tree, .indicators .btn-wrapper').removeClass('hidden');
          $('#map-wrapper, #map, #sunburst, #sunburst-form-wrap, #sliderChart, #downloadTreeImage, .sunburst-label').addClass('hidden');
          $('.indicators').removeClass('sunburst-operate tree-operate');
          $('.width-expand').removeClass('w-100');

          $('#revert').trigger('click');

          if (!ms_published)
          {
            $('#all-forms-wrap').addClass('hidden');
            $('#indicators-tree').removeClass('mt-5');
            $('.index-wrapper').css('max-height', 'none');
          }
        });

        $(document).on('click', '#indicators-tree .tree-arrow, #sliderChart .tree-arrow', function () {
          let arrow = $(this);

          arrow.parent().children('ul').toggleClass('collapsed');
          if (arrow.parent().children('ul').hasClass('collapsed')) {
            $(this).removeClass('open');
          }
          else {
            $(this).addClass('open');
          }
        });

        $(document).on('change', '#indicators-tree input', function () {
          updateCompareButton();
        });

        $(document).on('click', '#compare', function () {
          if (current_tab == 'barchart-tab') {
            buildBarPlot();
          }
          if (current_tab == 'tree-tab') {
            buildRangePlot();
          }
        });

        $(document).on('click', '#revert', function () {
          // Select first child of select2 - Sunburst Tab
          $('#sunburst-form-country-0')
            .val($('#sunburst-form-country-0 option:first-child').val())
            .select2()
            .trigger('change');

          // Select first child of select2 - Tree/Barchart Tabs
          $('#form-country-0')
            .val($('#form-country-0 option:first-child').val())
            .select2()
            .trigger('change');

          // Close open tree arrows - Barchart Tab
          $('#indicators-tree .tree-arrow.open').trigger('click');

          // Check only default checkboxes - Barchart Tab
          $('.checkbox-input').prop('checked', false);
          $('.checkbox-input.default').prop('checked', true);

          // Click view button - Tree/Barchart Tabs
          $('#compare').removeClass('disable-btn').trigger('click');
        });

        $(document).on('change', '.all', function () {
          let selectAll = $(this);
              
          if (selectAll.prop('checked') == true) {
            selectAll.parent().siblings().children('input').prop('checked', true);
          }
          else {
            selectAll.parent().siblings().children('input').prop('checked', false);
          }

          updateCompareButton();
        });
  
        $.ajax({
          'url': `/index/visualisation/node/data/${$('#areas-subareas').val()}`
        }).done(function (response) {
          map_data = response.data.map_data;
          chart_data = response.data.chart_data;
          eu_average_name = response.data.eu_average_name;
          year = response.data.configuration.year;
          eu_published = response.data.configuration.eu_published;
          ms_published = response.data.configuration.ms_published;
          isAdmin = response.data.isAdmin;
          isEnisa = response.data.isEnisa;
  
          if (map_data)
          {
            setCountriesIdx();
            drawMap();
          }
          if (chart_data)
          {
            buildBarPlot();
            buildRangePlot();
          }

          if (!ms_published) {
            $('#form-country-0').empty().trigger('change');
          }

          let loadEvent = new Event('visualisationLoaded');
          window.dispatchEvent(loadEvent);
        });
      });
    }
  };
})(jQuery, Drupal);

function formatItem(state)
{
  if (!state.id) { return state.text; }

  return jQuery(`<span class="${state.element.className}">${state.text}</span>`);
}

function updateCompareButton()
{
  jQuery(function($) {
    $('#compare').toggleClass('disable-btn', !$('#indicators-tree input').is(':checked'));
  });
}

function getNodes()
{
  nodes = [];
    
  jQuery(".checkbox-input:not('.all'):checked").each(function (i, input) {
    nodes.push(jQuery(input).prop('id'));
  });

  return nodes;
}

function getMapData()
{
  jQuery(function($) {
    $.ajax({
    'url': `/index/visualisation/node/data/${$('#areas-subareas').val()}`
    }).done(function (response) {
      map_data = response.data.map_data;
      
      if (map_data) 
      {
        setCountriesIdx();
        drawMap();
      }

      $('.loader').fadeOut();
    });
  });
}

function buildBarPlot()
{
  jQuery(function($) {
    selected_nodes = getNodes();    
    let series = [];
    let indices = [];
    let yAxis = {
      type: 'category',
      data: null
    };
    let indexName;
    let selected = $('#first-select select').val();
        
    $.each(selected, function (idx, selection) {
      indexName = selection;
      indices.push(indexName);
    });
    indices.push(eu_average_name + ' ' + year);
    
    indices.forEach(function (index) {
      let indexLocation = 0;
      y = [];
      x = [];
      let name;

      selected_nodes.forEach(function (node) {
        let map = node.split('-');
        let value;
        let valueX;
        let valueY;
        while (chart_data[0]['global_index_values'][indexLocation] && 
          Object.keys(chart_data[0]['global_index_values'][indexLocation])[0] !== index)
        {
          indexLocation++;
        }
        if (node == -1) {
          valueY = 'Aggregated Index';
          value = chart_data[0]['global_index_values'][indexLocation];
        }
        else
        {
          if (map.length == 1)
          {
            valueY = chart_data[map[0]]['area']['name'];
            value = chart_data[map[0]]['area']['values'][indexLocation];
          }
          if (map.length == 2)
          {
            valueY = chart_data[map[0]]['area']['subareas'][map[1] - 1]['name'];
            value = chart_data[map[0]]['area']['subareas'][map[1] - 1]['values'][indexLocation];
          }
          if (map.length == 3)
          {
            valueY = chart_data[map[0]]['area']['subareas'][map[1] - 1]['indicators'][map[2] - 1]['name'];
            value = chart_data[map[0]]['area']['subareas'][map[1] - 1]['indicators'][map[2] - 1]['values'][indexLocation];
          }
        }
        
        name = (value) ? Object.keys(value)[0] : null;
        valueX = (value) ? Object.values(value)[0] : null;
        y.unshift(valueY);
        x.unshift(valueX);
      });
      
      if (name)
      {
        let serie = {
          name: name,
          type: 'bar',
          data: x,
          itemStyle: {
            color: name.includes(eu_average_name) ? 'rgb(37,74,165)' : 'auto'
          }
        };

        series.push(serie);
      }
    });
    yAxis.data = y;
    
    let chart_dom = document.getElementById('chart');
    let bar_chart = echarts.init(chart_dom);
    
    let option = {
      toolbox: {
        show: true,
        left: 'right',
        top: 'top',
        feature: {
          saveAsImage: {
            name: 'Barchart',
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
      color: [
        '#9d9c9c',
        '#61a0a8',
        '#d48265',
        '#91c7ae',
        '#749f83',
        '#ca8622',
        '#bda29a',
        '#6e7074',
        '#546570',
        '#c4ccd3'
      ],
      tooltip: {
        trigger: 'axis',
        axisPointer: {
          type: 'shadow'
        }
      },
      legend: {},
      grid: {
        left: '3%',
        right: '4%',
        bottom: '3%',
        containLabel: true
      },
      xAxis: {
        type: 'value',
        boundaryGap: [0, 0.01]
      },
      yAxis: yAxis,
      series: series
    };

    option && bar_chart.setOption(option, true);
    bar_chart.resize();
  });
}

function buildRangePlot()
{
  jQuery(function($) {
    if (range_plot_html == '')
    {
      $.ajax({
        'url': '/index/visualisation/sliderchart/data'
      }).done(function (html) {
        range_plot_html = html;

        $('#sliderChart').html(DOMPurify.sanitize(range_plot_html));

        fixRangePlot();
      });
    }
    else
    {
      $('#sliderChart').html(DOMPurify.sanitize(range_plot_html));

      fixRangePlot();
    }
  });
}

function fixRangePlot()
{
  jQuery(function($) {
    let indices = [];
    let indexName;
    let selected = $('#first-select select').val();

    $.each(selected, function (idx, selection) {
      indexName = selection;
      indices.push(indexName);
    });
    indices.push(eu_average_name + ' ' + year);

    indices.forEach(function (index) {
      let indexLocation = 0;
      let indexName = index.replaceAll(' ', '.');
      $('.' + indexName).removeClass('hidden');

      default_nodes.forEach(function (node) {
        let map = node.split('-');
        while (chart_data[0]['global_index_values'][indexLocation] && 
          Object.keys(chart_data[0]['global_index_values'][indexLocation])[0] !== index)
        {
          indexLocation++;
        }
        if (node == -1) {
          $('.global-values .values').removeClass('hidden');
        }
        else
        {
          if (map.length == 1)
            {
            $(`.area-${map[0]}`).removeClass('hidden');
            $(`.area-${map[0]} .values`).removeClass('hidden');
          }
          if (map.length == 2)
          {
            $(`.area-${map[0]}`).removeClass('hidden');
            $(`.area-${map[0]} .subarea-${map[1]}`).removeClass('hidden');
            $(`.area-${map[0]} .subarea-${map[1]} .values`).removeClass('hidden');
          }
          if (map.length == 3)
          {
            $(`.area-${map[0]}`).removeClass('hidden');
            $(`.area-${map[0]} .subarea-${map[1]}`).removeClass('hidden');
            $(`.area-${map[0]} .subarea-${map[1]} .indicator-${map[2]}`).removeClass('hidden');
            $(`.area-${map[0]} .subarea-${map[1]} .indicator-${map[2]} .values`).removeClass('hidden');
          }
        }
      });
    });

    let key_value_list = document.querySelectorAll('#sliderChart .plotly-color:not(.hidden) .key-value');
    let tree_value_list = document.querySelectorAll('#sliderChart .plotly-color:not(.hidden) .tree-value');

    current_plotly_colors = [];

    for (let j = 0; j < indices.length; j++) {
      current_plotly_colors[j] = plotly_colors[j];
    }

    plotly_palette(current_plotly_colors, key_value_list);
    plotly_palette(current_plotly_colors, tree_value_list);
  });
}

function plotly_palette(colors, list)
{
  let newlist = Array.prototype.slice.call(list);

  colors[colors.length - 1] = '#254AA5';
  colors_length_list = [];
  for (let i = 0; i < newlist.length; i++)
  {
    colors_length_list[i] = colors[i % colors.length];
    newlist[i].style.color = colors_length_list[i];
  }
}

function setCountriesIdx()
{
  jQuery(function($) {
    $.each(map_data, function (idx, i) {
      switch (i.name)
      {
        case 'Cyprus':
          cyprus_idx = idx;

          break;
        case 'N. Cyprus':
          northern_cyprus_idx = idx;

          break;
      }
    });
  });
}

function drawMap()
{
  jQuery(function($) {
    let tr = $('<tr>');
    tr.append($('<td>').text(selected_node));
    tr.append($('<td>').text(map_data[0].value));
    if (!isAdmin && !isEnisa && ms_published) {
      tr.append($('<td>').text(map_data[0].country_value));
    }
    $('#baseline-table tbody').empty().append(tr);
    
    $.each(map_data[0].children, function (idx, i) {
      let tr = $('<tr>');
      tr.append($(`<td ${($('#areas-subareas').val() == 'Index' ? '' : ' class="ps-3"')}>`).text(i.name));
      tr.append($('<td>').text(i.value));
      if (!isAdmin && !isEnisa && ms_published) {
        tr.append($('<td>').text(i.country_value));
      }
      $('#baseline-table tbody').append(tr);
    });
    
    let chart_dom = document.getElementById('map');
    map_chart = echarts.init(chart_dom);
    map_chart.showLoading();

    $.get('/sites/default/files/EUN.json', function (world_json) {
      map_chart.hideLoading();
      echarts.registerMap('EUN', world_json, {});
      
      let option = {
        tooltip: {
          trigger: 'item',
          showDelay: 0,
          transitionDuration: 0.2,
          formatter: function (params) {
            if (params.data != undefined && params.data.children.length)
            {
              let tooltip = '';
              tooltip = '<font size="+1"><b>' + (params.name == 'N. Cyprus' ? 'Cyprus' : params.name) + '</b></font>' + '<br />';
              tooltip += '<b>' + ($('#areas-subareas').val() == 'Index' ? 'Aggregated Index' : selected_node.trim()) + ':</b> ' + params.data.value + '<br />';
              tooltip += '<div style="margin: 7px 0px 7px 0px; border-top: 1px dashed;"></div>';

              $.each(params.data.children, function (idx, i) {
                tooltip += '<b>' + i['name'] + ':</b> ' + i['value'] + '<br />';
              });
              return tooltip;
            }
            return '';
          }
        },
        visualMap: {
          type: 'piecewise',
          show: (ms_published) ? true : false,
          top: 'top',
          left: 'left',
          splitNumber: 5,
          pieces: [
            { min: 0, max: 19.999 },
            { min: 20.0, max: 39.999 },
            { min: 40.0, max: 59.999 },
            { min: 60.0, max: 79.999 },
            { min: 80.0, max: 100 }
          ],
          inRange: {
            color: (ms_published) ? [
              '#f5f7fd',
              '#d6dff6',
              '#8ea7e6',
              '#3a65d3',
              '#254AA5'
            ] : []
          },
          borderColor: '#141414',
          borderWidth: 0.5,
          borderRadius: 2,
          backgroundColor: '#ffffff'
        },
        toolbox: {
          show: (ms_published) ? true : false,
          left: 'right',
          top: 'top',
          feature: {
            saveAsImage: {
              name: 'Map',
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
        series: [
          {
            name: 'Cybersecurity Index',
            zoom: 6.9,
            center: [2.97, 52.71],
            type: 'map',
            roam: false,
            map: 'EUN',
            silent: (ms_published) ? false : true,
            label: {
              show: true,
              borderColor: '#141414',
              borderWidth: 0.5,
              borderRadius: 2,
              fontWeight: 350,
              fontSize: 10,
              backgroundColor: '#ffffff',
              padding: 2,
              formatter: function (data) {
                if (data.value) {
                  return data.name;
                }
                return '';
              }
            },
            select: {
              disabled: true
            },
            emphasis: {
              label: {
                show: true,
                formatter: function (data) {
                  if (data.value) {
                    return data.name;
                  }
                  return '';
                }
              },
              itemStyle: {
                areaColor: '#141414'
              }
            },
            data: map_data
          }
        ]
      };
      if (!isAdmin && !isEnisa) {
        option.visualMap.show = false;
      }
      option && map_chart.setOption(option, true);
    });

    map_chart.on('selectchanged', function (params) {
      if (params.fromAction == 'select')
      {
        let selectedId = params.selected[0].dataIndex[0];

        // State if legend is selected.     
        if (map_data[selectedId] && map_data[selectedId].children.length)
        {
          let country = (map_data[selectedId].name == 'N. Cyprus') ? map_data[cyprus_idx].selection_name : map_data[selectedId].selection_name;

          $('#tab-select #sunburst-tab').trigger('click');
          $('#sunburst-form-country-0').val(country);

          drawSunburst();

          $('#sunburst-form-country-0').select2('destroy');
          $('#sunburst-form-country-0').select2();
        }
      }
    });

    map_chart.on('mouseover', function (params) {
      if (params.name == 'Cyprus') {
        map_chart.dispatchAction({
          type: 'highlight',
          dataIndex: northern_cyprus_idx
        });
      }
      if (params.name == 'N. Cyprus') {
        map_chart.dispatchAction({
          type: 'highlight',
          dataIndex: cyprus_idx
        });
      }
    });

    map_chart.on('mouseout', function (params) {
      if (params.name == 'Cyprus') {
        map_chart.dispatchAction({
          type: 'downplay',
          dataIndex: northern_cyprus_idx
        });
      }
      if (params.name == 'N. Cyprus') {
        map_chart.dispatchAction({
          type: 'downplay',
          dataIndex: cyprus_idx
        });
      }
    });
  });
}

function downloadTreeImage(url)
{
  jQuery(function($) {
    let a = $("<a style='display:none'></a>")
      .attr('href', url)
      .attr('download', 'Tree.png')
      .appendTo('body');

    a[0].click();
    a.remove();
  });
}

window.saveCapture = function () {
  html2canvas($('#sliderChart')[0], { backgroundColor: '#ffffff' }).then(function (canvas) {
    downloadTreeImage(canvas.toDataURL('image/png'));
  });
}