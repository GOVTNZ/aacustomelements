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
		CustomElementsParser::register_custom_element('testelement', new CustomElementsParserTestElement_Handler());
		CustomElementsParser::register_custom_element('testparent', new CustomElementsParserTestParent_Handler());

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

	public function testNestedElement() {
		$this->assertEquals(
			'<div class="parent">hello, fred</div>',
			$this->parser->parse('<testparent>hello, <testelement data-attr="fred">def</testelement></testparent>')
		);
	}

	public function testCustomElementByClass() {
		CustomElementsParser::register_custom_element_by_class('test-element', new CustomElementsParserTestElement_Handler());
		CustomElementsParser::register_custom_element_by_class('test-parent', new CustomElementsParserTestParent_Handler());
	}
}

class CustomElementsParserTestElement_Handler implements CustomElementHandler {
	public function renderCustomElement(DOMNode $node, $parser, $context = NULL) {
		$mode = $node->getAttribute('data-test-mode');
		if (!$mode) {
			$mode = 'attribute';
		}

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

// The handler for <test-parent>. This simple aggregates the containing text, substitutes any DOM elements in the children,
// and returns that in a div.
class CustomElementsParserTestParent_Handler implements CustomElementHandler {
	public function renderCustomElement(DOMNode $node, $parser, $context = NULL) {
		$result = $node->ownerDocument->createDocumentFragment();
		$result->appendXML('<div class="parent"></div>');
		$container = $result->childNodes[0];

		// re-parent node's children to $result
		while ($node->childNodes->length > 0) {
			$container->appendChild($node->childNodes->item(0));
		}

		CustomElementsParser::reduce_in_children($result, $parser, $context);

		return $result;
	}
}
