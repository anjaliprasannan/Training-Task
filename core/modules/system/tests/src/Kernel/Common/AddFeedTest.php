<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Common;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;

/**
 * Make sure that attaching feeds works correctly with various constructs.
 *
 * @group Common
 */
class AddFeedTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Tests attaching feeds with paths, URLs, and titles.
   */
  public function testBasicFeedAddNoTitle(): void {
    $path = $this->randomMachineName(12);
    $external_url = 'http://' . $this->randomMachineName(12) . '/' . $this->randomMachineName(12);
    $fully_qualified_local_url = Url::fromUri('base:' . $this->randomMachineName(12), ['absolute' => TRUE])->toString();

    $path_for_title = $this->randomMachineName(12);
    $external_for_title = 'http://' . $this->randomMachineName(12) . '/' . $this->randomMachineName(12);
    $fully_qualified_for_title = Url::fromUri('base:' . $this->randomMachineName(12), ['absolute' => TRUE])->toString();

    $urls = [
      'path without title' => [
        'url' => Url::fromUri('base:' . $path, ['absolute' => TRUE])->toString(),
        'title' => '',
      ],
      'external URL without title' => [
        'url' => $external_url,
        'title' => '',
      ],
      'local URL without title' => [
        'url' => $fully_qualified_local_url,
        'title' => '',
      ],
      'path with title' => [
        'url' => Url::fromUri('base:' . $path_for_title, ['absolute' => TRUE])->toString(),
        'title' => $this->randomMachineName(12),
      ],
      'external URL with title' => [
        'url' => $external_for_title,
        'title' => $this->randomMachineName(12),
      ],
      'local URL with title' => [
        'url' => $fully_qualified_for_title,
        'title' => $this->randomMachineName(12),
      ],
    ];

    $build = [];
    foreach ($urls as $feed_info) {
      $build['#attached']['feed'][] = [$feed_info['url'], $feed_info['title']];
    }

    // Use the bare HTML page renderer to render our links.
    $renderer = $this->container->get('bare_html_page_renderer');
    $response = $renderer->renderBarePage($build, '', 'maintenance_page');
    // Glean the content from the response object.
    $this->setRawContent($response->getContent());
    // Assert that the content contains the RSS links we specified.
    foreach ($urls as $feed_info) {
      $this->assertPattern($this->urlToRSSLinkPattern($feed_info['url'], $feed_info['title']));
    }
  }

  /**
   * Creates a pattern representing the RSS feed in the page.
   */
  public function urlToRSSLinkPattern($url, $title = '') {
    // Escape any regular expression characters in the URL ('?' is the worst).
    $url = preg_replace('/([+?.*])/', '[$0]', $url);
    $generated_pattern = '%<link +href="' . $url . '" +rel="alternate" +title="' . $title . '" +type="application/rss.xml" */>%';
    return $generated_pattern;
  }

  /**
   * Checks that special characters are correctly escaped.
   *
   * @see https://www.drupal.org/node/1211668
   */
  public function testFeedIconEscaping(): void {
    $variables = [
      '#theme' => 'feed_icon',
      '#url' => 'node',
      '#title' => '<>&"\'',
    ];
    $text = (string) \Drupal::service('renderer')->renderRoot($variables);
    $this->assertEquals('Subscribe to &lt;&gt;&amp;&quot;&#039;', trim(strip_tags($text)), 'feed_icon template escapes reserved HTML characters.');
  }

  /**
   * Tests that the rendered output contains specific attributes.
   */
  public function testAttributeAdded(): void {
    $variables = [
      '#theme' => 'feed_icon',
      '#url' => 'node/add/',
      '#title' => 'testing title',
      '#attributes' => ['title' => 'some title', 'class' => ['some-class']],
    ];
    $rendered_output = (string) \Drupal::service('renderer')->renderRoot($variables);

    // Check if the class 'some-class' is present in the rendered output.
    $this->assertStringContainsString('some-class', $rendered_output, "The class 'some-class' should be present in the rendered output.");
  }

}
