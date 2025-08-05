<?php

namespace Drupal\index_management\Helper;

use Symfony\Component\Process\Process;

/**
 * Class Index Reports And Data Helper.
 */
class IndexReportsAndDataHelper {

  /**
   * Function exportReportExcel.
   */
  public static function exportReportExcel($year, $country_iso) {
    $command = [
      'drush',
      'export-report-excel',
      '--year=' . $year,
    ];

    if (!is_null($country_iso)) {
      array_push($command, '--country_iso=' . $country_iso);
    }

    $process = new Process($command);
    $process->run();

    if ($process->isSuccessful()) {
      return json_decode($process->getOutput(), TRUE);
    }

    return FALSE;
  }

  /**
   * Function exportMsRawDataExcel.
   */
  public static function exportMsRawDataExcel($year, $country_iso) {
    $command = [
      'drush',
      'export-ms-raw-data-excel',
      '--year=' . $year,
      '--country_iso=' . $country_iso,
    ];

    $process = new Process($command);
    $process->run();

    if ($process->isSuccessful()) {
      return json_decode($process->getOutput(), TRUE);
    }

    return FALSE;
  }

  /**
   * Function calculateEuRange.
   */
  public static function calculateEuRange(&$eu_index_data) {
    foreach ($eu_index_data['report_json_data'][0]['areas'] as &$area) {
      $numberOfCountries = $area['scores'][0]['numberOfCountries'];

      $area['eu_range'] = array_search(max($numberOfCountries), $numberOfCountries);

      foreach ($area['subareas'] as &$subarea) {
        $numberOfCountries = $subarea['scores'][0]['numberOfCountries'];

        $subarea['eu_range'] = array_search(max($numberOfCountries), $numberOfCountries);
      }
    }
  }

  /**
   * Function getReportChartMsData.
   */
  public static function getReportChartMsData($country_index_data) {
    $data = [];

    foreach ($country_index_data['report_json_data'][0]['areas'] as $idx => $area) {
      $data['area-' . $idx] = [
        'name' => $country_index_data['country']['name'],
        'indicator' => [],
        'country' => [],
        'eu' => [],
      ];

      foreach ($area['subareas'] as $subarea) {
        $data['area-' . $idx]['indicator'][] =
                    [
                      'name' => wordwrap($subarea['name'], 20, "\n"),
                      'nameTextStyle' => [
                        'fontSize' => 12,
                        'height' => 100,
                      ],
                      'max' => 100,
                    ];

        array_push($data['area-' . $idx]['country'], $subarea['scores']['country']);
        array_push($data['area-' . $idx]['eu'], $subarea['scores']['euAverage']);
      }
    }

    return $data;
  }

  /**
   * Function getReportChartEuData.
   */
  public static function getReportChartEuData($eu_index_data) {
    $data = [];

    foreach ($eu_index_data['report_json_data'][0]['areas'] as $idx => $area) {
      $data['area-' . $idx] = [
        'indicator' => [],
        'eu' => [],
      ];

      foreach ($area['subareas'] as $subarea) {
        $data['area-' . $idx]['indicator'][] =
                    [
                      'name' => wordwrap($subarea['name'], 20, "\n"),
                      'nameTextStyle' => [
                        'fontSize' => 12,
                        'height' => 100,
                      ],
                      'max' => 100,
                    ];

        array_push($data['area-' . $idx]['eu'], $subarea['scores'][0]['euAverage']);
      }
    }

    return $data;
  }

}
