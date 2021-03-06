<?php

/** CustomElementsParser is a replacement for ShortcodeParser. It provides all of shortcode parser's
 ** behaviours, but also allows for custom HTML elements to be defined that are substituted on the front
 ** end in the same way as shortcodes.
 **/
class CustomElementsParser extends ShortcodeParser {

	// Custom element definitions.
	protected static $custom_elements = array();

// refactor into $custom_elements, but we need to index it by element for speed.
protected static $custom_elements_map = array();

	// Register a custom element handler by element name,
	public static function register_custom_element($elementName, CustomElementHandler $handler) {
		self::$custom_elements[] = array(
			'elementName' => $elementName,
			'handler' => $handler
		);
		self::$custom_elements_map[$elementName] = $handler;
	}

	// Register a custom element handler by class.
	public static function register_custom_element_by_class($className, CustomElementHandler $handler) {
		self::$custom_elements[] = array(
			'elementClass' => $className,
			'handler' => $handler
		);
	}

	// Parse shortcodes in the content. This first handles substitution of any custom elements,
	// and then delegates to the default parsing method to handle short codes.
	public function parse($content) {
		$context = $this->getContext();

		// Replace custom elements
		$content = $this->substituteCustomElements($content, $context);

		// Delegate to parent to handle shortcodes proper.
		return parent::parse($content);
	}

	// Attempt to identify the context. Unfortunately the context we are rendering into is not
	// passed into the shortcode parser. So we guess based on the current controller. Most typically
	// this is a ContentController for a page, where data() will give us the page. If not, we return
	// null.
	protected function getContext() {
		$c = Controller::curr();
		if ($c instanceof ContentController) {
			return $c->data();
		}

		return null;
	}

	// look for custom elements, and substitute them. The approach is to parse the markup into a DOM
	// structure, perform a depth first substitution, and then convert the DOM structure back to HTML.
	protected function substituteCustomElements($content, $context) {
		// disable emission of warnings on invalid markup. We're making a couple of assumptions here:
		// 1. if you really care about invalid markup in HTML fields, you'll validate them first.
		// 2. the CMS user is checking that the result on the front end actually matches what they
		//    understand is in the HTML field. If there are markup errors and the content does not
		//    display properly, they will fix the problem.
 		$internal_errors = libxml_use_internal_errors(true);

 		// Create a DOMDocument to parse the markup into. We trick the parser into thinking it's
 		// UTF-8, which gives best results.
		$doc = new DOMDocument();
		$doc->loadHTML('<?xml encoding="UTF-8">' . $content);

		self::substitute_custom_element($doc, $this, $context);

		// foreach (self::$custom_elements as $custom) {
		// 	// Determine what the list of elements is for this custom elements definition. We either
		// 	// look up elements by element name, or by class.
		// 	if (isset($custom['elementName']) && $custom['elementName']) {
		// 		$elements = $doc->getElementsByTagName($custom['elementName']);
		// 	} else {
		// 		$xq = new DOMXPath($doc);
		// 		$elements = $xq->query("//*[@class='" . $custom['elementClass'] . "']");
		// 	}

		// 	// Process the elements until there aren't any more. Note that because we're replacing
		// 	// children, the elements collection gets smaller as as process the list.
		// 	while ($elements->length > 0) {
		// 		$node = $elements->item(0);

		// 		$handler = $custom['handler'];

		// 		// Get the handler to render the custom element.
		// 		$result = $handler->renderCustomElement($node, $this);

		// 		// $markup = $this->getFragment($node);

		// 		if ($result !== FALSE) {
		// 			// we got something to replace $node with.

		// 			if (is_string($result)) {
		// 				// We got markup back, so parse that into the document, and replace the custom
		// 				// element in the original doc with the new parsed fragment.
		// 				$markup = $result;
		// 				$result = $doc->createDocumentFragment();
		// 				$result->appendXML($markup);
		// 			} else if ($result instanceof HTMLText) {
		// 				$markup = $result->forTemplate();
		// 				$result = $doc->createDocumentFragment();
		// 				$result->appendXML($markup);
		// 			}

		// 			// At this point, $result is a DOMNode to replace $node, so now do the replacement.
		// 			$parent = $node->parentNode;
		// 			$parent->replaceChild($result, $node);
		// 		}
		// 	}
		// }

		libxml_use_internal_errors($internal_errors);

		$result = $doc->saveHTML();

		$result = $this->correctMarkup($result, $content);

		return $result;
	}

	// Substitute for custom elements at $node. If $node itself is a custom element, 
	// the handler is called, and $node is replaced with it's result. If not a custom
	// element, this calls reduce_in_children on $node. This is called by the parser
	// at the top level on the document, but individual handlers can also call this on their
	// child DOM notes.
	// There are two patterns:
	// - the custom element handler will replace it's DOM node with a substitute DOM sub-tree,
	//   and calls this function on the sub-tree. This is the preferred approach, and allows
	//   the expected nesting of elements.
	// - the custom element handled doesn't call this on the replacement. There may be cases
	//   where this makes sense, but it is not the general form.
	public static function substitute_custom_element($node, $parser, $context) {
		if (isset(self::$custom_elements_map[$node->nodeName])) {
			// Debug::show("substituting " . print_r($node, true));
			$handler = self::$custom_elements_map[$node->nodeName];

			// Get the handler to render the custom element.
			$result = $handler->renderCustomElement($node, $parser, $context);

			// $markup = $this->getFragment($node);

			if ($result !== FALSE) {
				// we got something to replace $node with.

				if (is_string($result)) {
					// We got markup back, so parse that into the document, and replace the custom
					// element in the original doc with the new parsed fragment.
					$markup = $result;
					$result = $node->ownerDocument->createDocumentFragment();
					$result->appendXML($markup);
				} else if ($result instanceof HTMLText) {
					$markup = $result->forTemplate();
					$result = $node->ownerDocument->createDocumentFragment();
					$result->appendXML($markup);
				}

				// At this point, $result is a DOMNode to replace $node, so now do the replacement.
				$parent = $node->parentNode;
				$parent->replaceChild($result, $node);
			}
		} else {
			self::reduce_in_children($node, $parser, $context);
		}
	}

	// Invoke substitute_custom_element on all children of node. The result is that
	// the $node sub-tree may have custom elements collapsed. It does not reduce the whole
	// tree, just custom elements.
	public static function reduce_in_children($node, $parser, $context) {
		if ($node->childNodes) {
			foreach ($node->childNodes as $child) {
				self::substitute_custom_element($child, $parser, $context);
			}
		}
	}

	// protected function substituteCustomElementsInNode($node) {

	// }

	protected function correctMarkup($s, $orig) {
		// saveHTML injects doctype, and html/body tags wrapping what we want, so we strip it off again if
		// it's present.
		$i = strpos($s, '<html><body>');
		if ($i !== FALSE) {
			// strip everything up to and including the html and body open tags
			$s = substr($s, $i + 12);

			$s = str_replace('</body></html>', '', $s);
		}


		// saveHTML also encodes URLs, which is OK, except it encodes "[" and "]", which breaks
		// default shortcode handling.
		$s = $this->restoreShortcodeURLs($s);

		// Remove UTF encoding if it's there, but only if it's not in the original.
		$utf8 = '<?xml encoding="UTF-8">';
		if (substr($orig, 0, strlen($utf8)) != $utf8) {
			$s = str_replace($utf8, '', $s);
		}

		// If the original content is not an element, saveHTML wraps it in a <p> tag. This breaks unit tests. So
		// we undo it.
		if (substr(trim($orig), 0, 1) != '<') {
			$s = trim($s);
			if (substr($s, 0, 3) == '<p>') {
				$s = substr($s, 3);
			}
			if (substr($s, -4) == '</p>') {
				$s = substr($s, 0, -4);
			}
		}

		// saveHTML also appears to inject a newline at the end. So remove it unless it was there. This is really just
		// so the framework unit tests run correctly, otherwise it's a bunch of false positives.
		if (substr($s, -1) == "\n" && substr($orig, -1) != "\n") {
			$s = substr($s, 0, -1);
		}

		return $s;
	}

	// Locate any <a> tags whose href is of the form %5B...%5D, and replace the %5B and %5D with "[" and "]" respectively.
	protected function restoreShortcodeURLs($s) {
		return preg_replace('/\<a href=\"\%5B(.*)%5D\"/', '<a href="[$1]"', $s);
	}

	// // Given a node that is a fragment, replace it
	// protected function getFragment($node) {
	// 	// Debug::show("getting fragment from " . print_r($node, true));
	// 	$id = $node->getAttribute('data-id');
	// 	// Debug::show("id is " . print_r($id, true));
	// 	if (!$id) {
	// 		return FALSE;
	// 	}

	// 	// @todo refactor out of this module
	// 	$element = AdaptiveElement::get()->byID($id);

	// 	// Get the element to render itself
	// 	$markup = $element->render(null);

	// 	// recursively invoke the shortcode parser on the result, in case the element returns shortcodes
	// 	// itself.
	// 	return $this->parse($markup);
	// }
}
