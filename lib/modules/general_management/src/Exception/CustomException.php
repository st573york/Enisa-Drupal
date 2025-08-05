<?php

namespace Drupal\general_management\Exception;

/**
 * Custom exception class for handling specific exceptions in the application.
 */
class CustomException extends \Exception {
  /**
   * Additional data associated with the exception.
   *
   * @var array
   */
  private $exceptionData;

  public function __construct($message, $code = 0, ?\Exception $previous = NULL, $exceptionData = []) {
    parent::__construct($message, $code, $previous);
    $this->exceptionData = $exceptionData;
  }

  /**
   * Get the data associated with the exception.
   */
  public function getExceptionData() {
    return $this->exceptionData;
  }

}
