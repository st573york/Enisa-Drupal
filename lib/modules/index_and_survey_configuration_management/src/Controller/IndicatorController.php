<?php

namespace Drupal\index_and_survey_configuration_management\Controller;

use Drupal\general_management\Helper\ConstantHelper;
use Drupal\index_management\Helper\IndexHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorActionHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorAccordionHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorAccordionQuestionHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorAccordionQuestionOptionHelper;
use Drupal\index_and_survey_configuration_management\Helper\SubareaHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Database\Database;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\general_management\Exception\CustomException;
use Twig\Environment;

/**
 * Class Indicator Controller.
 */
final class IndicatorController extends ControllerBase {
  const VALIDATION_EXCEPTION_CODE = 400;

  /**
   * Member variable entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * Member variable requestStack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;
  /**
   * Member variable database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;
  /**
   * Member variable dateFormatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;
  /**
   * Member variable twig.
   *
   * @var \Twig\Environment
   */
  protected $twig;

  /**
   * Function __construct.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RequestStack $request_stack,
    Connection $database,
    DateFormatterInterface $dateFormatter,
    Environment $twig,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->database = $database;
    $this->dateFormatter = $dateFormatter;
    $this->twig = $twig;
  }

  /**
   * Function create.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('twig')
    );
  }

  /**
   * Function listIndicators.
   */
  public function listIndicators(Request $request) {
    $inputs = $request->query->all();

    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $entities = IndicatorHelper::getIndicatorEntities($this->entityTypeManager, $year, $inputs['category']);
    $data = IndicatorHelper::getIndicatorsData($this->entityTypeManager, $this->database, $entities);

    return new JsonResponse([
      'status' => 'success',
      'data' => $data,
    ]);
  }

  /**
   * Function createOrShowIndicator.
   */
  public function createOrShowIndicator($indicator_data = []) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');
    $clone_year = NULL;

    $entities = IndicatorHelper::getIndicatorEntities($this->entityTypeManager, $year);
    $current_indicators_data = IndicatorHelper::getIndicatorsData($this->entityTypeManager, $this->database, $entities);
    $current_indicators_identifiers = array_column($current_indicators_data, 'identifier');
    foreach ($current_indicators_data as $current_indicator_data) {
      if (!empty($current_indicator_data['clone_year'])) {
        $clone_year = $current_indicator_data['clone_year'];

        break;
      }
    }
    if (is_null($clone_year)) {
      $last_indicator_entity = IndicatorHelper::getLastIndicatorEntity($this->entityTypeManager, $year);
      $last_indicator = $this->entityTypeManager->getStorage('node')->load($last_indicator_entity);
      $last_indicator_data = IndicatorHelper::getIndicatorData($last_indicator);

      $clone_year = $last_indicator_data['year'];
    }
    $entities = IndicatorHelper::getIndicatorEntities($this->entityTypeManager, $clone_year);
    $clone_from_indicators_data = IndicatorHelper::getIndicatorsData($this->entityTypeManager, $this->database, $entities);
    $clone_from_indicators_identifiers = array_column($clone_from_indicators_data, 'identifier');

    $not_linked_indicators = [];
    foreach ($clone_from_indicators_data as $clone_from_indicator_data) {
      if ((!empty($indicator_data) && $indicator_data['identifier'] == $clone_from_indicator_data['identifier']) ||
          !in_array($clone_from_indicator_data['identifier'], $current_indicators_identifiers)) {
        array_push($not_linked_indicators, $clone_from_indicator_data);
      }
    }
    usort($not_linked_indicators, function ($a, $b) {
      return strcmp($a['name'], $b['name']);
    });

    $max_indicator_identifier_entity = IndicatorHelper::getMaxIndicatorField($this->entityTypeManager, 'field_identifier');
    $max_indicator_identifier = $this->entityTypeManager->getStorage('node')->load($max_indicator_identifier_entity);
    $max_indicator_identifier_data = IndicatorHelper::getIndicatorData($max_indicator_identifier);

    $entities = SubareaHelper::getSubareaEntities($this->entityTypeManager, $year);
    $subareas_data = SubareaHelper::getSubareasData($this->entityTypeManager, $entities);

    return new JsonResponse($this->twig->render('@enisa/ajax/indicator-management.html.twig', [
      'selected_indicator' => $indicator_data,
      'max_identifier' => (int) $max_indicator_identifier_data['identifier'] + 1,
      'not_linked_indicators' => $not_linked_indicators,
      'is_identifier_linked' => (!empty($indicator_data) && in_array($indicator_data['identifier'], $clone_from_indicators_identifiers)) ? TRUE : FALSE,
      'subareas' => $subareas_data,
      'categories' => ConstantHelper::DEFAULT_CATEGORIES,
    ]));
  }

  /**
   * Function showIndicator.
   */
  public function showIndicator($indicator_id) {
    $indicator = $this->entityTypeManager->getStorage('node')->load($indicator_id);
    $indicator_data = IndicatorHelper::getIndicatorData($indicator);

    return $this->createOrShowIndicator($indicator_data);
  }

  /**
   * Function storeIndicator.
   */
  public function storeIndicator(Request $request) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $inputs = $request->request->all();
    $inputs['year'] = $year;

    $errors = IndicatorHelper::validateInputs($this->entityTypeManager, $inputs);
    if (!empty($errors)) {
      return new JsonResponse(['errors' => $errors, 'type' => 'pageModalForm'], 400);
    }

    IndicatorActionHelper::storeIndicator($this->entityTypeManager, $inputs);

    IndexHelper::updateDraftIndexJsonData($this->entityTypeManager, $year);

    return new JsonResponse(['status' => 'success']);
  }

  /**
   * Function updateIndicator.
   */
  public function updateIndicator(Request $request, $indicator_id) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $inputs = $request->request->all();
    $inputs['id'] = $indicator_id;
    $inputs['year'] = $year;

    $errors = IndicatorHelper::validateInputs($this->entityTypeManager, $inputs);
    if (!empty($errors)) {
      return new JsonResponse(['errors' => $errors, 'type' => 'pageModalForm'], 400);
    }

    $indicator = $this->entityTypeManager->getStorage('node')->load($indicator_id);

    IndicatorActionHelper::updateIndicator($this->entityTypeManager, $indicator, $inputs);

    IndexHelper::updateDraftIndexJsonData($this->entityTypeManager, $year);

    return new JsonResponse(['status' => 'success']);
  }

  /**
   * Function deleteIndicator.
   */
  public function deleteIndicator($indicator_id) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $indicator = $this->entityTypeManager->getStorage('node')->load($indicator_id);

    IndicatorActionHelper::deleteIndicator($this->entityTypeManager, $this->database, $indicator);

    IndexHelper::updateDraftIndexJsonData($this->entityTypeManager, $year);

    return new JsonResponse(['status' => 'success']);
  }

  /**
   * Function manageIndicatorSurvey.
   */
  public function manageIndicatorSurvey($indicator_id) {
    $indicator = $this->entityTypeManager->getStorage('node')->load($indicator_id);
    $indicator_data = IndicatorHelper::getIndicatorData($indicator, TRUE);

    $indicator_survey_data = IndicatorHelper::getIndicatorSurveyData($this->entityTypeManager, $indicator_data);

    $indicators_with_survey = IndicatorHelper::getIndicatorsWithSurvey($this->entityTypeManager, $this->dateFormatter, $indicator_data['year']);

    $canLoadLastIndicatorSurvey = FALSE;
    $last_indicator_entity = IndicatorHelper::getLastIndicatorEntity($this->entityTypeManager, $indicator_data['year'], $indicator_data['identifier']);
    if (!is_null($last_indicator_entity)) {
      $accordion_entities = IndicatorAccordionHelper::getIndicatorAccordionEntities($this->entityTypeManager, $last_indicator_entity);

      if (!empty($accordion_entities)) {
        $canLoadLastIndicatorSurvey = TRUE;
      }
    }

    return [
      '#theme' => 'manage-indicator-survey',
      // Pass data to Twig templates.
      '#canPreviewIndicator' => (isset($indicators_with_survey[$indicator_data['id']]['accordions']) ? TRUE : FALSE),
      '#canPreviewSurvey' => (!empty($indicators_with_survey) ? TRUE : FALSE),
      '#canLoadLastIndicatorSurvey' => $canLoadLastIndicatorSurvey,
      '#indicator_data' => $indicator_data,
      '#indicator_survey_data' => json_encode($indicator_survey_data),
      // Pass data to javascript files.
      '#attached' => [
        'drupalSettings' => [
          'indicator_survey' => [
            'indicator_data' => $indicator_data,
            'indicator_survey_data' => json_encode($indicator_survey_data),
          ],
        ],
      ],
    ];
  }

  /**
   * Function storeIndicatorSurvey.
   */
  public function storeIndicatorSurvey(Request $request, $indicator_id) {
    $inputs = $request->request->all();
    $save = ($inputs['action'] == 'save') ? TRUE : FALSE;
    $exceptions = [];

    $indicator = $this->entityTypeManager->getStorage('node')->load($indicator_id);

    $connection = Database::getConnection();
    $connection->startTransaction();

    try {
      IndicatorActionHelper::deleteIndicator($this->entityTypeManager, $this->database, $indicator, ['delete_survey_configuration']);

      if (IndicatorHelper::hasIndicatorSurveyAnyData($inputs, $exceptions)) {
        $indicator_survey_data = json_decode($inputs['indicator_survey_data'], TRUE);

        $db_accordion = NULL;
        $qkey = 0;

        foreach ($indicator_survey_data as $fkey => $element) {
          $type = IndicatorHelper::getIndicatorSurveyElementType($element);

          $next_element = $indicator_survey_data[$fkey + 1] ?? NULL;

          // Accordion.
          if ($type == 'header') {
            $qkey = 0;

            if (!IndicatorAccordionHelper::hasIndicatorAccordionAnyQuestion($next_element, $save, $exceptions)) {
              if ($save) {
                break;
              }
              else {
                continue;
              }
            }

            $db_accordion = IndicatorAccordionHelper::updateOrCreateIndicatorAccordion(
              $this->entityTypeManager,
              [
                'field_full_title' => (isset($element['label']) && !empty($element['label'])) ? $element['label'] : 'Questions',
                'field_indicator' => $indicator_id,
                'field_order' => $fkey,
              ]);
          }
          // Single-choice, Multiple-choice, Free-text.
          else {
            if (!IndicatorAccordionQuestionHelper::hasIndicatorAccordionQuestionAnyAccordion($db_accordion, $save, $exceptions) &&
                $save) {
              break;
            }

            $db_question = IndicatorAccordionQuestionHelper::processIndicatorAccordionQuestion($this->entityTypeManager, $indicator_id, $db_accordion, $qkey, $fkey, $element, $type, $save, $exceptions);

            if ($type == 'single-choice' ||
                $type == 'multiple-choice') {
              IndicatorAccordionQuestionOptionHelper::processIndicatorAccordionQuestionOptions($this->entityTypeManager, $element, $db_question, $save, $exceptions);
            }
          }
        }
      }

      if (!empty($exceptions)) {
        throw new CustomException('Custom exception occurred.', self::VALIDATION_EXCEPTION_CODE);
      }
    }
    catch (CustomException $e) {
      $indicator->set('field_validated', FALSE);
      $indicator->save();

      $data = [];
      foreach ($exceptions as $exception) {
        array_push($data, [
          'error' => $exception->getMessage(),
          'data' => $exception->getExceptionData(),
        ]);
      }

      return new JsonResponse($data, $e->getCode());
    }

    $indicator->set('field_validated', $save);
    $indicator->save();

    return new JsonResponse(['success' => 'Indicator survey have been successfully saved!']);
  }

  /**
   * Function loadIndicatorSurvey.
   */
  public function loadIndicatorSurvey($indicator_id) {
    $indicator = $this->entityTypeManager->getStorage('node')->load($indicator_id);
    $indicator_data = IndicatorHelper::getIndicatorData($indicator, TRUE);

    IndicatorActionHelper::deleteIndicator($this->entityTypeManager, $this->database, $indicator, ['delete_survey_configuration']);

    $last_indicator_entity = IndicatorHelper::getLastIndicatorEntity($this->entityTypeManager, $indicator_data['year'], $indicator_data['identifier']);
    $last_indicator = $this->entityTypeManager->getStorage('node')->load($last_indicator_entity);

    IndicatorActionHelper::cloneSurveyIndicator($this->entityTypeManager, $last_indicator, $indicator);

    return new JsonResponse(['success' => 'Indicator survey from last year have been successfully loaded!']);
  }

  /**
   * Function updateIndicatorsOrder.
   */
  public function updateIndicatorsOrder(Request $request) {
    $data = json_decode($request->getContent(), TRUE);

    IndicatorActionHelper::updateOrder($this->entityTypeManager, $data);

    return new JsonResponse('success');
  }

}
