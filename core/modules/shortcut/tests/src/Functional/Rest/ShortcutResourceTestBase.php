<?php

declare(strict_types=1);

namespace Drupal\Tests\shortcut\Functional\Rest;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\shortcut\Entity\Shortcut;
use Drupal\shortcut\Entity\ShortcutSet;
use Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase;

/**
 * Resource test base for Shortcut entity.
 */
abstract class ShortcutResourceTestBase extends EntityResourceTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['comment', 'shortcut'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'shortcut';

  /**
   * {@inheritdoc}
   */
  protected static $patchProtectedFieldNames = [];

  /**
   * The Shortcut entity.
   *
   * @var \Drupal\shortcut\ShortcutInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    switch ($method) {
      case 'GET':
      case 'POST':
      case 'PATCH':
      case 'DELETE':
        $this->grantPermissionsToTestedRole(['access shortcuts', 'customize shortcut links']);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    // Create shortcut.
    $shortcut = Shortcut::create([
      'shortcut_set' => 'default',
      'title' => $this->t('Comments'),
      'weight' => -20,
      'link' => [
        'uri' => 'internal:/admin/content/comment',
        'options' => [
          'fragment' => 'new',
        ],
      ],
    ]);
    $shortcut->save();

    return $shortcut;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    return [
      'uuid' => [
        [
          'value' => $this->entity->uuid(),
        ],
      ],
      'id' => [
        [
          'value' => (int) $this->entity->id(),
        ],
      ],
      'title' => [
        [
          'value' => 'Comments',
        ],
      ],
      'shortcut_set' => [
        [
          'target_id' => 'default',
          'target_type' => 'shortcut_set',
          'target_uuid' => ShortcutSet::load('default')->uuid(),
        ],
      ],
      'link' => [
        [
          'uri' => 'internal:/admin/content/comment',
          'title' => NULL,
          'options' => [
            'fragment' => 'new',
          ],
        ],
      ],
      'weight' => [
        [
          'value' => -20,
        ],
      ],
      'langcode' => [
        [
          'value' => 'en',
        ],
      ],
      'default_langcode' => [
        [
          'value' => TRUE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    return [
      'title' => [
        [
          'value' => 'Comments',
        ],
      ],
      'link' => [
        [
          'uri' => 'internal:/',
        ],
      ],
      'shortcut_set' => [
        [
          'target_id' => 'default',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    switch ($method) {
      case 'GET':
      case 'POST':
      case 'PATCH':
      case 'DELETE':
        return "The shortcut set must be the currently displayed set for the user and the user must have 'access shortcuts' AND 'customize shortcut links' permissions.";

      default:
        return parent::getExpectedUnauthorizedAccessMessage($method);
    }
  }

}
