<?php

namespace Drupal\country_survey_management\Helper;

/**
 * Class Country Survey Summary Data Helper.
 */
class CountrySurveySummaryDataHelper {

  /**
   * Function getCountrySurveyDataNotAvailable.
   */
  public static function getCountrySurveyDataNotAvailable($entityTypeManager, $dateFormatter, $survey_indicators) {
    $data_not_available = [];

    foreach ($survey_indicators as $survey_indicator) {
      $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($entityTypeManager, $dateFormatter, $survey_indicator);

      $indicator = $survey_indicator_data['indicator'];

      $survey_indicator_answer_entities = SurveyIndicatorHelper::getSurveyIndicatorAnswerEntities($entityTypeManager, $survey_indicator_data['id']);
      $survey_indicator_answers = $entityTypeManager->getStorage('node')->loadMultiple($survey_indicator_answer_entities);

      $number = 0;

      foreach ($survey_indicator_answers as $survey_indicator_answer) {
        $survey_indicator_answer_data = SurveyIndicatorHelper::getSurveyIndicatorAnswerData($entityTypeManager, $dateFormatter, $survey_indicator_answer);

        $number++;

        if ($survey_indicator_answer_data['choice']['id'] == 3) {
          array_push($data_not_available, [
            'order' => $indicator['order'],
            'title' => $indicator['name'],
            'number' => $number,
            'question' => $survey_indicator_answer_data['question']['title'],
          ]);
        }
      }
    }

    return $data_not_available;
  }

  /**
   * Function getCountrySurveyReferences.
   */
  public static function getCountrySurveyReferences($entityTypeManager, $dateFormatter, $survey_indicators) {
    $references = [];

    foreach ($survey_indicators as $survey_indicator) {
      $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($entityTypeManager, $dateFormatter, $survey_indicator);

      $indicator = $survey_indicator_data['indicator'];

      $survey_indicator_answer_entities = SurveyIndicatorHelper::getSurveyIndicatorAnswerEntities($entityTypeManager, $survey_indicator_data['id']);
      $survey_indicator_answers = $entityTypeManager->getStorage('node')->loadMultiple($survey_indicator_answer_entities);

      $number = 0;

      foreach ($survey_indicator_answers as $survey_indicator_answer) {
        $survey_indicator_answer_data = SurveyIndicatorHelper::getSurveyIndicatorAnswerData($entityTypeManager, $dateFormatter, $survey_indicator_answer);

        $number++;

        if (!empty($survey_indicator_answer_data['reference_year']) ||
              !empty($survey_indicator_answer_data['reference_source'])) {
          array_push($references, [
            'order' => $indicator['order'],
            'title' => $indicator['name'],
            'number' => $number,
            'reference_year' => $survey_indicator_answer_data['reference_year'],
            'reference_source' => $survey_indicator_answer_data['reference_source'],
          ]);
        }
      }
    }

    return $references;
  }

  /**
   * Function getCountrySurveyComments.
   */
  public static function getCountrySurveyComments($entityTypeManager, $dateFormatter, $survey_indicators) {
    $comments = [];

    foreach ($survey_indicators as $survey_indicator) {
      $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($entityTypeManager, $dateFormatter, $survey_indicator);

      $indicator = $survey_indicator_data['indicator'];

      if (!empty($survey_indicator_data['comments'])) {
        array_push($comments, [
          'order' => $indicator['order'],
          'title' => $indicator['name'],
          'comments' => $survey_indicator_data['comments'],
        ]);
      }
    }

    return $comments;
  }

}
