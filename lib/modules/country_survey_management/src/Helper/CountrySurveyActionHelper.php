<?php

namespace Drupal\country_survey_management\Helper;

use Drupal\index_and_survey_configuration_management\Helper\IndicatorAccordionHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorAccordionQuestionHelper;
use Drupal\user_management\Helper\UserPermissionsHelper;
use Drupal\general_management\Helper\ValidationHelper;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Validator\Constraints\NotBlank;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Class Country Survey Action Helper.
 */
class CountrySurveyActionHelper {

  /**
   * Function validateAnswers.
   */
  public static function validateAnswers($entityTypeManager, $answers, $data) {
    $messages = [];

    foreach ($answers as $indicator) {
      if (empty($data['indicators_assigned_exact']) ||
            !in_array($indicator['id'], $data['indicators_assigned_exact']['id'])) {
        continue;
      }

      $accordion_entity = IndicatorAccordionHelper::getIndicatorAccordionEntity($entityTypeManager, $indicator['id'], $indicator['accordion']);
      $question_entity = IndicatorAccordionQuestionHelper::getIndicatorAccordionQuestionEntity($entityTypeManager, $accordion_entity, $indicator['question']);

      $question = $entityTypeManager->getStorage('node')->load($question_entity);
      $question_data = IndicatorAccordionQuestionHelper::getIndicatorAccordionQuestionData($question);

      $validation = self::getQuestionValidationRequired($question_data);

      $answers = (isset($indicator['answers']) && !empty($indicator['answers'])) ? current($indicator['answers']) : NULL;
      $reference_year = $indicator['reference_year'];
      $reference_source = $indicator['reference_source'];
      $rating = ($indicator['rating'] > 0) ? $indicator['rating'] : NULL;

      $inputs = [
        $indicator['inputs']['answers'] => $answers,
        $indicator['inputs']['reference_year'] => $reference_year,
        $indicator['inputs']['reference_source'] => $reference_source,
        $indicator['inputs']['rating'] => $rating,
      ];

      $rules = [];

      foreach (array_keys($inputs) as $key) {
        $input = '';
        if (preg_match('/answers/', $key)) {
          $input = 'answers';
        }
        elseif (preg_match('/reference-year/', $key)) {
          $input = 'reference_year';
        }
        elseif (preg_match('/reference-source/', $key)) {
          $input = 'reference_source';
        }
        elseif (preg_match('/rating/', $key)) {
          $input = 'rating';
        }

        $rule = self::getQuestionValidationRule($input, $validation, $indicator);

        if (!is_null($rule)) {
          $rules = array_merge($rules, [
            $key => $rule,
          ]);
        }
      }

      if (!empty($rules)) {
        $errors = ValidationHelper::getValidationErrors($inputs, $rules);

        $messages = array_merge($messages, ValidationHelper::formatValidationErrors($errors));
      }
    }

    return $messages;
  }

  /**
   * Function getQuestionValidationRequired.
   */
  public static function getQuestionValidationRequired($question_data) {
    $validation = [];
    if ($question_data['answers_required']) {
      $validation['answers']['required'] = TRUE;
    }
    if ($question_data['reference_required']) {
      $validation['reference_year']['required'] = TRUE;
      $validation['reference_source']['required'] = TRUE;
    }
    $validation['rating']['required'] = TRUE;

    return $validation;
  }

  /**
   * Function getQuestionValidationRule.
   */
  public static function getQuestionValidationRule($input, $validation, $indicator) {
    $rule = NULL;
    $validation = (isset($validation[$input])) ? $validation[$input] : [];

    if (isset($validation['required']) &&
          $validation['required']) {
      switch ($input) {
        case 'answers':
        case 'reference_year':
        case 'reference_source':
          if ($indicator['choice'] != 3) {
            $rule = new NotBlank([
              'message' => ValidationHelper::$requiredMessage,
            ]);
          }

          break;

        case 'rating':
          $rule = new NotBlank([
            'message' => ValidationHelper::$requiredMessage,
          ]);

          break;

        default:
          break;
      }
    }

    return $rule;
  }

  /**
   * Function canSaveCountrySurvey.
   */
  public static function canSaveCountrySurvey($inputs, $assignee_country_survey_data) {
    if (empty($assignee_country_survey_data['indicators_assigned'])) {
      return [
        'type' => 'warning',
        'status' => 403,
        'msg' => 'You haven\'t been assigned any indicators!',
      ];
    }

    if (!in_array($inputs['active_indicator'], $assignee_country_survey_data['indicators_assigned']['id'])) {
      return [
        'type' => 'warning',
        'status' => 403,
        'msg' => 'You are no longer assigned this indicator. Please start the survey again!',
      ];
    }

    return [
      'type' => 'success',
      'status' => 200,
    ];
  }

  /**
   * Function canSubmitCountrySurvey.
   */
  public static function canSubmitCountrySurvey($currentUser, $indicators_list, $assignee_country_survey_data) {
    if ($indicators_list !== $assignee_country_survey_data['indicators_assigned']['id'] &&
          $assignee_country_survey_data['default_assignee']['id'] != $currentUser->id()) {
      return [
        'type' => 'warning',
        'status' => 403,
        'msg' => 'You are no longer assigned these indicators. Please start the survey again!',
      ];
    }

    return [
      'type' => 'success',
      'status' => 200,
    ];
  }

  /**
   * Function canFinaliseCountrySurvey.
   */
  public static function canFinaliseCountrySurvey($entityTypeManager, $currentUser) {
    if (UserPermissionsHelper::isAdmin($entityTypeManager, $currentUser)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Function exportSurveyExcel.
   */
  public static function exportSurveyExcel($year, $country_iso = NULL) {
    $command = [
      'drush',
      'export-survey-excel',
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
   * Function filterSurveyExcel.
   */
  public static function filterSurveyExcel(
    $entityTypeManager,
    $currentUser,
    $fileSystem,
    $year,
    $filename,
    $indicators_assigned,
  ) {
    $directory = 'public://offline-survey';

    $file_uri = $directory . '/' . $year . '/' . $filename;
    $file_path = $fileSystem->realpath($file_uri);

    $reader = IOFactory::createReaderForFile($file_path);

    $spreadsheet = $reader->load($file_path);

    if (UserPermissionsHelper::isOperator($entityTypeManager, $currentUser)) {
      $sheets = $spreadsheet->getAllSheets();

      foreach ($sheets as $sheet) {
        $title = $sheet->getTitle();

        if (!is_numeric($title)) {
          continue;
        }

        $rows = $sheet->toArray();

        $identifier = (isset($rows[0][3])) ? $rows[0][3] : NULL;

        // Remove sheet not assigned to user.
        if (is_null($identifier) ||
              !in_array($identifier, $indicators_assigned)) {
          $spreadsheet->removeSheetByIndex(
                $spreadsheet->getIndex(
                    $spreadsheet->getSheetByName($title)
                )
            );
        }
      }
    }

    $directory .= '/user-' . $currentUser->id();

    $fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $file_uri = $directory . '/' . $filename;
    $file_path = $fileSystem->realpath($file_uri);

    $writer = new Xlsx($spreadsheet);
    $writer->save($file_path);
  }

  /**
   * Function storeCountrySurvey.
   */
  public static function storeCountrySurvey($entityTypeManager, $survey_data, $user_data) {
    $data = [
      'type' => 'country_survey',
      'title' => $user_data['country']['name'] . ' - ' . $survey_data['title'],
      'field_default_assignee' => $user_data['id'],
      'field_survey' => $survey_data['id'],
      'field_country' => $user_data['country']['id'],
    ];

    $country_survey = $entityTypeManager->getStorage('node')->create($data);
    $country_survey->save();

    return $country_survey;
  }

}
