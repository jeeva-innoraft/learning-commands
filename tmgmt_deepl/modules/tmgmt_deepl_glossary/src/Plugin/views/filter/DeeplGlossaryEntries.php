<?php

namespace Drupal\tmgmt_deepl_glossary\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\filter\StringFilter;
use Drupal\views\Plugin\ViewsHandlerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter entries for deepl_glossary.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("tmgmt_deepl_glossary_entries")
 */
class DeeplGlossaryEntries extends StringFilter implements ContainerFactoryPluginInterface {

  /**
   * Views Handler Plugin Manager.
   *
   * @var \Drupal\views\Plugin\ViewsHandlerManager
   */
  protected ViewsHandlerManager $joinHandler;

  /**
   * Constructs a new LatestRevision.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\views\Plugin\ViewsHandlerManager $join_handler
   *   Views Handler Plugin Manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection, ViewsHandlerManager $join_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $connection);

    $this->joinHandler = $join_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // @phpstan-ignore-next-line
    return new static(
      $configuration, $plugin_id, $plugin_definition,
      $container->get('database'),
      $container->get('plugin.manager.views.join')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;

    // Add LEFT join definition for deepl_glossary__entries table.
    $definition = [
      'table' => 'deepl_glossary__entries',
      'type' => 'LEFT',
      'field' => 'entity_id',
      'left_table' => 'tmgmt_deepl_glossary',
      'left_field' => 'id',
    ];

    /** @var \Drupal\views\Plugin\views\join\JoinPluginBase $join */
    $join = $this->joinHandler->createInstance('standard', $definition);
    $table_alias = 'tmgmt_deepl_glossary_entries';
    $query->addTable('tmgmt_deepl_glossary', NULL, $join, $table_alias);

    // Add where query and filter by entries_subject or entries_definition.
    if (isset($this->value) && strlen($this->value) > 0) {
      $value = '%' . $this->connection->escapeLike($this->value) . '%';
      $where = '("' . $table_alias . '"."entries_subject" LIKE \'' . $value . '\' OR "' . $table_alias . '"."entries_definition" LIKE \'' . $value . '\')';
      $query->addWhereExpression($this->options['group'], $where);
    }
  }

}
