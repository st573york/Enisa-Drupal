<?php

namespace Drupal\index_and_survey_configuration_management\Helper;

use Drupal\general_management\Helper\GeneralHelper;
use Drupal\index_management\Helper\IndexHelper;
use Drupal\general_management\Helper\ValidationHelper;
use Drupal\Core\Database\Database;
use Symfony\Component\Process\Process;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\ConstraintViolation;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Class Index And Survey Configuration Helper.
 */
class IndexAndSurveyConfigurationHelper {

  /**
   * Function validateIndexAndSurveyConfigurationImport.
   */
  public static function validateIndexAndSurveyConfigurationImport($inputs) {
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
   * Function validateIndexAndSurveyConfigurationClone.
   */
  public static function validateIndexAndSurveyConfigurationClone($inputs) {
    $errors = ValidationHelper::getValidationErrors($inputs, [
      'clone-index' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
    ]);

    return ValidationHelper::formatValidationErrors($errors);
  }

  /**
   * Function exportConfigurationExcel.
   */
  public static function exportConfigurationExcel($year) {
    $command = [
      'drush',
      'export-configuration-excel',
      '--year=' . $year,
    ];

    $process = new Process($command);
    $process->run();

    if ($process->isSuccessful()) {
      return json_decode($process->getOutput(), TRUE);
    }

    return FALSE;
  }

  /**
   * Function storeIndexPropertiesAreasByYear.
   */
  public static function storeIndexPropertiesAreasByYear($entityTypeManager, $sheet, $year) {
    $rows = array_slice($sheet->toArray(), 1);
    $areas = [];

    foreach ($rows as $row) {
      $id = $row[0];
      $name = $row[1];

      if (in_array($name, $areas)) {
        throw new \Exception("Area name '{$name}' already exists. Please check id '{$id}' in the Areas sheet!");
      }

      array_push($areas, $name);

      $area = $entityTypeManager->getStorage('node')->create([
        'type' => 'area',
        'title' => $name,
        'field_description' => $row[2],
        'field_identifier' => $row[3],
        'field_default_weight' => $row[4],
        'field_year' => $year,
      ]);
      $area->save();
    }
  }

  /**
   * Function storeIndexPropertiesSubareasByYear.
   */
  public static function storeIndexPropertiesSubareasByYear($entityTypeManager, $sheet, $year) {
    $rows = array_slice($sheet->toArray(), 1);
    $subareas = [];

    foreach ($rows as $row) {
      $id = $row[0];
      $name = $row[1];
      $area = $row[5];

      if (in_array($name, $subareas)) {
        throw new \Exception("Subarea name '{$name}' already exists. Please check id '{$id}' in the Subareas sheet!");
      }

      if (!strlen(trim($area))) {
        throw new \Exception("Area name is missing. Please check id '{$id}' in the Subareas sheet!");
      }

      $area_entity = AreaHelper::getAreaEntity(
            $entityTypeManager,
            [
              'name' => $area,
              'year' => $year,
            ]
        );

      if (is_null($area_entity)) {
        throw new \Exception("Area name '{$area}' was not found in the Areas sheet. Please check id '{$id}' in the Subareas sheet!");
      }

      array_push($subareas, $name);

      $subarea = $entityTypeManager->getStorage('node')->create([
        'type' => 'subarea',
        'title' => $name,
        'field_short_name' => $row[2],
        'field_description' => $row[3],
        'field_identifier' => $row[4],
        'field_default_area' => $area_entity,
        'field_default_weight' => $row[6],
        'field_year' => $year,
      ]);
      $subarea->save();
    }
  }

  /**
   * Function storeIndexPropertiesIndicatorsByYear.
   */
  public static function storeIndexPropertiesIndicatorsByYear($entityTypeManager, $database, $sheet, $year, &$calculationVariables = []) {
    $rows = array_slice($sheet->toArray(), 1);
    $indicators = [];
    $order = 0;

    foreach ($rows as $row) {
      $id = $row[0];
      $name = $row[1];
      $identifier = $row[4];
      $category = $row[6];
      $algorithm = $row[7];
      $comment = $row[8];
      $predefined_divider = $row[13];
      $subarea = $row[17];
      $subarea_entity = NULL;

      if (in_array($name, $indicators)) {
        throw new \Exception("Indicator name '{$name}' already exists. Please check id '{$id}' in the Indicators sheet!");
      }

      if (!strlen(trim($category))) {
        throw new \Exception("Category name is missing. Please check id '{$id}' in the Indicators sheet!");
      }

      if (!in_array($category, ['survey', 'eurostat', 'manual', 'eu-wide'])) {
        throw new \Exception("Category name is invalid. Please check id '{$id}' in the Indicators sheet!");
      }

      if ($category != 'eu-wide') {
        if (!strlen(trim($subarea))) {
          throw new \Exception("Subarea name is missing. Please check id '{$id}' in the Indicators sheet!");
        }

        $subarea_entity = SubareaHelper::getSubareaEntity(
              $entityTypeManager,
              [
                'name' => $subarea,
                'year' => $year,
              ]
          );

        if (is_null($subarea_entity)) {
          throw new \Exception("Subarea name '{$subarea}' was not found in the Subareas sheet. Please check id '{$id}' in the Indicators sheet!");
        }
      }

      array_push($indicators, $name);

      $data = [
        'type' => 'indicator',
        'title' => $name,
        'field_short_name' => $row[2],
        'field_description' => $row[3],
        'field_identifier' => $identifier,
        'field_source' => $row[5],
        'field_category' => $category,
        'field_algorithm' => $algorithm,
        'field_comment' => $comment,
        'field_report_year' => $row[16],
        'field_default_subarea' => ($category != 'eu-wide') ? $subarea_entity : NULL,
        'field_default_weight' => $row[18],
        'field_year' => $year,
      ];

      if ($category == 'survey') {
        $data['field_order'] = ++$order;
        $data['field_validated'] = TRUE;
      }

      $indicator = $entityTypeManager->getStorage('node')->create($data);
      $indicator->save();

      $database->insert('indicator_disclaimers')->fields([
        'indicator_id' => $indicator->id(),
        'direction' => $row[9],
        'new_indicator' => $row[10],
        'min_max_0037_1' => $row[11],
        'min_max' => $row[12],
      ])->execute();

      if (strlen(trim($predefined_divider)) &&
            $predefined_divider == 0) {
        throw new \Exception("Predefined divider cannot be zero! Please check id '{$id}' in the Indicators sheet!");
      }

      $calculationVariables[$identifier] = [
        'indicator_id' => $indicator->id(),
        'algorithm' => $algorithm,
        'predefined_divider' => $predefined_divider,
        'normalize' => $row[14],
        'inverse_value' => $row[15],
      ];
    }
  }

  /**
   * Function importIndexPropertiesIndicatorsSurveyData.
   */
  public static function importIndexPropertiesIndicatorsSurveyData($entityTypeManager, $database, $sheet, $year, $calculationVariables) {
    $rows = array_slice($sheet->toArray(), 1);
    $identifier = NULL;
    $indicator_entity = NULL;
    $processed_accordions = [];
    $processed_questions = [];
    $db_accordion = NULL;
    $db_question = NULL;
    $value = 0;

    foreach ($rows as $row) {
      if (is_null($identifier)) {
        $identifier = $row[0];

        $indicator_entity = IndicatorHelper::getIndicatorEntity(
            $entityTypeManager,
            [
              'identifier' => $identifier,
              'year' => $year,
            ]
        );
      }

      $question_type = $row[3];
      $question_id = $row[5];
      $parts = explode('-', $question_id);

      if (!in_array($question_id, $processed_accordions)) {
        array_push($processed_accordions, $question_id);

        $db_accordion = IndicatorAccordionHelper::updateOrCreateIndicatorAccordion(
              $entityTypeManager,
              [
                'field_full_title' => $row[2],
                'field_indicator' => $indicator_entity,
                'field_order' => $parts[1],
              ]
          );
      }

      if (!in_array($question_id, $processed_questions)) {
        array_push($processed_questions, $question_id);

        $term = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'indicator_question_types', $question_type);

        $required = (mb_strtolower($row[6]) == 'yes') ? TRUE : FALSE;

        $db_question = IndicatorAccordionQuestionHelper::updateOrCreateIndicatorAccordionQuestion(
              $entityTypeManager,
              [
                'field_full_title' => $row[4],
                'field_accordion' => $db_accordion->id(),
                'field_order' => $parts[2],
                'field_type' => $term->id(),
                'field_info' => $row[10],
                'field_compatible' => (mb_strtolower($row[11]) == 'yes') ? TRUE : FALSE,
                'field_answers_required' => $required,
                'field_reference_required' => $required,
              ]
          );

        $data = $calculationVariables[$identifier];

        $database->insert('indicator_calculation_variables')->fields([
          'indicator_id' => $data['indicator_id'],
          'question_id' => $question_id,
          'algorithm' => $data['algorithm'],
          'type' => $term->id(),
          'predefined_divider' => $data['predefined_divider'],
          'normalize' => $data['normalize'],
          'inverse_value' => $data['inverse_value'],
        ])->execute();
      }

      if ($question_type == 'single-choice' ||
            $question_type == 'multiple-choice') {
        IndicatorAccordionQuestionOptionHelper::updateOrCreateIndicatorAccordionQuestionOption(
              $entityTypeManager,
              [
                'field_full_title' => $row[7],
                'field_question' => $db_question->id(),
                'field_master' => (mb_strtolower($row[8]) == 'yes') ? TRUE : FALSE,
                'field_score' => $row[9],
                'field_value' => ++$value,
              ]
          );
      }
    }
  }

  /**
   * Function importIndexProperties.
   */
  public static function importIndexProperties($entityTypeManager, $fileSystem, $database, $year, $excel, $original_name) {
    $file_path = $fileSystem->realpath($excel);

    $reader = IOFactory::createReaderForFile($file_path);
    $reader->setReadDataOnly(TRUE);

    $spreadsheet = $reader->load($file_path);

    $connection = Database::getConnection();
    $connection->startTransaction();

    try {
      // Delete.
      $entities = IndicatorHelper::getIndicatorEntities($entityTypeManager, $year);
      $indicators = $entityTypeManager->getStorage('node')->loadMultiple($entities);
      foreach ($indicators as $indicator) {
        IndicatorActionHelper::deleteIndicator($entityTypeManager, $database, $indicator);
      }

      $entities = SubareaHelper::getSubareaEntities($entityTypeManager, $year);
      $subareas = $entityTypeManager->getStorage('node')->loadMultiple($entities);
      $entityTypeManager->getStorage('node')->delete($subareas);

      $entities = AreaHelper::getAreaEntities($entityTypeManager, $year);
      $areas = $entityTypeManager->getStorage('node')->loadMultiple($entities);
      $entityTypeManager->getStorage('node')->delete($areas);

      // Areas.
      $sheet = $spreadsheet->getSheetByName('Areas');
      if (is_null($sheet)) {
        throw new \Exception("Areas sheet was not found in the {$original_name}!");
      }
      self::storeIndexPropertiesAreasByYear($entityTypeManager, $sheet, $year);

      $sheet = $spreadsheet->getSheetByName('Subareas');
      if (is_null($sheet)) {
        throw new \Exception("Subareas sheet was not found in the {$original_name}!");
      }
      self::storeIndexPropertiesSubareasByYear($entityTypeManager, $sheet, $year);

      // Indicators.
      $sheet = $spreadsheet->getSheetByName('Indicators');
      if (is_null($sheet)) {
        throw new \Exception("Indicators sheet was not found in the {$original_name}!");
      }
      self::storeIndexPropertiesIndicatorsByYear($entityTypeManager, $database, $sheet, $year, $calculationVariables);

      // Indicators Json Data.
      $sheets = $spreadsheet->getAllSheets();

      foreach ($sheets as $sheet) {
        $title = $sheet->getTitle();

        if (in_array($title, ['Areas', 'Subareas', 'Indicators'])) {
          continue;
        }

        self::importIndexPropertiesIndicatorsSurveyData($entityTypeManager, $database, $sheet, $year, $calculationVariables);
      }

      IndexHelper::updateDraftIndexJsonData($entityTypeManager, $year);
    }
    catch (\Exception $e) {
      return [
        'type' => 'error',
        'msg' => $e->getMessage(),
      ];
    }

    return [
      'type' => 'success',
      'msg' => 'Index properties have been successfully imported!',
    ];
  }

  /**
   * Function cloneArea.
   */
  private static function cloneArea($area, $year_from, $year_to) {
    $replicate_area = $area->createDuplicate();
    $replicate_area->set('field_year', $year_to);
    $replicate_area->set('field_clone_year', $year_from);

    $replicate_area->save();
  }

  /**
   * Function cloneSubarea.
   */
  private static function cloneSubarea($entityTypeManager, $subarea, $year_from, $year_to) {
    $subarea_data = SubareaHelper::getSubareaData($subarea);

    $replicate_subarea = $subarea->createDuplicate();
    $replicate_subarea->set('field_year', $year_to);
    $replicate_subarea->set('field_clone_year', $year_from);

    if (!empty($subarea_data['area'])) {
      $area_from = AreaHelper::getAreaEntity(
            $entityTypeManager,
            [
              'identifier' => $subarea_data['area']['identifier'],
              'year' => $year_to,
            ]
        );
      $replicate_subarea->set('field_default_area', $area_from);
    }

    $replicate_subarea->save();
  }

  /**
   * Function cloneIndicator.
   */
  private static function cloneIndicator($entityTypeManager, $indicator, $year_from, $year_to, $clone_survey) {
    $indicator_data = IndicatorHelper::getIndicatorData($indicator);

    $replicate_indicator = $indicator->createDuplicate();
    $replicate_indicator->set('field_year', $year_to);
    $replicate_indicator->set('field_clone_year', $year_from);
    if (!$clone_survey) {
      $replicate_indicator->set('field_validated', FALSE);
    }

    if (!empty($indicator_data['subarea'])) {
      $subarea_from = SubareaHelper::getSubareaEntity(
            $entityTypeManager,
            [
              'identifier' => $indicator_data['subarea']['identifier'],
              'year' => $year_to,
            ]
        );
      $replicate_indicator->set('field_default_subarea', $subarea_from);
    }

    $replicate_indicator->save();
  }

  /**
   * Function cloneIndexConfiguration.
   */
  public static function cloneIndexConfiguration($entityTypeManager, $database, $inputs) {
    $connection = Database::getConnection();
    $connection->startTransaction();

    try {
      $year_from = $inputs['year_from'];
      $year_to = $inputs['year_to'];
      $clone_survey = $inputs['clone_survey'];

      // Delete.
      $entities = IndicatorHelper::getIndicatorEntities($entityTypeManager, $year_to);
      $indicators = $entityTypeManager->getStorage('node')->loadMultiple($entities);
      foreach ($indicators as $indicator) {
        IndicatorActionHelper::deleteIndicator($entityTypeManager, $database, $indicator);
      }

      $entities = SubareaHelper::getSubareaEntities($entityTypeManager, $year_to);
      $subareas = $entityTypeManager->getStorage('node')->loadMultiple($entities);
      $entityTypeManager->getStorage('node')->delete($subareas);

      $entities = AreaHelper::getAreaEntities($entityTypeManager, $year_to);
      $areas = $entityTypeManager->getStorage('node')->loadMultiple($entities);
      $entityTypeManager->getStorage('node')->delete($areas);

      // Areas.
      $entities = AreaHelper::getAreaEntities($entityTypeManager, $year_from);
      $areas = $entityTypeManager->getStorage('node')->loadMultiple($entities);
      foreach ($areas as $area) {
        self::cloneArea($area, $year_from, $year_to);
      }

      // Subreas.
      $entities = SubareaHelper::getSubareaEntities($entityTypeManager, $year_from);
      $subareas = $entityTypeManager->getStorage('node')->loadMultiple($entities);
      foreach ($subareas as $subarea) {
        self::cloneSubarea($entityTypeManager, $subarea, $year_from, $year_to);
      }

      // Indicators.
      $entities = IndicatorHelper::getIndicatorEntities($entityTypeManager, $year_from);
      $indicators = $entityTypeManager->getStorage('node')->loadMultiple($entities);
      foreach ($indicators as $indicator) {
        self::cloneIndicator($entityTypeManager, $indicator, $year_from, $year_to, $clone_survey);
      }

      IndexHelper::updateDraftIndexJsonData($entityTypeManager, $year_to);
    }
    catch (\Exception $e) {
      return [
        'type' => 'error',
        'status' => 500,
        'msg' => $e->getMessage(),
      ];
    }

    return [
      'type' => 'success',
      'status' => 200,
      'msg' => 'Index configuration have been successfully cloned!',
    ];
  }

  /**
   * Function cloneSurveyConfiguration.
   */
  public static function cloneSurveyConfiguration($entityTypeManager, $inputs) {
    $connection = Database::getConnection();
    $connection->startTransaction();

    try {
      $year_from = $inputs['year_from'];
      $year_to = $inputs['year_to'];

      // Indicators.
      $entities = IndicatorHelper::getIndicatorEntities($entityTypeManager, $year_from);
      $indicators = $entityTypeManager->getStorage('node')->loadMultiple($entities);
      foreach ($indicators as $indicator) {
        $indicator_data = IndicatorHelper::getIndicatorData($indicator);

        $replicate_indicator_entity = IndicatorHelper::getIndicatorEntity(
        $entityTypeManager,
        [
          'identifier' => $indicator_data['identifier'],
          'year' => $year_to,
        ]
        );

        $replicate_indicator = $entityTypeManager->getStorage('node')->load($replicate_indicator_entity);

        IndicatorActionHelper::cloneSurveyIndicator($entityTypeManager, $indicator, $replicate_indicator);
      }
    }
    catch (\Exception $e) {
      return [
        'type' => 'error',
        'status' => 500,
        'msg' => $e->getMessage(),
      ];
    }

    return [
      'type' => 'success',
      'status' => 200,
      'msg' => 'Survey configuration have been successfully cloned!',
    ];
  }

}
