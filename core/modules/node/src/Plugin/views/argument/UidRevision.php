<?php

namespace Drupal\node\Plugin\views\argument;

use Drupal\user\Plugin\views\argument\Uid;
use Drupal\views\Attribute\ViewsArgument;

/**
 * Filter handler, accepts a user ID.
 *
 * Checks for nodes that a user posted or created a revision on.
 */
#[ViewsArgument(
  id: 'node_uid_revision',
)]
class UidRevision extends Uid {

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();
    $placeholder = $this->placeholder();
    $this->query->addWhereExpression(0, "$this->tableAlias.uid = $placeholder OR ((SELECT COUNT(DISTINCT vid) FROM {node_revision} nr WHERE nr.revision_uid = $placeholder AND nr.nid = $this->tableAlias.nid) > 0)", [$placeholder => $this->argument]);
  }

}
