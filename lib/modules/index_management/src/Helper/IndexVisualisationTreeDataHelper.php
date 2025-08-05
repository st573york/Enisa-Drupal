<?php

namespace Drupal\index_management\Helper;

/**
 * Helper class to prepare the index data for the tree visualisation.
 */
class IndexVisualisationTreeDataHelper {
  const SELECT_ALL = 'Select All';

  /**
   * Function to prepare the index data for the tree visualisation.
   */
  public static function prepareIndexForTree($json_data) {
    $array = [];

    if (!empty($json_data)) {
      $array = self::addAreasNodesToTree($array, $json_data);
    }

    $json = str_replace('name', 'text', json_encode(array_values($array)));
    $json = str_replace('subareas', 'children', $json);
    $json = str_replace('indicators', 'children', $json);

    return json_decode($json);
  }

  /**
   * Function to add areas nodes to the tree structure.
   */
  private static function addAreasNodesToTree($array, $json_data) {
    foreach ($json_data['contents'] as $area_key => $area_data) {
      $array[0]['id'] = 0;
      $array[0]['text'] = self::SELECT_ALL;
      if (!isset($area_data['area'])) {
        continue;
      }
      $array[$area_key + 1]['id'] = $area_key + 1;
      $array[$area_key + 1]['text'] = $area_data['area']['name'];
      $array[$area_key + 1]['idx'] = $area_data['area']['id'];
      $array[$area_key + 1]['checked'] = TRUE;
      $array[$area_key + 1]['subareas'][0]['id'] = $area_key + 1 . '-' . 0;
      $array[$area_key + 1]['subareas'][0]['name'] = self::SELECT_ALL;
      $array[$area_key + 1]['subareas'][0]['checked'] = FALSE;
      if (!isset($area_data['area']['subareas'])) {
        continue;
      }
      $array = self::addSubareaNodesToTree($array, $area_key, $area_data);
    }

    return $array;
  }

  /**
   * Function to add subareas nodes to the tree structure.
   */
  private static function addSubareaNodesToTree($array, $area_key, $area_data) {
    foreach ($area_data['area']['subareas'] as $subarea_key => $subarea_data) {
      if (!isset($subarea_data['name'])) {
        continue;
      }
      $array[$area_key + 1]['subareas'][$subarea_key + 1]['id'] = $area_key + 1 . '-' . $subarea_key + 1;
      $array[$area_key + 1]['subareas'][$subarea_key + 1]['idx'] = $subarea_data['id'];
      $array[$area_key + 1]['subareas'][$subarea_key + 1]['text'] = $subarea_data['name'];
      $array[$area_key + 1]['subareas'][$subarea_key + 1]['indicators'][0]['id'] = $area_key + 1 . '-' . $subarea_key + 1 . '-' . 0;
      $array[$area_key + 1]['subareas'][$subarea_key + 1]['indicators'][0]['text'] = self::SELECT_ALL;
      if (!isset($subarea_data['indicators'])) {
        continue;
      }
      $array = self::addIndicatorNodesToTree($array, $area_key, $subarea_key, $subarea_data);
    }

    return $array;
  }

  /**
   * Function to add indicators nodes to the tree structure.
   */
  private static function addIndicatorNodesToTree($array, $area_key, $subarea_key, $subarea_data) {
    foreach ($subarea_data['indicators'] as $indicator_key => $indicator_data) {
      if (!isset($indicator_data['name'])) {
        continue;
      }
      $array[$area_key + 1]['subareas'][$subarea_key + 1]['indicators'][$indicator_key + 1]['id'] = $area_key + 1 . '-' . $subarea_key + 1 . '-' . $indicator_key + 1;
      $array[$area_key + 1]['subareas'][$subarea_key + 1]['indicators'][$indicator_key + 1]['text'] = $indicator_data['name'];
      $array[$area_key + 1]['subareas'][$subarea_key + 1]['indicators'][$indicator_key + 1]['idx'] = $indicator_data['id'];
    }

    return $array;
  }

}
