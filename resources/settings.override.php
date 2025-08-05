<?php

/**
 * @file
 * Settings override file for the Drupal site.
 *
 * $config['system.logging']['error_level'] = 'verbose';
 */

$databases['enisa']['default'] = [
  'database' => getenv('DRUPAL_MIGRATION_DATABASE_NAME'),
  'username' => getenv('DRUPAL_DATABASE_USERNAME'),
  'password' => getenv('DRUPAL_DATABASE_PASSWORD'),
  'prefix' => getenv('DRUPAL_DATABASE_PREFIX'),
  'host' => getenv('DRUPAL_DATABASE_HOST'),
  'port' => getenv('DRUPAL_DATABASE_PORT'),
  'namespace' => getenv('DRUPAL_DATABASE_DRIVER') !== FALSE ? 'Drupal\\Core\\Database\\Driver\\' . getenv('DRUPAL_DATABASE_DRIVER') : 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => getenv('DRUPAL_DATABASE_DRIVER') !== FALSE ? getenv('DRUPAL_DATABASE_DRIVER') : 'mysql',
  'init_commands' => [
    'isolation_level' => 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED',
  ],
];

// Hours.
$settings['registration_deadline'] = '48';
