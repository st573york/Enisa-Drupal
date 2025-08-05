<?php

namespace Drupal\country_survey_management\Controller;

use Drupal\general_management\Helper\ConstantHelper;
use Drupal\country_survey_management\Helper\CountrySurveyHelper;
use Drupal\general_management\Helper\GeneralHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Class Country Survey Datatable Controller.
 */
final class CountrySurveyDatatableController extends ControllerBase {
  /**
   * Member variable entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * Member variable currentUser.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;
  /**
   * Member variable dateFormatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Function __construct.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $current_user,
    DateFormatterInterface $dateFormatter,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $current_user;
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * Function create.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('date.formatter')
    );
  }

  /**
   * Function listSurveyDashboard.
   */
  public function listSurveyDashboard($survey_id) {
    $data = [];
    $countries = GeneralHelper::getTaxonomyTerms($this->entityTypeManager, 'countries', 'entity');
    foreach ($countries as $country) {
      if ($country->label() == ConstantHelper::USER_GROUP) {
        continue;
      }

      $country_data = [
        'country' => [
          'name' => $country->label(),
        ],
      ];

      $country_survey_entity = CountrySurveyHelper::getCountrySurveyEntity($this->entityTypeManager, $survey_id, $country->id());
      if (!is_null($country_survey_entity)) {
        $country_survey = $this->entityTypeManager->getStorage('node')->load($country_survey_entity);
        $country_survey_data = CountrySurveyHelper::getCountrySurveyData($this->entityTypeManager, $this->dateFormatter, $country_survey);
        $country_survey_additional_data = CountrySurveyHelper::getCountrySurveyAdditionalData($this->entityTypeManager, $this->dateFormatter, $country_survey_data);

        $country_data = array_merge($country_data, $country_survey_data, $country_survey_additional_data);
        $country_data['country_data'] = TRUE;
      }

      array_push($data, $country_data);
    }

    return new JsonResponse([
      'status' => 'success',
      'data' => $data,
    ]);
  }

  /**
   * Function listCountrySurveyDashboard.
   */
  public function listCountrySurveyDashboard($country_survey_id) {
    $indicators = CountrySurveyHelper::getCountrySurveyIndicators($this->entityTypeManager, $this->currentUser, $this->dateFormatter, $country_survey_id);

    return new JsonResponse([
      'status' => 'success',
      'data' => $indicators,
    ]);
  }

}
