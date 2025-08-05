<?php

namespace Drupal\user_migration\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\ProcessPluginBase;

/**
 * Role Lookup By Name process plugin.
 *
 * @MigrateProcessPlugin(
 *   id = "role_lookup_by_name"
 * )
 */
class RoleLookupByName extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    switch ($value) {
      case 'admin':
        return 'enisa_administrator';

      case 'Primary PoC':
        return 'primary_poc';

      case 'PoC':
        return 'poc';

      case 'operator':
        return 'operator';

      case 'viewer':
        return 'viewer';

      default:
        return NULL;
    }
  }

}
