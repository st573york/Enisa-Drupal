<?php

namespace Drupal\index_and_survey_configuration_management\Helper;

use Drupal\general_management\Exception\CustomException;

/**
 * Helper class for Indicator Accordion Question Options.
 */
class IndicatorAccordionQuestionOptionHelper {

  /**
   * Function validateIndicatorAccordionQuestionOption.
   */
  public static function validateIndicatorAccordionQuestionOption($element, $okey, $option, &$exceptions) {
    $exception_data = [
      'field_id' => $element['name'],
      'element_type' => 'option',
      'element_id' => $okey,
    ];

    if (empty($option['label'])) {
      array_push($exceptions, new CustomException(
            'Option label is required!',
            400,
            NULL,
            $exception_data
        ));
    }

    if (!strlen($option['value'])) {
      array_push($exceptions, new CustomException(
            'Option score is required!',
            400,
            NULL,
            $exception_data
        ));
    }
    elseif (!is_numeric($option['value'])) {
      array_push($exceptions, new CustomException(
            'Option score must be integer!',
            400,
            NULL,
            $exception_data
        ));
    }
  }

  /**
   * Function getIndicatorAccordionQuestionOptionData.
   */
  public static function getIndicatorAccordionQuestionOptionData($node) {
    if (!$node) {
      return [];
    }

    return [
      'id' => $node->id(),
      'title' => $node->field_full_title->value,
      'master' => filter_var($node->field_master->value, FILTER_VALIDATE_BOOLEAN) ?? '',
      'score' => $node->field_score->value ?? '',
      'value' => $node->field_value->value ?? '',
    ];
  }

  /**
   * Function getIndicatorAccordionQuestionOptionEntity.
   */
  public static function getIndicatorAccordionQuestionOptionEntity($entityTypeManager, $question, $value) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'indicator_accordion_question_opt')
      ->condition('field_question', $question)
      ->condition('field_value', $value)
      ->condition('status', 1);

    $option = $query->execute();

    if (!empty($option)) {
      return reset($option);
    }

    return NULL;
  }

  /**
   * Function getIndicatorAccordionQuestionOptionEntities.
   */
  public static function getIndicatorAccordionQuestionOptionEntities($entityTypeManager, $questions) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'indicator_accordion_question_opt')
      ->condition('field_question', $questions, 'IN')
      ->condition('status', 1);

    return $query->execute();
  }

  /**
   * Function processIndicatorAccordionQuestionOptions.
   */
  public static function processIndicatorAccordionQuestionOptions($entityTypeManager, $element, $db_question, $save, &$exceptions) {
    foreach ($element['values'] as $okey => $option) {
      if ($save) {
        self::validateIndicatorAccordionQuestionOption($element, $okey, $option, $exceptions);
      }

      self::updateOrCreateIndicatorAccordionQuestionOption(
            $entityTypeManager,
            [
              'field_full_title' => (isset($option['label']) && !empty($option['label'])) ? $option['label'] : 'Option',
              'field_question' => $db_question->id(),
              'field_master' => (isset($element['master']) && in_array($option['label'], $element['master'])) ? TRUE : FALSE,
              'field_score' => (strlen($option['value']) && is_numeric($option['value'])) ? $option['value'] : NULL,
              'field_value' => ++$okey,
            ]);
    }
  }

  /**
   * Function updateOrCreateIndicatorAccordionQuestionOption.
   */
  public static function updateOrCreateIndicatorAccordionQuestionOption($entityTypeManager, $data) {
    $data['type'] = 'indicator_accordion_question_opt';
    $data['title'] = (strlen($data['field_full_title']) > 255) ? mb_substr($data['field_full_title'], 0, 255) : $data['field_full_title'];

    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $data['type'])
      ->condition('field_question', $data['field_question'])
      ->condition('field_value', $data['field_value'])
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
