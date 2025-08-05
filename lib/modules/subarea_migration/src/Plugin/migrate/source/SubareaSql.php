<?php

namespace Drupal\subarea_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for custom SQL.
 *
 * @MigrateSource(
 *   id = "subarea_sql"
 * )
 */
class SubareaSql extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('subareas', 'su')
      ->fields('su', ['id', 'name', 'short_name', 'description', 'default_weight', 'identifier', 'year', 'clone_year']);

    $query->leftJoin('areas', 'ar', 'su.default_area_id = ar.id');

    // Aliasing fields.
    $query->addField('ar', 'name', 'area_name');
    $query->addField('ar', 'identifier', 'area_identifier');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('ID'),
      'name' => $this->t('Subarea name'),
      'short_name' => $this->t('Subarea short name'),
      'description' => $this->t('Subarea description'),
      'default_weight' => $this->t('Subarea default weight'),
      'identifier' => $this->t('Subarea identifier'),
      'year' => $this->t('Subarea year'),
      'clone_year' => $this->t('Subarea clone year'),
      'area_name' => $this->t('Subarea default area name'),
      'area_identifier' => $this->t('Subarea default area identifier'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
        'alias' => 'su',
      ],
    ];
  }

}
