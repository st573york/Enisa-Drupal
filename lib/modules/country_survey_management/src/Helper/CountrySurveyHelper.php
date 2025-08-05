<?php

namespace Drupal\country_survey_management\Helper;

use Drupal\general_management\Helper\ConstantHelper;
use Drupal\survey_management\Helper\SurveyHelper;
use Drupal\user_management\Helper\UserHelper;
use Drupal\user_management\Helper\UserPermissionsHelper;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Component\Utility\UrlHelper;

/**
 * Class Country Survey Helper.
 */
class CountrySurveyHelper {

  /**
   * Function getPageData.
   */
  public static function getPageData(
    $entityTypeManager,
    $currentUser,
    $requestStack,
    $assignee_country_survey_data,
  ) {
    $session = $requestStack->getSession();
    $request = $requestStack->getCurrentRequest();
    $referer = $request->headers->get('referer');

    $previous = UrlHelper::parse($referer);
    if (!preg_match('/\/country\/survey\/view/', $referer)) {
      $session->set('PREVIOUS_URL', $previous['path']);
    }

    $previous = $session->get('PREVIOUS_URL');
    $breadcrumb_list = [];
    $return_to_label = '';

    $data = [
      'previous' => $previous,
      'breadcrumb_list' => $breadcrumb_list,
      'return_to_label' => &$return_to_label,
    ];

    if (preg_match('/\/survey\/dashboard\/management\/\d+/', $previous)) {
      array_push($data['breadcrumb_list'], [
        'label' => new TranslatableMarkup('Surveys'),
        'link' => '/survey/management',
      ]);

      array_push($data['breadcrumb_list'], [
        'label' => new TranslatableMarkup('Survey Dashboard') . ' - ' . $assignee_country_survey_data['survey']['title'],
        'link' => $previous,
      ]);

      $return_to_label = new TranslatableMarkup('Dashboard');
    }
    elseif (UserPermissionsHelper::isAdmin($entityTypeManager, $currentUser) && preg_match('/\/country\/survey\/dashboard\/management\/\d+/', $previous)) {
      array_push($data['breadcrumb_list'], [
        'label' => new TranslatableMarkup('Surveys'),
        'link' => '/survey/management',
      ]);

      array_push($data['breadcrumb_list'], [
        'label' => new TranslatableMarkup('Survey Dashboard') . ' - ' . $assignee_country_survey_data['survey']['title'],
        'link' => '/survey/dashboard/management/' . $assignee_country_survey_data['survey']['id'],
      ]);

      array_push($data['breadcrumb_list'], [
        'label' => new TranslatableMarkup('Survey Dashboard') . ' - ' . $assignee_country_survey_data['country']['name'],
        'link' => $previous,
      ]);

      $return_to_label = new TranslatableMarkup('Dashboard');
    }
    elseif (UserPermissionsHelper::isPoC($entityTypeManager, $currentUser) && preg_match('/\/country\/survey\/dashboard\/management\/\d+/', $previous)) {
      array_push($data['breadcrumb_list'], [
        'label' => new TranslatableMarkup('Surveys'),
        'link' => '/survey/user/management',
      ]);

      array_push($data['breadcrumb_list'], [
        'label' => new TranslatableMarkup('Survey Dashboard') . ' - ' . $assignee_country_survey_data['survey']['title'],
        'link' => $previous,
      ]);

      $return_to_label = new TranslatableMarkup('Dashboard');
    }
    elseif (preg_match('/\/survey\/user\/management/', $previous)) {
      array_push($data['breadcrumb_list'], [
        'label' => new TranslatableMarkup('Surveys'),
        'link' => $previous,
      ]);

      $return_to_label = new TranslatableMarkup('Surveys');
    }
    elseif (preg_match('/\/index\/survey\/configuration\/management/', $previous)) {
      array_push($data['breadcrumb_list'], [
        'label' => new TranslatableMarkup('Index & Survey Configuration'),
        'link' => $previous,
      ]);

      $return_to_label = new TranslatableMarkup('Index & Survey Configuration');
    }

    return $data;
  }

  /**
   * Function getCountrySurveyDataReferenceEntities.
   */
  public static function getCountrySurveyDataReferenceEntities($entityTypeManager, $dateFormatter, $node, &$node_data) {
    if ($node->hasField('field_default_assignee') &&
          !$node->get('field_default_assignee')->isEmpty()) {
      $default_assignee = $node->get('field_default_assignee')->entity;

      $node_data['default_assignee'] = UserHelper::getUserData($entityTypeManager, $dateFormatter, $default_assignee);
    }

    if ($node->hasField('field_submitted_user') &&
          !$node->get('field_submitted_user')->isEmpty()) {
      $submitted_user = $node->get('field_submitted_user')->entity;

      $node_data['submitted_user'] = UserHelper::getUserData($entityTypeManager, $dateFormatter, $submitted_user);
    }

    if ($node->hasField('field_approved_user') &&
          !$node->get('field_approved_user')->isEmpty()) {
      $approved_user = $node->get('field_approved_user')->entity;

      $node_data['approved_user'] = UserHelper::getUserData($entityTypeManager, $dateFormatter, $approved_user);
    }

    if ($node->hasField('field_survey') &&
          !$node->get('field_survey')->isEmpty()) {
      $survey = $node->get('field_survey')->entity;

      $node_data['survey'] = SurveyHelper::getSurveyData($dateFormatter, $survey);
    }

    if ($node->hasField('field_country') &&
          !$node->get('field_country')->isEmpty()) {
      $country = $node->get('field_country')->entity;

      if (!is_null($country)) {
        $node_data['country'] = [
          'id' => $country->id(),
          'name' => $country->getName(),
          'iso' => $country->field_iso->value,
        ];
      }
    }
  }

  /**
   * Function getCountrySurveyData.
   */
  public static function getCountrySurveyData($entityTypeManager, $dateFormatter, $node) {
    if (!$node) {
      return [];
    }

    $node_data = [
      'id' => $node->id(),
      'title' => $node->getTitle(),
      'description' => $node->field_description->value ?? '',
      'default_assignee' => '',
      'completed' => filter_var($node->field_completed->value, FILTER_VALIDATE_BOOLEAN) ?? '',
      'submitted_user' => '',
      'submitted_date' => ($node->field_submitted_date->value) ? $dateFormatter->format($node->field_submitted_date->value, 'custom', 'Y-m-d H:i:s') : '',
      'rc_submitted_date' => ($node->field_rc_submitted_date->value) ? $dateFormatter->format($node->field_rc_submitted_date->value, 'custom', 'Y-m-d H:i:s') : '',
      'approved_user' => '',
      'survey' => '',
      'country' => '',
    ];

    self::getCountrySurveyDataReferenceEntities($entityTypeManager, $dateFormatter, $node, $node_data);

    return $node_data;
  }

  /**
   * Function getCountrySurveyDatatableStatusData.
   */
  public static function getCountrySurveyDatatableStatusData($country_survey_data, &$data) {
    if (!empty($country_survey_data['approved_user'])) {
      $data['status'] = 'Approved';
      $data['style'] = 'positive-with-tooltip';
      $data['info'] = 'Survey has been approved by ' . ConstantHelper::USER_GROUP . ' and is considered closed for this year.';
    }
    elseif (!empty($country_survey_data['submitted_user'])) {
      $data['status'] = 'Submitted';
      $data['style'] = 'approved-with-tooltip';
      $data['info'] = 'Survey has been submitted by the MS and is under review by ' . ConstantHelper::USER_GROUP . '. Clarifications or changes may be requested.';
    }
    elseif ($data['percentage_processed']) {
      $data['status'] = 'In progress';
      $data['style'] = 'positive-invert-with-tooltip';
      $data['info'] = 'Survey completion or revision following request by ' . ConstantHelper::USER_GROUP . ' is in progress.';
    }
  }

  /**
   * Function getCountrySurveyAdditionalData.
   */
  public static function getCountrySurveyAdditionalData($entityTypeManager, $dateFormatter, $country_survey_data) {
    $data = [
      'percentage_in_progress' => NULL,
      'percentage_approved' => NULL,
      'status' => NULL,
      'style' => NULL,
      'info' => NULL,
    ];

    $survey_indicator_entities = SurveyIndicatorHelper::getSurveyIndicatorEntities($entityTypeManager, ['country_survey' => $country_survey_data['id']]);
    if (!empty($survey_indicator_entities)) {
      $indicators = [];
      $indicators_in_progress = [];
      $indicators_processed = [];
      $indicators_approved = [];
      $indicators_final_approved = [];

      $survey_indicators = $entityTypeManager->getStorage('node')->loadMultiple($survey_indicator_entities);
      foreach ($survey_indicators as $survey_indicator) {
        $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($entityTypeManager, $dateFormatter, $survey_indicator);

        $indicator = $survey_indicator_data['indicator'];
        $indicator_state = $survey_indicator_data['state']['id'];

        array_push($indicators, $indicator['id']);

        if (in_array($indicator_state, [2, 3, 5])) {
          array_push($indicators_in_progress, $indicator['id']);
        }

        if (($indicator_state == 2 && $survey_indicator_data['assignee']['id'] == $country_survey_data['default_assignee']['id']) ||
              $indicator_state > 5) {
          array_push($indicators_processed, $indicator['id']);
          array_push($indicators_approved, $indicator['id']);

          if ($indicator_state == 7) {
            array_push($indicators_final_approved, $indicator['id']);
          }
        }
      }

      $data['indicators'] = count($indicators);
      $data['indicators_processed'] = count($indicators_processed);
      $data['indicators_in_progress'] = count($indicators_in_progress);
      $data['indicators_approved'] = count($indicators_approved);
      $data['indicators_final_approved'] = count($indicators_final_approved);
      $data['percentage_processed'] = ($data['indicators']) ? round(($data['indicators_processed'] / $data['indicators']) * 100) : 0;
      $data['percentage_in_progress'] = ($data['indicators']) ? (int) round(($data['indicators_in_progress'] / $data['indicators']) * 100) : 0;
      $data['percentage_approved'] = ($data['indicators']) ? (int) round(($data['indicators_approved'] / $data['indicators']) * 100) : 0;
      $data['percentage_final_approved'] = ($data['indicators']) ? round(($data['indicators_final_approved'] / $data['indicators']) * 100) : 0;
      $data['primary_poc'] = $country_survey_data['default_assignee']['name'] . (!$country_survey_data['default_assignee']['is_active'] ? ' ' . ConstantHelper::USER_INACTIVE : '');

      self::getCountrySurveyDatatableStatusData($country_survey_data, $data);

      $latest_country_survey_requested_change_entity = SurveyIndicatorRequestChangeHelper::getLatestCountrySurveyRequestedChangeEntity($entityTypeManager, $country_survey_data['id']);
      if (!is_null($latest_country_survey_requested_change_entity)) {
        $latest_country_survey_requested_change = $entityTypeManager->getStorage('node')->load($latest_country_survey_requested_change_entity);
        $data['latest_requested_change'] = SurveyIndicatorRequestChangeHelper::getSurveyIndicatorRequestedChangeData($entityTypeManager, $dateFormatter, $latest_country_survey_requested_change);
      }
    }

    return $data;
  }

  /**
   * Function getCountrySurveyEntity.
   */
  public static function getCountrySurveyEntity($entityTypeManager, $survey, $country) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'country_survey')
      ->condition('field_survey', $survey)
      ->condition('field_country', $country)
      ->condition('status', 1);

    $country_survey = $query->execute();

    if (!empty($country_survey)) {
      return reset($country_survey);
    }

    return NULL;
  }

  /**
   * Function getCountrySurveyEntities.
   */
  public static function getCountrySurveyEntities($entityTypeManager, $countries, $country_survey_id = NULL) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'country_survey')
      ->condition('field_country', $countries, 'IN')
      ->condition('status', 1);

    if (!is_null($country_survey_id)) {
      $query->condition('nid', $country_survey_id);
    }

    return $query->sort('field_country')->execute();
  }

  /**
   * Function getAssigneeCountrySurveysData.
   */
  public static function getAssigneeCountrySurveysData($entityTypeManager, $currentUser, $dateFormatter, $country_survey_id = NULL) {
    $countries = UserPermissionsHelper::getUserCountries($entityTypeManager, $currentUser, $dateFormatter, 'id');

    $country_survey_entities = self::getCountrySurveyEntities($entityTypeManager, $countries, $country_survey_id);
    $country_surveys = $entityTypeManager->getStorage('node')->loadMultiple($country_survey_entities);

    $data = [];

    foreach ($country_surveys as $country_survey) {
      $survey_indicator_entities = SurveyIndicatorHelper::getSurveyIndicatorEntities($entityTypeManager, ['country_survey' => $country_survey->id()]);

      if (!empty($survey_indicator_entities)) {
        $survey_indicators = $entityTypeManager->getStorage('node')->loadMultiple($survey_indicator_entities);

        $indicators_data = SurveyIndicatorDataHelper::getSurveyIndicatorsData($entityTypeManager, $currentUser, $dateFormatter, $survey_indicators);

        $assignee_country_survey_data = [
          'survey_started' => $indicators_data['started'],
          'indicators_percentage' => $indicators_data['percentage'],
          'indicators_assigned' => $indicators_data['assigned'],
          'indicators_assigned_exact' => $indicators_data['assigned_exact'],
          'indicators_submitted' => $indicators_data['submitted'],
          'indicators_approved' => $indicators_data['approved'],
        ];

        $country_survey = $entityTypeManager->getStorage('node')->load($country_survey->id());
        $country_survey_data = CountrySurveyHelper::getCountrySurveyData($entityTypeManager, $dateFormatter, $country_survey);

        $assignee_country_survey_data += $country_survey_data;

        array_push($data, $assignee_country_survey_data);
      }
    }

    return (!is_null($country_survey_id) && !empty($data)) ? $data[0] : $data;
  }

  /**
   * Function getCountrySurveyIndicators.
   */
  public static function getCountrySurveyIndicators($entityTypeManager, $currentUser, $dateFormatter, $country_survey_id) {
    $survey_indicator_entities = SurveyIndicatorHelper::getSurveyIndicatorEntities($entityTypeManager, ['country_survey' => $country_survey_id]);
    $survey_indicators = $entityTypeManager->getStorage('node')->loadMultiple($survey_indicator_entities);

    $indicators = [];
    foreach ($survey_indicators as $survey_indicator) {
      $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($entityTypeManager, $dateFormatter, $survey_indicator);

      $indicator = $survey_indicator_data['indicator'];

      $assignee = $entityTypeManager->getStorage('user')->load($survey_indicator_data['assignee']['id']);
      $assignee_data = UserHelper::getUserData($entityTypeManager, $dateFormatter, $assignee);
      $assignee_info = '';
      if (!$assignee_data['is_active']) {
        $assignee_info = ConstantHelper::USER_INACTIVE;
      }
      elseif ($assignee_data['id'] == $currentUser->id()) {
        $assignee_info = '(you)';
      }
      if (!empty($assignee_info)) {
        $assignee_data['name'] .= ' ' . $assignee_info;
      }

      $survey_indicator_requested_change_entities = SurveyIndicatorRequestChangeHelper::getSurveyIndicatorRequestedChangeEntities($entityTypeManager, $country_survey_id, $indicator['id']);

      array_push($indicators, [
        'id' => $indicator['id'],
        'identifier' => $indicator['identifier'],
        'number' => $indicator['order'],
        'name' => $indicator['name'],
        'state' => $survey_indicator_data['state']['id'],
        'dashboard_state' => $survey_indicator_data['dashboard_state']['id'],
        'assignee' => $assignee_data,
        'deadline' => $survey_indicator_data['deadline'],
        'requested_changes' => (!empty($survey_indicator_requested_change_entities)) ? TRUE : FALSE,
        'country_survey' => $survey_indicator_data['country_survey'],
      ]);
    }

    return $indicators;
  }

}
