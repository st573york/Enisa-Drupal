<?php

namespace Drupal\migrations\Helper;

/**
 * Helper class for migrations schema.
 */
class MigrationsSchemaHelper {

  /**
   * Returns the audits schema.
   */
  public static function getAudits() {
    return [
      'fields' => [
        'id' => [
          'type' => 'serial',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'user_id' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => FALSE,
          'default' => NULL,
        ],
        'ip_address' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
        ],
        'user_agent' => [
          'type' => 'text',
          'size' => 'big',
          'not null' => TRUE,
        ],
        'action' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
        ],
        'description' => [
          'type' => 'text',
          'size' => 'big',
          'not null' => FALSE,
          'default' => NULL,
        ],
        'affected_entity' => [
          'type' => 'text',
          'size' => 'big',
          'not null' => FALSE,
          'default' => NULL,
        ],
        'old_values' => [
          'type' => 'text',
          'size' => 'big',
          'not null' => FALSE,
          'default' => NULL,
        ],
        'new_values' => [
          'type' => 'text',
          'size' => 'big',
          'not null' => FALSE,
          'default' => NULL,
        ],
        'timestamp' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
      ],
      'primary key' => ['id'],
      'indexes' => [
        'user_id' => ['user_id'],
        'timestamp' => ['timestamp'],
      ],
      'foreign keys' => [
        'user_fk' => [
          'table' => 'users_field_data',
          'columns' => ['user_id' => 'uid'],
        ],
      ],
    ];
  }

  /**
   * Returns the indicator disclaimers schema.
   */
  public static function getIndicatorDisclaimers() {
    return [
      'fields' => [
        'id' => [
          'type' => 'serial',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'indicator_id' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'what_100_means_eu' => [
          'type' => 'text',
          'not null' => FALSE,
        ],
        'what_100_means_ms' => [
          'type' => 'text',
          'not null' => FALSE,
        ],
        'frac_max_norm' => [
          'type' => 'int',
          'size' => 'tiny',
          'not null' => FALSE,
          'default' => 0,
        ],
        'rank_norm' => [
          'type' => 'int',
          'size' => 'tiny',
          'not null' => FALSE,
          'default' => 0,
        ],
        'target_100' => [
          'type' => 'int',
          'size' => 'tiny',
          'not null' => FALSE,
          'default' => 0,
        ],
        'target_75' => [
          'type' => 'int',
          'size' => 'tiny',
          'not null' => FALSE,
          'default' => 0,
        ],
        'direction' => [
          'type' => 'float',
          'not null' => FALSE,
        ],
        'new_indicator' => [
          'type' => 'int',
          'size' => 'tiny',
          'not null' => FALSE,
          'default' => 0,
        ],
        'min_max_0037_1' => [
          'type' => 'int',
          'size' => 'tiny',
          'not null' => FALSE,
          'default' => 0,
        ],
        'min_max' => [
          'type' => 'int',
          'size' => 'tiny',
          'not null' => FALSE,
          'default' => 0,
        ],
      ],
      'primary key' => ['id'],
      'indexes' => [
        'indicator_id' => ['indicator_id'],
      ],
      'foreign keys' => [
        'indicator_fk_node' => [
          'table' => 'node',
          'columns' => ['indicator_id' => 'nid'],
        ],
      ],
    ];
  }

  /**
   * Returns the indicator calculation variables schema.
   */
  public static function getIndicatorCalculationVariables() {
    return [
      'fields' => [
        'id' => [
          'type' => 'serial',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'indicator_id' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'question_id' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
        ],
        'algorithm' => [
          'type' => 'text',
          'not null' => FALSE,
        ],
        'type' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'neutral_score' => [
          'type' => 'float',
          'not null' => FALSE,
        ],
        'predefined_divider' => [
          'type' => 'int',
          'not null' => FALSE,
        ],
        'normalize' => [
          'type' => 'int',
          'size' => 'tiny',
          'not null' => TRUE,
          'default' => 0,
        ],
        'inverse_value' => [
          'type' => 'int',
          'not null' => FALSE,
        ],
        'custom_function_name' => [
          'type' => 'text',
          'not null' => FALSE,
        ],
      ],
      'primary key' => ['id'],
      'indexes' => [
        'indicator_id' => ['indicator_id'],
      ],
      'foreign keys' => [
        'indicator_fk_node' => [
          'table' => 'node',
          'columns' => ['indicator_id' => 'nid'],
        ],
      ],
    ];
  }

  /**
   * Returns the indicator values schema.
   */
  public static function getIndicatorValues() {
    return [
      'fields' => [
        'id' => [
          'type' => 'serial',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'indicator_id' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'country_id' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'value' => [
          'type' => 'float',
          'not null' => TRUE,
        ],
        'year' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
      ],
      'primary key' => ['id'],
      'indexes' => [
        'indicator_id' => ['indicator_id'],
      ],
      'foreign keys' => [
        'indicator_fk_node' => [
          'table' => 'node',
          'columns' => ['indicator_id' => 'nid'],
        ],
        'country_fk_term' => [
          'table' => 'taxonomy_term_data',
          'columns' => ['country_id' => 'tid'],
        ],
      ],
    ];
  }

  /**
   * Returns the Eurostat indicators schema.
   */
  public static function getEurostatIndicators() {
    return [
      'fields' => [
        'id' => [
          'type' => 'serial',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'country_id' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'name' => [
          'type' => 'text',
          'not null' => TRUE,
        ],
        'source' => [
          'type' => 'text',
          'not null' => FALSE,
        ],
        'identifier' => [
          'type' => 'text',
          'not null' => FALSE,
        ],
        'report_year' => [
          'type' => 'text',
          'not null' => FALSE,
        ],
        'value' => [
          'type' => 'text',
          'not null' => FALSE,
        ],
      ],
      'primary key' => ['id'],
      'foreign keys' => [
        'country_fk_term' => [
          'table' => 'taxonomy_term_data',
          'columns' => ['country_id' => 'tid'],
        ],
      ],
    ];
  }

  /**
   * Returns the Eurostat indicator variables schema.
   */
  public static function getEurostatIndicatorVariables() {
    return [
      'fields' => [
        'id' => [
          'type' => 'serial',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'eurostat_indicator_id' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'country_id' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'variable_identifier' => [
          'type' => 'text',
          'not null' => TRUE,
        ],
        'variable_code' => [
          'type' => 'text',
          'not null' => FALSE,
        ],
        'variable_name' => [
          'type' => 'text',
          'not null' => FALSE,
        ],
        'variable_value' => [
          'type' => 'float',
          'not null' => FALSE,
        ],
      ],
      'primary key' => ['id'],
      'foreign keys' => [
        'indicator_fk' => [
          'table' => 'eurostat_indicators',
          'columns' => ['eurostat_indicator_id' => 'id'],
        ],
        'country_fk_term' => [
          'table' => 'taxonomy_term_data',
          'columns' => ['country_id' => 'tid'],
        ],
      ],
    ];
  }

}
