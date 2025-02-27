<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Template;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Markup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Template\AttributeArray;
use Drupal\Core\Template\AttributeString;
use Drupal\Core\Template\Loader\StringLoader;
use Drupal\Tests\UnitTestCase;
use Twig\Environment;

/**
 * @coversDefaultClass \Drupal\Core\Template\Attribute
 * @group Template
 */
class AttributeTest extends UnitTestCase {

  /**
   * Tests the constructor of the attribute class.
   */
  public function testConstructor(): void {
    $attribute = new Attribute(['class' => ['example-class']]);
    $this->assertTrue(isset($attribute['class']));
    $this->assertEquals(new AttributeArray('class', ['example-class']), $attribute['class']);

    // Test adding boolean attributes through the constructor.
    $attribute = new Attribute(['selected' => TRUE, 'checked' => FALSE]);
    $this->assertTrue($attribute['selected']->value());
    $this->assertFalse($attribute['checked']->value());

    // Test that non-array values with name "class" are cast to array.
    $attribute = new Attribute(['class' => 'example-class']);
    $this->assertTrue(isset($attribute['class']));
    $this->assertEquals(new AttributeArray('class', ['example-class']), $attribute['class']);

    // Test that safe string objects work correctly.
    $safe_string = $this->prophesize(MarkupInterface::class);
    $safe_string->__toString()->willReturn('example-class');
    $attribute = new Attribute(['class' => $safe_string->reveal()]);
    $this->assertTrue(isset($attribute['class']));
    $this->assertEquals(new AttributeArray('class', ['example-class']), $attribute['class']);
  }

  /**
   * Tests set of values.
   */
  public function testSet(): void {
    $attribute = new Attribute();
    $attribute['class'] = ['example-class'];

    $this->assertTrue(isset($attribute['class']));
    $this->assertEquals(new AttributeArray('class', ['example-class']), $attribute['class']);
  }

  /**
   * Tests adding new values to an existing part of the attribute.
   */
  public function testAdd(): void {
    $attribute = new Attribute(['class' => ['example-class']]);

    $attribute['class'][] = 'other-class';
    $this->assertEquals(new AttributeArray('class', ['example-class', 'other-class']), $attribute['class']);
  }

  /**
   * Tests removing of values.
   */
  public function testRemove(): void {
    $attribute = new Attribute(['class' => ['example-class']]);
    unset($attribute['class']);
    $this->assertFalse(isset($attribute['class']));
  }

  /**
   * Tests setting attributes.
   *
   * @covers ::setAttribute
   */
  public function testSetAttribute(): void {
    $attribute = new Attribute();

    // Test adding various attributes.
    $attributes = ['alt', 'id', 'src', 'title', 'value'];
    foreach ($attributes as $key) {
      foreach (['kitten', ''] as $value) {
        $attribute = new Attribute();
        $attribute->setAttribute($key, $value);
        $this->assertEquals($value, $attribute[$key]);
      }
    }

    // Test adding array to class.
    $attribute = new Attribute();
    $attribute->setAttribute('class', ['kitten', 'cat']);
    $this->assertEquals(['kitten', 'cat'], $attribute['class']->value());

    // Test adding boolean attributes.
    $attribute = new Attribute();
    $attribute['checked'] = TRUE;
    $this->assertTrue($attribute['checked']->value());
  }

  /**
   * Tests removing attributes.
   *
   * @covers ::removeAttribute
   */
  public function testRemoveAttribute(): void {
    $attributes = [
      'alt' => 'Alternative text',
      'id' => 'bunny',
      'src' => 'zebra',
      'style' => 'color: pink;',
      'title' => 'kitten',
      'value' => 'ostrich',
      'checked' => TRUE,
    ];
    $attribute = new Attribute($attributes);

    // Single value.
    $attribute->removeAttribute('alt');
    $this->assertEmpty($attribute['alt']);

    // Multiple values.
    $attribute->removeAttribute('id', 'src');
    $this->assertEmpty($attribute['id']);
    $this->assertEmpty($attribute['src']);

    // Single value in array.
    $attribute->removeAttribute(['style']);
    $this->assertEmpty($attribute['style']);

    // Boolean value.
    $attribute->removeAttribute('checked');
    $this->assertEmpty($attribute['checked']);

    // Multiple values in array.
    $attribute->removeAttribute(['title', 'value']);
    $this->assertEmpty((string) $attribute);

  }

  /**
   * Tests adding class attributes with the AttributeArray helper method.
   *
   * @covers ::addClass
   */
  public function testAddClasses(): void {
    // Add a class with the array syntax without first initializing the 'class'
    // attribute.
    $attribute = new Attribute();
    $attribute['class'][] = 'test-class';
    $this->assertEquals(new AttributeArray('class', ['test-class']), $attribute['class']);

    $attribute = new Attribute();
    // Add no class on empty attribute.
    $attribute->addClass();
    $this->assertEmpty($attribute['class']->value());

    // Test various permutations of adding values to empty Attribute objects.
    foreach ([NULL, FALSE, '', []] as $value) {
      // Single value.
      $attribute->addClass($value);
      $this->assertEmpty((string) $attribute);

      // Multiple values.
      $attribute->addClass($value, $value);
      $this->assertEmpty((string) $attribute);

      // Single value in array.
      $attribute->addClass([$value]);
      $this->assertEmpty((string) $attribute);

      // Single value in arrays.
      $attribute->addClass([$value], [$value]);
      $this->assertEmpty((string) $attribute);
    }

    // Add one class on empty attribute.
    $attribute->addClass('banana');
    $this->assertEquals(['banana'], $attribute['class']->value());

    // Add one class.
    $attribute->addClass('aa');
    $this->assertEquals(['banana', 'aa'], $attribute['class']->value());

    // Add multiple classes.
    $attribute->addClass('xx', 'yy');
    $this->assertEquals(['banana', 'aa', 'xx', 'yy'], $attribute['class']->value());

    // Add an array of classes.
    $attribute->addClass(['red', 'green', 'blue']);
    $this->assertEquals(['banana', 'aa', 'xx', 'yy', 'red', 'green', 'blue'], $attribute['class']->value());

    // Add an array of duplicate classes.
    $attribute->addClass(['red', 'green', 'blue'], ['aa', 'aa', 'banana'], 'yy');
    $this->assertEquals('banana aa xx yy red green blue', (string) $attribute['class']);
  }

  /**
   * Tests removing class attributes with the AttributeArray helper method.
   *
   * @covers ::removeClass
   */
  public function testRemoveClasses(): void {
    // Add duplicate class to ensure that both duplicates are removed.
    $classes = ['example-class', 'aa', 'xx', 'yy', 'red', 'green', 'blue', 'red'];
    $attribute = new Attribute(['class' => $classes]);

    // Remove one class.
    $attribute->removeClass('example-class');
    $this->assertNotContains('example-class', $attribute['class']->value());

    // Remove multiple classes.
    $attribute->removeClass('xx', 'yy');
    $this->assertNotContains(['xx', 'yy'], $attribute['class']->value());

    // Remove an array of classes.
    $attribute->removeClass(['red', 'green', 'blue']);
    $this->assertNotContains(['red', 'green', 'blue'], $attribute['class']->value());

    // Remove a class that does not exist.
    $attribute->removeClass('gg');
    $this->assertNotContains(['gg'], $attribute['class']->value());
    // Test that the array index remains sequential.
    $this->assertEquals(['aa'], $attribute['class']->value());

    $attribute->removeClass('aa');
    $this->assertEmpty((string) $attribute);
  }

  /**
   * Tests checking for class names with the Attribute method.
   *
   * @covers ::hasClass
   */
  public function testHasClass(): void {
    // Test an attribute without any classes.
    $attribute = new Attribute();
    $this->assertFalse($attribute->hasClass('a-class-nowhere-to-be-found'));

    // Add a class to check for.
    $attribute->addClass('we-totally-have-this-class');
    // Check that this class exists.
    $this->assertTrue($attribute->hasClass('we-totally-have-this-class'));
  }

  /**
   * Tests removing class attributes with the Attribute helper methods.
   *
   * @covers ::removeClass
   * @covers ::addClass
   */
  public function testChainAddRemoveClasses(): void {
    $attribute = new Attribute(
      ['class' => ['example-class', 'red', 'green', 'blue']]
    );

    $attribute
      ->removeClass(['red', 'green', 'pink'])
      ->addClass(['apple', 'lime', 'grapefruit'])
      ->addClass(['banana']);
    $expected = ['example-class', 'blue', 'apple', 'lime', 'grapefruit', 'banana'];
    $this->assertEquals($expected, $attribute['class']->value(), 'Attributes chained');
  }

  /**
   * Tests the twig calls to the Attribute.
   *
   * @dataProvider providerTestAttributeClassHelpers
   *
   * @covers ::removeClass
   * @covers ::addClass
   */
  public function testTwigAddRemoveClasses($template, $expected, $seed_attributes = []): void {
    $loader = new StringLoader();
    $twig = new Environment($loader);
    $data = ['attributes' => new Attribute($seed_attributes)];
    $result = $twig->createTemplate($template)->render($data);
    $this->assertEquals($expected, $result);
  }

  /**
   * Provides tests data for testEscaping.
   *
   * @return array
   *   An array of test data each containing of a twig template string,
   *   a resulting string of classes and an optional array of attributes.
   */
  public static function providerTestAttributeClassHelpers() {
    // cSpell:disable
    return [
      ["{{ attributes.class }}", ''],
      ["{{ attributes.addClass('everest').class }}", 'everest'],
      ["{{ attributes.addClass(['k2', 'kangchenjunga']).class }}", 'k2 kangchenjunga'],
      ["{{ attributes.addClass('lhotse', 'makalu', 'cho-oyu').class }}", 'lhotse makalu cho-oyu'],
      [
        "{{ attributes.addClass('nanga-parbat').class }}",
        'dhaulagiri manaslu nanga-parbat',
        ['class' => ['dhaulagiri', 'manaslu']],
      ],
      [
        "{{ attributes.removeClass('annapurna').class }}",
        'gasherbrum-i',
        ['class' => ['annapurna', 'gasherbrum-i']],
      ],
      [
        "{{ attributes.removeClass(['broad peak']).class }}",
        'gasherbrum-ii',
        ['class' => ['broad peak', 'gasherbrum-ii']],
      ],
      [
        "{{ attributes.removeClass('gyachung-kang', 'shishapangma').class }}",
        '',
        ['class' => ['shishapangma', 'gyachung-kang']],
      ],
      [
        "{{ attributes.removeClass('nuptse').addClass('annapurna-ii').class }}",
        'himalchuli annapurna-ii',
        ['class' => ['himalchuli', 'nuptse']],
      ],
      // Test for the removal of an empty class name.
      ["{{ attributes.addClass('rakaposhi', '').class }}", 'rakaposhi'],
    ];
    // cSpell:enable
  }

  /**
   * Tests iterating on the values of the attribute.
   */
  public function testIterate(): void {
    $attribute = new Attribute(['class' => ['example-class'], 'id' => 'example-id']);

    $counter = 0;
    foreach ($attribute as $key => $value) {
      if ($counter == 0) {
        $this->assertEquals('class', $key);
        $this->assertEquals(new AttributeArray('class', ['example-class']), $value);
      }
      if ($counter == 1) {
        $this->assertEquals('id', $key);
        $this->assertEquals(new AttributeString('id', 'example-id'), $value);
      }
      $counter++;
    }
  }

  /**
   * Tests printing of an attribute.
   */
  public function testPrint(): void {
    $attribute = new Attribute(['class' => ['example-class'], 'id' => 'example-id', 'enabled' => TRUE]);

    $content = $this->randomMachineName();
    $html = '<div' . (string) $attribute . '>' . $content . '</div>';
    $this->assertClass('example-class', $html);
    $this->assertNoClass('example-class2', $html);

    $this->assertID('example-id', $html);
    $this->assertNoID('example-id2', $html);

    $this->assertStringContainsString('enabled', $html);
  }

  /**
   * @covers ::createAttributeValue
   * @dataProvider providerTestAttributeValues
   */
  public function testAttributeValues(array $attributes, $expected): void {
    $this->assertEquals($expected, (new Attribute($attributes))->__toString());
  }

  public static function providerTestAttributeValues() {
    $data = [];

    $string = '"> <script>alert(123)</script>"';
    $data['safe-object-xss1'] = [['title' => Markup::create($string)], ' title="&quot;&gt; alert(123)&quot;"'];
    $data['non-safe-object-xss1'] = [['title' => $string], ' title="' . Html::escape($string) . '"'];
    $string = '&quot;><script>alert(123)</script>';
    $data['safe-object-xss2'] = [['title' => Markup::create($string)], ' title="&quot;&gt;alert(123)"'];
    $data['non-safe-object-xss2'] = [['title' => $string], ' title="' . Html::escape($string) . '"'];

    return $data;
  }

  /**
   * Checks that the given CSS class is present in the given HTML snippet.
   *
   * @param string $class
   *   The CSS class to check.
   * @param string $html
   *   The HTML snippet to check.
   *
   * @internal
   */
  protected function assertClass(string $class, string $html): void {
    $xpath = "//*[@class='$class']";
    self::assertTrue((bool) $this->getXPathResultCount($xpath, $html));
  }

  /**
   * Checks that the given CSS class is not present in the given HTML snippet.
   *
   * @param string $class
   *   The CSS class to check.
   * @param string $html
   *   The HTML snippet to check.
   *
   * @internal
   */
  protected function assertNoClass(string $class, string $html): void {
    $xpath = "//*[@class='$class']";
    self::assertFalse((bool) $this->getXPathResultCount($xpath, $html));
  }

  /**
   * Checks that the given CSS ID is present in the given HTML snippet.
   *
   * @param string $id
   *   The CSS ID to check.
   * @param string $html
   *   The HTML snippet to check.
   *
   * @internal
   */
  protected function assertID(string $id, string $html): void {
    $xpath = "//*[@id='$id']";
    self::assertTrue((bool) $this->getXPathResultCount($xpath, $html));
  }

  /**
   * Checks that the given CSS ID is not present in the given HTML snippet.
   *
   * @param string $id
   *   The CSS ID to check.
   * @param string $html
   *   The HTML snippet to check.
   *
   * @internal
   */
  protected function assertNoID(string $id, string $html): void {
    $xpath = "//*[@id='$id']";
    self::assertFalse((bool) $this->getXPathResultCount($xpath, $html));
  }

  /**
   * Counts the occurrences of the given XPath query in a given HTML snippet.
   *
   * @param string $query
   *   The XPath query to execute.
   * @param string $html
   *   The HTML snippet to check.
   *
   * @return int
   *   The number of results that are found.
   */
  protected function getXPathResultCount($query, $html): int {
    $document = Html::load($html);
    $xpath = new \DOMXPath($document);

    return $xpath->query($query)->length;
  }

  /**
   * Tests the storage method.
   */
  public function testStorage(): void {
    $attribute = new Attribute(['class' => ['example-class']]);

    $this->assertEquals(['class' => new AttributeArray('class', ['example-class'])], $attribute->storage());
  }

  /**
   * Provides tests data for testHasAttribute.
   *
   * @return array
   *   An array of test data each containing an array of attributes, the name
   *   of the attribute to check existence of, and the expected result.
   */
  public static function providerTestHasAttribute() {
    return [
      [['class' => ['example-class']], 'class', TRUE],
      [[], 'class', FALSE],
      [['class' => ['example-class']], 'id', FALSE],
      [['class' => ['example-class'], 'id' => 'foo'], 'id', TRUE],
      [['id' => 'foo'], 'class', FALSE],
    ];
  }

  /**
   * @covers ::hasAttribute
   * @dataProvider providerTestHasAttribute
   */
  public function testHasAttribute(array $test_data, $test_attribute, $expected): void {
    $attributes = new Attribute($test_data);
    $this->assertSame($expected, $attributes->hasAttribute($test_attribute));
  }

  /**
   * Provides tests data for testMerge.
   *
   * @return array
   *   An array of test data each containing an initial Attribute object, an
   *   Attribute object or array to be merged, and the expected result.
   */
  public static function providerTestMerge() {
    return [
      [new Attribute([]), new Attribute(['class' => ['class1']]), new Attribute(['class' => ['class1']])],
      [new Attribute(['class' => ['example-class']]), new Attribute(['class' => ['class1']]), new Attribute(['class' => ['example-class', 'class1']])],
      [new Attribute(['class' => ['example-class']]), new Attribute(['id' => 'foo', 'href' => 'bar']), new Attribute(['class' => ['example-class'], 'id' => 'foo', 'href' => 'bar'])],
    ];
  }

  /**
   * @covers ::merge
   * @dataProvider providerTestMerge
   */
  public function testMerge($original, $merge, $expected): void {
    $this->assertEquals($expected, $original->merge($merge));
  }

  /**
   * @covers ::merge
   */
  public function testMergeArgumentException(): void {
    $attributes = new Attribute(['class' => ['example-class']]);
    $this->expectException(\TypeError::class);
    $attributes->merge('not an array');
  }

}
