<?php

namespace Drupal\export_management\Helper;

use Drupal\country_survey_management\Helper\CountrySurveyHelper;
use Drupal\general_management\Helper\GeneralHelper;
use Drupal\index_management\Helper\IndexHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorAccordionHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorAccordionQuestionHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorAccordionQuestionOptionHelper;
use Drupal\country_survey_management\Helper\SurveyIndicatorHelper;

/**
 * Class Export Helper.
 */
class ExportHelper {

  /**
   * Function copyDataAndStyle.
   */
  public static function copyDataAndStyle($from, $to) {
    foreach ($from->getRowIterator() as $row) {
      foreach ($row->getCellIterator() as $cell) {
        $coord = $cell->getCoordinate();

        $to->setCellValue($coord, $cell->getValue());
        $to->getStyle($coord)->applyFromArray(
            $from->getStyle($coord)->exportArray()
        );
      }
    }
  }

  /**
   * Function prepareCountriesIso.
   */
  public static function prepareCountriesIso($countries_iso, $categories = []) {
    if (count($categories) > 1) {
      array_unshift($countries_iso, 'EU');
    }

    return array_unique($countries_iso);
  }

  /**
   * Function getIndicatorValues.
   */
  public static function getIndicatorValues($entityTypeManager, $database, $year, $countries, $categories, $sources = []) {
    $indicator_values = $database->select('indicator_values', 'iv')
      ->fields('iv')
      ->condition('year', $year)
      ->condition('country_id', $countries, 'IN')
      ->orderBy('iv.country_id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $data = [];
    foreach ($indicator_values as $indicator_value) {
      $ret = TRUE;

      $country = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'countries', $indicator_value['country_id'], 'tid');

      $indicator = $entityTypeManager->getStorage('node')->load($indicator_value['indicator_id']);

      $indicator_data = IndicatorHelper::getIndicatorData($indicator, TRUE);
      $indicator_data['value'] = $indicator_value['value'];
      $indicator_data['iso'] = $country->field_iso->value;

      if (!in_array($indicator_data['category'], $categories)) {
        $ret &= FALSE;
      }

      if (!empty($sources) &&
            !in_array(mb_strtolower($indicator_data['source']), $sources)) {
        $ret &= FALSE;
      }

      if ($ret) {
        array_push($data, $indicator_data);
      }
    }

    usort($data, function ($a, $b) {
        return $a['identifier'] <=> $b['identifier'];
    });

    return $data;
  }

  /**
   * Function getSurveyIndicatorRawData.
   */
  public static function getSurveyIndicatorRawData($entityTypeManager, $dateFormatter, $country_survey_data, $export_data) {
    $survey_indicator_entities = SurveyIndicatorHelper::getSurveyIndicatorEntities($entityTypeManager, ['country_survey' => $country_survey_data['id']]);

    $survey_indicators = $entityTypeManager->getStorage('node')->loadMultiple($survey_indicator_entities);

    foreach ($survey_indicators as $survey_indicator) {
      $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($entityTypeManager, $dateFormatter, $survey_indicator);

      $indicator = $survey_indicator_data['indicator'];

      $entities = IndicatorAccordionHelper::getIndicatorAccordionEntities($entityTypeManager, $indicator['id']);
      $accordions = $entityTypeManager->getStorage('node')->loadMultiple($entities);

      $identifier = $indicator['identifier'];

      $export_data[$identifier]['indicator'] = $indicator;

      if (!isset($export_data[$identifier]['questions'])) {
        $export_data[$identifier]['questions'] = [];
      }

      foreach ($accordions as $accordion) {
        $accordion_data = IndicatorAccordionHelper::getIndicatorAccordionData($accordion);

        $entities = IndicatorAccordionQuestionHelper::getIndicatorAccordionQuestionEntities($entityTypeManager, [$accordion->id()]);
        $questions = $entityTypeManager->getStorage('node')->loadMultiple($entities);

        foreach ($questions as $question) {
          $question_data = IndicatorAccordionQuestionHelper::getIndicatorAccordionQuestionData($question);

          $question_id = $identifier . '-' . $accordion_data['order'] . '-' . $question_data['order'];
          $question_export_data = &$export_data[$identifier]['questions'][$question_id];
          $question_export_data['section'] = $accordion_data['title'];
          $question_export_data['type'] = $question_data['type']['name'];
          $question_export_data['name'] = $question_data['title'];

          if (!isset($question_export_data['options'])) {
            $question_export_data['options'] = [];
          }

          $entities = IndicatorAccordionQuestionOptionHelper::getIndicatorAccordionQuestionOptionEntities($entityTypeManager, [$question->id()]);
          $options = $entityTypeManager->getStorage('node')->loadMultiple($entities);

          $survey_indicator_answer_data = [];
          $survey_indicator_answer_entity = SurveyIndicatorHelper::getSurveyIndicatorAnswerEntity($entityTypeManager, $survey_indicator_data['id'], $question_data['id']);
          if (!is_null($survey_indicator_answer_entity)) {
            $survey_indicator_answer = $entityTypeManager->getStorage('node')->load($survey_indicator_answer_entity);
            $survey_indicator_answer_data = SurveyIndicatorHelper::getSurveyIndicatorAnswerData($entityTypeManager, $dateFormatter, $survey_indicator_answer);
          }

          if (count($options)) {
            $option_export_data = &$question_export_data['options'];

            foreach ($options as $option) {
              $option_data = IndicatorAccordionQuestionOptionHelper::getIndicatorAccordionQuestionOptionData($option);

              $option_export_data[$option_data['value']]['name'] = $option_data['title'];
              $option_export_data[$option_data['value']]['score'] = $option_data['score'];

              if (!isset($option_export_data[$option_data['value']]['selected'])) {
                $option_export_data[$option_data['value']]['selected'] = [];
              }

              if (isset($survey_indicator_answer_data['options']) &&
                    in_array($option_data['value'], $survey_indicator_answer_data['options'])) {
                array_push($option_export_data[$option_data['value']]['selected'], $country_survey_data['country']['iso']);
              }
            }
          }
        }
      }
    }

    return $export_data;
  }

  /**
   * Function getEurostatIndicatorRawData.
   */
  public static function getEurostatIndicatorRawData($entityTypeManager, $database, $year, $countries) {
    $query = $database->select('eurostat_indicator_variables', 'eiv');
    $query->leftJoin('eurostat_indicators', 'ei', 'ei.id = eiv.eurostat_indicator_id');
    $query->fields('eiv');
    $query->fields('ei', ['identifier']);
    $query->condition('eiv.country_id', $countries, 'IN');
    $query->addExpression('CAST(ei.identifier AS UNSIGNED)', 'identifier_numeric');
    $query->orderBy('identifier_numeric')
      ->orderBy('eiv.country_id')
      ->orderBy('eiv.variable_code');

    $eurostat_indicator_variables = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    $indicator_entities = IndicatorHelper::getIndicatorEntities($entityTypeManager, $year);
    $indicators = $entityTypeManager->getStorage('node')->loadMultiple($indicator_entities);

    $indicators_data = [];
    foreach ($indicators as $indicator) {
      $indicator_data = IndicatorHelper::getIndicatorData($indicator, TRUE);

      $indicators_data[$indicator_data['identifier']] = $indicator_data;
    }

    $data = [];
    foreach ($eurostat_indicator_variables as $eurostat_indicator_variable) {
      if (!in_array($eurostat_indicator_variable['identifier'], array_keys($indicators_data))) {
        continue;
      }

      $indicator_data = $indicators_data[$eurostat_indicator_variable['identifier']];

      $country = GeneralHelper::getTaxonomyTerm($entityTypeManager, 'countries', $eurostat_indicator_variable['country_id'], 'tid');

      $indicator_data['variable_identifier'] = $eurostat_indicator_variable['variable_identifier'];
      $indicator_data['variable_code'] = $eurostat_indicator_variable['variable_code'];
      $indicator_data['variable_name'] = $eurostat_indicator_variable['variable_name'];
      $indicator_data['variable_value'] = $eurostat_indicator_variable['variable_value'];
      $indicator_data['iso'] = $country->field_iso->value;

      array_push($data, $indicator_data);
    }

    return $data;
  }

  /**
   * Function getSurveyIndicatorConfigurationData.
   */
  public static function getSurveyIndicatorConfigurationData($entityTypeManager, $indicator_data) {
    $export_data = [
      'number' => $indicator_data['order'],
      'identifier' => $indicator_data['identifier'],
      'name' => $indicator_data['name'],
      'questions' => [],
    ];

    $entities = IndicatorAccordionHelper::getIndicatorAccordionEntities($entityTypeManager, $indicator_data['id']);
    $accordions = $entityTypeManager->getStorage('node')->loadMultiple($entities);

    foreach ($accordions as $accordion) {
      $accordion_data = IndicatorAccordionHelper::getIndicatorAccordionData($accordion);

      $entities = IndicatorAccordionQuestionHelper::getIndicatorAccordionQuestionEntities($entityTypeManager, [$accordion->id()]);
      $questions = $entityTypeManager->getStorage('node')->loadMultiple($entities);

      foreach ($questions as $question) {
        $question_data = IndicatorAccordionQuestionHelper::getIndicatorAccordionQuestionData($question);

        $question_id = $indicator_data['identifier'] . '-' . $accordion_data['order'] . '-' . $question_data['order'];
        $question_export_data = &$export_data['questions'][$question_id];
        $question_export_data = [
          'id' => $question_data['id'],
          'section' => $accordion_data['title'],
          'type' => $question_data['type']['name'],
          'name' => $question_data['title'],
          'compatible' => ($question_data['compatible']) ? 'Yes' : 'No',
          'required' => ($question_data['answers_required'] && $question_data['reference_required']) ? 'Yes' : 'No',
          'info' => $question_data['info'],
        ];

        $entities = IndicatorAccordionQuestionOptionHelper::getIndicatorAccordionQuestionOptionEntities($entityTypeManager, [$question->id()]);
        $options = $entityTypeManager->getStorage('node')->loadMultiple($entities);

        if (!empty($options)) {
          $question_export_data['options'] = [];
          $option_export_data = &$question_export_data['options'];

          foreach ($options as $option) {
            $option_data = IndicatorAccordionQuestionOptionHelper::getIndicatorAccordionQuestionOptionData($option);

            $option_export_data[$option_data['value']] = [
              'id' => $option_data['id'],
              'name' => $option_data['title'],
              'master' => ($option_data['master']) ? 'Yes' : 'No',
              'score' => $option_data['score'],
            ];
          }
        }
      }
    }

    return $export_data;
  }

  /**
   * Function getIndexPropertiesOverviewData.
   */
  public static function getIndexPropertiesOverviewData($country_data, &$properties_data) {
    $properties_data['index'][$country_data['iso']] = $country_data['json_data']['contents'][0]['global_index_values'][0][$country_data['title']];

    foreach ($country_data['json_data']['contents'] as $key => $content) {
      if ($key === 0) {
        continue;
      }

      $area = $content['area'];
      $area_name = $area['name'];
      $properties_data['areas'][$area_name]['weight'] = (isset($area['normalized_weight'])) ? $area['normalized_weight'] : 'N/A';
      $properties_data['areas'][$area_name][$country_data['iso']] = $area['values'][0][$country_data['title']];

      foreach ($area['subareas'] as $subarea) {
        $subarea_name = $subarea['name'];
        $properties_data['subareas'][$subarea_name]['weight'] = (isset($subarea['normalized_weight'])) ? $subarea['normalized_weight'] : 'N/A';
        $properties_data['subareas'][$subarea_name]['area'] = $area_name;
        $properties_data['subareas'][$subarea_name][$country_data['iso']] = $subarea['values'][0][$country_data['title']];

        foreach ($subarea['indicators'] as $indicator) {
          if (!isset($indicator['values'])) {
            continue;
          }

          $properties_data['indicators'][$indicator['name']]['weight'] = (isset($indicator['normalized_weight'])) ? $indicator['normalized_weight'] : 'N/A';
          $properties_data['indicators'][$indicator['name']][$country_data['iso']] = $indicator['values'][0][$country_data['title']];
          $properties_data['indicators'][$indicator['name']]['subarea'] = $subarea_name;
          $properties_data['indicators'][$indicator['name']]['area'] = $area_name;
        }
      }
    }
  }

  /**
   * Function prepareIndexPropertiesOverviewData.
   */
  public static function prepareIndexPropertiesOverviewData($entityTypeManager, $dateFormatter, $index, $countries) {
    $data = [];
    $countries_iso = [];
    $properties_data = [];

    $eu_index_entity = IndexHelper::getEuIndexEntity($entityTypeManager, $index);
    $country_index_entities = IndexHelper::getCountryIndexEntities($entityTypeManager, $index, $countries);

    $countries_index = $entityTypeManager->getStorage('node')->loadMultiple($country_index_entities);

    if (!is_null($eu_index_entity)) {
      $eu_index = $entityTypeManager->getStorage('node')->load($eu_index_entity);
      $eu_index_data = IndexHelper::getEuIndexData($dateFormatter, $eu_index);
      $eu_index_data['iso'] = 'EU';

      array_push($data, $eu_index_data);
    }

    foreach ($countries_index as $country_index) {
      $country_index_data = IndexHelper::getCountryIndexData($dateFormatter, $country_index);
      $country_index_data['iso'] = $country_index_data['country']['iso'];

      array_push($data, $country_index_data);
    }

    foreach ($data as $country_data) {
      if (!isset($country_data['json_data']['contents'])) {
        continue;
      }

      array_push($countries_iso, $country_data['iso']);

      self::getIndexPropertiesOverviewData($country_data, $properties_data);
    }

    return [
      'hasData' =>
      (isset($properties_data['index']) &&
               isset($properties_data['areas']) &&
               isset($properties_data['subareas']) &&
               isset($properties_data['indicators'])) ? TRUE : FALSE,
      'countries_iso' => $countries_iso,
      'properties_data' => $properties_data,
    ];
  }

  /**
   * Function prepareIndicatorValuesData.
   */
  public static function prepareIndicatorValuesData($entityTypeManager, $database, $year, $countries, $categories, $data) {
    $indicator_values = self::getIndicatorValues($entityTypeManager, $database, $year, $countries, $categories);

    $countries_iso = [];
    $indicator_values_data = [];

    foreach ($indicator_values as $indicator_value) {
      $identifier = $indicator_value['identifier'];

      $indicator_values_data[$identifier]['name'] = $indicator_value['name'];
      $indicator_values_data[$identifier]['area'] = $indicator_value['subarea']['area']['name'] ?? 'N/A';
      $indicator_values_data[$identifier]['subarea'] = $indicator_value['subarea']['name'] ?? 'N/A';
      $indicator_values_data[$identifier]['source'] = $indicator_value['source'];
      $indicator_values_data[$identifier]['year'] = $indicator_value['report_year'];
      if (count($categories) > 1) {
        $indicator_values_data[$identifier]['EU'] = $data['properties_data']['indicators'][$indicator_value['name']]['EU'];
      }
      $indicator_values_data[$identifier][$indicator_value['iso']] = $indicator_value['value'];

      array_push($countries_iso, $indicator_value['iso']);
    }

    $countries_iso = self::prepareCountriesIso($countries_iso, $categories);

    return [
      'hasData' => (!empty($countries_iso) && !empty($indicator_values_data)) ? TRUE : FALSE,
      'countries_iso' => $countries_iso,
      'indicator_values_data' => $indicator_values_data,
    ];
  }

  /**
   * Function prepareEuWideIndicatorValuesData.
   */
  public static function prepareEuWideIndicatorValuesData($entityTypeManager, $database, $year, $countries, $categories) {
    $indicator_values = self::getIndicatorValues($entityTypeManager, $database, $year, $countries, $categories);

    $indicator_values_data = [];
    foreach ($indicator_values as $indicator_value) {
      $identifier = $indicator_value['identifier'];

      $indicator_values_data[$identifier]['name'] = $indicator_value['name'];
      $indicator_values_data[$identifier]['source'] = $indicator_value['source'];
      $indicator_values_data[$identifier]['year'] = $indicator_value['report_year'];
      $indicator_values_data[$identifier]['value'] = $indicator_value['value'];
    }

    return [
      'hasData' => (!empty($indicator_values_data)) ? TRUE : FALSE,
      'indicator_values_data' => $indicator_values_data,
    ];
  }

  /**
   * Function prepareSurveyIndicatorRawData.
   */
  public static function prepareSurveyIndicatorRawData($entityTypeManager, $dateFormatter, $year, $countries) {
    $country_survey_entities = CountrySurveyHelper::getCountrySurveyEntities($entityTypeManager, $countries);

    $country_surveys = $entityTypeManager->getStorage('node')->loadMultiple($country_survey_entities);

    $countries_iso = [];
    $indicator_values = [];

    foreach ($country_surveys as $country_survey) {
      $country_survey_data = CountrySurveyHelper::getCountrySurveyData($entityTypeManager, $dateFormatter, $country_survey);

      if ($country_survey_data['survey']['year'] != $year) {
        continue;
      }

      $indicator_values = self::getSurveyIndicatorRawData($entityTypeManager, $dateFormatter, $country_survey_data, $indicator_values);

      array_push($countries_iso, $country_survey_data['country']['iso']);
    }

    $indicator_values_data = [];
    foreach ($indicator_values as $indicator_value) {
      $indicator = $indicator_value['indicator'];
      $identifier = $indicator['identifier'];

      $indicator_values_data[$identifier]['name'] = $indicator['name'];
      $indicator_values_data[$identifier]['area'] = $indicator['subarea']['area']['name'] ?? 'N/A';
      $indicator_values_data[$identifier]['subarea'] = $indicator['subarea']['name'] ?? 'N/A';
      $indicator_values_data[$identifier]['questions'] = $indicator_value['questions'];
    }

    return [
      'hasData' => (!empty($countries_iso) && !empty($indicator_values_data)) ? TRUE : FALSE,
      'countries_iso' => $countries_iso,
      'indicator_values_data' => $indicator_values_data,
    ];
  }

  /**
   * Function prepareEurostatIndicatorRawData.
   */
  public static function prepareEurostatIndicatorRawData($entityTypeManager, $database, $year, $countries) {
    $indicator_values = self::getEurostatIndicatorRawData($entityTypeManager, $database, $year, $countries);

    $countries_iso = [];
    $indicator_values_data = [];

    foreach ($indicator_values as $indicator_value) {
      $variable_identifier = $indicator_value['variable_identifier'];

      if (!isset($indicator_values_data[$variable_identifier])) {
        $indicator_values_data[$variable_identifier] = [
          'identifier' => $indicator_value['identifier'],
          'name' => $indicator_value['name'],
          'variable_name' => $indicator_value['variable_name'],
          'variable_code' => $indicator_value['variable_code'],
          'area' => $indicator_value['subarea']['area']['name'] ?? 'N/A',
          'subarea' => $indicator_value['subarea']['name'] ?? 'N/A',
          'year' => $indicator_value['report_year'],
        ];
      }

      $indicator_values_data[$variable_identifier][$indicator_value['iso']] = $indicator_value['variable_value'];

      array_push($countries_iso, $indicator_value['iso']);
    }

    $countries_iso = self::prepareCountriesIso($countries_iso);

    return [
      'hasData' => (!empty($countries_iso) && !empty($indicator_values_data)) ? TRUE : FALSE,
      'countries_iso' => $countries_iso,
      'indicator_values_data' => $indicator_values_data,
    ];
  }

  /**
   * Function prepareShodanIndicatorRawData.
   */
  public static function prepareShodanIndicatorRawData($entityTypeManager, $database, $year, $countries) {
    $indicator_values = self::getIndicatorValues($entityTypeManager, $database, $year, $countries, ['manual'], ['shodan']);

    $countries_iso = [];
    $indicator_values_data = [];

    foreach ($indicator_values as $indicator_value) {
      $indicator_values_data[$indicator_value['identifier']] = [
        'name' => $indicator_value['name'],
        'area' => $indicator_value['subarea']['area']['name'] ?? 'N/A',
        'subarea' => $indicator_value['subarea']['name'] ?? 'N/A',
        'year' => $indicator_value['report_year'],
        $indicator_value['iso'] => $indicator_value['value'],
      ];

      array_push($countries_iso, $indicator_value['iso']);
    }

    $countries_iso = self::prepareCountriesIso($countries_iso);

    return [
      'hasData' => (!empty($countries_iso) && !empty($indicator_values_data)) ? TRUE : FALSE,
      'countries_iso' => $countries_iso,
      'indicator_values_data' => $indicator_values_data,
    ];
  }

}
