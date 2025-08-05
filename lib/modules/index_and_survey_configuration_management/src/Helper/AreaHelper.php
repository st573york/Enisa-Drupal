<?php

namespace Drupal\index_and_survey_configuration_management\Helper;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Drupal\general_management\Helper\ValidationHelper;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Class Area Helper.
 */
class AreaHelper {

  /**
   * Function validateInputs.
   */
  public static function validateInputs($entityTypeManager, $inputs) {
    $errors = ValidationHelper::getValidationErrors($inputs, [
      'title' => new NotBlank([
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

    if (!self::isAreaUnique($entityTypeManager, $inputs)) {
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
   * Function isAreaUnique.
   */
  public static function isAreaUnique($entityTypeManager, $inputs) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'area')
      ->condition('title', $inputs['title']);

    if (isset($inputs['id'])) {
      $query->condition('nid', $inputs['id'], '<>');
    }
    if (isset($inputs['year'])) {
      $query->condition('field_year', $inputs['year']);
    }

    $area = $query->execute();

    if (!empty($area)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Function getAreaData.
   */
  public static function getAreaData($node) {
    if (!$node) {
      return [];
    }

    return [
      'id' => $node->id(),
      'name' => $node->getTitle(),
      'description' => $node->field_description->value ?? '',
      'identifier' => $node->field_identifier->value ?? '',
      'year' => $node->field_year->value ?? '',
      'weight' => $node->field_default_weight->value ?? '',
      'status' => ($node->status->value) ? 'Published' : 'Draft',
    ];
  }

  /**
   * Function getAreasData.
   */
  public static function getAreasData($entityTypeManager, $entities) {
    $areas = $entityTypeManager->getStorage('node')->loadMultiple($entities);

    $data = [];
    foreach ($areas as $area) {
      $area_data = self::getAreaData($area);

      array_push($data, $area_data);
    }

    return $data;
  }

  /**
   * Function getAreaEntity.
   */
  public static function getAreaEntity($entityTypeManager, $data) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'area')
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

    $area = $query->execute();

    if (!empty($area)) {
      return reset($area);
    }

    return NULL;
  }

  /**
   * Function getAreaEntities.
   */
  public static function getAreaEntities($entityTypeManager, $year) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'area')
      ->condition('field_year', $year)
      ->condition('status', 1);

    return $query->sort('field_identifier')->execute();
  }

  /**
   * Function getMaxAreaField.
   */
  public static function getMaxAreaField($entityTypeManager, $field, $year = '') {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'area')
      ->condition('status', 1);

    if (!empty($year)) {
      $query->condition('field_year', $year);
    }

    $area = $query->sort($field, 'DESC')->execute();

    if (!empty($area)) {
      return reset($area);
    }

    return NULL;
  }

  /**
   * Function getAreaDataForSave.
   */
  public static function getAreaDataForSave($inputs) {
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
   * Function storeArea.
   */
  public static function storeArea($entityTypeManager, $inputs) {
    $data = self::getAreaDataForSave($inputs);
    $data['type'] = 'area';

    $max_area_identifier_entity = self::getMaxAreaField($entityTypeManager, 'field_identifier');
    $max_area_identifier = $entityTypeManager->getStorage('node')->load($max_area_identifier_entity);
    $max_area_identifier_data = self::getAreaData($max_area_identifier);

    $data['field_identifier'] = $max_area_identifier_data['identifier'] + 1;

    $area = $entityTypeManager->getStorage('node')->create($data);
    $area->save();
  }

  /**
   * Function updateArea.
   */
  public static function updateArea($area, $inputs) {
    $data = self::getAreaDataForSave($inputs);

    foreach ($data as $key => $value) {
      $area->set($key, $value);
    }
    $area->save();
  }

  /**
   * Function deleteArea.
   */
  public static function deleteArea($area) {
    $area->delete();
  }

}
