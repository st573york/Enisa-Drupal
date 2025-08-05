<?php

namespace Drupal\index_and_survey_configuration_management\Controller;

use Drupal\general_management\Helper\ConstantHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndexAndSurveyConfigurationHelper;
use Drupal\index_management\Helper\IndexHelper;
use Drupal\index_and_survey_configuration_management\Helper\IndicatorHelper;
use Drupal\survey_management\Helper\SurveyHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Twig\Environment;

/**
 * Class Index And Survey Config Controller.
 */
final class IndexAndSurveyConfigController extends ControllerBase {
  const ERROR_NOT_ALLOWED = 'The requested action is not allowed!';

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
   * Member variable fileSystem.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;
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
   * Member variable time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;
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
    EntityTypeManagerInterface $entityTypeManager,
    RequestStack $requestStack,
    FileSystemInterface $fileSystem,
    Connection $database,
    DateFormatterInterface $dateFormatter,
    TimeInterface $time,
    Environment $twig,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->requestStack = $requestStack;
    $this->fileSystem = $fileSystem;
    $this->database = $database;
    $this->dateFormatter = $dateFormatter;
    $this->time = $time;
    $this->twig = $twig;
  }

  /**
   * Function create.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('file_system'),
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('datetime.time'),
      $container->get('twig')
    );
  }

  /**
   * Function manageIndexAndSurveyConfiguration.
   */
  public function manageIndexAndSurveyConfiguration() {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $years = ConstantHelper::getYearsToDateAndNext($this->dateFormatter, $this->time);
    arsort($years);

    $published_index_entity = (!is_null(IndexHelper::getExistingPublishedIndexForYearEntity($this->entityTypeManager, $year))) ? TRUE : FALSE;
    $published_survey_entity = (!is_null(SurveyHelper::getExistingPublishedSurveyForYearEntity($this->entityTypeManager, $year))) ? TRUE : FALSE;

    $indicators_with_survey = IndicatorHelper::getIndicatorsWithSurvey($this->entityTypeManager, $this->dateFormatter, $year);

    return [
      '#theme' => 'manage-configuration',
      // Pass data to Twig templates.
      '#years' => $years,
      '#publishedIndex' => $published_index_entity,
      '#publishedSurvey' => $published_survey_entity,
      '#canPreviewSurvey' => (!empty($indicators_with_survey) ? TRUE : FALSE),
      // Pass data to javascript files.
      '#attached' => [
        'drupalSettings' => [
          'configuration' => [
            'publishedIndex' => $published_index_entity,
            'publishedSurvey' => $published_survey_entity,
          ],
        ],
      ],
    ];
  }

  /**
   * Function showIndexAndSurveyConfigurationImport.
   */
  public function showIndexAndSurveyConfigurationImport() {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $published_index_entity = IndexHelper::getExistingPublishedIndexForYearEntity($this->entityTypeManager, $year);
    if (!is_null($published_index_entity)) {
      return new JsonResponse(['error' => self::ERROR_NOT_ALLOWED], 405);
    }

    return new JsonResponse($this->twig->render('@enisa/ajax/index-and-survey-import.html.twig'));
  }

  /**
   * Function storeIndexAndSurveyConfigurationImport.
   */
  public function storeIndexAndSurveyConfigurationImport(Request $request) {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $inputs = $request->request->all();
    $inputs['file'] = $file = $request->files->get('file');

    $errors = IndexAndSurveyConfigurationHelper::validateIndexAndSurveyConfigurationImport($inputs);
    if (!empty($errors)) {
      return new JsonResponse(['errors' => $errors], 400);
    }

    $published_index_entity = IndexHelper::getExistingPublishedIndexForYearEntity($this->entityTypeManager, $year);
    if (!is_null($published_index_entity)) {
      return new JsonResponse(['error' => self::ERROR_NOT_ALLOWED], 405);
    }

    $directory = 'public://index-properties/' . $year;

    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $original_name = $file->getClientOriginalName();
    $filename = time() . '_' . $original_name;

    $file->move($directory, $filename);

    $excel = $directory . '/' . $filename;

    try {
      $resp = IndexAndSurveyConfigurationHelper::importIndexProperties($this->entityTypeManager, $this->fileSystem, $this->database, $year, $excel, $original_name);
      if ($resp['type'] == 'error') {
        throw new \Exception($resp['msg']);
      }

      return new JsonResponse('success');
    }
    catch (\Exception $e) {
      $this->fileSystem->unlink($excel);

      return new JsonResponse(['error' => $e->getMessage()], 500);
    }
  }

  /**
   * Function showIndexAndSurveyConfigurationClone.
   */
  public function showIndexAndSurveyConfigurationClone() {
    $year = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $published_index_entity = IndexHelper::getExistingPublishedIndexForYearEntity($this->entityTypeManager, $year);
    if (!is_null($published_index_entity)) {
      return new JsonResponse(['error' => self::ERROR_NOT_ALLOWED], 405);
    }

    $published_index_entities = IndexHelper::getPublishedIndexEntities($this->entityTypeManager);
    $published_indexes = IndexHelper::getIndexesData($this->entityTypeManager, $this->dateFormatter, $published_index_entities);

    array_pop($published_indexes);

    return new JsonResponse($this->twig->render('@enisa/ajax/index-and-survey-clone.html.twig', [
      'published_indexes' => $published_indexes,
    ]));
  }

  /**
   * Function storeIndexAndSurveyConfigurationClone.
   */
  public function storeIndexAndSurveyConfigurationClone(Request $request) {
    $inputs = $request->request->all();

    $errors = IndexAndSurveyConfigurationHelper::validateIndexAndSurveyConfigurationClone($inputs);
    if (!empty($errors)) {
      return new JsonResponse(['errors' => $errors], 400);
    }

    $index = $this->entityTypeManager->getStorage('node')->load($inputs['clone-index']);
    $index_data = IndexHelper::getIndexData($this->dateFormatter, $index);

    $published_index_entity = IndexHelper::getExistingPublishedIndexForYearEntity($this->entityTypeManager, $index_data['year']);
    if (is_null($published_index_entity)) {
      return new JsonResponse(['error' => self::ERROR_NOT_ALLOWED], 405);
    }

    $inputs['year_from'] = $index_data['year'];
    $inputs['year_to'] = $this->requestStack->getCurrentRequest()->cookies->get('index-year');

    $resp = IndexAndSurveyConfigurationHelper::cloneIndexConfiguration($this->entityTypeManager, $this->database, $inputs);
    if ($resp['type'] == 'error') {
      return new JsonResponse([$resp['type'] => $resp['msg']], $resp['status']);
    }

    if ($inputs['clone-survey']) {
      $resp = IndexAndSurveyConfigurationHelper::cloneSurveyConfiguration($this->entityTypeManager, $inputs);
      if ($resp['type'] == 'error') {
        return new JsonResponse([$resp['type'] => $resp['msg']], $resp['status']);
      }
    }

    return new JsonResponse('success');
  }

}
