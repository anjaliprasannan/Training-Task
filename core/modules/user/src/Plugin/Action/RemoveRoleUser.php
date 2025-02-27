<?php

namespace Drupal\user\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Removes a role from a user.
 */
#[Action(
  id: 'user_remove_role_action',
  label: new TranslatableMarkup('Remove a role from the selected users'),
  type: 'user'
)]
class RemoveRoleUser extends ChangeUserRoleBase {

  /**
   * {@inheritdoc}
   */
  public function execute($account = NULL) {
    $rid = $this->configuration['rid'];
    // Skip removing the role from the user if they already don't have it.
    if ($account !== FALSE && $account->hasRole($rid)) {
      // For efficiency manually save the original account before applying
      // any changes.
      $account->setOriginal(clone $account);
      $account->removeRole($rid)->save();
    }
  }

}
