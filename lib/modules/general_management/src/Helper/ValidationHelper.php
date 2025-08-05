<?php

namespace Drupal\general_management\Helper;

use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Validation;

/**
 * Class Validation Helper.
 */
class ValidationHelper {
  /**
   * Static variable requiredMessage.
   *
   * @var string
   */
  public static $requiredMessage = 'This field is required.';

  /**
   * Function getValidationErrors.
   */
  public static function getValidationErrors($data, $rules) {
    $validator = Validation::createValidator();

    $constraints = new Collection(
          fields: $rules,
          allowExtraFields: TRUE,
          allowMissingFields: FALSE,
          missingFieldsMessage: 'This field is required.'
      );

    return $validator->validate($data, $constraints);
  }

  /**
   * Function formatValidationErrors.
   */
  public static function formatValidationErrors($validation_errors) {
    $formatted_errors = [];

    if (count($validation_errors)) {
      foreach ($validation_errors as $validation_error) {
        // Remove brackets.
        $field = str_replace(['[', ']'], '', $validation_error->getPropertyPath());

        $formatted_errors[$field] = $validation_error->getMessage();
      }
    }

    return $formatted_errors;
  }

}
