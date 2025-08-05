<?php

namespace Drupal\country_survey_management\Helper;

use Drupal\index_and_survey_configuration_management\Helper\IndicatorAccordionHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorAccordionQuestionHelper;

/**
 * Helper class for survey indicator data processing.
 */
class SurveyIndicatorDataHelper {

  /**
   * Function to get the processed survey indicators.
   */
  private static function getProcessedSurveyIndicators($indicator, $indicator_state, $assignee, $default_assignee, &$processed) {
    if (($indicator_state == 2 && $assignee == $default_assignee) ||
          $indicator_state > 5) {
      array_push($processed, $indicator['id']);
    }
  }

  /**
   * Function to get the completed survey indicators.
   */
  private static function getCompletedSurveyIndicators($indicator_state, $assignee, $default_assignee, &$completed) {
    if ($assignee != $default_assignee) {
      $completed &= (in_array($indicator_state, [3, 6, 7]));
    }
  }

  /**
   * Function to get the submitted survey indicators.
   */
  private static function getSubmittedSurveyIndicators($currentUser, $indicator_state, $assignee, &$submitted) {
    if ($assignee == $currentUser->id()) {
      $submitted &= (in_array($indicator_state, [3, 6, 7]));
    }
  }

  /**
   * Function to get the approved survey indicators.
   */
  private static function getApprovedSurveyIndicators($indicator_state, &$approved) {
    $approved &= ($indicator_state == 7);
  }

  /**
   * Function to get the assigned survey indicators.
   */
  private static function getAssignedExactSurveyIndicators($currentUser, $indicator, $assignee, &$assigned_exact) {
    if ($assignee == $currentUser->id()) {
      array_push($assigned_exact['id'], $indicator['id']);
      array_push($assigned_exact['identifier'], $indicator['identifier']);
    }
  }

  /**
   * Function to get survey indicators data.
   */
  public static function getSurveyIndicatorsData($entityTypeManager, $currentUser, $dateFormatter, $survey_indicators) {
    $indicators = [];
    $processed = [];
    $assigned = [
      'id' => [],
      'identifier' => [],
    ];
    $assigned_exact = [
      'id' => [],
      'identifier' => [],
    ];
    $started = FALSE;
    $completed = TRUE;
    $submitted = TRUE;
    $approved = TRUE;

    foreach ($survey_indicators as $survey_indicator) {
      $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($entityTypeManager, $dateFormatter, $survey_indicator);

      $indicator = $survey_indicator_data['indicator'];
      $indicator_state = $survey_indicator_data['state']['id'];
      $assignee = (!empty($survey_indicator_data['assignee'])) ? $survey_indicator_data['assignee']['id'] : '';
      $default_assignee = (!empty($survey_indicator_data['country_survey']['default_assignee'])) ? $survey_indicator_data['country_survey']['default_assignee']['id'] : '';

      array_push($indicators, $indicator['id']);

      self::getProcessedSurveyIndicators($indicator, $indicator_state, $assignee, $default_assignee, $processed);
      self::getCompletedSurveyIndicators($indicator_state, $assignee, $default_assignee, $completed);
      self::getApprovedSurveyIndicators($indicator_state, $approved);

      if (!SurveyIndicatorAccessHelper::hasSurveyIndicatorPermissions($entityTypeManager, $currentUser, $assignee)) {
        continue;
      }

      $survey_indicator_pending_requested_change_entity = SurveyIndicatorRequestChangeHelper::getSurveyIndicatorPendingRequestedChangeEntity($entityTypeManager, $survey_indicator_data['country_survey']['id'], $indicator['id']);

      $survey_indicator_pending_requested_change_data = [];
      if (!is_null($survey_indicator_pending_requested_change_entity)) {
        $survey_indicator_pending_requested_change = $entityTypeManager->getStorage('node')->load($survey_indicator_pending_requested_change_entity);
        $survey_indicator_pending_requested_change_data = SurveyIndicatorRequestChangeHelper::getSurveyIndicatorRequestedChangeData($entityTypeManager, $dateFormatter, $survey_indicator_pending_requested_change);
      }

      $started |= ((isset($survey_indicator_pending_requested_change_data['requested_user']) && $survey_indicator_pending_requested_change_data['requested_user']['id'] == $currentUser->id()) ||
                     (!empty($survey_indicator_data['approved_user']) && $survey_indicator_data['approved_user']['id'] == $currentUser->id()) ||
                     ($assignee == $currentUser->id() && in_array($indicator_state, [2, 5])));

      array_push($assigned['id'], $indicator['id']);
      array_push($assigned['identifier'], $indicator['identifier']);

      self::getSubmittedSurveyIndicators($currentUser, $indicator_state, $assignee, $submitted);
      self::getAssignedExactSurveyIndicators($currentUser, $indicator, $assignee, $assigned_exact);
    }

    $is_assigned_exact = (!empty($assigned_exact['id']) && !empty($assigned_exact['identifier'])) ? TRUE : FALSE;

    return [
      'indicators' => $indicators,
      'percentage' => (count($indicators)) ? round((count($processed) / count($indicators)) * 100) : 0,
      'assigned' => (!empty($assigned['id']) && !empty($assigned['identifier'])) ? $assigned : [],
      'assigned_exact' => ($is_assigned_exact) ? $assigned_exact : [],
      'started' => $started,
      'completed' => $completed,
      'submitted' => ($is_assigned_exact && $submitted) ? TRUE : FALSE,
      'approved' => $approved,
    ];
  }

  /**
   * Function to get the survey indicators processed.
   */
  public static function getSurveyIndicatorsProcessed($entityTypeManager, $dateFormatter, $country_survey, $indicators) {
    $survey_indicator_entities = SurveyIndicatorHelper::getSurveyIndicatorEntities($entityTypeManager, ['country_survey' => $country_survey]);
    $survey_indicators = $entityTypeManager->getStorage('node')->loadMultiple($survey_indicator_entities);

    $indicators_processed = [];
    foreach ($indicators as $indicator) {
      foreach ($survey_indicators as $survey_indicator) {
        $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($entityTypeManager, $dateFormatter, $survey_indicator);

        if ($indicator['id'] == $survey_indicator_data['indicator']['id']) {
          if (in_array($survey_indicator_data['state']['id'], [2, 3, 5, 6])) {
            array_push($indicators_processed, $indicator);
          }

          break;
        }
      }
    }

    return $indicators_processed;
  }

  /**
   * Function to get the survey indicator answers.
   */
  public static function getSurveyIndicatorAnswers($entityTypeManager, $indicator, $inputs) {
    $answers = [];

    foreach ($inputs as $data) {
      if ($indicator['id'] == $data['id']) {
        $accordion_entity = IndicatorAccordionHelper::getIndicatorAccordionEntity($entityTypeManager, $indicator['id'], $data['accordion']);
        $data['question_entity'] = IndicatorAccordionQuestionHelper::getIndicatorAccordionQuestionEntity($entityTypeManager, $accordion_entity, $data['question']);

        array_push($answers, $data);
      }
    }

    return $answers;
  }

  /**
   * Function to get the survey indicator rating and comments.
   */
  public static function getSurveyIndicatorRatingAndComments($indicator, $inputs) {
    foreach ($inputs as $data) {
      if ($indicator['id'] == $data['id']) {
        return [$data['rating'], htmlspecialchars($data['comments'])];
      }
    }
  }

}
