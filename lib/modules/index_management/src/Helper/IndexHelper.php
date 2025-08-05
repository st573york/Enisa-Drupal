<?php

namespace Drupal\index_management\Helper;

use Drupal\index_and_survey_configuration_management\Helper\AreaHelper;
use Drupal\audit_management\Helper\AuditHelper;
use Drupal\general_management\Helper\ValidationHelper;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Class Index Helper.
 */
class IndexHelper {

  /**
   * Function validateInputs.
   */
  public static function validateInputs($entityTypeManager, $inputs) {
    $errors = ValidationHelper::getValidationErrors($inputs, [
      'name' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
      'year' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
    ]);

    if (!self::isIndexUnique($entityTypeManager, $inputs)) {
      $errors->add(new ConstraintViolation(
            'The name has already been taken.',
            '',
            [],
            '',
            'name',
            $inputs['name']
        ));
    }

    return ValidationHelper::formatValidationErrors($errors);
  }

  /**
   * Function isIndexUnique.
   */
  public static function isIndexUnique($entityTypeManager, $inputs) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'index')
      ->condition('title', $inputs['name']);

    if (isset($inputs['id'])) {
      $query->condition('nid', $inputs['id'], '<>');
    }

    $index = $query->execute();

    if (!empty($index)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Function validateIndexStatusAndData.
   */
  public static function validateIndexStatusAndData($entityTypeManager, $inputs) {
    $published = filter_var($inputs['status'], FILTER_VALIDATE_BOOLEAN);

    $published_index_entity = self::getExistingPublishedIndexForYearEntity($entityTypeManager, $inputs['year']);

    if ($published &&
          !is_null($published_index_entity) &&
          $published_index_entity != $inputs['id']) {
      return 'Another official configuration already exists for this year!';
    }

    if (!$published) {
      $published_index_entities = self::getPublishedIndexEntities($entityTypeManager, $inputs['id']);

      if (empty($published_index_entities)) {
        return 'There are no other official configurations exist in the tool!';
      }

      $eu_index_entities = self::getEuIndexEntities($entityTypeManager, $inputs['id']);
      $country_index_entities = self::getCountryIndexEntities($entityTypeManager, $inputs['id']);

      if (count($eu_index_entities) &&
            count($country_index_entities)) {
        return 'This configuration has active indexes!';
      }
    }

    return NULL;
  }

  /**
   * Function getIndexYearChoices.
   */
  public static function getIndexYearChoices($entityTypeManager, $dateFormatter) {
    $published_index_entities = self::getPublishedIndexEntities($entityTypeManager);
    $published_indexes = self::getIndexesData($entityTypeManager, $dateFormatter, $published_index_entities);

    $year_choices = [];
    foreach ($published_indexes as $published_index) {
      $year_choices[$published_index['id']] = $published_index['year'];
    }
    arsort($year_choices);

    return $year_choices;
  }

  /**
   * Function getIndexData.
   */
  public static function getIndexData($dateFormatter, $node) {
    if (!$node) {
      return [];
    }

    $author = $node->getOwner();

    return [
      'id' => $node->id(),
      'title' => $node->getTitle(),
      'description' => $node->field_description->value ?? '',
      'year' => $node->field_year->value ?? '',
      'created_by' => $author->field_full_name->value ?? '',
      'status' => ($node->status->value) ? 'Published' : 'Unpublished',
      'eu_published' => ($node->field_eu_published->value) ? TRUE : FALSE,
      'ms_published' => ($node->field_ms_published->value) ? TRUE : FALSE,
      'json_data' => ($node->field_json_data->value) ? json_decode($node->field_json_data->value, TRUE) : '',
      'last_action' => $node->field_last_action->value ?? '',
      'last_action_label' => '',
      'last_action_state' => $node->field_last_action_state->value ?? '',
      'last_action_date' => ($node->field_last_action_date->value) ? $dateFormatter->format($node->field_last_action_date->value, 'custom', 'd-m-Y H:i:s') : '',
    ];
  }

  /**
   * Function getEuIndexData.
   */
  public static function getEuIndexData($dateFormatter, $node) {
    if (!$node) {
      return [];
    }

    $node_data = [
      'id' => $node->id(),
      'title' => $node->getTitle(),
      'description' => $node->field_description->value ?? '',
      'json_data' => ($node->field_json_data->value) ? json_decode($node->field_json_data->value, TRUE) : '',
      'report_json_data' => ($node->field_report_json_data->value) ? json_decode($node->field_report_json_data->value, TRUE) : '',
      'report_date' => ($node->field_report_date->value) ? $dateFormatter->format($node->field_report_date->value, 'custom', 'd-m-Y H:i:s') : '',
      'index' => '',
    ];

    if ($node->hasField('field_index') &&
          !$node->get('field_index')->isEmpty()) {
      $index = $node->get('field_index')->entity;

      $node_data['index'] = IndexHelper::getIndexData($dateFormatter, $index);
    }

    return $node_data;
  }

  /**
   * Function getCountryIndexData.
   */
  public static function getCountryIndexData($dateFormatter, $node) {
    if (!$node) {
      return [];
    }

    $node_data = [
      'id' => $node->id(),
      'title' => $node->getTitle(),
      'description' => $node->field_description->value ?? '',
      'json_data' => ($node->field_json_data->value) ? json_decode($node->field_json_data->value, TRUE) : '',
      'report_json_data' => ($node->field_report_json_data->value) ? json_decode($node->field_report_json_data->value, TRUE) : '',
      'report_date' => ($node->field_report_date->value) ? $dateFormatter->format($node->field_report_date->value, 'custom', 'd-m-Y H:i:s') : '',
      'country' => '',
      'index' => '',
      'state' => '',
    ];

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

    if ($node->hasField('field_index') &&
          !$node->get('field_index')->isEmpty()) {
      $index = $node->get('field_index')->entity;

      $node_data['index'] = IndexHelper::getIndexData($dateFormatter, $index);
    }

    if ($node->hasField('field_index_state') &&
          !$node->get('field_index_state')->isEmpty()) {
      $state = $node->get('field_index_state')->entity;

      if (!is_null($state)) {
        $node_data['state'] = [
          'id' => (int) $state->field_state_id->value ?? '',
          'name' => $state->getName(),
        ];
      }
    }

    return $node_data;
  }

  /**
   * Function getIndexesData.
   */
  public static function getIndexesData($entityTypeManager, $dateFormatter, $entities) {
    $indexes = $entityTypeManager->getStorage('node')->loadMultiple($entities);

    $data = [];
    foreach ($indexes as $index) {
      $index_data = self::getIndexData($dateFormatter, $index);

      array_push($data, $index_data);
    }

    return $data;
  }

  /**
   * Function getCountriesIndexData.
   */
  public static function getCountriesIndexData($entityTypeManager, $dateFormatter, $entities) {
    $countries_index = $entityTypeManager->getStorage('node')->loadMultiple($entities);

    $data = [];
    foreach ($countries_index as $country_index) {
      $country_index_data = self::getCountryIndexData($dateFormatter, $country_index);

      array_push($data, $country_index_data);
    }

    return $data;
  }

  /**
   * Function getIndexEntities.
   */
  public static function getIndexEntities($entityTypeManager) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'index');

    return $query->execute();
  }

  /**
   * Function getEuIndexEntity.
   */
  public static function getEuIndexEntity($entityTypeManager, $index) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'eu_index')
      ->condition('field_index', $index)
      ->condition('status', 1);

    $eu_index = $query->execute();

    if (!empty($eu_index)) {
      return reset($eu_index);
    }

    return NULL;
  }

  /**
   * Function getEuIndexEntities.
   */
  public static function getEuIndexEntities($entityTypeManager, $index) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'eu_index')
      ->condition('field_index', $index)
      ->condition('status', 1);

    return $query->execute();
  }

  /**
   * Function getCountryIndexEntity.
   */
  public static function getCountryIndexEntity($entityTypeManager, $index, $country) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'country_index')
      ->condition('field_index', $index)
      ->condition('field_country', $country)
      ->condition('status', 1);

    $country_index = $query->execute();

    if (!empty($country_index)) {
      return reset($country_index);
    }

    return NULL;
  }

  /**
   * Function getCountryIndexEntities.
   */
  public static function getCountryIndexEntities($entityTypeManager, $index, $countries = []) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'country_index')
      ->condition('field_index', $index)
      ->condition('status', 1);

    if (!empty($countries)) {
      $query->condition('field_country', $countries, 'IN');
    }

    return $query->sort('field_country')->execute();
  }

  /**
   * Function getLoadedPublishedIndexEntity.
   */
  public static function getLoadedPublishedIndexEntity($entityTypeManager, $year, $latest_index_entity = NULL) {
    $loaded_index_entity = self::getExistingPublishedIndexForYearEntity($entityTypeManager, $year);
    if (is_null($loaded_index_entity)) {
      $loaded_index_entity = (is_null($latest_index_entity)) ? self::getLatestPublishedIndexEntity($entityTypeManager) : $latest_index_entity;
    }

    return $loaded_index_entity;
  }

  /**
   * Function getLatestPublishedIndexEntity.
   */
  public static function getLatestPublishedIndexEntity($entityTypeManager) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'index')
      ->condition('status', 1);

    $index = $query->sort('field_year', 'DESC')->execute();

    if (!empty($index)) {
      return reset($index);
    }

    return NULL;
  }

  /**
   * Function getPublishedIndexEntities.
   */
  public static function getPublishedIndexEntities($entityTypeManager, $index = NULL) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'index')
      ->condition('status', 1);

    if (!is_null($index)) {
      $query->condition('nid', $index, '<>');
    }

    return $query->sort('field_year', 'DESC')->execute();
  }

  /**
   * Function getExistingDraftIndexForYearEntity.
   */
  public static function getExistingDraftIndexForYearEntity($entityTypeManager, $year) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'index')
      ->condition('field_year', $year)
      ->condition('status', 0);

    $index = $query->execute();

    if (!empty($index)) {
      return reset($index);
    }

    return NULL;
  }

  /**
   * Function getExistingPublishedIndexForYearEntity.
   */
  public static function getExistingPublishedIndexForYearEntity($entityTypeManager, $year) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'index')
      ->condition('field_year', $year)
      ->condition('status', 1);

    $index = $query->execute();

    if (!empty($index)) {
      return reset($index);
    }

    return NULL;
  }

  /**
   * Function generateIndexTemplate.
   */
  public static function generateIndexTemplate($entityTypeManager, $year) {
    $entities = AreaHelper::getAreaEntities($entityTypeManager, $year);
    $data = AreaHelper::getAreasData($entityTypeManager, $entities);

    return (!empty($data)) ? ['contents' => $data] : '{}';
  }

  /**
   * Function updateDraftIndexJsonData.
   */
  public static function updateDraftIndexJsonData($entityTypeManager, $year) {
    $draft_index_entity = self::getExistingDraftIndexForYearEntity($entityTypeManager, $year);
    if (!is_null($draft_index_entity)) {
      $draft_index = $entityTypeManager->getStorage('node')->load($draft_index_entity);

      $draft_index->set('field_json_data', json_encode(self::generateIndexTemplate($entityTypeManager, $year)));
      $draft_index->save();
    }
  }

  /**
   * Function storeIndex.
   */
  public static function storeIndex($services, $inputs) {
    $data = [
      'type' => 'index',
      'title' => $inputs['name'],
      'field_description' => $inputs['description'],
      'field_year' => $inputs['year'],
      'status' => $inputs['status'],
      'field_eu_published' => $inputs['eu_published'],
      'field_ms_published' => $inputs['ms_published'],
    ];

    $index = $services['entityTypeManager']->getStorage('node')->create($data);
    $index->save();

    $changes = AuditHelper::getChangesOnCreate($index);

    AuditHelper::transformIndex($changes);
    AuditHelper::setCustomEvent(
      $services,
      [
        'action' => 'created',
        'entity' => $inputs['name'],
        'description' => 'The index has been created',
        'new_values' => json_encode($changes['new_values']),
      ],
    );

    return $index;
  }

  /**
   * Function updateIndex.
   */
  public static function updateIndex($services, $index, $index_data, $inputs) {
    $replicate_index = $index->createDuplicate();

    $index->set('title', $inputs['name']);
    $index->set('field_description', $inputs['description']);
    $index->set('field_year', $inputs['year']);
    $index->set('status', $inputs['status']);
    $index->set('field_eu_published', $inputs['eu_published']);
    $index->set('field_ms_published', $inputs['ms_published']);
    $index->save();

    $changes = AuditHelper::getChangesOnUpdate($replicate_index, $index);

    if (!empty($changes)) {
      AuditHelper::transformIndex($changes);
      AuditHelper::setCustomEvent(
        $services,
        [
          'action' => 'updated',
          'entity' => $index_data['title'],
          'description' => 'The index has been updated',
          'old_values' => json_encode($changes['old_values']),
          'new_values' => json_encode($changes['new_values']),
        ],
      );
    }

    return $index;
  }

  /**
   * Function deleteIndex.
   */
  public static function deleteIndex($services, $index, $index_data) {
    $index->delete();

    AuditHelper::setCustomEvent(
      $services,
      [
        'action' => 'deleted',
        'entity' => $index_data['title'],
        'description' => 'The index has been deleted',
      ],
    );
  }

}
