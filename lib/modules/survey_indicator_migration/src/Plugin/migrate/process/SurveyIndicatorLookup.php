<?php

namespace Drupal\survey_indicator_migration\Plugin\migrate\process;

use Drupal\general_management\Helper\GeneralHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\ProcessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Survey Indicator Lookup process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "survey_indicator_lookup"
 * )
 */
final class SurveyIndicatorLookup extends ProcessPluginBase implements ContainerFactoryPluginInterface {
  /**
   * Member variable entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Function __construct.
   */
  public function __construct($configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Function create.
   */
  public static function create(ContainerInterface $container, $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $additional_fields = $this->configuration['additional_fields'];

    $survey_name = $row->getSourceProperty($additional_fields['survey_name']) ?? '';
    $country_name = $row->getSourceProperty($additional_fields['country_name']) ?? '';
    $indicator_name = $row->getSourceProperty($additional_fields['indicator_name']) ?? '';
    $indicator_identifier = $row->getSourceProperty($additional_fields['indicator_identifier']) ?? '';
    $indicator_year = $row->getSourceProperty($additional_fields['indicator_year']) ?? '';

    $entities = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'survey',
        'title' => $survey_name,
      ]);

    $survey_entity = NULL;
    if (!empty($entities)) {
      $survey_entity = reset($entities);
    }

    $country_entity = GeneralHelper::getTaxonomyTerm($this->entityTypeManager, 'countries', $country_name);

    $entities = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'country_survey',
        'field_survey' => $survey_entity?->id(),
        'field_country' => $country_entity?->id(),
      ]);

    $country_survey_entity = NULL;
    if (!empty($entities)) {
      $country_survey_entity = reset($entities);
    }

    $entities = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'indicator',
        'title' => $indicator_name,
        'field_identifier' => $indicator_identifier,
        'field_year' => $indicator_year,
      ]);

    $indicator_entity = NULL;
    if (!empty($entities)) {
      $indicator_entity = reset($entities);
    }

    $entities = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'survey_indicator',
        'field_country_survey' => $country_survey_entity?->id(),
        'field_indicator' => $indicator_entity?->id(),
      ]);

    if (!empty($entities)) {
      $entity = reset($entities);

      return $entity->id();
    }

    return NULL;
  }

}
