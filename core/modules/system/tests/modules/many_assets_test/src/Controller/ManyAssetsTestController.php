<?php

declare(strict_types=1);

namespace Drupal\many_assets_test\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides a controller for testing asset loading order.
 */
class ManyAssetsTestController extends ControllerBase {

  /**
   * The render array of the assets testing page.
   *
   * @return array
   */
  public function build() {
    return [
      '#markup' => 'I am a page that tests loading order of many dependencies',
      '#attached' => [
        'library' => [
          'many_assets_test/weighted',
          'many_assets_test/many-dependencies',
          'many_assets_test/weighted_again',
        ],
      ],
    ];
  }

}
