<?php

namespace Drupal\data_collection_management\Helper;

use Drupal\general_management\Helper\ConstantHelper;
use Drupal\country_survey_management\Helper\CountrySurveyHelper;
use Drupal\general_management\Helper\GeneralHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorHelper;
use Drupal\general_management\Helper\ValidationHelper;
use Drupal\Core\Database\Database;
use Symfony\Component\Process\Process;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\ConstraintViolation;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Class Data Collection Helper.
 */
class DataCollectionHelper {

  /**
   * Function validateDataCollectionImport.
   */
  public static function validateDataCollectionImport($inputs) {
    $errors = ValidationHelper::getValidationErrors($inputs, [
      'file' => new Sequentially([
        new NotBlank([
          'message' => ValidationHelper::$requiredMessage,
        ]),
        new File([
          'maxSize' => '2M',
        ]),
      ]),
    ]);

    if (!is_null($inputs['file'])) {
      $finfo = new \finfo(FILEINFO_MIME_TYPE);
      $file_type = $finfo->file($inputs['file']->getPathname());
      $file_extension = $inputs['file']->getClientOriginalExtension();

      if ($file_type != 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ||
            $file_extension != 'xlsx') {
        $errors->add(new ConstraintViolation(
              'The file must be of type xlsx.',
              '',
              [],
              '',
              'file',
              $inputs['file']
          ));
      }
    }

    return ValidationHelper::formatValidationErrors($errors);
  }

  /**
   * Function updateOrCreateManualOrEurostatIndicators.
   */
  public static function updateOrCreateManualOrEurostatIndicators($entityTypeManager, $database, $rows, $year) {
    $countries_codes = array_shift($rows);
    $processed_countries = [];

    foreach ($rows as $row) {
      $identifier = array_shift($row);

      $indicator_entity = IndicatorHelper::getIndicatorEntity(
            $entityTypeManager,
            [
              'identifier' => $identifier,
              'year' => $year,
            ]
        );

      if (is_null($indicator_entity)) {
        continue;
      }

      foreach ($row as $key => $value) {
        $country_code = mb_strtoupper($countries_codes[$key + 1]);

        if (!array_key_exists($country_code, $processed_countries)) {
          $country = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'countries', $country_code, 'field_code');

          $processed_countries[$country_code] = $country->id();
        }

        if (!is_null($value)) {
          $query = "INSERT INTO {indicator_values} (indicator_id, country_id, year, value)
                              VALUES (:indicator_id, :country_id, :year, :value)
                              ON DUPLICATE KEY UPDATE value = VALUES(value)";

          $database->query($query, [
            ':indicator_id' => $indicator_entity,
            ':country_id' => $processed_countries[$country_code],
            ':year' => $year,
            ':value' => $value,
          ]);
        }
      }
    }
  }

  /**
   * Function updateOrCreateEuWideIndicators.
   */
  public static function updateOrCreateEuWideIndicators($entityTypeManager, $database, $rows, $year) {
    unset($rows[0]);

    $country = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'countries', ConstantHelper::USER_GROUP);

    foreach ($rows as $row) {
      $identifier = array_shift($row);

      $indicator_entity = IndicatorHelper::getIndicatorEntity(
            $entityTypeManager,
            [
              'identifier' => $identifier,
              'year' => $year,
            ]
        );

      if (is_null($indicator_entity)) {
        continue;
      }

      foreach ($row as $value) {
        if (!is_null($value)) {
          $query = "INSERT INTO {indicator_values} (indicator_id, country_id, year, value)
                              VALUES (:indicator_id, :country_id, :year, :value)
                              ON DUPLICATE KEY UPDATE value = VALUES(value)";

          $database->query($query, [
            ':indicator_id' => $indicator_entity,
            ':country_id' => $country->id(),
            ':year' => $year,
            ':value' => $value,
          ]);
        }
      }
    }
  }

  /**
   * Function importData.
   */
  public static function importData($entityTypeManager, $fileSystem, $database, $year, $excel) {
    $file_path = $fileSystem->realpath($excel);

    $reader = IOFactory::createReaderForFile($file_path);
    $reader->setReadDataOnly(TRUE);

    $spreadsheet = $reader->load($file_path);

    $connection = Database::getConnection();
    $connection->startTransaction();

    try {
      $sheets = $spreadsheet->getAllSheets();

      foreach ($sheets as $sheet) {
        $title = mb_strtolower($sheet->getTitle());
        $rows = $sheet->toArray();

        if (preg_match('/enisa/', $title)) {
          self::updateOrCreateManualOrEurostatIndicators($entityTypeManager, $database, $rows, $year);
        }
        elseif (preg_match('/eu-wide/', $title)) {
          self::updateOrCreateEUWideIndicators($entityTypeManager, $database, $rows, $year);
        }
        elseif (preg_match('/eurostat/', $title)) {
          self::updateOrCreateManualOrEurostatIndicators($entityTypeManager, $database, $rows, $year);
        }
        else {
          throw new \Exception('No indicators were imported! Please check the sheet names i.e. ENISA, EU-wide, Eurostat.');
        }
      }
    }
    catch (\Exception $e) {
      return [
        'type' => 'error',
        'msg' => $e->getMessage(),
      ];
    }

    return [
      'type' => 'success',
      'msg' => 'Import data have been successfully imported!',
    ];
  }

  /**
   * Function exportDataExcel.
   */
  public static function exportDataExcel($year, $country_iso, $source) {
    $command = [
      'drush',
      'export-data-excel',
      '--year=' . $year,
      '--country_iso=' . $country_iso,
      '--source=' . $source,
    ];

    $process = new Process($command);
    $process->run();

    if ($process->isSuccessful()) {
      return json_decode($process->getOutput(), TRUE);
    }

    return FALSE;
  }

  /**
   * Function getDataCollectionCountries.
   */
  public static function getDataCollectionCountries(
    $entityTypeManager,
    $database,
    $dateFormatter,
    $imported_indicators,
    $eurostat_indicators,
    $year,
    $survey,
  ) {
    $countries = GeneralHelper::getTaxonomyTerms($entityTypeManager, 'countries', 'entity');

    $data = [];
    foreach ($countries as $country) {
      if ($country->label() == ConstantHelper::USER_GROUP) {
        continue;
      }

      $country_data = [
        'country' => [
          'name' => $country->label(),
        ],
      ];

      $imported_indicators_approved = self::getDataCollectionIndicators($entityTypeManager, $database, $year, $country->id(), 'manual');

      $country_data['imported_indicators_approved'] = count($imported_indicators_approved);
      $country_data['imported_indicators'] = count($imported_indicators);

      $eurostat_indicators_approved = self::getDataCollectionIndicators($entityTypeManager, $database, $year, $country->id(), 'eurostat');

      $country_data['eurostat_indicators_approved'] = count($eurostat_indicators_approved);
      $country_data['eurostat_indicators'] = count($eurostat_indicators);

      $country_survey_entity = CountrySurveyHelper::getCountrySurveyEntity($entityTypeManager, $survey, $country->id());
      if (!is_null($country_survey_entity)) {
        $country_survey = $entityTypeManager->getStorage('node')->load($country_survey_entity);
        $country_survey_data = CountrySurveyHelper::getCountrySurveyData($entityTypeManager, $dateFormatter, $country_survey);
        $country_survey_additional_data = CountrySurveyHelper::getCountrySurveyAdditionalData($entityTypeManager, $dateFormatter, $country_survey_data);

        $country_data['survey_indicators'] = $country_survey_additional_data['indicators'];
        $country_data['survey_indicators_final_approved'] = $country_survey_additional_data['indicators_final_approved'];
        $country_data['survey_indicators_percentage_final_approved'] = $country_survey_additional_data['percentage_final_approved'];

        $country_data = array_merge($country_data, $country_survey_data);
      }

      array_push($data, $country_data);
    }

    return $data;
  }

  /**
   * Function getDataCollectionIndicators.
   */
  public static function getDataCollectionIndicators($entityTypeManager, $database, $year, $country, $category) {
    $indicator_values = $database->select('indicator_values', 'iv')
      ->fields('iv')
      ->condition('year', $year)
      ->condition('country_id', $country)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $indicators = $entityTypeManager->getStorage('node')->loadMultiple(array_column($indicator_values, 'indicator_id'));

    $data = [];
    foreach ($indicators as $indicator) {
      $indicator_data = IndicatorHelper::getIndicatorData($indicator);

      if ($indicator_data['category'] != $category) {
        continue;
      }

      array_push($data, $indicator_data);
    }

    return $data;
  }

}
