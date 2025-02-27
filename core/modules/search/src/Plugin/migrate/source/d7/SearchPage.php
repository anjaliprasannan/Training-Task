<?php

namespace Drupal\search\Plugin\migrate\source\d7;

use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\Variable;

/**
 * Drupal 7 search active core modules and rankings source from database.
 *
 * For available configuration keys, refer to the parent classes.
 *
 * @see \Drupal\migrate_drupal\Plugin\migrate\source\Variable
 * @see \Drupal\migrate\Plugin\migrate\source\SqlBase
 * @see \Drupal\migrate\Plugin\migrate\source\SourcePluginBase
 */
#[MigrateSource(
  id: 'd7_search_page',
  source_module: 'search',
)]
class SearchPage extends Variable {

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    return new \ArrayIterator($this->values());
  }

  /**
   * {@inheritdoc}
   */
  protected function values() {
    $search_active_modules = $this->variableGet('search_active_modules', '');
    $values = [];
    foreach (['node', 'user'] as $module) {
      if (isset($search_active_modules[$module])) {
        // Add a module key to identify the source search provider. This value
        // is used in the EntitySearchPage destination plugin.
        $tmp = [
          'module' => $module,
          'status' => $search_active_modules[$module],
        ];
        // Add the node_rank_* variables (only relevant to the node module).
        if ($module === 'node') {
          $tmp = array_merge($tmp, parent::values());
        }
        $values[] = $tmp;
      }
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'module' => $this->t('The module providing a search page.'),
      'status' => $this->t('Whether or not this module is enabled for search.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['module']['type'] = 'string';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  protected function doCount() {
    return $this->initializeIterator()->count();
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $exists = $this->moduleExists($row->getSourceProperty('module'));
    $row->setSourceProperty('module_exists', $exists);
    return parent::prepareRow($row);
  }

}
