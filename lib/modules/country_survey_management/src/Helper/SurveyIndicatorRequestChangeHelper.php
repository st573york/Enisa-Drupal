<?php

namespace Drupal\country_survey_management\Helper;

use Drupal\general_management\Helper\GeneralHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorHelper;
use Drupal\user_management\Helper\UserHelper;
use Drupal\user_management\Helper\UserPermissionsHelper;
use Drupal\general_management\Helper\ValidationHelper;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Helper class for managing survey indicator request changes.
 */
class SurveyIndicatorRequestChangeHelper {

  /**
   * Validates the inputs for survey indicator request changes.
   */
  public static function validateInputs($inputs) {
    $errors = ValidationHelper::getValidationErrors($inputs, [
      'changes' => new NotBlank([
        'message' => 'The changes field is required.',
      ]),
      'deadline' => new NotBlank([
        'message' => 'The deadline field is required.',
      ]),
    ]);

    return ValidationHelper::formatValidationErrors($errors);
  }

  /**
   * Checks if the current user can request changes to an indicator.
   */
  public static function canRequestChangesCountrySurveyIndicator($entityTypeManager, $currentUser) {
    if (UserPermissionsHelper::isPoC($entityTypeManager, $currentUser) ||
          UserPermissionsHelper::isAdmin($entityTypeManager, $currentUser)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if the current user can discard requested changes.
   */
  public static function canDiscardRequestedChangesCountrySurveyIndicator($entityTypeManager, $currentUser) {
    if (UserPermissionsHelper::isPoC($entityTypeManager, $currentUser) ||
          UserPermissionsHelper::isAdmin($entityTypeManager, $currentUser)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if the current user can submit changes.
   */
  public static function canSubmitCountrySurveyRequestedChanges($entityTypeManager, $currentUser) {
    if (UserPermissionsHelper::isPoC($entityTypeManager, $currentUser) ||
          UserPermissionsHelper::isAdmin($entityTypeManager, $currentUser)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Gets the country survey reference entity data and adds it to the node data.
   */
  private static function getCountrySurveyReferenceEntity($entityTypeManager, $dateFormatter, $node, &$node_data) {
    if ($node->hasField('field_country_survey') &&
          !$node->get('field_country_survey')->isEmpty()) {
      $country_survey = $node->get('field_country_survey')->entity;

      $node_data['country_survey'] = CountrySurveyHelper::getCountrySurveyData($entityTypeManager, $dateFormatter, $country_survey);
    }
  }

  /**
   * Gets the indicator reference entity data and adds it to the node data.
   */
  private static function getIndicatorReferenceEntity($node, &$node_data) {
    if ($node->hasField('field_indicator') &&
          !$node->get('field_indicator')->isEmpty()) {
      $indicator = $node->get('field_indicator')->entity;

      $node_data['indicator'] = IndicatorHelper::getIndicatorData($indicator);
    }
  }

  /**
   * Gets the requested user reference entity data and adds it to the node data.
   */
  private static function getRequestedUserReferenceEntity($entityTypeManager, $dateFormatter, $node, &$node_data) {
    if ($node->hasField('field_requested_user') &&
          !$node->get('field_requested_user')->isEmpty()) {
      $requested_user = $node->get('field_requested_user')->entity;

      $node_data['requested_user'] = UserHelper::getUserData($entityTypeManager, $dateFormatter, $requested_user);
    }
  }

  /**
   * Gets the assignee reference entity data and adds it to the node data.
   */
  private static function getAssigneeReferenceEntity($entityTypeManager, $dateFormatter, $node, &$node_data) {
    if ($node->hasField('field_assignee') &&
          !$node->get('field_assignee')->isEmpty()) {
      $assignee = $node->get('field_assignee')->entity;

      $node_data['assignee'] = UserHelper::getUserData($entityTypeManager, $dateFormatter, $assignee);
    }
  }

  /**
   * Gets the state reference entity data and adds it to the node data.
   */
  private static function getStateReferenceEntity($node, &$node_data) {
    if ($node->hasField('field_survey_indicator_rc_state') &&
          !$node->get('field_survey_indicator_rc_state')->isEmpty()) {
      $state = $node->get('field_survey_indicator_rc_state')->entity;

      if (!is_null($state)) {
        $node_data['state'] = [
          'id' => (int) $state->field_state_id->value ?? '',
          'name' => $state->getName(),
        ];
      }
    }
  }

  /**
   * Gets the indicator previous state data and adds it to the node data.
   */
  private static function getIndicatorPrevStateReferenceEntity($node, &$node_data) {
    if ($node->hasField('field_indicator_prev_state') &&
          !$node->get('field_indicator_prev_state')->isEmpty()) {
      $indicator_prev_state = $node->get('field_indicator_prev_state')->entity;

      if (!is_null($indicator_prev_state)) {
        $node_data['indicator_prev_state'] = [
          'id' => (int) $indicator_prev_state->field_state_id->value ?? '',
          'name' => $indicator_prev_state->getName(),
        ];
      }
    }
  }

  /**
   * Gets the indicator previous assignee and adds it to the node data.
   */
  private static function getIndicatorPrevAssigneeReferenceEntity($entityTypeManager, $dateFormatter, $node, &$node_data) {
    if ($node->hasField('field_indicator_prev_assignee') &&
          !$node->get('field_indicator_prev_assignee')->isEmpty()) {
      $indicator_prev_assignee = $node->get('field_indicator_prev_assignee')->entity;

      $node_data['indicator_prev_assignee'] = UserHelper::getUserData($entityTypeManager, $dateFormatter, $indicator_prev_assignee);
    }
  }

  /**
   * Gets the survey indicator requested change data for a given node.
   */
  public static function getSurveyIndicatorRequestedChangeData($entityTypeManager, $dateFormatter, $node) {
    if (!$node) {
      return [];
    }

    $node_data = [
      'id' => $node->id(),
      'title' => $node->getTitle(),
      'description' => $node->field_description->value ?? '',
      'changes' => ($node->field_changes->value) ? urldecode($node->field_changes->value) : '',
      'country_survey' => '',
      'indicator' => '',
      'deadline' => ($node->field_deadline->value) ? $dateFormatter->format(strtotime($node->field_deadline->value), 'custom', 'd-m-Y') : '',
      'requested_user' => '',
      'assignee' => '',
      'requested_date' => ($node->field_requested_date->value) ? $dateFormatter->format($node->field_requested_date->value, 'custom', 'Y-m-d H:i:s') : '',
      'answered_date' => ($node->field_answered_date->value) ? $dateFormatter->format($node->field_answered_date->value, 'custom', 'Y-m-d H:i:s') : '',
      'state' => '',
      'indicator_prev_state' => '',
      'indicator_prev_assignee' => '',
    ];

    self::getCountrySurveyReferenceEntity($entityTypeManager, $dateFormatter, $node, $node_data);
    self::getIndicatorReferenceEntity($node, $node_data);
    self::getRequestedUserReferenceEntity($entityTypeManager, $dateFormatter, $node, $node_data);
    self::getAssigneeReferenceEntity($entityTypeManager, $dateFormatter, $node, $node_data);
    self::getStateReferenceEntity($node, $node_data);
    self::getIndicatorPrevStateReferenceEntity($node, $node_data);
    self::getIndicatorPrevAssigneeReferenceEntity($entityTypeManager, $dateFormatter, $node, $node_data);

    return $node_data;
  }

  /**
   * Gets the country survey requested change entities.
   */
  public static function getCountrySurveyRequestedChangeEntities($entityTypeManager, $currentUser, $country_survey, $states = [], $include_role = TRUE) {
    $requested_users = [];

    if ($include_role) {
      $roles = [];
      if (UserPermissionsHelper::isAdmin($entityTypeManager, $currentUser)) {
        $roles = UserPermissionsHelper::getRolesBetweenWeights($entityTypeManager, 'id', 5, 5);
      }
      elseif (UserPermissionsHelper::isPoC($entityTypeManager, $currentUser)) {
        $roles = (UserPermissionsHelper::isPrimaryPoC($entityTypeManager, $currentUser)) ? UserPermissionsHelper::getRolesBetweenWeights($entityTypeManager, 'id', 6, 6) : UserPermissionsHelper::getRolesBetweenWeights($entityTypeManager, 'id', 7, 7);
      }

      if (!empty($roles)) {
        $query = $entityTypeManager
          ->getStorage('user')
          ->getQuery()
          ->accessCheck(FALSE)
          ->condition('roles', $roles, 'IN')
          ->condition('status', 1);

        $requested_users = $query->execute();
      }
    }

    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'survey_indicator_rc')
      ->condition('field_country_survey', $country_survey)
      ->condition('status', 1);

    if (!empty($requested_users)) {
      $query->condition('field_requested_user', $requested_users, 'IN');
    }

    if (!empty($states)) {
      $rc_states = [];
      foreach ($states as $state) {
        $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'survey_indicator_rc_states', $state, 'field_state_id');

        array_push($rc_states, $term->id());
      }

      $query->condition('field_survey_indicator_rc_state', $rc_states, 'IN');
    }

    return $query->execute();
  }

  /**
   * Gets the latest country survey requested change entity.
   */
  public static function getLatestCountrySurveyRequestedChangeEntity($entityTypeManager, $country_survey) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'survey_indicator_rc')
      ->condition('field_country_survey', $country_survey)
      ->condition('status', 1);

    $requested_change = $query->sort('field_deadline', 'DESC')->execute();

    if (!empty($requested_change)) {
      return reset($requested_change);
    }

    return NULL;
  }

  /**
   * Gets the survey indicator pending requested change entity.
   */
  public static function getSurveyIndicatorPendingRequestedChangeEntity($entityTypeManager, $country_survey, $indicator) {
    $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'survey_indicator_rc_states', 1, 'field_state_id');

    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'survey_indicator_rc')
      ->condition('field_country_survey', $country_survey)
      ->condition('field_indicator', $indicator)
      ->condition('field_survey_indicator_rc_state', $term->id())
      ->condition('status', 1);

    $requested_change = $query->execute();

    if (!empty($requested_change)) {
      return reset($requested_change);
    }

    return NULL;
  }

  /**
   * Gets the latest requested change entity for a survey indicator.
   */
  public static function getSurveyIndicatorLatestRequestedChangeEntity($entityTypeManager, $country_survey, $indicator, $states = []) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'survey_indicator_rc')
      ->condition('field_country_survey', $country_survey)
      ->condition('field_indicator', $indicator)
      ->condition('status', 1);

    $requested_change = $query->sort('nid', 'DESC')->execute();

    if (!empty($states)) {
      $rc_states = [];
      foreach ($states as $state) {
        $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'survey_indicator_rc_states', $state, 'field_state_id');

        array_push($rc_states, $term->id());
      }

      $query->condition('field_survey_indicator_rc_state', $rc_states, 'IN');
    }

    if (!empty($requested_change)) {
      return reset($requested_change);
    }

    return NULL;
  }

  /**
   * Gets the survey indicator requested change entities.
   */
  public static function getSurveyIndicatorRequestedChangeEntities($entityTypeManager, $country_survey, $indicator, $states = []) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'survey_indicator_rc')
      ->condition('field_country_survey', $country_survey)
      ->condition('field_indicator', $indicator)
      ->condition('status', 1);

    if (!empty($states)) {
      $rc_states = [];
      foreach ($states as $state) {
        $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'survey_indicator_rc_states', $state, 'field_state_id');

        array_push($rc_states, $term->id());
      }

      $query->condition('field_survey_indicator_rc_state', $rc_states, 'IN');
    }

    return $query->execute();
  }

}
