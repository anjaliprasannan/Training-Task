<?php

namespace Drupal\task_caching\Cache\Context;

use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Defines a cache context for a user's preferred category.
 */
class UserPreferredCategoryCacheContext implements CacheContextInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new UserPreferredCategoryCacheContext.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(AccountProxyInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t("User's preferred category");
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    // Load the user entity.
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    
    if ($user && $user->hasField('field_preferred_category')) {
      $terms = $user->get('field_preferred_category')->referencedEntities();
      return !empty($terms) ? $terms[0]->id() : 0;
    }
    
    return 0; // Default to no category.
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new \Drupal\Core\Cache\CacheableMetadata();
  }
}
