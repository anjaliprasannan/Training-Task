<?php

namespace Drupal\task_caching\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Provides a 'Latest Articles' Block with User Email.
 *
 * @Block(
 *   id = "latest_articles_block",
 *   admin_label = @Translation("Latest Articles Block with Email")
 * )
 */
class LatestArticlesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The path alias manager service.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new LatestArticlesBlock.
   *
   * @param array $configuration
   *   A configuration array.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The alias manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AliasManagerInterface $alias_manager, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->aliasManager = $alias_manager;
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
      $container->get('path_alias.manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $articles = $this->getLatestArticles();
    $items = [];
    $cache_tags = ['node_list:article'];
  
    foreach ($articles as $article) {
      $alias = $this->aliasManager->getAliasByPath('/node/' . $article->nid);
      $items[] = [
        '#markup' => $this->t('<a href=":url">@title</a>', [
          ':url' => $alias,
          '@title' => $article->title,
        ]),
      ];
      $cache_tags[] = 'node:' . $article->nid;
    }
  
    // Get the current user's email
    $user_email = $this->currentUser->getEmail() ?? 'No email available';
  
    $items[] = [
      '#markup' => $this->t('Logged in as: <strong>@email</strong>', ['@email' => $user_email]),
    ];
  
    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#cache' => [
        'tags' => $cache_tags,
        'contexts' => ['user'],
      ],
    ];
  }  

  /**
   * Fetch the latest 3 published articles.
   */
  private function getLatestArticles() {
    $query = Database::getConnection()->select('node_field_data', 'n');
    $query->fields('n', ['nid', 'title']);
    $query->condition('n.status', 1);
    $query->condition('n.type', 'article');
    $query->orderBy('n.created', 'DESC');
    $query->range(0, 3);

    return $query->execute()->fetchAll();
  }

}
