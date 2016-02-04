<?php
/**
 * @package aacustomelements
 * @subpackage tests
 */
class CustomElementsParserTest extends SapphireTest {
	
	protected $arguments, $contents, $tagName, $parser;
	protected $extra = array();
	
	public function setUp() {
		// ShortcodeParser::get('test')->register('test_shortcode', array($this, 'shortcodeSaver'));
		$this->parser = ShortcodeParser::get('test');
	
		parent::setUp();
	}

	public function testCustomElement() {
		CustomElementsParser::register_custom_element('testelement', new CustomElementsParserTest_Handler());

		// Check no substitution case, with <p> tags
		$this->assertEquals(
			'<p>no shortcode</p>',
			$this->parser->parse('<p>no shortcode</p>')
		);

		// Check no substitution case, without <p> tags
		$this->assertEquals(
			'no shortcode',
			$this->parser->parse('no shortcode')
		);

		// Check substitution case with no configured element
		$this->assertEquals(
			'<unknown>a</unknown>',
			$this->parser->parse('<unknown>a</unknown>')
		);

		$this->assertEquals(
			'abc',
			$this->parser->parse('<testelement data-test-mode="attribute" data-attr="abc">def</testelement>')
		);

	}

	public function testCustomElementByClass() {
		CustomElementsParser::register_custom_element_by_class('test-element', new CustomElementsParserTest_Handler());
	}
}

class CustomElementsParserTest_Handler implements CustomElementHandler {
	public function renderCustomElement(DOMNode $node, $parser) {
		$mode = $node->getAttribute('data-test-mode');

		switch ($mode) {
			case 'attribute':
				$markup = $node->getAttribute('data-attr');
				Debug::show($markup);
				break;

			case 'nested':
				$markup = '<testelement>def</testelement>';
				break;

			default:
				$markup = '';
				break;
		}

		// recursively invoke the shortcode parser on the result, in case the element returns shortcodes
		// itself.
		return $parser->parse($markup);
	}
}

