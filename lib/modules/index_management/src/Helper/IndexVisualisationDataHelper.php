<?php

namespace Drupal\index_management\Helper;

use Drupal\general_management\Helper\ConstantHelper;
use Drupal\user_management\Helper\UserPermissionsHelper;

/**
 * Helper class to prepare the index data for visualisation in comparison mode.
 */
class IndexVisualisationDataHelper {

  /**
   * Function to get the user country based on permissions and index data.
   */
  private static function getUserCountry($entityTypeManager, $currentUser, $published_index_data, $countries_index_data) {
    return (UserPermissionsHelper::isPoC($entityTypeManager, $currentUser)) ? trim(str_replace($published_index_data['year'], '', $countries_index_data[0]['title'])) : '';
  }

  /**
   * Merges two index data arrays into one.
   */
  private static function mergeIndexData($indexA, $indexB) {
    if (empty($indexA)) {
      return ($indexB) ? $indexB['json_data']['contents'] : NULL;
    }
    if (!$indexB) {
      return $indexA;
    }

    $arrayB = $indexB['json_data']['contents'];

    array_push($indexA[0]['global_index_values'], $arrayB[0]['global_index_values'][0]);

    foreach ($indexA as $areaKey => $area) {
      if ($areaKey == 0) {
        continue;
      }

      array_push($indexA[$areaKey]['area']['values'], $arrayB[$areaKey]['area']['values'][0]);

      foreach ($area['area']['subareas'] as $subKey => $subarea) {
        if (isset($arrayB[$areaKey]['area']['subareas'][$subKey]['values'])) {
          array_push($indexA[$areaKey]['area']['subareas'][$subKey]['values'], $arrayB[$areaKey]['area']['subareas'][$subKey]['values'][0]);
        }

        foreach (array_keys($subarea['indicators']) as $indiKey) {
          if (isset($indexB[$areaKey]['area']['subareas'][$subKey]['indicators'][$indiKey]['values'])) {
            array_push($indexA[$areaKey]['area']['subareas'][$subKey]['indicators'][$indiKey]['values'], $arrayB[$areaKey]['area']['subareas'][$subKey]['indicators'][$indiKey]['values'][0]);
          }
        }
      }
    }

    return $indexA;
  }

  /**
   * Function to get the visualisation data for comparison.
   */
  public static function getVisualisationDataForComparison($entityTypeManager, $currentUser, $dateFormatter, $published_index_data, $countries) {
    $country_index_entities = IndexHelper::getCountryIndexEntities($entityTypeManager, $published_index_data['id'], $countries);
    $countries_index_data = IndexHelper::getCountriesIndexData($entityTypeManager, $dateFormatter, $country_index_entities);

    return [
      'eu_average_name' => ConstantHelper::EU_AVERAGE_NAME,
      'countries' => $countries_index_data,
      'published_index_data' => $published_index_data,
      'configuration' => [
        'id' => -1,
        'text' => $published_index_data['title'],
        'year' => $published_index_data['year'],
        'children' => IndexVisualisationTreeDataHelper::prepareIndexForTree($published_index_data['json_data']),
        'checked' => FALSE,
      ],
      'user_country' => self::getUserCountry($entityTypeManager, $currentUser, $published_index_data, $countries_index_data),
      'isAdmin' => UserPermissionsHelper::isAdmin($entityTypeManager, $currentUser),
      'isViewer' => UserPermissionsHelper::isViewer($entityTypeManager, $currentUser),
      'isEnisa' => UserPermissionsHelper::isEnisa($entityTypeManager, $currentUser, $dateFormatter),
    ];
  }

  /**
   * Function to get the node visualisation data for comparison.
   */
  public static function getNodeVisualisationDataForComparison($entityTypeManager, $currentUser, $dateFormatter, $published_index_data, $countries, $node) {
    $map_data = NULL;
    $chart_data = NULL;

    $country_index_entities = IndexHelper::getCountryIndexEntities($entityTypeManager, $published_index_data['id'], $countries);
    $countries_index_data = IndexHelper::getCountriesIndexData($entityTypeManager, $dateFormatter, $country_index_entities);
    $user_country = self::getUserCountry($entityTypeManager, $currentUser, $published_index_data, $countries_index_data);

    foreach ($countries_index_data as $country_index_data) {
      $chart_data = self::mergeIndexData($chart_data, $country_index_data);
    }

    $eu_index_entity = IndexHelper::getEuIndexEntity($entityTypeManager, $published_index_data['id']);

    $eu_index = $entityTypeManager->getStorage('node')->load($eu_index_entity);
    $eu_index_data = IndexHelper::getEuIndexData($dateFormatter, $eu_index);

    $chart_data = self::mergeIndexData($chart_data, $eu_index_data);
    $map_data = IndexVisualisationMapDataHelper::getMapData($entityTypeManager, $chart_data, $eu_index_data['index']['year'], $node, $user_country);

    return [
      'eu_average_name' => ConstantHelper::EU_AVERAGE_NAME,
      'countries' => $countries_index_data,
      'configuration' => [
        'id' => -1,
        'text' => $published_index_data['title'],
        'year' => $published_index_data['year'],
        'eu_published' => $published_index_data['eu_published'],
        'ms_published' => $published_index_data['ms_published'],
        'children' => IndexVisualisationTreeDataHelper::prepareIndexForTree($published_index_data['json_data']),
        'checked' => FALSE,
      ],
      'map_data' => $map_data,
      'chart_data' => $chart_data,
      'isAdmin' => UserPermissionsHelper::isAdmin($entityTypeManager, $currentUser),
      'isEnisa' => UserPermissionsHelper::isEnisa($entityTypeManager, $currentUser, $dateFormatter),
    ];
  }

}
