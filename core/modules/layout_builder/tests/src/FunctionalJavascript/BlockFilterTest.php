<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the JavaScript functionality of the block add filter.
 *
 * @group layout_builder
 */
class BlockFilterTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'node',
    'datetime',
    'layout_builder',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $user = $this->drupalCreateUser([
      'configure any layout',
      'administer node display',
      'administer node fields',
    ]);
    $this->drupalLogin($user);
    $this->createContentType(['type' => 'bundle_with_section_field']);
  }

  /**
   * Tests block filter.
   */
  public function testBlockFilter() {
    $assert_session = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();

    // From the manage display page, go to manage the layout.
    $field_ui_prefix = 'admin/structure/types/manage/bundle_with_section_field';
    $this->drupalPostForm("$field_ui_prefix/display/default", ['layout[enabled]' => TRUE], 'Save');
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals("$field_ui_prefix/display/default/layout");

    // Open the block listing.
    $assert_session->linkExists('Add block');
    $this->clickLink('Add block');
    $assert_session->assertWaitOnAjaxRequest();

    // Get all blocks, for assertions later.
    $blocks = $page->findAll('css', '.js-layout-builder-block-link');
    $categories = $page->findAll('css', '.js-layout-builder-category');

    $filter = $assert_session->elementExists('css', '.js-layout-builder-filter');

    // Set announce to ensure it is not cleared.
    $init_message = 'init message';
    $session->evaluateScript("Drupal.announce('$init_message')");
    // Test block filter does not take effect for 1 character.
    $filter->setValue('a');
    $this->assertAnnounceContains($init_message);
    $visible_rows = $this->filterVisibleElements($blocks);
    $this->assertEquals(count($blocks), count($visible_rows));

    // Get the Content Fields category, which will be closed before filtering.
    $contentFieldsCategory = $page->find('named', ['content', 'Content fields']);
    // Link that belongs to the Content Fields category, to verify collapse.
    $promoteToFrontPageLink = $page->find('named', ['content', 'Promoted to front page']);
    // Test that front page link is visible until Content Fields collapsed.
    $this->assertTrue($promoteToFrontPageLink->isVisible());
    $contentFieldsCategory->click();
    $this->assertFalse($promoteToFrontPageLink->isVisible());

    // Test block filter reduces the number of visible rows.
    $filter->setValue('ad');
    $fewer_blocks_message = ' blocks are available in the modified list';
    $this->assertAnnounceContains($fewer_blocks_message);
    $visible_rows = $this->filterVisibleElements($blocks);
    $this->assertGreaterThan(0, count($blocks));
    $this->assertLessThan(count($blocks), count($visible_rows));
    $visible_categories = $this->filterVisibleElements($categories);
    $this->assertGreaterThan(0, count($visible_categories));
    $this->assertLessThan(count($categories), count($visible_categories));

    // Test Drupal.announce() message when multiple matches are present.
    $expected_message = count($visible_rows) . $fewer_blocks_message;
    $this->assertAnnounceContains($expected_message);

    // Test Drupal.announce() message when only one match is present.
    $filter->setValue('Powered by');
    $this->assertAnnounceContains(' block is available');
    $visible_rows = $this->filterVisibleElements($blocks);
    $this->assertCount(1, $visible_rows);
    $visible_categories = $this->filterVisibleElements($categories);
    $this->assertCount(1, $visible_categories);
    $this->assertAnnounceContains('1 block is available in the modified list.');

    // Test Drupal.announce() message when no matches are present.
    $filter->setValue('Pan-Galactic Gargle Blaster');
    $visible_rows = $this->filterVisibleElements($blocks);
    $this->assertCount(0, $visible_rows);
    $visible_categories = $this->filterVisibleElements($categories);
    $this->assertCount(0, $visible_categories);
    $announce_element = $page->find('css', '#drupal-live-announce');
    $page->waitFor(2, function () use ($announce_element) {
      return strpos($announce_element->getText(), '0 blocks are available') === 0;
    });

    // Test Drupal.announce() message when all blocks are listed.
    $filter->setValue('');
    $this->assertAnnounceContains('All available blocks are listed.');
    // Confirm the Content Fields category remains collapsed after filtering.
    $this->assertFalse($promoteToFrontPageLink->isVisible());
  }

  /**
   * Removes any non-visible elements from the passed array.
   *
   * @param \Behat\Mink\Element\NodeElement[] $elements
   *   An array of node elements.
   *
   * @return \Behat\Mink\Element\NodeElement[]
   *   An array of visible node elements.
   */
  protected function filterVisibleElements(array $elements) {
    return array_filter($elements, function (NodeElement $element) {
      return $element->isVisible();
    });
  }

  /**
   * Checks for inclusion of text in #drupal-live-announce.
   *
   * @param string $expected_message
   *   The text expected to be present in #drupal-live-announce.
   */
  protected function assertAnnounceContains($expected_message) {
    $assert_session = $this->assertSession();
    $this->assertNotEmpty($assert_session->waitForElement('css', "#drupal-live-announce:contains('$expected_message')"));
  }

}
