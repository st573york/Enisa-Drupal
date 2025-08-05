<?php

namespace Drupal\index_management\Helper;

use Drupal\general_management\Helper\ConstantHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorHelper;

/**
 * Helper class to prepare the index data for sunburst visualisation.
 */
class IndexVisualisationSunburstDataHelper {
  const ALMOST_WHITE = '#adb5bd';

  /**
   * Converts a value to a color based on predefined ranges.
   */
  private static function value2color($value) {
    switch ($value) {
      case ($value == '#NUM!'):
        return self::ALMOST_WHITE;

      case ($value < 20):
        return '#f5f7fd';

      case ($value < 40):
        return '#d6dff6';

      case ($value < 60):
        return '#8ea7e6';

      case ($value < 80):
        return '#3a65d3';

      case ($value <= 100):
        return '#254AA5';

      default:
        return self::ALMOST_WHITE;
    }
  }

  /**
   * Generates a linear gradient color index from rgb.
   */
  private static function lineargradient($ra, $ga, $ba, $rz, $gz, $bz, $iterationnr) {
    $colorindex = [];

    for ($iterationc = 1; $iterationc <= $iterationnr; $iterationc++) {
      $iterationdiff = $iterationnr - $iterationc;
      array_push($colorindex, [
        '#' .
        dechex(intval((($ra * $iterationc) + ($rz * $iterationdiff)) / $iterationnr)) .
        dechex(intval((($ga * $iterationc) + ($gz * $iterationdiff)) / $iterationnr)) .
        dechex(intval((($ba * $iterationc) + ($bz * $iterationdiff)) / $iterationnr)),
      ]);
    }

    return $colorindex;
  }

  /**
   * Function to get the indicator algorithms for the sunburst visualisation.
   */
  private static function getIndicatorAlgorithms($entityTypeManager, $database) {
    $indicators = [];

    $entities = IndicatorHelper::getIndicatorEntities($entityTypeManager);
    $indicators_data = IndicatorHelper::getIndicatorsData($entityTypeManager, $database, $entities);

    foreach ($indicators_data as $indicator_data) {
      $indicators[$indicator_data['name']] = $indicator_data['algorithm'];
    }

    return $indicators;
  }

  /**
   * Function to get the sunburst data for a given index.
   */
  private static function getSunburstData($index_data, $eu_index = FALSE) {
    $colorindex = self::lineargradient(
          37,
          74,
    // Rgb of the start color.
          165,

          245,
          247,
    // Rgb of the end color.
          253,

    // Number of colors in your linear gradient.
          101
      );

    $index_country_name = ($eu_index ? ConstantHelper::EU_AVERAGE_NAME : $index_data['country']['name']) . ' ' . $index_data['index']['year'];
    $sunburst_data = [
      'name' => $index_country_name,
      'fullName' => $index_country_name,
      'val' => 0,
      'value' => 0,
      'children' => [],
    ];

    foreach ($index_data['json_data']['contents'] as $key => $row) {
      if ($key == 0) {
        $sunburst_data['value'] = 1;
        $sunburst_data['val'] = array_values($row['global_index_values'][0])[0];
        $sunburst_data['itemStyle'] = [
          'color' => $colorindex[round($sunburst_data['val'])],
        ];
      }
      else {
        $value = '#NUM!';
        if (isset($row['area']['values'])) {
          $value = array_values($row['area']['values'][0])[0];
        }
        $area_data = [
          'name' => $row['area']['name'],
          'fullName' => $row['area']['name'],
          'val' => ($value == '#NUM!') ? 'Not available' : $value,
          'value' => $row['area']['normalized_weight'],
          'weight' => round($row['area']['weight'], 2),
          'itemStyle' => [
            'color' => self::value2color($value),
          ],
          'children' => [],
        ];

        foreach ($row['area']['subareas'] as $subarea) {
          $value = '#NUM!';
          if (isset($subarea['values'])) {
            $value = array_values($subarea['values'][0])[0];
          }
          $subarea_data = [
            'name' => $subarea['short_name'],
            'fullName' => $subarea['name'],
            'val' => ($value == '#NUM!') ? 'Not available' : $value,
            'value' => $subarea['normalized_weight'],
            'weight' => round($subarea['weight'], 2),
            'itemStyle' => [
              'color' => self::value2color($value),
            ],
            'children' => [],
          ];

          foreach ($subarea['indicators'] as $indicator) {
            $value = '#NUM!';
            if (isset($indicator['values'])) {
              $value = array_values($indicator['values'][0])[0];
            }
            $indicator_data = [
              'name' => $indicator['short_name'],
              'fullName' => $indicator['name'],
              'val' => ($value == '#NUM!') ? 'Not available' : $value,
            // Temporary solution till actual weights are fixed.
              'value' => $subarea['normalized_weight'] / count($subarea['indicators']),
              'algorithm' => '',
              'itemStyle' => [
                'color' => self::value2color($value),
              ],
              'weight' => round($indicator['weight'], 2),
              'nodeClick' => 'false',

            ];
            array_push($subarea_data['children'], $indicator_data);
          }
          array_push($area_data['children'], $subarea_data);
        }
        array_push($sunburst_data['children'], $area_data);
      }
    }

    return $sunburst_data;
  }

  /**
   * Function to get the sunburst visualisation data for comparison.
   */
  public static function getSunburstVisualisationDataForComparison($entityTypeManager, $database, $dateFormatter, $published_index_data, $countries) {
    $sunburst_data = NULL;

    $country_index_entities = IndexHelper::getCountryIndexEntities($entityTypeManager, $published_index_data['id'], $countries);
    $countries_index_data = IndexHelper::getCountriesIndexData($entityTypeManager, $dateFormatter, $country_index_entities);

    foreach ($countries_index_data as $country_index_data) {
      $sunburst_data[$country_index_data['country']['name'] . ' ' . $country_index_data['index']['year']] = self::getSunburstData($country_index_data);
    }

    $eu_index_entity = IndexHelper::getEUIndexEntity($entityTypeManager, $published_index_data['id']);

    $eu_index = $entityTypeManager->getStorage('node')->load($eu_index_entity);
    $eu_index_data = IndexHelper::getEUIndexData($dateFormatter, $eu_index);

    $sunburst_data[ConstantHelper::EU_AVERAGE_NAME . ' ' . $eu_index_data['index']['year']] = self::getSunburstData($eu_index_data, TRUE);
    $sunburst_data['algorithms'] = self::getIndicatorAlgorithms($entityTypeManager, $database);

    return $sunburst_data;
  }

}
