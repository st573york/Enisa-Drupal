<?php

namespace Drupal\country_survey_management\Helper;

use Drupal\user_management\Helper\UserPermissionsHelper;
use Drupal\general_management\Helper\ValidationHelper;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Helper class for managing permissions to survey indicators.
 */
class SurveyIndicatorAccessHelper {
  const ERROR_NOT_ALLOWED = 'Indicator cannot be updated as the requested action is not allowed!';

  /**
   * Validates the inputs for survey indicator actions.
   */
  public static function validateInputs($dateFormatter, $time, $country_survey_data, $inputs) {
    $errors = ValidationHelper::getValidationErrors($inputs, [
      'assignee' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
      'deadline' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
    ]);

    $deadline = strtotime($inputs['deadline']);
    $today = $dateFormatter->format($time->getCurrentTime(), 'custom', 'd-m-Y');
    $survey_deadline = $dateFormatter->format(strtotime($country_survey_data['survey']['deadline']), 'custom', 'd-m-Y');

    if (!filter_var($inputs['requested_changes'], FILTER_VALIDATE_BOOLEAN) &&
          $deadline &&
          ($deadline < strtotime($today) ||
           $deadline > strtotime($survey_deadline))) {
      $errors->add(new ConstraintViolation(
            'The deadline field must be between ' . $today . ' and ' . $survey_deadline . '.',
            '',
            [],
            '',
            'deadline',
            $inputs['deadline']
        ));
    }

    return ValidationHelper::formatValidationErrors($errors);
  }

  /**
   * Checks if user has permissions to view or edit survey indicators.
   */
  public static function hasSurveyIndicatorPermissions($entityTypeManager, $currentUser, $assignee) {
    if (!UserPermissionsHelper::isAdmin($entityTypeManager, $currentUser) &&
          !UserPermissionsHelper::isPoC($entityTypeManager, $currentUser) &&
          $assignee != $currentUser->id()) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Checks if the indicator can be edited based on its state.
   */
  public static function canEditIndicator($indicator_state, &$resp) {
    $can_update = (in_array($indicator_state, [1, 2, 3, 5]));

    if (!$can_update) {
      $resp = [
        'type' => 'error',
        'msg' => self::ERROR_NOT_ALLOWED,
        'status' => 405,
      ];

      return FALSE;
    }

    return TRUE;
  }

  /**
   * Checks if the indicator can be updated based on the requested action.
   */
  public static function canLoadResetCountrySurveyIndicatorAnswers($currentUser, $survey_indicator_data) {
    return ($survey_indicator_data['assignee']['id'] == $currentUser->id());
  }

  /**
   * Checks if user can update indicator data based on role and action.
   */
  public static function canUpdateSurveyIndicatorData($entityTypeManager, $currentUser, $inputs) {
    if ((UserPermissionsHelper::isPoC($entityTypeManager, $currentUser) && $inputs['action'] == 'edit') ||
          (UserPermissionsHelper::isPoC($entityTypeManager, $currentUser)&& $inputs['action'] == 'approve') ||
          (UserPermissionsHelper::isAdmin($entityTypeManager, $currentUser) && $inputs['action'] == 'final_approve') ||
          (UserPermissionsHelper::isAdmin($entityTypeManager, $currentUser) && $inputs['action'] == 'unapprove')) {
      return TRUE;
    }

    return FALSE;
  }

}
