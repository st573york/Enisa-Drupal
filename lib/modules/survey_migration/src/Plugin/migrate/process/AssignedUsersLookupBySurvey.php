<?php

namespace Drupal\survey_migration\Plugin\migrate\process;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\ProcessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Assigned Users Lookup By Survey process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "assigned_users_lookup_by_survey"
 * )
 */
final class AssignedUsersLookupBySurvey extends ProcessPluginBase implements ContainerFactoryPluginInterface {
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
    $drupal_entity_ids = [];
    $questionnaire_id = $value;

    $connection = Database::getConnection('default', 'enisa');

    // Execute a query in the migration database.
    $query = $connection->select('questionnaire_countries', 'qc')
      ->fields('us', ['email'])
      ->condition('qc.questionnaire_id', $questionnaire_id);

    $query->leftJoin('questionnaire_users', 'qu', 'qc.id = qu.questionnaire_country_id');
    $query->leftJoin('users', 'us', 'qu.user_id = us.id');

    $migration_assigned_users = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    if (!empty($migration_assigned_users)) {
      $emails = array_column($migration_assigned_users, 'email');

      $query = $this->entityTypeManager
        ->getStorage('user')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('mail', $emails, 'IN');

      $entities = $query->execute();

      foreach ($entities as $entity) {
        array_push($drupal_entity_ids, [
          'target_id' => $entity,
        ]);
      }
    }

    return $drupal_entity_ids;
  }

}
