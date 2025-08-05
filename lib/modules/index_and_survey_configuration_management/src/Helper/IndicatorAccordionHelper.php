<?php

namespace Drupal\index_and_survey_configuration_management\Helper;

use Drupal\general_management\Exception\CustomException;

/**
 * Helper class for Indicator Accordion.
 */
class IndicatorAccordionHelper {

  /**
   * Function to check if the next element is a valid accordion section.
   */
  public static function hasIndicatorAccordionAnyQuestion($next_element, $save, &$exceptions) {
    if (is_null($next_element) ||
          (!is_null($next_element) &&
           $next_element['type'] == 'header')) {
      if ($save) {
        array_push($exceptions, new CustomException(
            'Sections must be followed by at least one question. Please add a question or remove empty sections!',
            400,
            NULL,
            [
              'element_type' => 'alert',
            ]
        ));
      }

      return FALSE;
    }

    return TRUE;
  }

  /**
   * Function validateIndicatorAccordion.
   */
  public static function getIndicatorAccordionData($node) {
    if (!$node) {
      return [];
    }

    return [
      'id' => $node->id(),
      'title' => $node->field_full_title->value,
      'order' => $node->field_order->value ?? '',
    ];
  }

  /**
   * Function getIndicatorAccordionEntity.
   */
  public static function getIndicatorAccordionEntity($entityTypeManager, $indicator, $order) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'indicator_accordion')
      ->condition('field_indicator', $indicator)
      ->condition('field_order', $order)
      ->condition('status', 1);

    $accordion = $query->execute();

    if (!empty($accordion)) {
      return reset($accordion);
    }

    return NULL;
  }

  /**
   * Function getIndicatorAccordionEntities.
   */
  public static function getIndicatorAccordionEntities($entityTypeManager, $indicator) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'indicator_accordion')
      ->condition('field_indicator', $indicator)
      ->condition('status', 1);

    return $query->sort('field_order')->execute();
  }

  /**
   * Function updateOrCreateIndicatorAccordion.
   */
  public static function updateOrCreateIndicatorAccordion($entityTypeManager, $data) {
    $data['type'] = 'indicator_accordion';
    $data['title'] = (strlen($data['field_full_title']) > 255) ? mb_substr($data['field_full_title'], 0, 255) : $data['field_full_title'];

    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $data['type'])
      ->condition('field_indicator', $data['field_indicator'])
      ->condition('field_order', $data['field_order'])
      ->range(0, 1);

    $entities = $query->execute();

    if (!empty($entities)) {
      $entity = $entityTypeManager->getStorage('node')->load(reset($entities));

      foreach ($data as $key => $value) {
        $entity->set($key, $value);
      }
    }
    else {
      $entity = $entityTypeManager->getStorage('node')->create($data);
    }

    $entity->save();

    return $entity;
  }

}
