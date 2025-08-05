<?php

namespace Drupal\index_management\Helper;

use Drupal\general_management\Helper\ConstantHelper;
use Drupal\general_management\Helper\GeneralHelper;

/**
 * Helper class for Index Visualisation Map Data.
 */
class IndexVisualisationMapDataHelper {
  const NON_EU_COUNTRIES = [
    'ALA' => 'Aland',
    'ALB' => 'Albania',
    'DZA' => 'Algeria',
    'AND' => 'Andorra',
    'BLR' => 'Belarus',
    'BIH' => 'Bosnia and Herz.',
    'TRNC' => 'N. Cyprus',
    'EGY' => 'Egypt',
    'FRO' => 'Faeroe Is.',
    'GRL' => 'Greenland',
    'ISL' => 'Iceland',
    'IMN' => 'Isle of Man',
    'ISR' => 'Israel',
    'JEY' => 'Jersey',
    'JOR' => 'Jordan',
    'LBN' => 'Lebanon',
    'LBY' => 'Libya',
    'LIE' => 'Liechtenstein',
    'MDA' => 'Moldova',
    'MNE' => 'Montenegro',
    'MAR' => 'Morocco',
    'MKD' => 'North Macedonia',
    'NOR' => 'Norway',
    'PSE' => 'Palestine',
    'RUS' => 'Russia',
    'SRB' => 'Serbia',
    'CHE' => 'Switzerland',
    'TUN' => 'Tunisia',
    'TUR' => 'Turkey',
    'UKR' => 'Ukraine',
    'GBR' => 'United Kingdom',
  ];

  /**
   * Function to get the map data for the index visualisation.
   */
  private static function getIndexMapData($chart_data, $year, $country, $user_country, &$value, &$country_value) {
    foreach ($chart_data[0]['global_index_values'] as $map_value) {
      $country_name = trim(str_replace($year, '', array_key_first($map_value)));
      if ($country_name == $country) {
        $value = array_values($map_value)[0];
        if (!$user_country) {
          break;
        }

        if ($country_name == $user_country) {
          $country_value = $value;

          break;
        }
      }
    }
  }

  /**
   * Function to get the map data for the area or subarea.
   */
  private static function getAreaMapData($area, $year, $country, $user_country, &$value, &$country_value) {
    foreach ($area['area']['values'] as $map_value) {
      $country_name = trim(str_replace($year, '', array_key_first($map_value)));
      if ($country_name == $country) {
        $value = array_values($map_value)[0];
        if (!$user_country) {
          break;
        }

        if ($country_name == $user_country) {
          $country_value = $value;

          break;
        }
      }
    }
  }

  /**
   * Function to get the map data for the subarea.
   */
  private static function getSubareaMapData($subarea, $year, $country, $user_country, &$value, &$country_value) {
    foreach ($subarea['values'] as $map_value) {
      $country_name = trim(str_replace($year, '', array_key_first($map_value)));
      if ($country_name == $country) {
        $value = array_values($map_value)[0];
        if (!$user_country) {
          break;
        }

        if ($country_name == $user_country) {
          $country_value = $value;

          break;
        }
      }
    }
  }

  /**
   * Function to get the label map data based on the country and value.
   */
  private static function getLabelMapData($country, $value) {
    $label = [];
    if ($country == 'Cyprus') {
      $label = ['offset' => [0, 14]];
    }
    elseif ($country == 'Malta') {
      $label = ['offset' => [0, 10]];
    }
    elseif ($country == 'N. Cyprus') {
      $label = ['show' => FALSE];
    }
    if (!$value) {
      $label = ['backgroundColor' => '', 'borderWidth' => 0];
    }

    return $label;
  }

  /**
   * Function to get the map data for the index visualisation.
   */
  public static function getMapData($entityTypeManager, $chart_data, $year, $node, $user_country) {
    $map_data = [];
    $countries =
            [ConstantHelper::EU_AVERAGE_NAME => ConstantHelper::EU_AVERAGE_NAME] +
            GeneralHelper::getTaxonomyTerms($entityTypeManager, 'countries', 'name') +
            self::NON_EU_COUNTRIES;
    $cyprus_value = $cyprus_children = $country_value = NULL;

    foreach ($countries as $country) {
      $value = NULL;
      if ($node == 'Index') {
        self::getIndexMapData($chart_data, $year, $country, $user_country, $value, $country_value);
      }
      else {
        $node_array = explode('-', $node);
        $node_type = $node_array[0];
        $node_id = $node_array[1];

        foreach ($chart_data as $key => $area) {
          if ($key == 0) {
            continue;
          }

          if ($node_type == 'area' &&
                $node_id == $area['area']['id']) {
            self::getAreaMapData($area, $year, $country, $user_country, $value, $country_value);
          }
          else {
            foreach ($area['area']['subareas'] as $subarea) {
              if ($node_id == $subarea['id']) {
                self::getSubareaMapData($subarea, $year, $country, $user_country, $value, $country_value);
              }
            }
          }
        }
      }

      $children = self::fillChildrenForMap($chart_data, $year, $node, $country, $user_country);

      if ($country == 'Cyprus') {
        $cyprus_value = $value;
        $cyprus_children = $children;
      }
      if ($country == 'N. Cyprus') {
        $value = $cyprus_value;
        $children = $cyprus_children;
      }

      array_push($map_data, [
        'name' => $country,
        'selection_name' => $country . ' ' . $year,
        'value' => $value,
        'emphasis' => (!$value) ? ['disabled' => TRUE] : NULL,
        'label' => self::getLabelMapData($country, $value),
        'children' => $children,
      ]);
    }

    if ($country_value) {
      $map_data[0]['country_value'] = $country_value;
    }

    return $map_data;
  }

  /**
   * Function to get the area, subarea or indicator children map data.
   */
  private static function getAreaChildrenMapData($area, $year, $country, $user_country, &$children) {
    $name = $value = $country_value = NULL;

    foreach ($area['area']['values'] as $val) {
      $country_name = trim(str_replace($year, '', array_key_first($val)));
      if ($country_name == $country) {
        $name = $area['area']['name'];
        $value = array_values($val)[0];
        if (!$user_country) {
          break;
        }
      }
      if ($country_name == $user_country) {
        $country_value = array_values($val)[0];
        if (!is_null($name) && !is_null($value)) {
          break;
        }
      }
    }
    if (!is_null($name) && !is_null($value)) {
      array_push($children, [
        'name' => $name,
        'value' => $value,
        'country_value' => $country_value,
      ]);
    }
  }

  /**
   * Function to get the subarea children map data.
   */
  private static function getSubareaChildrenMapData($subarea, $year, $country, $user_country, &$children) {
    $name = $value = $country_value = NULL;

    foreach ($subarea['values'] as $val) {
      $country_name = trim(str_replace($year, '', array_key_first($val)));
      if ($country_name == $country) {
        $name = $subarea['name'];
        $value = array_values($val)[0];
        if (!$user_country) {
          break;
        }
      }
      if ($country_name == $user_country) {
        $country_value = array_values($val)[0];
        if (!is_null($name) && !is_null($value)) {
          break;
        }
      }
    }
    if (!is_null($name) && !is_null($value)) {
      array_push($children, [
        'name' => $name,
        'value' => $value,
        'country_value' => $country_value,
      ]);
    }
  }

  /**
   * Function to get the indicator children map data.
   */
  private static function getIndicatorChildrenMapData($indicator, $year, $country, $user_country, &$children) {
    $name = $value = $country_value = NULL;

    foreach ($indicator['values'] as $val) {
      $country_name = trim(str_replace($year, '', array_key_first($val)));
      if ($country_name == $country) {
        $name = $indicator['name'];
        $value = array_values($val)[0];
        if (!$user_country) {
          break;
        }
      }
      if ($country_name == $user_country) {
        $country_value = array_values($val)[0];
        if (!is_null($name) && !is_null($value)) {
          break;
        }
      }
    }
    if (!is_null($name) && !is_null($value)) {
      array_push($children, [
        'name' => $name,
        'value' => $value,
        'country_value' => $country_value,
      ]);
    }
  }

  /**
   * Function to fill the children for the map data.
   */
  private static function fillChildrenForMap($chart_data, $year, $node, $country, $user_country) {
    $children = [];

    foreach ($chart_data as $key => $area) {
      if ($key > 0) {
        if ($node == 'Index') {
          self::getAreaChildrenMapData($area, $year, $country, $user_country, $children);
        }
        else {
          $node_array = explode('-', $node);
          $node_type = $node_array[0];
          $node_id = $node_array[1];

          if ($node_type == 'area' &&
                $node_id == $area['area']['id']) {
            foreach ($area['area']['subareas'] as $subarea) {
              self::getSubareaChildrenMapData($subarea, $year, $country, $user_country, $children);
            }
          }
          else {
            foreach ($area['area']['subareas'] as $subarea) {
              if ($node_id == $subarea['id']) {
                foreach ($subarea['indicators'] as $indicator) {
                  self::getIndicatorChildrenMapData($indicator, $year, $country, $user_country, $children);
                }
              }
            }
          }
        }
      }
    }

    return $children;
  }

}
