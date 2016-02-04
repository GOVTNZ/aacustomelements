
## aacustomelements

aacustomelements provides extended shortcode handling. While it still supports the built-in [shortcode] syntax, it also provides for custom HTML elements, or standard HTML elements with classes.

Benefits of this approach include:

 *  Lets you define new element structures in the tinyMCE editor.
 *  Lets you apply styling to those elements within the editor, so it clearer
    to CMS users which parts of the content are custom elements (e.g. you can delineate a custom element with a border)
 *  Lets you create multi-element nested structures. e.g. you might create
    <gallery> and <galleryitem> elements.

## Status

This module is still in development. Use in production systems is not recommended without understanding the potential issues.

## How it works

The module overrides the default ShortcodeParser class by providing a custom class to replace it, CustomElementsParser. The custom class is itself an extension of ShortcodeParser, so provides the exact same handling of the shortcode format.

CustomElementsParser provides methods for you to register custom elements, either using non-standard element name, or standard elements with a class.

When an HTMLText fiseld is rendered, the shortcode handler is invoked:

 *  Replaces custom elements
 *  Delegates to standard ShortcodeParser replacement

Custom elements are handled as follows:

 *  The content field is parsed into a DOMDocument, with a UTF-8 encoding.
 *  For each registered custom element, all instances matching the element are
    processed. For each instance, the handler is invoked, and returns either markup or a replacement DOMNode.
 *  This continues until all registered custom elements have been processed.
 *  The result is converted back to markup, before being passed to the
    standard shortcode handler.

When a custom element is handled, the handler is passed the original DOMNode. If required, the handler can process the child nodes in order to create the result, or can simply discard the contents of the source node; this is up to the handler.

Nested elements are possible, although requires some consideration:

 *  registered custom elements are processed in the order they are defined.
 *  all matching nodes are identified and processed in order.
 *  a handler should not return replacement markup that contains itself.

## Usage

### Create a handler

A handler is just an object that implements CustomElementHandler, which has a single function:

	renderCustomElement(DOMNode $node, $parser);

When a custom element is identified in the parsed content, a DOMNode is passed in that represents that custom element. The
parser instance is also passed in.

Here is a sample element handler:

	class MyElementHandler implements CustomElementHandler {
		public function renderCustomElement(DOMNode $node, $parser) {
			// how to get an attribute from the node.
			$id = $node->getAttribute('data-id');
			if (!$id) {
				return FALSE;
			}

			... do whatever else

			$markup = '...';   // compute the markup to substitute for the element

			// recursively invoke the shortcode parser on the result, in case the element returns shortcodes
			// itself.
			return $parser->parse($markup);
		}
	}


### Configure a custom element

You need to register the handler for the custom element (e.g. in _config.php):

	CustomElementsParser::register_custom_element('myelement', new MyElementHandler());


You also need to tell tinymce about the element:

	HtmlEditorConfig::get('cms')->setOption('custom_elements', '~myelement'); // for inline
	HtmlEditorConfig::get('cms')->setOption('custom_elements', 'myelement'); // for block


### Configure a standard element with custom class

You need to register the handler for the custom element using the class name (e.g. in _config.php):

	CustomElementsParser::register_custom_element_by_class('my-element', new MyElementHandler());


You also need to tell tinymce about the class. In this case, assuming that we're using a span with
a custom element class of my-element. Your configuration may already allow this:

	$htmlEditorConfig->setOption(
		'extended_valid_elements',
		$htmlEditorConfig->getOption('extended_valid_elements') .
		'span[class]'
	);

## Limitations ##

 *	Currently there is no protection against infinite recursion with a custom element that resolves to a reference containing itself.
    Should probably tie into the error handling.
 *	If the markup structure is invalid, custom elements may fail to resolve on the front end. Shortcodes should still work.
 *	The module starts with "aa" to force it to be first to evaluate. This is because it must replace ShortcodeParser references with the
 	custom shortcode parser before any other module references shortcode parser. CMS module currently explicitly references shortcode parser
 	in it's _config.php. Using dependencies in the config system doesn't fix this case.

## To do ##

 *	Improve error handling.
 *	Consider not parsing the whole content as HTML, but using regex to locate candidate elements. This would be harder, but
 	tolerate markup structure errors better.
