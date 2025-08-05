<?php

namespace Drupal\general_management\Helper;

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Class General Helper.
 */
class GeneralHelper {

  /**
   * Function dateFormat.
   */
  public static function dateFormat($date, $format = 'Y-m-d') {
    $timestamp = strtotime($date);
    if ($timestamp === FALSE) {
      return NULL;
    }

    return DrupalDateTime::createFromTimestamp($timestamp)->format($format);
  }

  /**
   * Function getTaxonomyTerm.
   */
  public static function getTaxonomyTerm($entityTypeManager, $vocabulary, $value, $field = 'name') {
    $terms = $entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'vid' => $vocabulary,
        $field => $value,
      ]);

    if (!empty($terms)) {
      return reset($terms);
    }

    return NULL;
  }

  /**
   * Function getTaxonomyTerms.
   */
  public static function getTaxonomyTerms($entityTypeManager, $vocabulary, $field = 'id') {
    $terms = $entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'vid' => $vocabulary,
      ]);

    $data = [];
    foreach ($terms as $term) {
      if ($field == 'id') {
        array_push($data, $term->id());
      }
      elseif ($field == 'name') {
        array_push($data, $term->label());
      }
      elseif ($field == 'entity') {
        array_push($data, $term);
      }
      else {
        array_push($data, $term->$field->value);
      }
    }

    return $data;
  }

}
