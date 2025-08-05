<?php

namespace Drupal\general_management\Helper;

/**
 * Class Constant Helper.
 */
class ConstantHelper {
  public const USER_GROUP = 'ENISA';
  public const USER_INACTIVE = '(inactive)';
  public const EU_AVERAGE_NAME = 'European Average';
  public const EU_AVERAGE_CODE = 'EU27';
  public const DEFAULT_CATEGORIES = [
    'eu-wide' => 'EU-Wide',
    'eurostat' => 'Eurostat',
    'manual' => 'Other',
    'survey' => 'Survey',
  ];

  /**
   * Function getYearsToDateAndNext.
   */
  public static function getYearsToDateAndNext($dateFormatter, $time) {
    return range(2022, $dateFormatter->format($time->getCurrentTime(), 'custom', 'Y') + 2);
  }

}
