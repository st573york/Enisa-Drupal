<?php

namespace Drupal\index_and_survey_configuration_management\Helper;

use Drupal\country_survey_management\Helper\SurveyIndicatorHelper;

/**
 * Helper class for managing indicator actions.
 */
class IndicatorActionHelper {

  /**
   * Function to store a new indicator.
   */
  public static function storeIndicator($entityTypeManager, $inputs) {
    $data = IndicatorHelper::getIndicatorDataForSave($entityTypeManager, $inputs);
    $data['type'] = 'indicator';
    $data['field_short_name'] = mb_substr($inputs['title'], 0, 20);

    $indicator = $entityTypeManager->getStorage('node')->create($data);
    $indicator->save();
  }

  /**
   * Function to update an existing indicator.
   */
  public static function updateIndicator($entityTypeManager, $indicator, $inputs) {
    $indicator_data = IndicatorHelper::getIndicatorData($indicator);

    $data = IndicatorHelper::getIndicatorDataForSave($entityTypeManager, $inputs, $indicator_data);

    if ($inputs['category'] != 'survey') {
      $data['field_order'] = [];
      $data['field_validated'] = [];
    }
    if ($inputs['category'] == 'eu-wide') {
      $data['field_default_subarea'] = [];
    }

    foreach ($data as $key => $value) {
      $indicator->set($key, $value);
    }
    $indicator->save();
  }

  /**
   * Function to update the order of indicators.
   */
  public static function updateOrder($entityTypeManager, $data) {
    foreach ($data as $row) {
      $parts = explode('row_', $row['id']);

      $indicator = $entityTypeManager->getStorage('node')->load($parts[1]);

      $indicator->set('field_order', $row['order']);
      $indicator->save();
    }
  }

  /**
   * Function to delete an indicator and its related entities.
   */
  public static function deleteIndicator($entityTypeManager, $database, $indicator, $data = []) {
    if (empty($data)) {
      $data = [
        'delete_survey_data',
        'delete_survey_configuration',
        'delete_indicator',
      ];
    }

    $entities = [];

    // Delete all survey data for this indicator.
    if (in_array('delete_survey_data', $data)) {
      $survey_indicator_entities = SurveyIndicatorHelper::getSurveyIndicatorEntities($entityTypeManager, ['indicators' => [$indicator->id()]]);
      $entities += $survey_indicator_entities;
    }

    // Delete all survey configuration for this indicator.
    if (in_array('delete_survey_configuration', $data)) {
      $accordion_entities = IndicatorAccordionHelper::getIndicatorAccordionEntities($entityTypeManager, $indicator->id());
      $entities += $accordion_entities;

      if (!empty($accordion_entities)) {
        $question_entities = IndicatorAccordionQuestionHelper::getIndicatorAccordionQuestionEntities($entityTypeManager, $accordion_entities);
        $entities += $question_entities;

        if (!empty($question_entities)) {
          $option_entities = IndicatorAccordionQuestionOptionHelper::getIndicatorAccordionQuestionOptionEntities($entityTypeManager, $question_entities);
          $entities += $option_entities;
        }
      }
    }

    if (!empty($entities)) {
      $nodes = $entityTypeManager->getStorage('node')->loadMultiple($entities);
      $entityTypeManager->getStorage('node')->delete($nodes);
    }

    if (in_array('delete_indicator', $data)) {
      $database->delete('indicator_disclaimers')
        ->condition('indicator_id', $indicator->id())
        ->execute();

      $database->delete('indicator_calculation_variables')
        ->condition('indicator_id', $indicator->id())
        ->execute();

      $indicator->delete();
    }
  }

  /**
   * Function to clone a survey indicator and its related entities.
   */
  public static function cloneSurveyIndicator($entityTypeManager, $indicator, $replicate_indicator) {
    $entities = IndicatorAccordionHelper::getIndicatorAccordionEntities($entityTypeManager, $indicator->id());
    $accordions = $entityTypeManager->getStorage('node')->loadMultiple($entities);

    foreach ($accordions as $accordion) {
      $replicate_accordion = $accordion->createDuplicate();
      $replicate_accordion->set('field_indicator', $replicate_indicator->id());

      $replicate_accordion->save();

      $entities = IndicatorAccordionQuestionHelper::getIndicatorAccordionQuestionEntities($entityTypeManager, [$accordion->id()]);
      $questions = $entityTypeManager->getStorage('node')->loadMultiple($entities);

      foreach ($questions as $question) {
        $replicate_question = $question->createDuplicate();
        $replicate_question->set('field_accordion', $replicate_accordion->id());

        $replicate_question->save();

        $entities = IndicatorAccordionQuestionOptionHelper::getIndicatorAccordionQuestionOptionEntities($entityTypeManager, [$question->id()]);
        $options = $entityTypeManager->getStorage('node')->loadMultiple($entities);

        foreach ($options as $option) {
          $replicate_option = $option->createDuplicate();
          $replicate_option->set('field_question', $replicate_question->id());

          $replicate_option->save();
        }
      }
    }
  }

}
