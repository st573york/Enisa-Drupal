<?php

namespace Drupal\index_and_survey_configuration_management\Helper;

use Drupal\country_survey_management\Helper\SurveyIndicatorHelper;
use Drupal\general_management\Helper\ValidationHelper;
use Drupal\general_management\Exception\CustomException;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Class Indicator Helper.
 */
class IndicatorHelper {

  /**
   * Function validateInputs.
   */
  public static function validateInputs($entityTypeManager, $inputs) {
    $rules = [
      'indicator_link' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
      'category' => new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]),
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
        new Regex([
          'pattern' => '/^0(\.\d{1,20})?$/',
          'message' => 'This field must be between 0 and 0.99999999999999999999.',
        ]),
      ]),
    ];

    if (isset($inputs['category']) &&
          $inputs['category'] != 'eu-wide') {
      $rules['default_subarea'] = new NotBlank([
        'message' => ValidationHelper::$requiredMessage,
      ]);
    }

    $errors = ValidationHelper::getValidationErrors($inputs, $rules);

    if (!self::isIndicatorUnique($entityTypeManager, $inputs)) {
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
   * Function isIndicatorUnique.
   */
  public static function isIndicatorUnique($entityTypeManager, $inputs) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'indicator')
      ->condition('title', $inputs['title']);

    if (isset($inputs['id'])) {
      $query->condition('nid', $inputs['id'], '<>');
    }
    if (isset($inputs['year'])) {
      $query->condition('field_year', $inputs['year']);
    }

    $indicator = $query->execute();

    if (!empty($indicator)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Function areIndicatorsValidated.
   */
  public static function areIndicatorsValidated($entityTypeManager, $year) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'indicator')
      ->condition('field_category', 'survey')
      ->condition('field_year', $year)
      ->condition('field_validated', FALSE)
      ->condition('status', 1);

    $indicators_not_validated = $query->execute();

    if (!empty($indicators_not_validated)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Function hasIndicatorSurveyAnyData.
   */
  public static function hasIndicatorSurveyAnyData($inputs, &$exceptions) {
    if (isset($inputs['indicator_survey_data']) &&
          $inputs['indicator_survey_data'] != '[]') {
      return TRUE;
    }

    array_push($exceptions, new CustomException(
          'Please add one or more sections/questions!',
          400,
          NULL,
          [
            'element_type' => 'alert',
          ]
      ));

    return FALSE;
  }

  /**
   * Function getIndicatorSurveyElementType.
   */
  public static function getIndicatorSurveyElementType($element) {
    $type = $element['type'];
    if ($type == 'radio-group') {
      $type = 'single-choice';
    }
    elseif ($type == 'checkbox-group') {
      $type = 'multiple-choice';
    }
    elseif ($type == 'text') {
      $type = 'free-text';
    }

    return $type;
  }

  /**
   * Function getIndicatorData.
   */
  public static function getIndicatorData($node, $include_area = FALSE) {
    if (!$node) {
      return [];
    }

    $node_data = [
      'id' => $node->id(),
      'order' => $node->field_order->value ?? '',
      'name' => $node->getTitle(),
      'short_name' => $node->field_short_name->value ?? '',
      'description' => $node->field_description->value ?? '',
      'category' => $node->field_category->value ?? '',
      'identifier' => $node->field_identifier->value ?? '',
      'year' => $node->field_year->value ?? '',
      'clone_year' => $node->field_clone_year->value ?? '',
      'report_year' => $node->field_report_year->value ?? '',
      'weight' => $node->field_default_weight->value ?? '',
      'algorithm' => ($node->field_algorithm->value) ? urldecode($node->field_algorithm->value) : '',
      'source' => $node->field_source->value ?? '',
      'comment' => $node->field_comment->value ?? '',
      'validated' => filter_var($node->field_validated->value, FILTER_VALIDATE_BOOLEAN) ?? '',
      'subarea' => '',
      'status' => ($node->status->value) ? 'Published' : 'Draft',
    ];

    if ($node->hasField('field_default_subarea') &&
          !$node->get('field_default_subarea')->isEmpty()) {
      $subarea = $node->get('field_default_subarea')->entity;

      $node_data['subarea'] = SubareaHelper::getSubareaData($subarea, $include_area);
    }

    return $node_data;
  }

  /**
   * Function getIndicatorsData.
   */
  public static function getIndicatorsData($entityTypeManager, $database, $entities, $data_to_export = FALSE) {
    $indicators = $entityTypeManager->getStorage('node')->loadMultiple($entities);

    $data = [];
    foreach ($indicators as $indicator) {
      $indicator_data = self::getIndicatorData($indicator);

      if ($data_to_export) {
        $indicator_data['algorithm'] = strip_tags(preg_replace('/<\/p[^>]*>/', ' ', $indicator_data['algorithm']));

        $indicator_data['disclaimers'] = [];
        $indicator_disclaimers = $database->select('indicator_disclaimers', 'id')
          ->fields('id')
          ->condition('indicator_id', $indicator_data['id'])
          ->execute()
          ->fetchAssoc();

        if (!empty($indicator_disclaimers)) {
          $indicator_data['disclaimers'] = $indicator_disclaimers;
        }

        $indicator_data['calculation_variables'] = [];
        $indicator_calculation_variables = $database->select('indicator_calculation_variables', 'icv')
          ->fields('icv')
          ->condition('indicator_id', $indicator_data['id'])
          ->execute()
          ->fetchAssoc();

        if (!empty($indicator_calculation_variables)) {
          $indicator_data['calculation_variables'] = $indicator_calculation_variables;
        }
      }

      array_push($data, $indicator_data);
    }

    return $data;
  }

  /**
   * Function getIndicatorEntity.
   */
  public static function getIndicatorEntity($entityTypeManager, $data) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'indicator')
      ->condition('status', 1);

    if (isset($data['identifier'])) {
      $query->condition('field_identifier', $data['identifier']);
    }
    if (isset($data['year'])) {
      $query->condition('field_year', $data['year']);
    }

    $indicator = $query->execute();

    if (!empty($indicator)) {
      return reset($indicator);
    }

    return NULL;
  }

  /**
   * Function getLastIndicatorEntity.
   */
  public static function getLastIndicatorEntity($entityTypeManager, $year, $identifier = NULL) {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'indicator')
      ->condition('field_year', $year, '<')
      ->condition('status', 1);

    if (!is_null($identifier)) {
      $query->condition('field_identifier', $identifier);
    }

    $indicator = $query->sort('field_year', 'DESC')->execute();

    if (!empty($indicator)) {
      return reset($indicator);
    }

    return NULL;
  }

  /**
   * Function getIndicatorEntities.
   */
  public static function getIndicatorEntities($entityTypeManager, $year = '', $category = '') {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'indicator')
      ->condition('status', 1);

    if (!empty($year)) {
      $query->condition('field_year', $year);
    }

    if (!empty($category)) {
      $query->condition('field_category', $category);
    }

    return $query->sort('field_category')->sort('field_order')->sort('field_identifier')->execute();
  }

  /**
   * Function getMaxIndicatorField.
   */
  public static function getMaxIndicatorField($entityTypeManager, $field, $year = '') {
    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'indicator')
      ->condition('status', 1);

    if (!empty($year)) {
      $query->condition('field_year', $year);
    }

    $indicator = $query->sort($field, 'DESC')->execute();

    if (!empty($indicator)) {
      return reset($indicator);
    }

    return NULL;
  }

  /**
   * Function getIndicatorDataForSave.
   */
  public static function getIndicatorDataForSave($entityTypeManager, $inputs, $indicator_data = []) {
    $data = [
      'title' => $inputs['title'],
    ];

    if ($inputs['indicator_link'] != 'new_indicator') {
      $past_indicator = $entityTypeManager->getStorage('node')->load($inputs['indicator_link']);
      $past_indicator_data = self::getIndicatorData($past_indicator);

      $data['field_identifier'] = $past_indicator_data['identifier'];
    }
    elseif (empty($indicator_data)) {
      $data['field_identifier'] = $inputs['max_identifier'];
    }

    unset($inputs['id'], $inputs['title'], $inputs['indicator_link'], $inputs['max_identifier']);

    foreach ($inputs as $key => $value) {
      $data['field_' . $key] = $value;
    }

    if ((empty($indicator_data) ||
           $indicator_data['category'] != 'survey') &&
          $inputs['category'] == 'survey') {
      $max_order = 0;
      $max_indicator_order_entity = self::getMaxIndicatorField($entityTypeManager, 'field_order', $inputs['year']);
      if (!is_null($max_indicator_order_entity)) {
        $max_indicator_order = $entityTypeManager->getStorage('node')->load($max_indicator_order_entity);
        $max_indicator_order_data = self::getIndicatorData($max_indicator_order);

        $max_order = $max_indicator_order_data['order'];
      }

      $data['field_order'] = $max_order + 1;
      $data['field_validated'] = FALSE;
    }

    return $data;
  }

  /**
   * Function getIndicatorFormData.
   */
  public static function getIndicatorFormData($entityTypeManager, $dateFormatter, $indicator, $survey_indicator = NULL) {
    $data = [];

    $choices = IndicatorAccordionQuestionHelper::getIndicatorAccordionQuestionChoices($entityTypeManager);

    $entities = IndicatorAccordionHelper::getIndicatorAccordionEntities($entityTypeManager, $indicator);
    $accordions = $entityTypeManager->getStorage('node')->loadMultiple($entities);

    foreach ($accordions as $accordion) {
      $accordion_data = IndicatorAccordionHelper::getIndicatorAccordionData($accordion);

      $data['accordions'][$accordion_data['order']] = $accordion_data;

      $entities = IndicatorAccordionQuestionHelper::getIndicatorAccordionQuestionEntities($entityTypeManager, [$accordion_data['id']]);
      $questions = $entityTypeManager->getStorage('node')->loadMultiple($entities);

      foreach ($questions as $question) {
        $question_data = IndicatorAccordionQuestionHelper::getIndicatorAccordionQuestionData($question);

        if (!is_null($survey_indicator)) {
          $survey_indicator_answer_entity = SurveyIndicatorHelper::getSurveyIndicatorAnswerEntity($entityTypeManager, $survey_indicator, $question_data['id']);
          if (!is_null($survey_indicator_answer_entity)) {
            $survey_indicator_answer = $entityTypeManager->getStorage('node')->load($survey_indicator_answer_entity);
            $question_data['answers'] = SurveyIndicatorHelper::getSurveyIndicatorAnswerData($entityTypeManager, $dateFormatter, $survey_indicator_answer);
          }
        }

        $data['accordions'][$accordion_data['order']]['questions'][$question_data['order']] = $question_data;

        $question_choices = $choices;
        if ($question_data['type']['id'] == 3) {
          unset($question_choices[1]);
        }
        else {
          unset($question_choices[2]);
        }
        $data['accordions'][$accordion_data['order']]['questions'][$question_data['order']]['choices'] = $question_choices;

        $entities = IndicatorAccordionQuestionOptionHelper::getIndicatorAccordionQuestionOptionEntities($entityTypeManager, [$question_data['id']]);
        $options = $entityTypeManager->getStorage('node')->loadMultiple($entities);

        foreach ($options as $option) {
          $option_data = IndicatorAccordionQuestionOptionHelper::getIndicatorAccordionQuestionOptionData($option);

          $data['accordions'][$accordion_data['order']]['questions'][$question_data['order']]['options'][$option_data['value']] = $option_data;
        }
      }
    }

    return $data;
  }

  /**
   * Function getIndicatorSurveyData.
   */
  public static function getIndicatorSurveyData($entityTypeManager, $indicator_data) {
    $indicator_survey_data = [];

    $entities = IndicatorAccordionHelper::getIndicatorAccordionEntities($entityTypeManager, $indicator_data['id']);
    $accordions = $entityTypeManager->getStorage('node')->loadMultiple($entities);

    foreach ($accordions as $accordion) {
      $accordion_data = IndicatorAccordionHelper::getIndicatorAccordionData($accordion);

      array_push($indicator_survey_data, [
        'type' => 'header',
        'subtype' => 'h1',
        'label' => (!empty($accordion_data['title'])) ? htmlspecialchars_decode($accordion_data['title']) : ' ',
      ]);

      $entities = IndicatorAccordionQuestionHelper::getIndicatorAccordionQuestionEntities($entityTypeManager, [$accordion->id()]);
      $questions = $entityTypeManager->getStorage('node')->loadMultiple($entities);

      foreach ($questions as $question) {
        $question_data = IndicatorAccordionQuestionHelper::getIndicatorAccordionQuestionData($question);

        $question_type = '';
        if ($question_data['type']['id'] == 1) {
          $question_type = 'radio-group';
        }
        elseif ($question_data['type']['id'] == 2) {
          $question_type = 'checkbox-group';
        }
        elseif ($question_data['type']['id'] == 3) {
          $question_type = 'text';
        }

        $name = $question_type . '-' . $accordion_data['order'] . '-' . $question_data['order'];

        $data = [
          'type' => $question_type,
          'required' => ($question_data['answers_required'] && $question_data['reference_required']) ? 1 : 0,
          'description' => $question_data['info'],
          'label' => (!empty($question_data['title'])) ? htmlspecialchars_decode($question_data['title']) : ' ',
          'name' => $name,
          'compatible' => ($question_data['compatible']) ? 1 : 0,
        ];

        if ($question_data['type']['id'] == 1 ||
          $question_data['type']['id'] == 2) {
          if ($question_data['type']['id'] == 2) {
            $data['master'] = $name;
            $data['master_options'] = [];
          }

          $entities = IndicatorAccordionQuestionOptionHelper::getIndicatorAccordionQuestionOptionEntities($entityTypeManager, [$question->id()]);
          $options = $entityTypeManager->getStorage('node')->loadMultiple($entities);

          $values = [];
          foreach ($options as $option) {
            $option_data = IndicatorAccordionQuestionOptionHelper::getIndicatorAccordionQuestionOptionData($option);

            array_push($values, [
              'label' => htmlspecialchars_decode($option_data['title'] ?? ''),
              'value' => (string) $option_data['score'],
            ]);

            if ($question_data['type']['id'] == 2) {
              $data['master_options'][$option_data['id']]['label'] = htmlspecialchars_decode($option_data['title'] ?? '');

              if ($option_data['master']) {
                $data['master_options'][$option_data['id']]['selected'] = TRUE;
              }
            }
          }

          $data['values'] = $values;
        }

        array_push($indicator_survey_data, $data);
      }
    }

    return $indicator_survey_data;
  }

  /**
   * Function getIndicatorsWithSurvey.
   */
  public static function getIndicatorsWithSurvey($entityTypeManager, $dateFormatter, $year) {
    $indicators = [];

    $query = $entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'indicator')
      ->condition('field_category', 'survey')
      ->condition('field_year', $year)
      ->condition('status', 1);

    $indicators_with_survey = $query->sort('field_order')->execute();

    foreach ($indicators_with_survey as $indicator_with_survey) {
      $indicator = $entityTypeManager->getStorage('node')->load($indicator_with_survey);

      $indicator_data = self::getIndicatorData($indicator, TRUE);
      $indicator_data += self::getIndicatorFormData($entityTypeManager, $dateFormatter, $indicator_with_survey);

      if (isset($indicator_data['accordions'])) {
        $indicators[$indicator_with_survey] = $indicator_data;
      }
    }

    return $indicators;
  }

  /**
   * Function getIndicatorsWithSurveyAndAnswers.
   */
  public static function getIndicatorsWithSurveyAndAnswers($entityTypeManager, $dateFormatter, $country_survey_id, $assignee_country_survey_data) {
    $indicators = [];

    if (isset($assignee_country_survey_data['indicators_assigned']['id'])) {
      foreach ($assignee_country_survey_data['indicators_assigned']['id'] as $indicator_id) {
        $indicator = $entityTypeManager->getStorage('node')->load($indicator_id);

        $survey_indicator_entity = SurveyIndicatorHelper::getSurveyIndicatorEntity($entityTypeManager, $country_survey_id, $indicator_id);

        $survey_indicator = $entityTypeManager->getStorage('node')->load($survey_indicator_entity);
        $survey_indicator_data = SurveyIndicatorHelper::getSurveyIndicatorData($entityTypeManager, $dateFormatter, $survey_indicator);

        $indicator_data = self::getIndicatorData($indicator, TRUE);
        $indicator_data += self::getIndicatorFormData($entityTypeManager, $dateFormatter, $indicator_id, $survey_indicator_entity);
        $indicator_data['rating'] = $survey_indicator_data['rating'];
        $indicator_data['comments'] = $survey_indicator_data['comments'];

        if (isset($indicator_data['accordions'])) {
          $indicators[$indicator_id] = $indicator_data;
        }
      }
    }

    return $indicators;
  }

}
