<?php

namespace Drupal\country_survey_management\Helper;

use Drupal\general_management\Helper\GeneralHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorAccordionQuestionHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorAccordionQuestionOptionHelper;

/**
 * Helper class for managing survey indicator actions.
 */
class SurveyIndicatorActionHelper {
  const ERROR_NOT_ALLOWED = 'Indicator cannot be updated as the requested action is not allowed!';

  /**
   * Validates the inputs for survey indicator actions.
   */
  public static function storeSurveyIndicator($entityTypeManager, $country_survey_data, $user_data, $indicator_data) {
    $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'indicator_states', 1, 'field_state_id');

    $data = [
      'type' => 'survey_indicator',
      'title' => $country_survey_data['survey']['title'] . ' - ' . $country_survey_data['country']['name'] . ' - ' . $indicator_data['identifier'],
      'field_country_survey' => $country_survey_data['id'],
      'field_indicator' => $indicator_data['id'],
      'field_assignee' => $user_data['id'],
      'field_indicator_state' => $term->id(),
      'field_indicator_dashboard_state' => $term->id(),
      'field_deadline' => GeneralHelper::dateFormat($country_survey_data['survey']['deadline']),
    ];

    $survey_indicator = $entityTypeManager->getStorage('node')->create($data);
    $survey_indicator->save();
  }

  /**
   * Edits the survey indicator based on the inputs provided.
   */
  private static function editIndicator(
    $entityTypeManager,
    $currentUser,
    $dateFormatter,
    &$country_survey,
    $country_survey_data,
    &$survey_indicator,
    $survey_indicator_data,
    $inputs,
    &$resp,
  ) {
    $indicator = $survey_indicator_data['indicator'];
    $indicator_state = $survey_indicator_data['state']['id'];
    $indicator_title = $indicator['order'] . '. ' . $indicator['name'];
    $default_assignee = $country_survey_data['default_assignee']['id'];
    $assignee = $survey_indicator_data['assignee']['id'];

    $survey_indicator_latest_requested_change_entity = SurveyIndicatorRequestChangeHelper::getSurveyIndicatorLatestRequestedChangeEntity($entityTypeManager, $country_survey_data['id'], $indicator['id']);

    $survey_indicator_latest_requested_change = NULL;
    $survey_indicator_latest_requested_change_data = [];
    if ($survey_indicator_latest_requested_change_entity) {
      $survey_indicator_latest_requested_change = $entityTypeManager->getStorage('node')->load($survey_indicator_latest_requested_change_entity);
      $survey_indicator_latest_requested_change_data = SurveyIndicatorRequestChangeHelper::getSurveyIndicatorRequestedChangeData($entityTypeManager, $dateFormatter, $survey_indicator_latest_requested_change);
    }

    if (!SurveyIndicatorAccessHelper::canEditIndicator($indicator_state, $resp)) {
      return FALSE;
    }

    $new_assignee = (int) $inputs['assignee'];
    $new_deadline = $inputs['deadline'];

    $assignee_updated = ($assignee != $new_assignee) ? TRUE : FALSE;
    $deadline_updated = ($survey_indicator_data['deadline'] != $new_deadline) ? TRUE : FALSE;

    // Reset indicator when assignee changes.
    if ($assignee_updated) {
      self::resetIndicator(
            $entityTypeManager,
            $country_survey,
            $survey_indicator,
            $default_assignee,
            $new_assignee,
            $survey_indicator_latest_requested_change,
            $survey_indicator_latest_requested_change_data
        );

      if (isset($inputs['reset-answers']) &&
            $inputs['reset-answers']) {
        self::resetSurveyIndicatorAnswers($entityTypeManager, $survey_indicator, $survey_indicator_data);
      }

      self::notifyForReAssignedIndicators($entityTypeManager, $currentUser, $indicator_title, $default_assignee, $assignee, $resp);
    }

    if ($assignee_updated ||
          ($deadline_updated && in_array($indicator_state, [1, 2, 5]))) {
      self::notifyForAssignedIndicators($currentUser, $indicator_title, $default_assignee, $new_assignee, $resp);
    }

    $survey_indicator->set('field_assignee', $new_assignee);
    $survey_indicator->set('field_deadline', GeneralHelper::dateFormat($new_deadline, 'Y-m-d'));

    return TRUE;
  }

  /**
   * Resets the indicator when the assignee changes.
   */
  private static function saveIndicator(
    $entityTypeManager,
    $currentUser,
    $dateFormatter,
    $time,
    &$survey_indicator,
    $survey_indicator_data,
    $inputs,
    &$resp,
  ) {
    $indicator = $survey_indicator_data['indicator'];
    $indicator_state = $survey_indicator_data['state']['id'];
    $assignee = $survey_indicator_data['assignee']['id'];

    $survey_indicator_pending_requested_change_entity = SurveyIndicatorRequestChangeHelper::getSurveyIndicatorPendingRequestedChangeEntity($entityTypeManager, $survey_indicator_data['country_survey']['id'], $indicator['id']);

    $can_update = ($assignee == $currentUser->id() && in_array($indicator_state, [1, 2, 5]));

    if (!$can_update) {
      $resp = [
        'type' => 'error',
        'msg' => self::ERROR_NOT_ALLOWED,
        'status' => 405,
      ];

      return FALSE;
    }

    if ($indicator_state == 5 &&
          !is_null($survey_indicator_pending_requested_change_entity)) {
      $resp = [
        'type' => 'warning',
        'msg' => 'Indicator cannot be updated as there are requested changes that have not been submitted yet.',
        'status' => 405,
      ];

      return FALSE;
    }

    self::updateSurveyIndicatorAnswers(
          $entityTypeManager,
          $dateFormatter,
          $time,
          $indicator,
          $survey_indicator,
          $survey_indicator_data,
          $inputs['indicator_answers']);

    return TRUE;
  }

  /**
   * Resets the indicator when the assignee changes.
   */
  private static function submitIndicator(
    $entityTypeManager,
    $currentUser,
    $country_survey_data,
    &$survey_indicator,
    $survey_indicator_data,
    &$resp,
  ) {
    $indicator = $survey_indicator_data['indicator'];
    $indicator_state = $survey_indicator_data['state']['id'];
    $default_assignee = $country_survey_data['default_assignee']['id'];
    $assignee = $survey_indicator_data['assignee']['id'];

    $survey_indicator_pending_requested_change_entity = SurveyIndicatorRequestChangeHelper::getSurveyIndicatorPendingRequestedChangeEntity($entityTypeManager, $survey_indicator_data['country_survey']['id'], $indicator['id']);

    if ($assignee == $currentUser->id()) {
      if ($indicator_state == 5 &&
            !is_null($survey_indicator_pending_requested_change_entity)) {
        $resp = [
          'type' => 'warning',
          'msg' => 'Survey cannot be submitted as there are requested changes that have not been submitted yet.',
          'status' => 405,
        ];

        return FALSE;
      }

      $can_update = (in_array($indicator_state, [1, 2, 3, 5]));

      if ($can_update) {
        $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'indicator_states', (($default_assignee == $currentUser->id()) ? 6 : 3), 'field_state_id');

        $survey_indicator->set('field_indicator_state', $term->id());
        $survey_indicator->set('field_indicator_dashboard_state', $term->id());
        $survey_indicator->set('field_answers_loaded', FALSE);

        if ($default_assignee == $currentUser->id()) {
          $survey_indicator->set('field_approved_user', $currentUser->id());
        }
      }
    }

    return TRUE;
  }

  /**
   * Approves the indicator.
   */
  private static function approveIndicator(
    $entityTypeManager,
    $currentUser,
    &$survey_indicator,
    $survey_indicator_data,
    &$resp,
  ) {
    $can_update = ($survey_indicator_data['state']['id'] == 3);

    if (!$can_update) {
      $resp = [
        'type' => 'error',
        'msg' => self::ERROR_NOT_ALLOWED,
        'status' => 405,
      ];

      return FALSE;
    }

    $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'indicator_states', 6, 'field_state_id');

    $survey_indicator->set('field_indicator_state', $term->id());
    $survey_indicator->set('field_indicator_dashboard_state', $term->id());
    $survey_indicator->set('field_approved_user', $currentUser->id());

    return TRUE;
  }

  /**
   * Finalizes the approval of the indicator.
   */
  private static function finalApproveIndicator(
    $entityTypeManager,
    $currentUser,
    $country_survey_data,
    &$survey_indicator,
    $survey_indicator_data,
    &$resp,
  ) {
    $can_update = (!empty($country_survey_data['submitted_user']) && $survey_indicator_data['state']['id'] == 6);

    if (!$can_update) {
      $resp = [
        'type' => 'error',
        'msg' => self::ERROR_NOT_ALLOWED,
        'status' => 405,
      ];

      return FALSE;
    }

    $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'indicator_states', 7, 'field_state_id');

    $survey_indicator->set('field_indicator_state', $term->id());
    $survey_indicator->set('field_indicator_dashboard_state', $term->id());
    $survey_indicator->set('field_approved_user', $currentUser->id());

    return TRUE;
  }

  /**
   * Unapproves the indicator.
   */
  private static function unapproveIndicator(
    $entityTypeManager,
    &$survey_indicator,
    $survey_indicator_data,
    &$resp,
  ) {
    $can_update = ($survey_indicator_data['state']['id'] == 7);

    if (!$can_update) {
      $resp = [
        'type' => 'error',
        'msg' => self::ERROR_NOT_ALLOWED,
        'status' => 405,
      ];

      return FALSE;
    }

    $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'indicator_states', 6, 'field_state_id');

    $survey_indicator->set('field_indicator_state', $term->id());
    $survey_indicator->set('field_indicator_dashboard_state', $term->id());

    return TRUE;
  }

  /**
   * Updates survey indicators data based on the action requested by the user.
   */
  public static function updateSurveyIndicatorsData(
    $entityTypeManager,
    $currentUser,
    $dateFormatter,
    $time,
    $country_survey,
    $country_survey_data,
    $indicators,
    $inputs,
  ) {
    $resp = [
      'type' => 'success',
      'data' => [],
    ];

    foreach ($indicators as $indicator) {
      $survey_indicator_entity = SurveyIndicatorHelper::getSurveyIndicatorEntity($entityTypeManager, $country_survey_data['id'], $indicator);

      $survey_indicator = $entityTypeManager->getStorage('node')->load($survey_indicator_entity);
      $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($entityTypeManager, $dateFormatter, $survey_indicator);

      if ($inputs['action'] == 'edit') {
        self::editIndicator(
              $entityTypeManager,
              $currentUser,
              $dateFormatter,
              $country_survey,
              $country_survey_data,
              $survey_indicator,
              $survey_indicator_data,
              $inputs,
              $resp);
      }
      elseif ($inputs['action'] == 'save') {
        self::saveIndicator(
              $entityTypeManager,
              $currentUser,
              $dateFormatter,
              $time,
              $survey_indicator,
              $survey_indicator_data,
              $inputs,
              $resp);
      }
      elseif ($inputs['action'] == 'submit') {
        self::submitIndicator(
              $entityTypeManager,
              $currentUser,
              $country_survey_data,
              $survey_indicator,
              $survey_indicator_data,
              $resp);
      }
      elseif ($inputs['action'] == 'approve') {
        self::approveIndicator(
              $entityTypeManager,
              $currentUser,
              $survey_indicator,
              $survey_indicator_data,
              $resp);
      }
      elseif ($inputs['action'] == 'final_approve') {
        self::finalApproveIndicator(
              $entityTypeManager,
              $currentUser,
              $country_survey_data,
              $survey_indicator,
              $survey_indicator_data,
              $resp);
      }
      elseif ($inputs['action'] == 'unapprove') {
        self::unapproveIndicator(
              $entityTypeManager,
              $survey_indicator,
              $survey_indicator_data,
              $resp);
      }

      if ($resp['type'] == 'warning' ||
            $resp['type'] == 'error') {
        return $resp;
      }

      $survey_indicator->save();
    }

    $country_survey->save();

    return [
      'type' => 'success',
      'msg' => 'Indicators have been successfully updated!',
      'data' => $resp['data'],
    ];
  }

  /**
   * Resets the survey indicator answers when the assignee changes.
   */
  private static function updateSurveyIndicatorAnswers(
    $entityTypeManager,
    $dateFormatter,
    $time,
    $indicator,
    &$survey_indicator,
    $survey_indicator_data,
    $inputs,
  ) {
    $last_saved = $time->getCurrentTime();

    $answers = SurveyIndicatorDataHelper::getSurveyIndicatorAnswers($entityTypeManager, $indicator, $inputs);
    foreach ($answers as $answer_data) {
      $question = $entityTypeManager->getStorage('node')->load($answer_data['question_entity']);
      $question_data = IndicatorAccordionQuestionHelper::getIndicatorAccordionQuestionData($question);

      $choice = (int) $answer_data['choice'];
      $free_text = ($question_data['type']['id'] == 3 && !empty($answer_data['answers'])) ? $answer_data['answers'][0] : '';
      $reference_year = $answer_data['reference_year'];
      $reference_source = $answer_data['reference_source'];

      $new_answer = FALSE;

      $survey_indicator_answer_entity = SurveyIndicatorHelper::getSurveyIndicatorAnswerEntity($entityTypeManager, $survey_indicator_data['id'], $answer_data['question_entity']);
      if (is_null($survey_indicator_answer_entity)) {
        $data = [
          'type' => 'survey_indicator_answer',
          'title' => $survey_indicator_data['country_survey']['survey']['title'] . ' - ' . $survey_indicator_data['country_survey']['country']['name'] . ' - ' . $indicator['identifier'] . ' - ' . $answer_data['accordion'] . ' - ' . $answer_data['question'],
          'field_survey_indicator' => $survey_indicator_data['id'],
          'field_question' => $answer_data['question_entity'],
        ];

        $survey_indicator_answer = $entityTypeManager->getStorage('node')->create($data);
        $survey_indicator_answer_data['options'] = [];

        $new_answer |= TRUE;
      }
      else {
        $survey_indicator_answer = $entityTypeManager->getStorage('node')->load($survey_indicator_answer_entity);
        $survey_indicator_answer_data = SurveyIndicatorHelper::getSurveyIndicatorAnswerData($entityTypeManager, $dateFormatter, $survey_indicator_answer);

        if ($survey_indicator_answer_data['choice']['id'] != $choice ||
              $survey_indicator_answer_data['free_text'] != $free_text ||
              $survey_indicator_answer_data['reference_year'] != $reference_year ||
              $survey_indicator_answer_data['reference_source'] != $reference_source) {
          $new_answer |= TRUE;
        }
      }

      if ($question_data['type']['id'] == 1 ||
            $question_data['type']['id'] == 2) {
        sort($survey_indicator_answer_data['options']);
        sort($answer_data['answers']);

        if ($survey_indicator_answer_data['options'] !== $answer_data['answers']) {
          $options = [];
          foreach ($answer_data['answers'] as $value) {
            array_push($options, [
              'target_id' => IndicatorAccordionQuestionOptionHelper::getIndicatorAccordionQuestionOptionEntity($entityTypeManager, $answer_data['question_entity'], $value),
            ]);
          }

          $survey_indicator_answer->set('field_options', $options);

          $new_answer |= TRUE;
        }
      }

      if ($new_answer) {
        $survey_indicator_answer->set('field_last_saved', $last_saved);
      }

      $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'indicator_question_choices', $choice, 'field_choice_id');

      $survey_indicator_answer->set('field_choice', $term->id());
      $survey_indicator_answer->set('field_free_text', $free_text);
      $survey_indicator_answer->set('field_reference_year', $reference_year);
      $survey_indicator_answer->set('field_reference_source', $reference_source);
      $survey_indicator_answer->save();
    }

    $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'indicator_states', 2, 'field_state_id');

    if ($survey_indicator_data['state']['id'] == 1) {
      $survey_indicator->set('field_indicator_state', $term->id());
    }
    $survey_indicator->set('field_indicator_dashboard_state', $term->id());
    [$rating, $comments] = SurveyIndicatorDataHelper::getSurveyIndicatorRatingAndComments($indicator, $inputs);
    $survey_indicator->set('field_rating', $rating);
    $survey_indicator->set('field_comments', $comments);
    $survey_indicator->set('field_last_saved', $last_saved);
    $survey_indicator->set('field_answers_loaded', FALSE);
  }

  /**
   * Resets the indicator when the assignee changes.
   */
  private static function resetIndicator(
    $entityTypeManager,
    &$country_survey,
    &$survey_indicator,
    $default_assignee,
    $new_assignee,
    &$survey_indicator_latest_requested_change,
    $survey_indicator_latest_requested_change_data,
  ) {
    if ($new_assignee != $default_assignee) {
      $country_survey->set('field_completed', FALSE);
    }

    $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'indicator_states', 1, 'field_state_id');

    $survey_indicator->set('field_indicator_state', $term->id());
    $survey_indicator->set('field_indicator_dashboard_state', $term->id());

    if (!is_null($survey_indicator_latest_requested_change) &&
          !empty($survey_indicator_latest_requested_change_data) &&
          empty($survey_indicator_latest_requested_change_data['answered_date'])) {
      $survey_indicator_latest_requested_change->set('field_assignee', $new_assignee);
      $survey_indicator_latest_requested_change->save();

      $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'indicator_states', 5, 'field_state_id');

      $survey_indicator->set('field_indicator_state', $term->id());
    }
  }

  /**
   * Resets the survey indicator answers when the assignee changes.
   */
  private static function resetSurveyIndicatorAnswers($entityTypeManager, &$survey_indicator, $survey_indicator_data) {
    $survey_indicator->set('field_rating', []);
    $survey_indicator->set('field_comments', []);
    $survey_indicator->set('field_last_saved', []);

    $survey_indicator_answer_entities = SurveyIndicatorHelper::getSurveyIndicatorAnswerEntities($entityTypeManager, $survey_indicator_data['id']);
    $nodes = $entityTypeManager->getStorage('node')->loadMultiple($survey_indicator_answer_entities);
    $entityTypeManager->getStorage('node')->delete($nodes);
  }

  /**
   * Notifies users for reassigned indicators.
   */
  private static function notifyForReAssignedIndicators($entityTypeManager, $currentUser, $indicator_title, $default_assignee, $assignee, &$resp) {
    // Notify user except default assignee.
    if (($currentUser->id() == $default_assignee &&
           $assignee != $default_assignee) ||
          $currentUser->id() != $default_assignee) {
      $user = $entityTypeManager->getStorage('user')->load($assignee);
      if ($user->isActive()) {
        if (!isset($resp['data']['reassigned_indicators'][$assignee])) {
          $resp['data']['reassigned_indicators'][$assignee] = [];
        }

        array_push($resp['data']['reassigned_indicators'][$assignee], $indicator_title);
      }
    }
  }

  /**
   * Notifies user for assigned indicators.
   */
  private static function notifyForAssignedIndicators($currentUser, $indicator_title, $default_assignee, $new_assignee, &$resp) {
    // Notify user except default assignee.
    if (($currentUser->id() == $default_assignee &&
           $new_assignee != $default_assignee) ||
          $currentUser->id() != $default_assignee) {
      if (!isset($resp['data']['assigned_indicators'][$new_assignee])) {
        $resp['data']['assigned_indicators'][$new_assignee] = [];
      }

      array_push($resp['data']['assigned_indicators'][$new_assignee], $indicator_title);
    }
  }

}
