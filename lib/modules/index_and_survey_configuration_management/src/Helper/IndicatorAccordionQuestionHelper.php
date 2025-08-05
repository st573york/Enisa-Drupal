<?php

namespace Drupal\index_and_survey_configuration_management\Helper;

use Drupal\general_management\Helper\GeneralHelper;
use Drupal\general_management\Exception\CustomException;

/**
 * Class Indicator Accordion Question Helper.
 */
class IndicatorAccordionQuestionHelper {

  /**
   * Function hasIndicatorAccordionQuestionAnyAccordion.
   */
  public static function hasIndicatorAccordionQuestionAnyAccordion($db_accordion, $save, &$exceptions) {
    if (is_null($db_accordion)) {
      if ($save) {
        array_push($exceptions, new CustomException(
            'Section is mandatory. Please add at least one section!',
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
   * Function validateIndicatorAccordionQuestion.
   */
  public static function validateIndicatorAccordionQuestion($element, &$exceptions) {
    if (!isset($element['label'])) {
      array_push($exceptions, new CustomException(
            'Question title is required!',
            400,
            NULL,
            [
              'field_id' => $element['name'],
              'element_type' => 'question',
            ]));
    }
  }

  /**
   * Function getIndicatorAccordionQuestionData.
   */
  public static function getIndicatorAccordionQuestionData($node) {
    if (!$node) {
      return [];
    }

    $node_data = [
      'id' => $node->id(),
      'title' => $node->field_full_title->value,
      'order' => $node->field_order->value ?? '',
      'type' => '',
      'info' => $node->field_info->value ?? '',
      'compatible' => filter_var($node->field_compatible->value, FILTER_VALIDATE_BOOLEAN) ?? '',
      'answers_required' => filter_var($node->field_answers_required->value, FILTER_VALIDATE_BOOLEAN) ?? '',
      'reference_required' => filter_var($node->field_reference_required->value, FILTER_VALIDATE_BOOLEAN) ?? '',
    ];

    if ($node->hasField('field_type') &&
          !$node->get('field_type')->isEmpty()) {
      $type = $node->get('field_type')->entity;

      if (!is_null($type)) {
        $node_data['type'] = [
          'id' => (int) $type->field_type_id->value ?? '',
          'name' => $type->getName(),
        ];
      }
    }

    return $node_data;
  }

  /**
   * Function getIndicatorAccordionQuestionEntity.
   */
  public static function getIndicatorAccordionQuestionEntity($entityTypeManager, $accordion, $order) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'indicator_accordion_question')
      ->condition('field_accordion', $accordion)
      ->condition('field_order', $order)
      ->condition('status', 1);

    $question = $query->execute();

    if (!empty($question)) {
      return reset($question);
    }

    return NULL;
  }

  /**
   * Function getIndicatorAccordionQuestionEntities.
   */
  public static function getIndicatorAccordionQuestionEntities($entityTypeManager, $accordions) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'indicator_accordion_question')
      ->condition('field_accordion', $accordions, 'IN')
      ->condition('status', 1);

    return $query->sort('field_order')->execute();
  }

  /**
   * Function getIndicatorAccordionQuestionChoices.
   */
  public static function getIndicatorAccordionQuestionChoices($entityTypeManager) {
    $terms = GeneralHelper::getTaxonomyTerms($entityTypeManager, 'indicator_question_choices', 'entity');

    $choices = [];
    foreach ($terms as $term) {
      $choice_id = $term->field_choice_id->value;

      $choices[$choice_id] = [
        'id' => $choice_id,
        'name' => $term->getName(),
      ];
    }
    asort($choices);

    return $choices;
  }

  /**
   * Function processIndicatorAccordionQuestion.
   */
  public static function processIndicatorAccordionQuestion($entityTypeManager, $indicator_id, $db_accordion, $qkey, $fkey, $element, $type, $save, &$exceptions) {
    if (is_null($db_accordion)) {
      $db_accordion = IndicatorAccordionHelper::updateOrCreateIndicatorAccordion(
            $entityTypeManager,
            [
              'field_full_title' => 'Section Title',
              'field_indicator' => $indicator_id,
              'field_order' => $fkey,
            ]);
    }

    if ($save) {
      self::validateIndicatorAccordionQuestion($element, $exceptions);
    }

    $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'indicator_question_types', $type);

    return IndicatorAccordionQuestionHelper::updateOrCreateIndicatorAccordionQuestion(
            $entityTypeManager,
            [
              'field_full_title' => (isset($element['label']) && !empty($element['label'])) ? $element['label'] : 'Question',
              'field_accordion' => $db_accordion->id(),
              'field_order' => $qkey++,
              'field_type' => $term->id(),
              'field_info' => $element['description'] ?? '',
              'field_compatible' => filter_var($element['compatible'], FILTER_VALIDATE_BOOLEAN),
              'field_answers_required' => filter_var($element['required'], FILTER_VALIDATE_BOOLEAN),
              'field_reference_required' => filter_var($element['required'], FILTER_VALIDATE_BOOLEAN),
            ]);
  }

  /**
   * Function updateOrCreateIndicatorAccordionQuestion.
   */
  public static function updateOrCreateIndicatorAccordionQuestion($entityTypeManager, $data) {
    $data['type'] = 'indicator_accordion_question';
    $data['title'] = (strlen($data['field_full_title']) > 255) ? mb_substr($data['field_full_title'], 0, 255) : $data['field_full_title'];

    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $data['type'])
      ->condition('field_accordion', $data['field_accordion'])
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
