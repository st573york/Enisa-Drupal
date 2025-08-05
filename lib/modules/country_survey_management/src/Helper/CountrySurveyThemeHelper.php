<?php

namespace Drupal\country_survey_management\Helper;

use Drupal\index_and_survey_configuration_management\Helper\IndicatorHelper;
use Drupal\survey_management\Helper\SurveyHelper;

/**
 * Class Country Survey Theme Helper.
 */
class CountrySurveyThemeHelper {

  /**
   * Function getLastCountrySurveyData.
   */
  public static function getLastCountrySurveyData($entityTypeManager, $dateFormatter, $assignee_country_survey_data) {
    $last_country_survey_data = [];

    $last_published_survey_entity = SurveyHelper::getLastPublishedSurveyEntity($entityTypeManager, $assignee_country_survey_data['survey']['year']);
    if (!is_null($last_published_survey_entity)) {
      $last_country_survey_entity = CountrySurveyHelper::getCountrySurveyEntity($entityTypeManager, $last_published_survey_entity, $assignee_country_survey_data['country']['id']);
      if (!is_null($last_country_survey_entity)) {
        $last_country_survey = $entityTypeManager->getStorage('node')->load($last_country_survey_entity);
        $last_country_survey_data = CountrySurveyHelper::getCountrySurveyData($entityTypeManager, $dateFormatter, $last_country_survey);
      }
    }

    return $last_country_survey_data;
  }

  /**
   * Function getLastSurveyIndicatorsData.
   */
  public static function getLastSurveyIndicatorsData($entityTypeManager, $dateFormatter, $last_country_survey_data) {
    $last_survey_indicators_data = [];

    if (!empty($last_country_survey_data)) {
      $survey_indicator_entities = SurveyIndicatorHelper::getSurveyIndicatorEntities($entityTypeManager, ['country_survey' => $last_country_survey_data['id']]);
      if (!empty($survey_indicator_entities)) {
        $survey_indicators = $entityTypeManager->getStorage('node')->loadMultiple($survey_indicator_entities);
        foreach ($survey_indicators as $survey_indicator) {
          $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($entityTypeManager, $dateFormatter, $survey_indicator);
          $survey_indicator_data += IndicatorHelper::getIndicatorFormData($entityTypeManager, $dateFormatter, $survey_indicator_data['indicator']['id'], $survey_indicator_data['id']);

          $last_survey_indicators_data[$survey_indicator_data['indicator']['identifier']] = $survey_indicator_data;
        }
      }
    }

    return $last_survey_indicators_data;
  }

  /**
   * Function getSurveyIndicatorsData.
   */
  public static function getSurveyIndicatorsData($entityTypeManager, $dateFormatter, $assignee_country_survey_data) {
    $survey_indicators_data = [];

    $survey_indicator_entities = (!empty($assignee_country_survey_data['indicators_assigned'])) ? SurveyIndicatorHelper::getSurveyIndicatorEntities($entityTypeManager, [
      'country_survey' => $assignee_country_survey_data['id'],
      'indicators' => $assignee_country_survey_data['indicators_assigned']['id'],
    ]) : [];
    if (!empty($survey_indicator_entities)) {
      $survey_indicators = $entityTypeManager->getStorage('node')->loadMultiple($survey_indicator_entities);
      foreach ($survey_indicators as $survey_indicator) {
        $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($entityTypeManager, $dateFormatter, $survey_indicator);
        $survey_indicator_data += IndicatorHelper::getIndicatorFormData($entityTypeManager, $dateFormatter, $survey_indicator_data['indicator']['id'], $survey_indicator_data['id']);
        $survey_indicator_data['latest_requested_change'] = [];
        $survey_indicator_data['submitted_requested_changes'] = [];

        $survey_indicator_latest_requested_change_entity = SurveyIndicatorRequestChangeHelper::getSurveyIndicatorLatestRequestedChangeEntity($entityTypeManager, $assignee_country_survey_data['id'], $survey_indicator_data['indicator']['id']);
        if (!is_null($survey_indicator_latest_requested_change_entity)) {
          $survey_indicator_latest_requested_change = $entityTypeManager->getStorage('node')->load($survey_indicator_latest_requested_change_entity);
          $survey_indicator_data['latest_requested_change'] = SurveyIndicatorRequestChangeHelper::getSurveyIndicatorRequestedChangeData($entityTypeManager, $dateFormatter, $survey_indicator_latest_requested_change);
        }

        $survey_indicator_submitted_requested_change_entities = SurveyIndicatorRequestChangeHelper::getSurveyIndicatorRequestedChangeEntities($entityTypeManager, $assignee_country_survey_data['id'], $survey_indicator_data['indicator']['id'], [2]);
        if (!empty($survey_indicator_submitted_requested_change_entities)) {
          $survey_indicator_submitted_requested_changes = $entityTypeManager->getStorage('node')->loadMultiple($survey_indicator_submitted_requested_change_entities);
          foreach ($survey_indicator_submitted_requested_changes as $survey_indicator_submitted_requested_change) {
            $survey_indicator_submitted_requested_change_data = SurveyIndicatorRequestChangeHelper::getSurveyIndicatorRequestedChangeData($entityTypeManager, $dateFormatter, $survey_indicator_submitted_requested_change);

            if (!empty($survey_indicator_data['latest_requested_change']) &&
                  $survey_indicator_data['latest_requested_change']['id'] == $survey_indicator_submitted_requested_change_data['id'] &&
                  (in_array($survey_indicator_data['state']['id'], [3, 5]) ||
                   ($survey_indicator_data['state']['id'] == 6 &&
                    !empty($assignee_country_survey_data['submitted_user'])))) {
              continue;
            }

            array_push($survey_indicator_data['submitted_requested_changes'], $survey_indicator_submitted_requested_change_data);
          }
        }

        $survey_indicators_data[$survey_indicator_data['indicator']['identifier']] = $survey_indicator_data;
      }
    }

    return $survey_indicators_data;
  }

}
