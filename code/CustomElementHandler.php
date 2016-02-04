<?php

interface CustomElementHandler {
	// Given a node in a DOM tree representing the content field, return something that
	// can be used to replace the node with on the front end. This can be one of two things:
	// - a string that is the markup to replace $node
	// - a new DOMNode created within the same document as $node ($node->ownerDocument)
	// $node is the DOMNode that represents the custom element in the HTML field. It's children
	// are the elements under that element in the HTML field.
	// $parser is the short code parser instance that is invoking the handler, which provides for
	// a handler to apply the parser to the substituted content.
	// @todo figure out an efficient way to "transclude" $node's children, especially given that
	// @todo we need to apply the same substitution rules to it. Shortcodes can be ignored at this
	// @todo point, as that is done after it's translated back to HTML.
	public function renderCustomElement(DOMNode $node, $parser);
}