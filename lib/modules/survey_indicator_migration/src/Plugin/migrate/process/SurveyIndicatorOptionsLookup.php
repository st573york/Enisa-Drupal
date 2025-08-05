<?php

namespace Drupal\survey_indicator_migration\Plugin\migrate\process;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\ProcessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Survey Indicator Options Lookup process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "survey_indicator_options_lookup"
 * )
 */
final class SurveyIndicatorOptionsLookup extends ProcessPluginBase implements ContainerFactoryPluginInterface {
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
   * Get indicator entity.
   */
  private function getIndicatorEntity($migration_answer) {
    $entities = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'indicator',
        'title' => $migration_answer->indicator_name,
        'field_identifier' => $migration_answer->indicator_identifier,
        'field_year' => $migration_answer->indicator_year,
      ]);

    if (!empty($entities)) {
      return reset($entities);
    }

    return NULL;
  }

  /**
   * Get accordion entity.
   */
  private function getAccordionEntity($migration_answer, $indicator_entity) {
    if (is_null($indicator_entity)) {
      return NULL;
    }

    $entities = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'indicator_accordion',
        'field_full_title' => $migration_answer->accordion_name,
        'field_indicator' => $indicator_entity->id(),
        'field_order' => $migration_answer->accordion_order,
      ]);

    if (!empty($entities)) {
      return reset($entities);
    }

    return NULL;
  }

  /**
   * Get question entity.
   */
  private function getQuestionEntity($migration_answer, $accordion_entity) {
    if (is_null($accordion_entity)) {
      return NULL;
    }

    $entities = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'indicator_accordion_question',
        'field_full_title' => $migration_answer->question_name,
        'field_accordion' => $accordion_entity->id(),
        'field_order' => $migration_answer->question_order,
      ]);

    if (!empty($entities)) {
      return reset($entities);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $drupal_entity_ids = [];

    $additional_fields = $this->configuration['additional_fields'];

    $survey_indicator_id = $row->getSourceProperty($additional_fields['survey_indicator_id']) ?? '';
    $question_id = $row->getSourceProperty($additional_fields['question_id']) ?? '';

    $connection = Database::getConnection('default', 'enisa');

    // Execute a query in the migration database.
    $query = $connection->select('survey_indicator_answers', 'sia')
      ->fields('sia', ['choice_id'])
      ->condition('sia.survey_indicator_id', $survey_indicator_id)
      ->condition('sia.question_id', $question_id);

    $query->leftJoin('indicator_accordion_questions', 'iaq', 'sia.question_id = iaq.id');
    $query->leftJoin('indicator_accordions', 'ia', 'iaq.accordion_id = ia.id');
    $query->leftJoin('indicators', 'is', 'ia.indicator_id = is.id');

    // Aliasing fields.
    $query->addField('iaq', 'title', 'question_name');
    $query->addField('iaq', 'order', 'question_order');
    $query->addField('ia', 'title', 'accordion_name');
    $query->addField('ia', 'order', 'accordion_order');
    $query->addField('is', 'name', 'indicator_name');
    $query->addField('is', 'identifier', 'indicator_identifier');
    $query->addField('is', 'year', 'indicator_year');

    $migration_answer = $query->execute()->fetch();

    if ($migration_answer->choice_id == 3) {
      return $drupal_entity_ids;
    }

    // Execute a query in the migration database.
    $query = $connection->select('survey_indicator_options', 'sio')
      ->fields('iaqo', ['text', 'value'])
      ->condition('sio.survey_indicator_id', $survey_indicator_id);

    $query->leftJoin('indicator_accordion_question_options', 'iaqo', 'sio.option_id = iaqo.id');

    $migration_options = $query->execute()->fetchAll();

    if (empty($migration_options)) {
      return $drupal_entity_ids;
    }

    $indicator_entity = $this->getIndicatorEntity($migration_answer);
    $accordion_entity = $this->getAccordionEntity($migration_answer, $indicator_entity);
    $question_entity = $this->getQuestionEntity($migration_answer, $accordion_entity);

    foreach ($migration_options as $migration_option) {
      $entities = $this->entityTypeManager
        ->getStorage('node')
        ->loadByProperties([
          'type' => 'indicator_accordion_question_opt',
          'field_full_title' => $migration_option->text,
          'field_question' => $question_entity?->id(),
          'field_value' => $migration_option->value,
        ]);

      if (!empty($entities)) {
        $entity = reset($entities);

        array_push($drupal_entity_ids, [
          'target_id' => $entity->id(),
        ]);
      }
    }

    return $drupal_entity_ids;
  }

}
