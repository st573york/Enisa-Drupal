<?php

namespace Drupal\index_and_survey_configuration_management\Helper;

use Drupal\general_management\Helper\ValidationHelper;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Class Subarea Helper.
 */
class SubareaHelper {

  /**
   * Function validateInputs.
   */
  public static function validateInputs($entityTypeManager, $inputs) {
    $errors = ValidationHelper::getValidationErrors($inputs, [
      'title' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
      'default_area' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
      'default_weight' => new Sequentially([
        new NotBlank([
          'message' => ValidationHelper::$requiredMessage,
        ]),
        new Type([
          'type' => 'numeric',
          'message' => 'This field must be a number.',
        ]),
      ]),
    ]);

    if (!self::isSubareaUnique($entityTypeManager, $inputs)) {
      $errors->add(new ConstraintViolation(
            'The name has already been taken.',
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
   * Function isSubareaUnique.
   */
  public static function isSubareaUnique($entityTypeManager, $inputs) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'subarea')
      ->condition('title', $inputs['title']);

    if (isset($inputs['id'])) {
      $query->condition('nid', $inputs['id'], '<>');
    }
    if (isset($inputs['year'])) {
      $query->condition('field_year', $inputs['year']);
    }

    $subarea = $query->execute();

    if (!empty($subarea)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Function getSubareaData.
   */
  public static function getSubareaData($node, $include_area = TRUE) {
    if (!$node) {
      return [];
    }

    $node_data = [
      'id' => $node->id(),
      'name' => $node->getTitle(),
      'short_name' => $node->field_short_name->value ?? '',
      'description' => $node->field_description->value ?? '',
      'identifier' => $node->field_identifier->value ?? '',
      'year' => $node->field_year->value ?? '',
      'weight' => $node->field_default_weight->value ?? '',
      'status' => ($node->status->value) ? 'Published' : 'Draft',
    ];

    if ($include_area) {
      $node_data['area'] = '';

      if ($node->hasField('field_default_area') &&
            !$node->get('field_default_area')->isEmpty()) {
        $area = $node->get('field_default_area')->entity;

        $node_data['area'] = AreaHelper::getAreaData($area);
      }
    }

    return $node_data;
  }

  /**
   * Function getSubareasData.
   */
  public static function getSubareasData($entityTypeManager, $entities) {
    $subareas = $entityTypeManager->getStorage('node')->loadMultiple($entities);

    $data = [];
    foreach ($subareas as $subarea) {
      $subarea_data = self::getSubareaData($subarea);

      array_push($data, $subarea_data);
    }

    return $data;
  }

  /**
   * Function getSubareaEntity.
   */
  public static function getSubareaEntity($entityTypeManager, $data) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'subarea')
      ->condition('status', 1);

    if (isset($data['name'])) {
      $query->condition('title', $data['name']);
    }
    if (isset($data['identifier'])) {
      $query->condition('field_identifier', $data['identifier']);
    }
    if (isset($data['year'])) {
      $query->condition('field_year', $data['year']);
    }

    $subarea = $query->execute();

    if (!empty($subarea)) {
      return reset($subarea);
    }

    return NULL;
  }

  /**
   * Function getSubareaEntities.
   */
  public static function getSubareaEntities($entityTypeManager, $year) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'subarea')
      ->condition('field_year', $year)
      ->condition('status', 1);

    return $query->sort('field_identifier')->execute();
  }

  /**
   * Function getMaxSubareaField.
   */
  public static function getMaxSubareaField($entityTypeManager, $field, $year = '') {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'subarea')
      ->condition('status', 1);

    if (!empty($year)) {
      $query->condition('field_year', $year);
    }

    $subarea = $query->sort($field, 'DESC')->execute();

    if (!empty($subarea)) {
      return reset($subarea);
    }

    return NULL;
  }

  /**
   * Function getSubareaDataForSave.
   */
  public static function getSubareaDataForSave($inputs) {
    $data = [
      'title' => $inputs['title'],
    ];

    unset($inputs['id'], $inputs['title'], $inputs['max_identifier']);

    foreach ($inputs as $key => $value) {
      $data['field_' . $key] = $value;
    }

    return $data;
  }

  /**
   * Function storeSubarea.
   */
  public static function storeSubarea($entityTypeManager, $inputs) {
    $data = self::getSubareaDataForSave($inputs);
    $data['type'] = 'subarea';
    $data['field_short_name'] = mb_substr($inputs['title'], 0, 20);

    $max_subarea_identifier_entity = self::getMaxSubareaField($entityTypeManager, 'field_identifier');
    $max_subarea_identifier = $entityTypeManager->getStorage('node')->load($max_subarea_identifier_entity);
    $max_subarea_identifier_data = self::getSubareaData($max_subarea_identifier);

    $data['field_identifier'] = $max_subarea_identifier_data['identifier'] + 1;

    $subarea = $entityTypeManager->getStorage('node')->create($data);
    $subarea->save();
  }

  /**
   * Function updateSubarea.
   */
  public static function updateSubarea($subarea, $inputs) {
    $data = self::getSubareaDataForSave($inputs);

    foreach ($data as $key => $value) {
      $subarea->set($key, $value);
    }
    $subarea->save();
  }

  /**
   * Function deleteSubarea.
   */
  public static function deleteSubarea($subarea) {
    $subarea->delete();
  }

}
