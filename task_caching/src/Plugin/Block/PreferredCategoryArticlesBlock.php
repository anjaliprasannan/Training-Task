<?php

namespace Drupal\task_caching\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Provides a 'Preferred Category Articles' Block.
 *
 * @Block(
 *   id = "preferred_category_articles_block",
 *   admin_label = @Translation("Preferred Category Articles Block")
 * )
 */
class PreferredCategoryArticlesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new PreferredCategoryArticlesBlock.
   *
   * @param array $configuration
   *   A configuration array.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $category_id = $this->getUserPreferredCategory();
    if (!$category_id) {
      return ['#markup' => $this->t('No preferred category selected.')];
    }

    $articles = $this->getArticlesByCategory($category_id);
    $items = [];

    foreach ($articles as $article) {
      $items[] = [
        '#markup' => $this->t('<a href="/node/@nid">@title</a>', [
          '@nid' => $article->nid,
          '@title' => $article->title,
        ]),
      ];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#cache' => [
        'contexts' => ['user_preferred_category'],
        'tags' => ['node_list:article'],
      ],
    ];
  }

  /**
   * Gets the preferred category ID of the current user.
   */
  private function getUserPreferredCategory() {
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());

    if ($user && $user->hasField('field_preferred_category')) {
      $terms = $user->get('field_preferred_category')->referencedEntities();
      return !empty($terms) ? $terms[0]->id() : NULL;
    }

    return NULL;
  }

  /**
   * Fetches articles based on category.
   */
  private function getArticlesByCategory($category_id) {
    $query = Database::getConnection()->select('node__field_category', 'c');
    $query->join('node_field_data', 'n', 'c.entity_id = n.nid');
    $query->fields('n', ['nid', 'title']);
    $query->condition('n.status', 1);
    $query->condition('n.type', 'article');
    $query->condition('c.field_category_target_id', $category_id);
    $query->orderBy('n.created', 'DESC');
    $query->range(0, 5);

    return $query->execute()->fetchAll();
  }

}
