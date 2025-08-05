<?php

namespace Drupal\country_survey_management\Helper;

use Drupal\index_and_survey_configuration_management\Helper\IndicatorAccordionQuestionHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorHelper;
use Drupal\user_management\Helper\UserHelper;

/**
 * Helper class for survey indicators.
 */
class SurveyIndicatorHelper {

  /**
   * Function to get the country survey reference entity data.
   */
  private static function getCountrySurveyReferenceEntity($entityTypeManager, $dateFormatter, $node, &$node_data) {
    if ($node->hasField('field_country_survey') &&
          !$node->get('field_country_survey')->isEmpty()) {
      $country_survey = $node->get('field_country_survey')->entity;

      $node_data['country_survey'] = CountrySurveyHelper::getCountrySurveyData($entityTypeManager, $dateFormatter, $country_survey);
    }
  }

  /**
   * Function to get the indicator reference entity data.
   */
  private static function getIndicatorReferenceEntity($node, &$node_data) {
    if ($node->hasField('field_indicator') &&
          !$node->get('field_indicator')->isEmpty()) {
      $indicator = $node->get('field_indicator')->entity;

      $node_data['indicator'] = IndicatorHelper::getIndicatorData($indicator, TRUE);
    }
  }

  /**
   * Function to get the assignee reference entity data.
   */
  private static function getAssigneeReferenceEntity($entityTypeManager, $dateFormatter, $node, &$node_data) {
    if ($node->hasField('field_assignee') &&
          !$node->get('field_assignee')->isEmpty()) {
      $assignee = $node->get('field_assignee')->entity;

      $node_data['assignee'] = UserHelper::getUserData($entityTypeManager, $dateFormatter, $assignee);
    }
  }

  /**
   * Function to get the indicator state reference entity data.
   */
  private static function getIndicatorStateReferenceEntity($node, &$node_data) {
    if ($node->hasField('field_indicator_state') &&
          !$node->get('field_indicator_state')->isEmpty()) {
      $state = $node->get('field_indicator_state')->entity;

      if (!is_null($state)) {
        $node_data['state'] = [
          'id' => (int) $state->field_state_id->value ?? '',
          'name' => $state->getName(),
        ];
      }
    }
  }

  /**
   * Function to get the indicator dashboard state reference entity data.
   */
  private static function getIndicatorDashboardStateReferenceEntity($node, &$node_data) {
    if ($node->hasField('field_indicator_dashboard_state') &&
          !$node->get('field_indicator_dashboard_state')->isEmpty()) {
      $dashboard_state = $node->get('field_indicator_dashboard_state')->entity;

      if (!is_null($dashboard_state)) {
        $node_data['dashboard_state'] = [
          'id' => (int) $dashboard_state->field_state_id->value ?? '',
          'name' => $dashboard_state->getName(),
        ];
      }
    }
  }

  /**
   * Function to get the approved user reference entity data.
   */
  private static function getApprovedUserReferenceEntity($entityTypeManager, $dateFormatter, $node, &$node_data) {
    if ($node->hasField('field_approved_user') &&
          !$node->get('field_approved_user')->isEmpty()) {
      $approved_user = $node->get('field_approved_user')->entity;

      $node_data['approved_user'] = UserHelper::getUserData($entityTypeManager, $dateFormatter, $approved_user);
    }
  }

  /**
   * Function getSurveyIndicatorData.
   */
  public static function getSurveyIndicatorData($entityTypeManager, $dateFormatter, $node) {
    if (!$node) {
      return [];
    }

    $node_data = [
      'id' => $node->id(),
      'title' => $node->getTitle(),
      'description' => $node->field_description->value ?? '',
      'rating' => $node->field_rating->value ?? '',
      'comments' => $node->field_comments->value ?? '',
      'answers_loaded' => filter_var($node->field_answers_loaded->value, FILTER_VALIDATE_BOOLEAN) ?? '',
      'deadline' => ($node->field_deadline->value) ? $dateFormatter->format(strtotime($node->field_deadline->value), 'custom', 'Y-m-d H:i:s') : '',
      'last_saved' => ($node->field_last_saved->value) ? $dateFormatter->format($node->field_last_saved->value, 'custom', 'd-m-Y H:i:s') : '',
      'country_survey' => '',
      'indicator' => '',
      'assignee' => '',
      'state' => '',
      'dashboard_state' => '',
      'approved_user' => '',
    ];

    self::getCountrySurveyReferenceEntity($entityTypeManager, $dateFormatter, $node, $node_data);
    self::getIndicatorReferenceEntity($node, $node_data);
    self::getAssigneeReferenceEntity($entityTypeManager, $dateFormatter, $node, $node_data);
    self::getIndicatorStateReferenceEntity($node, $node_data);
    self::getIndicatorDashboardStateReferenceEntity($node, $node_data);
    self::getApprovedUserReferenceEntity($entityTypeManager, $dateFormatter, $node, $node_data);

    return $node_data;
  }

  /**
   * Function getSurveyIndicatorAnswerData.
   */
  public static function getSurveyIndicatorAnswerData($entityTypeManager, $dateFormatter, $node) {
    if (!$node) {
      return [];
    }

    $node_data = [
      'id' => $node->id(),
      'title' => $node->getTitle(),
      'description' => $node->field_description->value ?? '',
      'free_text' => $node->field_free_text->value ?? '',
      'reference_year' => $node->field_reference_year->value ?? '',
      'reference_source' => $node->field_reference_source->value ?? '',
      'last_saved' => ($node->field_last_saved->value) ? $dateFormatter->format($node->field_last_saved->value, 'custom', 'd-m-Y H:i:s') : '',
      'survey_indicator' => '',
      'question' => '',
      'choice' => '',
      'options' => [],
    ];

    if ($node->hasField('field_survey_indicator') &&
          !$node->get('field_survey_indicator')->isEmpty()) {
      $survey_indicator = $node->get('field_survey_indicator')->entity;

      $node_data['survey_indicator'] = self::getSurveyIndicatorData($entityTypeManager, $dateFormatter, $survey_indicator);
    }

    if ($node->hasField('field_question') &&
          !$node->get('field_question')->isEmpty()) {
      $question = $node->get('field_question')->entity;

      $node_data['question'] = IndicatorAccordionQuestionHelper::getIndicatorAccordionQuestionData($question);
    }

    if ($node->hasField('field_choice') &&
          !$node->get('field_choice')->isEmpty()) {
      $choice = $node->get('field_choice')->entity;

      if (!is_null($choice)) {
        $node_data['choice'] = [
          'id' => (int) $choice->field_choice_id->value ?? '',
          'name' => $choice->getName(),
        ];
      }
    }

    if ($node->hasField('field_options') &&
          !$node->get('field_options')->isEmpty()) {
      $options = $node->get('field_options')->referencedEntities();

      foreach ($options as $option) {
        array_push($node_data['options'], $option->field_value->value);
      }
    }

    return $node_data;
  }

  /**
   * Function getSurveyIndicatorEntity.
   */
  public static function getSurveyIndicatorEntity($entityTypeManager, $country_survey, $indicator) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'survey_indicator')
      ->condition('field_country_survey', $country_survey)
      ->condition('field_indicator', $indicator)
      ->condition('status', 1);

    $survey_indicator = $query->execute();

    if (!empty($survey_indicator)) {
      return reset($survey_indicator);
    }

    return NULL;
  }

  /**
   * Function getSurveyIndicatorEntities.
   */
  public static function getSurveyIndicatorEntities($entityTypeManager, $data) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'survey_indicator')
      ->condition('status', 1);

    if (isset($data['country_survey'])) {
      $query->condition('field_country_survey', $data['country_survey']);
    }

    if (isset($data['indicators'])) {
      $query->condition('field_indicator', $data['indicators'], 'IN');
    }

    return $query->execute();
  }

  /**
   * Function getSurveyIndicatorAnswerEntity.
   */
  public static function getSurveyIndicatorAnswerEntity($entityTypeManager, $survey_indicator, $question) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'survey_indicator_answer')
      ->condition('field_survey_indicator', $survey_indicator)
      ->condition('field_question', $question)
      ->condition('status', 1);

    $survey_indicator_answer = $query->execute();

    if (!empty($survey_indicator_answer)) {
      return reset($survey_indicator_answer);
    }

    return NULL;
  }

  /**
   * Function getSurveyIndicatorAnswerEntities.
   */
  public static function getSurveyIndicatorAnswerEntities($entityTypeManager, $survey_indicator) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'survey_indicator_answer')
      ->condition('field_survey_indicator', $survey_indicator)
      ->condition('status', 1);

    return $query->execute();
  }

}
