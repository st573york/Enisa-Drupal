<?php

namespace Drupal\survey_management\Helper;

use Drupal\audit_management\Helper\AuditHelper;
use Drupal\general_management\Helper\GeneralHelper;
use Drupal\index_management\Helper\IndexHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorHelper;
use Drupal\user_management\Helper\UserHelper;
use Drupal\general_management\Helper\ValidationHelper;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Class Survey Helper.
 */
class SurveyHelper {

  /**
   * Function validateInputs.
   */
  public static function validateInputs($entityTypeManager, $inputs) {
    $errors = ValidationHelper::getValidationErrors($inputs, [
      'survey_configuration_id' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
      'title' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
      'deadline' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
    ]);

    if (!self::isSurveyUnique($entityTypeManager, $inputs)) {
      $errors->add(new ConstraintViolation(
            'The title has already been taken.',
            '',
            [],
            '',
            'title',
            $inputs['title']
        ));
    }

    return ValidationHelper::formatValidationErrors($errors);
  }

  /**
   * Function isSurveyUnique.
   */
  public static function isSurveyUnique($entityTypeManager, $inputs) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'survey')
      ->condition('title', $inputs['title']);

    if (isset($inputs['id'])) {
      $query->condition('nid', $inputs['id'], '<>');
    }

    $survey = $query->execute();

    if (!empty($survey)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Function getSurveyData.
   */
  public static function getSurveyData($dateFormatter, $node) {
    if (!$node) {
      return [];
    }

    $author = $node->getOwner();

    $node_data = [
      'id' => $node->id(),
      'index' => '',
      'title' => $node->getTitle(),
      'description' => ($node->field_description->value) ? urldecode($node->field_description->value) : '',
      'year' => $node->field_year->value ?? '',
      'deadline' => ($node->field_deadline->value) ? $dateFormatter->format(strtotime($node->field_deadline->value), 'custom', 'Y-m-d H:i:s') : '',
      'created_by' => $author->field_full_name->value ?? '',
      'assigned_indicators' => array_column($node->get('field_assigned_indicators')->getValue(), 'target_id'),
      'assigned_users' => array_column($node->get('field_assigned_users')->getValue(), 'target_id'),
      'status' => ($node->status->value) ? 'Published' : 'Draft',
    ];

    if ($node->hasField('field_index') &&
          !$node->get('field_index')->isEmpty()) {
      $index = $node->get('field_index')->entity;

      $node_data['index'] = IndexHelper::getIndexData($dateFormatter, $index);
    }

    return $node_data;
  }

  /**
   * Function getSurveysData.
   */
  public static function getSurveysData($entityTypeManager, $dateFormatter, $entities) {
    $surveys = $entityTypeManager->getStorage('node')->loadMultiple($entities);

    $data = [];
    foreach ($surveys as $survey) {
      $survey_data = self::getSurveyData($dateFormatter, $survey);

      array_push($data, $survey_data);
    }

    return $data;
  }

  /**
   * Function getSurveyEntities.
   */
  public static function getSurveyEntities($entityTypeManager) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'survey');

    return $query->execute();
  }

  /**
   * Function getPublishedSurveyEntities.
   */
  public static function getPublishedSurveyEntities($entityTypeManager) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'survey')
      ->condition('status', 1);

    return $query->sort('field_year', 'DESC')->execute();
  }

  /**
   * Function getLastPublishedSurveyEntity.
   */
  public static function getLastPublishedSurveyEntity($entityTypeManager, $year) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'survey')
      ->condition('field_year', $year, '<')
      ->condition('status', 1);

    $survey = $query->sort('field_year', 'DESC')->execute();

    if (!empty($survey)) {
      return reset($survey);
    }

    return NULL;
  }

  /**
   * Function getExistingPublishedSurveyForYearEntity.
   */
  public static function getExistingPublishedSurveyForYearEntity($entityTypeManager, $year) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'survey')
      ->condition('field_year', $year)
      ->condition('status', 1);

    $survey = $query->execute();

    if (!empty($survey)) {
      return reset($survey);
    }

    return NULL;
  }

  /**
   * Function getSurveyUsers.
   */
  public static function getSurveyUsers($entityTypeManager, $dateFormatter, $survey_data) {
    $query = $entityTypeManager
      ->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('roles', 'primary_poc')
      ->condition('status', 1);

    $entities = $query->execute();

    $users = $entityTypeManager->getStorage('user')->loadMultiple($entities);

    $data = [];
    foreach ($users as $user) {
      $user_data = UserHelper::getUserData($entityTypeManager, $dateFormatter, $user);
      $user_data['notified'] = (in_array($user_data['id'], $survey_data['assigned_users'])) ? TRUE : FALSE;

      array_push($data, $user_data);
    }

    return $data;
  }

  /**
   * Function storeSurvey.
   */
  public static function storeSurvey($services, $inputs) {
    $data = [
      'type' => 'survey',
      'field_index' => $inputs['survey_configuration_id'],
      'title' => $inputs['title'],
      'field_deadline' => GeneralHelper::dateFormat($inputs['deadline']),
      'field_description' => $inputs['description'],
      'field_year' => $inputs['year'],
      'field_assigned_indicators' => IndicatorHelper::getIndicatorEntities($services['entityTypeManager'], $inputs['year'], 'survey'),
      'status' => 0,
    ];

    $survey = $services['entityTypeManager']->getStorage('node')->create($data);
    $survey->save();

    $changes = AuditHelper::getChangesOnCreate($survey);

    AuditHelper::transformSurvey($services, $changes);
    AuditHelper::setCustomEvent(
      $services,
      [
        'action' => 'created',
        'entity' => $inputs['title'],
        'description' => 'The survey has been created',
        'new_values' => json_encode($changes['new_values']),
      ],
    );
  }

  /**
   * Function updateSurvey.
   */
  public static function updateSurvey($services, $survey, $survey_data, $inputs) {
    $replicate_survey = $survey->createDuplicate();

    $survey->set('field_index', $inputs['survey_configuration_id']);
    $survey->set('title', $inputs['title']);
    $survey->set('field_deadline', GeneralHelper::dateFormat($inputs['deadline']));
    $survey->set('field_description', $inputs['description']);
    $survey->set('field_year', $inputs['year']);
    $survey->save();

    $changes = AuditHelper::getChangesOnUpdate($replicate_survey, $survey);

    if (!empty($changes)) {
      AuditHelper::transformSurvey($services, $changes);
      AuditHelper::setCustomEvent(
        $services,
        [
          'action' => 'updated',
          'entity' => $survey_data['title'],
          'description' => 'The survey has been updated',
          'old_values' => json_encode($changes['old_values']),
          'new_values' => json_encode($changes['new_values']),
        ],
      );
    }

    return $survey;
  }

  /**
   * Function publishSurvey.
   */
  public static function publishSurvey($survey) {
    $survey->set('status', 1);
    $survey->save();
  }

  /**
   * Function deleteSurvey.
   */
  public static function deleteSurvey($services, $survey, $survey_data) {
    $survey->delete();

    AuditHelper::setCustomEvent(
      $services,
      [
        'action' => 'deleted',
        'entity' => $survey_data['title'],
        'description' => 'The survey has been deleted',
      ],
    );
  }

}
