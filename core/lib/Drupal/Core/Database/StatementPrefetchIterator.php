<?php

namespace Drupal\Core\Database;

use Drupal\Core\Database\Event\StatementExecutionEndEvent;
use Drupal\Core\Database\Event\StatementExecutionFailureEvent;
use Drupal\Core\Database\Event\StatementExecutionStartEvent;

/**
 * An implementation of StatementInterface that prefetches all data.
 *
 * This class behaves very similar to a StatementWrapperIterator of a
 * \PDOStatement but as it always fetches every row it is possible to
 * manipulate those results.
 */
class StatementPrefetchIterator implements \Iterator, StatementInterface {

  use StatementIteratorTrait;
  use FetchModeTrait;

  /**
   * Main data store.
   *
   * The resultset is stored as a \PDO::FETCH_ASSOC array.
   */
  protected array $data = [];

  /**
   * The list of column names in this result set.
   *
   * @var string[]
   */
  protected ?array $columnNames = NULL;

  /**
   * The number of rows matched by the last query.
   */
  protected ?int $rowCount = NULL;

  /**
   * Holds the default fetch style.
   */
  protected int $defaultFetchStyle = \PDO::FETCH_OBJ;

  /**
   * Holds fetch options.
   *
   * @var string[]
   */
  protected array $fetchOptions = [
    'class' => 'stdClass',
    'constructor_args' => [],
    'object' => NULL,
    'column' => 0,
  ];

  /**
   * Constructs a StatementPrefetchIterator object.
   *
   * @param object $clientConnection
   *   Client database connection object, for example \PDO.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param string $queryString
   *   The query string.
   * @param array $driverOptions
   *   Driver-specific options.
   * @param bool $rowCountEnabled
   *   (optional) Enables counting the rows matched. Defaults to FALSE.
   */
  public function __construct(
    protected readonly object $clientConnection,
    protected readonly Connection $connection,
    protected string $queryString,
    protected array $driverOptions = [],
    protected readonly bool $rowCountEnabled = FALSE,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getConnectionTarget(): string {
    return $this->connection->getTarget();
  }

  /**
   * {@inheritdoc}
   */
  public function execute($args = [], $options = []) {
    if (isset($options['fetch'])) {
      if (is_string($options['fetch'])) {
        // Default to an object. Note: db fields will be added to the object
        // before the constructor is run. If you need to assign fields after
        // the constructor is run. See https://www.drupal.org/node/315092.
        $this->setFetchMode(\PDO::FETCH_CLASS, $options['fetch']);
      }
      else {
        $this->setFetchMode($options['fetch']);
      }
    }

    if ($this->connection->isEventEnabled(StatementExecutionStartEvent::class)) {
      $startEvent = new StatementExecutionStartEvent(
        spl_object_id($this),
        $this->connection->getKey(),
        $this->connection->getTarget(),
        $this->getQueryString(),
        $args ?? [],
        $this->connection->findCallerFromDebugBacktrace()
      );
      $this->connection->dispatchEvent($startEvent);
    }

    // Prepare and execute the statement.
    try {
      $statement = $this->getStatement($this->queryString, $args);
      $return = $statement->execute($args);
    }
    catch (\Exception $e) {
      if (isset($startEvent) && $this->connection->isEventEnabled(StatementExecutionFailureEvent::class)) {
        $this->connection->dispatchEvent(new StatementExecutionFailureEvent(
          $startEvent->statementObjectId,
          $startEvent->key,
          $startEvent->target,
          $startEvent->queryString,
          $startEvent->args,
          $startEvent->caller,
          $startEvent->time,
          get_class($e),
          $e->getCode(),
          $e->getMessage(),
        ));
      }
      throw $e;
    }

    // Fetch all the data from the reply, in order to release any lock as soon
    // as possible.
    $this->data = $statement->fetchAll(\PDO::FETCH_ASSOC);
    $this->rowCount = $this->rowCountEnabled ? $statement->rowCount() : NULL;
    // Destroy the statement as soon as possible. See the documentation of
    // \Drupal\sqlite\Driver\Database\sqlite\Statement for an explanation.
    unset($statement);
    $this->markResultsetIterable($return);

    $this->columnNames = count($this->data) > 0 ? array_keys($this->data[0]) : [];

    if (isset($startEvent) && $this->connection->isEventEnabled(StatementExecutionEndEvent::class)) {
      $this->connection->dispatchEvent(new StatementExecutionEndEvent(
        $startEvent->statementObjectId,
        $startEvent->key,
        $startEvent->target,
        $startEvent->queryString,
        $startEvent->args,
        $startEvent->caller,
        $startEvent->time
      ));
    }

    return $return;
  }

  /**
   * Grab a PDOStatement object from a given query and its arguments.
   *
   * Some drivers (including SQLite) will need to perform some preparation
   * themselves to get the statement right.
   *
   * @param string $query
   *   The query.
   * @param array|null $args
   *   An array of arguments. This can be NULL.
   *
   * @return object
   *   A PDOStatement object.
   */
  protected function getStatement(string $query, ?array &$args = []): object {
    return $this->connection->prepare($query, $this->driverOptions);
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryString() {
    return $this->queryString;
  }

  /**
   * {@inheritdoc}
   */
  public function setFetchMode($mode, $a1 = NULL, $a2 = []) {
    assert(in_array($mode, $this->supportedFetchModes), 'Fetch mode ' . ($this->fetchModeLiterals[$mode] ?? $mode) . ' is not supported. Use supported modes only.');

    $this->defaultFetchStyle = $mode;
    switch ($mode) {
      case \PDO::FETCH_CLASS:
        $this->fetchOptions['class'] = $a1;
        if ($a2) {
          $this->fetchOptions['constructor_args'] = $a2;
        }
        break;

      case \PDO::FETCH_COLUMN:
        $this->fetchOptions['column'] = $a1;
        break;

      case \PDO::FETCH_INTO:
        $this->fetchOptions['object'] = $a1;
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function rowCount() {
    // SELECT query should not use the method.
    if ($this->rowCountEnabled) {
      return $this->rowCount;
    }
    else {
      throw new RowCountException();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fetch($fetch_style = NULL, $cursor_orientation = \PDO::FETCH_ORI_NEXT, $cursor_offset = NULL) {
    $currentKey = $this->getResultsetCurrentRowIndex();

    // We can remove the current record from the prefetched data, before
    // moving to the next record.
    unset($this->data[$currentKey]);
    $currentKey++;
    if (!isset($this->data[$currentKey])) {
      $this->markResultsetFetchingComplete();
      return FALSE;
    }

    // Now, format the next prefetched record according to the required fetch
    // style.
    $rowAssoc = $this->data[$currentKey];
    $mode = $fetch_style ?? $this->defaultFetchStyle;
    $row = match($mode) {
      \PDO::FETCH_ASSOC => $rowAssoc,
      \PDO::FETCH_CLASS, \PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE => $this->assocToClass($rowAssoc, $this->fetchOptions['class'], $this->fetchOptions['constructor_args']),
      \PDO::FETCH_COLUMN => $this->assocToColumn($rowAssoc, $this->columnNames, $this->fetchOptions['column']),
      \PDO::FETCH_NUM => $this->assocToNum($rowAssoc),
      \PDO::FETCH_OBJ => $this->assocToObj($rowAssoc),
      default => throw new DatabaseExceptionWrapper('Fetch mode ' . ($this->fetchModeLiterals[$mode] ?? $mode) . ' is not supported. Use supported modes only.'),
    };
    $this->setResultsetCurrentRow($row);
    return $row;
  }

  /**
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use
   *   ::fetchField() instead.
   *
   * @see https://www.drupal.org/node/3490312
   */
  public function fetchColumn($index = 0) {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use ::fetchField() instead. See https://www.drupal.org/node/3490312', E_USER_DEPRECATED);
    return $this->fetchField($index);
  }

  /**
   * {@inheritdoc}
   */
  public function fetchField($index = 0) {
    if ($row = $this->fetch(\PDO::FETCH_ASSOC)) {
      return $this->assocToColumn($row, $this->columnNames, $index);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchObject(?string $class_name = NULL, array $constructor_arguments = []) {
    if (!isset($class_name)) {
      return $this->fetch(\PDO::FETCH_OBJ);
    }
    $this->fetchOptions = [
      'class' => $class_name,
      'constructor_args' => $constructor_arguments,
    ];
    return $this->fetch(\PDO::FETCH_CLASS);
  }

  /**
   * {@inheritdoc}
   */
  public function fetchAssoc() {
    return $this->fetch(\PDO::FETCH_ASSOC);
  }

  /**
   * {@inheritdoc}
   */
  public function fetchAll($mode = NULL, $column_index = NULL, $constructor_arguments = NULL) {
    $fetchStyle = $mode ?? $this->defaultFetchStyle;

    assert(in_array($fetchStyle, $this->supportedFetchModes), 'Fetch mode ' . ($this->fetchModeLiterals[$fetchStyle] ?? $fetchStyle) . ' is not supported. Use supported modes only.');

    if (isset($column_index)) {
      $this->fetchOptions['column'] = $column_index;
    }
    if (isset($constructor_arguments)) {
      $this->fetchOptions['constructor_args'] = $constructor_arguments;
    }

    $result = [];
    while ($row = $this->fetch($fetchStyle)) {
      $result[] = $row;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchCol($index = 0) {
    $result = [];
    while (($columnValue = $this->fetchField($index)) !== FALSE) {
      $result[] = $columnValue;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchAllKeyed($key_index = 0, $value_index = 1) {
    if (!isset($this->columnNames[$key_index]) || !isset($this->columnNames[$value_index])) {
      return [];
    }

    $key = $this->columnNames[$key_index];
    $value = $this->columnNames[$value_index];

    $result = [];
    while ($row = $this->fetch(\PDO::FETCH_ASSOC)) {
      $result[$row[$key]] = $row[$value];
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchAllAssoc($key, $fetch_style = NULL) {
    $fetchStyle = $fetch_style ?? $this->defaultFetchStyle;

    $result = [];
    while ($row = $this->fetch($fetchStyle)) {
      $result[$this->data[$this->getResultsetCurrentRowIndex()][$key]] = $row;
    }
    return $result;
  }

}
