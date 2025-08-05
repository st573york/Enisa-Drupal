<?php

namespace Drupal\country_survey_management\Helper;

use Drupal\general_management\Helper\GeneralHelper;
use Drupal\user_management\Helper\UserHelper;
use Drupal\user_management\Helper\UserPermissionsHelper;

/**
 * Helper class for handling survey indicator request change actions.
 */
class SurveyIndicatorRequestChangeActionHelper {

  /**
   * Function to check if user can request changes for indicator.
   */
  private static function canRequestChangesCountrySurveyIndicator(
    $entityTypeManager,
    $currentUser,
    $country_survey_data,
    $indicator_state,
    $is_assigned,
    $survey_indicator_pending_requested_change_author_role,
  ) {
    $can_request_changes = FALSE;
    if (UserPermissionsHelper::isAdmin($entityTypeManager, $currentUser)) {
      $can_request_changes |= (($indicator_state == 5 && $survey_indicator_pending_requested_change_author_role == 5 ||
                                  $indicator_state == 6) &&
                                 !empty($country_survey_data['submitted_user']) &&
                                 !$is_assigned);
    }
    elseif (UserPermissionsHelper::isPoC($entityTypeManager, $currentUser)) {
      if (UserPermissionsHelper::isPrimaryPoC($entityTypeManager, $currentUser)) {
        $can_request_changes |= ((($indicator_state == 5 && $survey_indicator_pending_requested_change_author_role == 6) ||
                                  $indicator_state == 3) &&
                                 $country_survey_data['completed'] &&
                                 !$is_assigned);
      }
      else {
        $can_request_changes |= ((($indicator_state == 5 && $survey_indicator_pending_requested_change_author_role == 7) ||
                                    $indicator_state == 3) &&
                                   !$is_assigned);
      }
    }

    return $can_request_changes;
  }

  /**
   * Function to request changes for a country survey indicator.
   */
  public static function requestChangesCountrySurveyIndicator($entityTypeManager, $currentUser, $dateFormatter, $time, $indicator_id, $inputs) {
    $country_survey_id = $inputs['country_survey_id'];
    $country_survey = $entityTypeManager->getStorage('node')->load($country_survey_id);
    $country_survey_data = CountrySurveyHelper::getCountrySurveyData($entityTypeManager, $dateFormatter, $country_survey);

    $survey_indicator_entity = SurveyIndicatorHelper::getSurveyIndicatorEntity($entityTypeManager, $country_survey_id, $indicator_id);

    $survey_indicator = $entityTypeManager->getStorage('node')->load($survey_indicator_entity);
    $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($entityTypeManager, $dateFormatter, $survey_indicator);

    $indicator_state = $survey_indicator_data['state']['id'];
    $indicator_assignee_data = $survey_indicator_data['assignee'];
    $is_assigned = ($indicator_assignee_data['id'] == $currentUser->id()) ? TRUE : FALSE;

    $survey_indicator_pending_requested_change_author_role = NULL;
    $survey_indicator_pending_requested_change_entity = SurveyIndicatorRequestChangeHelper::getSurveyIndicatorPendingRequestedChangeEntity($entityTypeManager, $country_survey_id, $indicator_id);
    if (!is_null($survey_indicator_pending_requested_change_entity)) {
      $survey_indicator_pending_requested_change = $entityTypeManager->getStorage('node')->load($survey_indicator_pending_requested_change_entity);
      $survey_indicator_pending_requested_change_data = SurveyIndicatorRequestChangeHelper::getSurveyIndicatorRequestedChangeData($entityTypeManager, $dateFormatter, $survey_indicator_pending_requested_change);

      $survey_indicator_pending_requested_change_author_role = $survey_indicator_pending_requested_change_data['requested_user']['role']['weight'];
    }

    if (!self::canRequestChangesCountrySurveyIndicator(
          $entityTypeManager,
          $currentUser,
          $country_survey_data,
          $indicator_state,
          $is_assigned,
          $survey_indicator_pending_requested_change_author_role)) {
      return [
        'type' => 'error',
        'msg' => 'Changes cannot be requested as the requested action is not allowed!',
        'status' => 405,
      ];
    }

    if (UserPermissionsHelper::isAdmin($entityTypeManager, $currentUser)) {
      $indicator_assignee = $entityTypeManager->getStorage('user')->load($country_survey_data['default_assignee']['id']);
      $indicator_assignee_data = UserHelper::getUserData($entityTypeManager, $dateFormatter, $indicator_assignee);
    }

    if (!$indicator_assignee_data['is_active']) {
      return [
        'type' => 'warning',
        'msg' => 'Request changes are not allowed as ' . (UserPermissionsHelper::isAdmin($entityTypeManager, $currentUser)
                ? 'Primary PoC'
                : 'indicator assignee') . ' is inactive.' . (UserPermissionsHelper::isAdmin($entityTypeManager, $currentUser)
                    ? ''
                    : ' Please re-assign indicator to an active user.'),
        'status' => 405,
      ];
    }

    if (is_null($survey_indicator_pending_requested_change_entity)) {
      $data = [
        'type' => 'survey_indicator_rc',
        'title' => $indicator_assignee_data['name'],
        'field_country_survey' => $country_survey_id,
        'field_indicator' => $indicator_id,
      ];

      $survey_indicator_pending_requested_change = $entityTypeManager->getStorage('node')->create($data);
    }

    self::saveSurveyIndicatorPendingRequestedChange(
          $entityTypeManager,
          $currentUser,
          $time,
          $survey_indicator_pending_requested_change,
          $survey_indicator_data,
          $indicator_assignee_data,
          $indicator_state,
          $inputs);

    self::saveSurveyIndicator($entityTypeManager, $currentUser, $survey_indicator, $indicator_assignee_data, $inputs);

    return [
      'type' => 'success',
      'msg' => 'Indicator has been successfully requested changes!',
    ];
  }

  /**
   * Function to save survey indicator pending requested change.
   */
  private static function saveSurveyIndicatorPendingRequestedChange(
    $entityTypeManager,
    $currentUser,
    $time,
    $survey_indicator_pending_requested_change,
    $survey_indicator_data,
    $indicator_assignee_data,
    $indicator_state,
    $inputs,
  ) {
    $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'indicator_states', $indicator_state, 'field_state_id');

    $survey_indicator_pending_requested_change->set('field_indicator_prev_state', $term->id());

    $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'survey_indicator_rc_states', 1, 'field_state_id');

    $survey_indicator_pending_requested_change->set('field_survey_indicator_rc_state', $term->id());

    $survey_indicator_pending_requested_change->set('field_changes', $inputs['changes']);
    $survey_indicator_pending_requested_change->set('field_deadline', GeneralHelper::dateFormat($inputs['deadline'], 'Y-m-d'));
    $survey_indicator_pending_requested_change->set('field_requested_user', $currentUser->id());
    $survey_indicator_pending_requested_change->set('field_assignee', $indicator_assignee_data['id']);
    $survey_indicator_pending_requested_change->set('field_requested_date', $time->getCurrentTime());
    $survey_indicator_pending_requested_change->set('field_indicator_prev_assignee', $survey_indicator_data['assignee']['id']);
    $survey_indicator_pending_requested_change->save();
  }

  /**
   * Function to save survey indicator.
   */
  private static function saveSurveyIndicator($entityTypeManager, $currentUser, $survey_indicator, $indicator_assignee_data, $inputs) {
    // Reset indicator assignee to default assignee.
    if (UserPermissionsHelper::isAdmin($entityTypeManager, $currentUser)) {
      $survey_indicator->set('field_assignee', $indicator_assignee_data['id']);
    }
    $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'indicator_states', 5, 'field_state_id');

    $survey_indicator->set('field_indicator_state', $term->id());

    $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'indicator_states', 4, 'field_state_id');

    $survey_indicator->set('field_indicator_dashboard_state', $term->id());

    $survey_indicator->set('field_deadline', GeneralHelper::dateFormat($inputs['deadline'], 'Y-m-d'));
    $survey_indicator->set('field_approved_user', []);
    $survey_indicator->save();
  }

  /**
   * Function answerCountrySurveyRequestedChanges.
   */
  public static function answerCountrySurveyRequestedChanges($entityTypeManager, $currentUser, $time, $country_survey) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'survey_indicator_rc')
      ->condition('field_country_survey', $country_survey)
      ->condition('field_assignee', $currentUser->id())
      ->condition('field_answered_date', NULL, 'IS NULL')
      ->condition('status', 1);

    $requested_change_entities = $query->execute();

    if (!empty($requested_change_entities)) {
      $requested_changes = $entityTypeManager->getStorage('node')->loadMultiple($requested_change_entities);
      foreach ($requested_changes as $requested_change) {
        $requested_change->set('field_answered_date', $time->getCurrentTime());
        $requested_change->save();
      }
    }
  }

  /**
   * Function discardRequestedChangesCountrySurveyIndicator.
   */
  public static function discardRequestedChangesCountrySurveyIndicator($entityTypeManager, $currentUser, $dateFormatter, $indicator_id, $inputs) {
    $country_survey_id = $inputs['country_survey_id'];
    $country_survey = $entityTypeManager->getStorage('node')->load($country_survey_id);
    $country_survey_data = CountrySurveyHelper::getCountrySurveyData($entityTypeManager, $dateFormatter, $country_survey);

    $survey_indicator_entity = SurveyIndicatorHelper::getSurveyIndicatorEntity($entityTypeManager, $country_survey_id, $indicator_id);

    $survey_indicator = $entityTypeManager->getStorage('node')->load($survey_indicator_entity);
    $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($entityTypeManager, $dateFormatter, $survey_indicator);

    $indicator_state = $survey_indicator_data['state']['id'];
    $indicator_assignee = $survey_indicator_data['assignee']['id'];
    $is_assigned = ($indicator_assignee == $currentUser->id()) ? TRUE : FALSE;

    $survey_indicator_pending_requested_change = NULL;
    $survey_indicator_pending_requested_change_data = [];
    $survey_indicator_pending_requested_change_author_role = NULL;
    $survey_indicator_pending_requested_change_entity = SurveyIndicatorRequestChangeHelper::getSurveyIndicatorPendingRequestedChangeEntity($entityTypeManager, $country_survey_id, $indicator_id);
    if (!is_null($survey_indicator_pending_requested_change_entity)) {
      $survey_indicator_pending_requested_change = $entityTypeManager->getStorage('node')->load($survey_indicator_pending_requested_change_entity);
      $survey_indicator_pending_requested_change_data = SurveyIndicatorRequestChangeHelper::getSurveyIndicatorRequestedChangeData($entityTypeManager, $dateFormatter, $survey_indicator_pending_requested_change);

      $survey_indicator_pending_requested_change_author_role = $survey_indicator_pending_requested_change_data['requested_user']['role']['weight'];
    }

    $can_discard = FALSE;
    if (UserPermissionsHelper::isAdmin($entityTypeManager, $currentUser)) {
      $can_discard |= ($indicator_state == 5 && $survey_indicator_pending_requested_change_author_role == 5 &&
                         !empty($country_survey_data['submitted_user']) &&
                         !$is_assigned);
    }
    elseif (UserPermissionsHelper::isPoC($entityTypeManager, $currentUser)) {
      if (UserPermissionsHelper::isPrimaryPoC($entityTypeManager, $currentUser)) {
        $can_discard |= ($indicator_state == 5 && $survey_indicator_pending_requested_change_author_role == 6 &&
                         $country_survey_data['completed'] &&
                         !$is_assigned);
      }
      else {
        $can_discard |= ($indicator_state == 5 && $survey_indicator_pending_requested_change_author_role == 7 &&
                           !$is_assigned);
      }
    }

    $can_discard &= (!is_null($survey_indicator_pending_requested_change));

    if (!$can_discard) {
      return [
        'type' => 'error',
        'msg' => 'Requested changes cannot be discarded as the requested action is not allowed!',
        'status' => 405,
      ];
    }

    $survey_indicator_pending_requested_change->delete();

    $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'indicator_states', $survey_indicator_pending_requested_change_data['indicator_prev_state']['id'], 'field_state_id');

    $survey_indicator->set('field_indicator_state', $term->id());
    $survey_indicator->set('field_indicator_dashboard_state', $term->id());
    $survey_indicator->set('field_assignee', $survey_indicator_pending_requested_change_data['indicator_prev_assignee']['id']);
    $survey_indicator->save();

    return [
      'type' => 'success',
      'msg' => 'Indicator requested changes have been successfully discarded!',
    ];
  }

  /**
   * Function to submit country survey requested changes.
   */
  public static function submitCountrySurveyRequestedChanges($entityTypeManager, $currentUser, $dateFormatter, $country_survey_data) {
    $pending_requested_change_entities = SurveyIndicatorRequestChangeHelper::getCountrySurveyRequestedChangeEntities($entityTypeManager, $currentUser, $country_survey_data['id'], [1]);

    $pending_requested_changes = $entityTypeManager->getStorage('node')->loadMultiple($pending_requested_change_entities);
    foreach ($pending_requested_changes as $pending_requested_change) {
      $pending_requested_change_data = SurveyIndicatorRequestChangeHelper::getSurveyIndicatorRequestedChangeData($entityTypeManager, $dateFormatter, $pending_requested_change);

      $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'survey_indicator_rc_states', 2, 'field_state_id');

      $pending_requested_change->set('field_survey_indicator_rc_state', $term->id());
      $pending_requested_change->save();

      $survey_indicator_entity = SurveyIndicatorHelper::getSurveyIndicatorEntity($entityTypeManager, $country_survey_data['id'], $pending_requested_change_data['indicator']['id']);

      $survey_indicator = $entityTypeManager->getStorage('node')->load($survey_indicator_entity);

      $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'indicator_states', 5, 'field_state_id');

      $survey_indicator->set('field_indicator_dashboard_state', $term->id());
      $survey_indicator->save();
    }
  }

}
