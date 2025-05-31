<?php
#
# Markdown  -  A text-to-HTML conversion tool for web writers
#
# PHP Markdown
# Copyright (c) 2004-2013 Michel Fortin
# <http://michelf.ca/projects/php-markdown/>
#
# Original Markdown
# Copyright (c) 2004-2006 John Gruber
# <http://daringfireball.net/projects/markdown/>
#


define( 'MARKDOWN_VERSION',  "1.0.2" ); # 29 Nov 2013


#
# Global default settings:
#

# Change to ">" for HTML output
@define( 'MARKDOWN_EMPTY_ELEMENT_SUFFIX',  " />");

# Define the width of a tab for code blocks.
@define( 'MARKDOWN_TAB_WIDTH',     4 );


#
# WordPress settings:
#

# Change to false to remove Markdown from posts and/or comments.
@define( 'MARKDOWN_WP_POSTS',      true );
@define( 'MARKDOWN_WP_COMMENTS',   true );



### Standard Function Interface ###

@define( 'MARKDOWN_PARSER_CLASS',  'MarkdownParser' );

function Markdown(string $text): string {
#
# Initialize the parser and return the result of its transform method.
#
	# Setup static parser variable.
	static $parser;
	if (!isset($parser)) {
		$parserClass = MARKDOWN_PARSER_CLASS;
		$parser = new $parserClass;
	}

	# Transform text using parser.
	return $parser->transform($text);
}


### WordPress Plugin Interface ###

/*
Plugin Name: Markdown
Plugin URI: http://michelf.ca/projects/php-markdown/
Description: <a href="http://daringfireball.net/projects/markdown/syntax">Markdown syntax</a> allows you to write using an easy-to-read, easy-to-write plain text format. Based on the original Perl version by <a href="http://daringfireball.net/">John Gruber</a>. <a href="http://michelf.ca/projects/php-markdown/">More...</a>
Version: 1.0.2
Author: Michel Fortin
Author URI: http://michelf.ca/
*/

if (isset($wp_version)) {
	# More details about how it works here:
	# <http://michelf.ca/weblog/2005/wordpress-text-flow-vs-markdown/>

	# Post content and excerpts
	# - Remove WordPress paragraph generator.
	# - Run Markdown on excerpt, then remove all tags.
	# - Add paragraph tag around the excerpt, but remove it for the excerpt rss.
	if (MARKDOWN_WP_POSTS) {
		remove_filter('the_content',     'wpautop');
		remove_filter('the_content_rss', 'wpautop');
		remove_filter('the_excerpt',     'wpautop');
		add_filter('the_content',        'Markdown', 6);
		add_filter('the_content_rss',    'Markdown', 6);
		add_filter('get_the_excerpt',    'Markdown', 6);
		add_filter('get_the_excerpt',    'trim', 7);
		add_filter('the_excerpt',        'mdwp_add_p');
		add_filter('the_excerpt_rss',    'mdwp_strip_p');

		remove_filter('content_save_pre',  'balanceTags', 50);
		remove_filter('excerpt_save_pre',  'balanceTags', 50);
		add_filter('the_content',  	  'balanceTags', 50);
		add_filter('get_the_excerpt', 'balanceTags', 9);
	}

	# Comments
	# - Remove WordPress paragraph generator.
	# - Remove WordPress auto-link generator.
	# - Scramble important tags before passing them to the kses filter.
	# - Run Markdown on excerpt then remove paragraph tags.
	if (MARKDOWN_WP_COMMENTS) {
		remove_filter('comment_text', 'wpautop', 30);
		remove_filter('comment_text', 'make_clickable');
		add_filter('pre_comment_content', 'Markdown', 6);
		add_filter('pre_comment_content', 'mdwp_hide_tags', 8);
		add_filter('pre_comment_content', 'mdwp_show_tags', 12);
		add_filter('get_comment_text',    'Markdown', 6);
		add_filter('get_comment_excerpt', 'Markdown', 6);
		add_filter('get_comment_excerpt', 'mdwp_strip_p', 7);

		global $mdwpHiddenTags, $mdwpPlaceholders;
		$mdwpHiddenTags = explode(' ',
			'<p> </p> <pre> </pre> <ol> </ol> <ul> </ul> <li> </li>');
		$mdwpPlaceholders = explode(' ', str_rot13(
			'pEj07ZbbBZ U1kqgh4w4p pre2zmeN6K QTi31t9pre ol0MP1jzJR '.
			'ML5IjmbRol ulANi1NsGY J7zRLJqPul liA8ctl16T K9nhooUHli'));
	}

	function mdwp_add_p(string $text): string {
		if (!preg_match('{^$|^<(p|ul|ol|dl|pre|blockquote)>}i', $text)) {
			$text = '<p>'.$text.'</p>';
			$text = preg_replace('{\n{2,}}', "</p>\n\n<p>", $text);
		}
		return $text;
	}

	function mdwp_strip_p(string $text): string {
		return preg_replace('{</?p>}i', '', $text);
	}

	function mdwp_hide_tags(string $text): string {
		global $mdwpHiddenTags, $mdwpPlaceholders;
		return str_replace($mdwpHiddenTags, $mdwpPlaceholders, $text);
	}
	function mdwp_show_tags(string $text): string {
		global $mdwpHiddenTags, $mdwpPlaceholders;
		return str_replace($mdwpPlaceholders, $mdwpHiddenTags, $text);
	}
}


### bBlog Plugin Info ###

function identify_modifier_markdown(): array {
	return array(
		'name'			=> 'markdown',
		'type'			=> 'modifier',
		'nicename'		=> 'Markdown',
		'description'	=> 'A text-to-HTML conversion tool for web writers',
		'authors'		=> 'Michel Fortin and John Gruber',
		'licence'		=> 'BSD-like',
		'version'		=> MARKDOWN_VERSION,
		'help'			=> '<a href="http://daringfireball.net/projects/markdown/syntax">Markdown syntax</a> allows you to write using an easy-to-read, easy-to-write plain text format. Based on the original Perl version by <a href="http://daringfireball.net/">John Gruber</a>. <a href="http://michelf.ca/projects/php-markdown/">More...</a>'
	);
}


### Smarty Modifier Interface ###

function smarty_modifier_markdown($text) {
	return Markdown($text);
}


### Textile Compatibility Mode ###

# Rename this file to "classTextile.php" and it can replace Textile everywhere.

if (strcasecmp(substr(__FILE__, -16), "classTextile.php") == 0) {
	# Try to include PHP SmartyPants. Should be in the same directory.
	@include_once 'smartypants.php';
	# Fake Textile class. It calls Markdown instead.
	class Textile {
		function textileThis(
							string $text, string $lite='', string $encode=''): string {
			if ($lite == '' && $encode == '')    $text = Markdown($text);
			if (function_exists('SmartyPants'))  $text = SmartyPants($text);
			return $text;
		}
		# Fake restricted version: restrictions are not supported for now.
		function textileRestricted(
							string $text, string $lite='', string $noImage=''): string {
			return $this->textileThis($text, $lite);
		}
		# Workaround to ensure compatibility with TextPattern 4.0.3.
		function blockLite($text) { return $text; }
	}
}


/**
	* Markdown Parser Class
	*
	* @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
	* @SuppressWarnings(PHPMD.ExcessiveClassLength)
	* @SuppressWarnings(PHPMD.ExcessivePublicCount)
	* @SuppressWarnings(PHPMD.TooManyFields)
	* @SuppressWarnings(PHPMD.TooManyMethods)
	* @SuppressWarnings(PHPMD.TooManyPublicMethods)
	*/
class MarkdownParser {

	### Configuration Variables ###

	# Change to ">" for HTML output.
	var $emptyElementSuffix = MARKDOWN_EMPTY_ELEMENT_SUFFIX;
	var $tabWidth = MARKDOWN_TAB_WIDTH;

	# Change to `true` to disallow markup or entities.
	var $noMarkup = false;
	var $noEntities = false;

	# Predefined urls and titles for reference links and images.
	var $predefURLs = array();
	var $predefTitles = array();


	### Parser Implementation ###

	# Regex to match balanced [brackets].
	# Needed to insert a maximum bracked depth while converting to PHP.
	var $nestedBracketsDepth = 6;
	var $nestedBracketsRe;

	var $nestedUrlParenthesisDepth = 4;
	var $nestedUrlParenthesisRe;

	# Table of hash values for escaped characters:
	var $escapeChars = '\`*_{}[]()>#+-.!';
	var $escapeCharsRe;


	function __construct() {
	#
	# Constructor function. Initialize appropriate member variables.
	#
		$this->initDetab();
		$this->prepareItalicsAndBold();

		$this->nestedBracketsRe =
			str_repeat('(?>[^\[\]]+|\[', $this->nestedBracketsDepth).
			str_repeat('\])*', $this->nestedBracketsDepth);

		$this->nestedUrlParenthesisRe =
			str_repeat('(?>[^()\s]+|\(', $this->nestedUrlParenthesisDepth).
			str_repeat('(?>\)))*', $this->nestedUrlParenthesisDepth);

		$this->escapeCharsRe = '['.preg_quote($this->escapeChars).']';

		# Sort document, block, and span gamut in ascendent priority order.
		asort($this->documentGamut);
		asort($this->blockGamut);
		asort($this->spanGamut);
	}


	# Internal hashes used during transformation.
	var $urls = array();
	var $titles = array();
	var $htmlHashes = array();

	# Status flag to avoid invalid nesting.
	var $inAnchor = false;


	function setup(): void {
	#
	# Called before the transformation process starts to setup parser
	# states.
	#
		# Clear global hashes.
		$this->urls = $this->predefURLs;
		$this->titles = $this->predefTitles;
		$this->htmlHashes = array();

		$this->inAnchor = false;
	}

	function teardown(): void {
	#
	# Called after the transformation process to clear any variable
	# which may be taking up memory unnecessarly.
	#
		$this->urls = array();
		$this->titles = array();
		$this->htmlHashes = array();
	}


	function transform(string $text): string {
	#
	# Main function. Performs some preprocessing on the input text
	# and pass it through the document gamut.
	#
		$this->setup();

		# Remove UTF-8 BOM and marker character in input, if present.
		$text = preg_replace('{^\xEF\xBB\xBF|\x1A}', '', $text);

		# Standardize line endings:
		#   DOS to Unix and Mac to Unix
		$text = preg_replace('{\r\n?}', "\n", $text);

		# Make sure $text ends with a couple of newlines:
		$text .= "\n\n";

		# Convert all tabs to spaces.
		$text = $this->detab($text);

		# Turn block-level HTML blocks into hash entries
		$text = $this->hashHTMLBlocks($text);

		# Strip any lines consisting only of spaces and tabs.
		# This makes subsequent regexen easier to write, because we can
		# match consecutive blank lines with /\n+/ instead of something
		# contorted like /[ ]*\n+/ .
		$text = preg_replace('/^[ ]+$/m', '', $text);

		# Run document gamut methods.
		foreach ($this->documentGamut as $method => $priority) {
			$text = $this->$method($text);
		}

		$this->teardown();

		return $text . "\n";
	}

	var $documentGamut = array(
		# Strip link definitions, store in hashes.
		"stripLinkDefinitions" => 20,

		"runBasicBlockGamut"   => 30,
		);


	function stripLinkDefinitions(string $text): string {
	#
	# Strips link definitions from text, stores the URLs and titles in
	# hash references.
	#
		$lessThanTab = $this->tabWidth - 1;

		# Link defs are in the form: ^[id]: url "optional title"
		$text = preg_replace_callback('{
							^[ ]{0,'.$lessThanTab.'}\[(.+)\][ ]?:	# id = $1
							  [ ]*
							  \n?				# maybe *one* newline
							  [ ]*
							(?:
							  <(.+?)>			# url = $2
							|
							  (\S+?)			# url = $3
							)
							  [ ]*
							  \n?				# maybe one newline
							  [ ]*
							(?:
								(?<=\s)			# lookbehind for whitespace
								["(]
								(.*?)			# title = $4
								[")]
								[ ]*
							)?	# title is optional
							(?:\n+|\Z)
			}xm',
			array(&$this, 'stripLinkDefinitionsCallback'),
			$text);
		return $text;
	}
	function stripLinkDefinitionsCallback(array $matches): string {
		$linkId = strtolower($matches[1]);
		$url = $matches[2] == '' ? $matches[3] : $matches[2];
		$this->urls[$linkId] = $url;
		$this->titles[$linkId] =& $matches[4];
		return ''; # String that will replace the block
	}

	/** * @SuppressWarnings(PHPMD.ExcessiveMethodLength) */
	function hashHTMLBlocks(string $text): string {
		if ($this->noMarkup)  return $text;

		$lessThanTab = $this->tabWidth - 1;

		# Hashify HTML blocks:
		# We only want to do this for block-level HTML tags, such as headers,
		# lists, and tables. That's because we still want to wrap <p>s around
		# "paragraphs" that are wrapped in non-block-level tags, such as anchors,
		# phrase emphasis, and spans. The list of tags we're looking for is
		# hard-coded:
		#
		# *  List "a" is made of tags which can be both inline or block-level.
		#    These will be treated block-level when the start tag is alone on
		#    its line, otherwise they're not matched here and will be taken as
		#    inline later.
		# *  List "b" is made of tags which are always block-level;
		#
		$blockTagsARe = 'ins|del';
		$blockTagsBRe = 'p|div|h[1-6]|blockquote|pre|table|dl|ol|ul|address|'.
						   'script|noscript|form|fieldset|iframe|math|svg|'.
						   'article|section|nav|aside|hgroup|header|footer|'.
						   'figure';

		# Regular expression for the content of a block tag.
		$nestedTagsLevel = 4;
		$attr = '
			(?>				# optional tag attributes
			  \s			# starts with whitespace
			  (?>
				[^>"/]+		# text outside quotes
			  |
				/+(?!>)		# slash not followed by ">"
			  |
				"[^"]*"		# text inside double quotes (tolerate ">")
			  |
				\'[^\']*\'	# text inside single quotes (tolerate ">")
			  )*
			)?
			';
		$content =
			str_repeat('
				(?>
				  [^<]+			# content without tag
				|
				  <\2			# nested opening tag
					'.$attr.'	# attributes
					(?>
					  />
					|
					  >', $nestedTagsLevel).	# end of opening tag
					  '.*?'.					# last level nested tag content
			str_repeat('
					  </\2\s*>	# closing nested tag
					)
				  |
					<(?!/\2\s*>	# other tags with a different name
				  )
				)*',
				$nestedTagsLevel);
		$content2 = str_replace('\2', '\3', $content);

		# First, look for nested blocks, e.g.:
		# 	<div>
		# 		<div>
		# 		tags for inner block must be indented.
		# 		</div>
		# 	</div>
		#
		# The outermost tags must start at the left margin for this to match, and
		# the inner nested divs must be indented.
		# We need to do this before the next, more liberal match, because the next
		# match will start at the first `<div>` and stop at the first `</div>`.
		$text = preg_replace_callback('{(?>
			(?>
				(?<=\n\n)		# Starting after a blank line
				|				# or
				\A\n?			# the beginning of the doc
			)
			(						# save in $1

			  # Match from `\n<tag>` to `</tag>\n`, handling nested tags
			  # in between.

						[ ]{0,'.$lessThanTab.'}
						<('.$blockTagsBRe.')# start tag = $2
						'.$attr.'>			# attributes followed by > and \n
						'.$content.'		# content, support nesting
						</\2>				# the matching end tag
						[ ]*				# trailing spaces/tabs
						(?=\n+|\Z)	# followed by a newline or end of document

			| # Special version for tags of group a.

						[ ]{0,'.$lessThanTab.'}
						<('.$blockTagsARe.')# start tag = $3
						'.$attr.'>[ ]*\n	# attributes followed by >
						'.$content2.'		# content, support nesting
						</\3>				# the matching end tag
						[ ]*				# trailing spaces/tabs
						(?=\n+|\Z)	# followed by a newline or end of document

			| # Special case just for <hr />. It was easier to make a special
			  # case than to make the other regex more complicated.

						[ ]{0,'.$lessThanTab.'}
						<(hr)				# start tag = $2
						'.$attr.'			# attributes
						/?>					# the matching end tag
						[ ]*
						(?=\n{2,}|\Z)		# followed by a blank line or end of document

			| # Special case for standalone HTML comments:

					[ ]{0,'.$lessThanTab.'}
					(?s:
						<!-- .*? -->
					)
					[ ]*
					(?=\n{2,}|\Z)		# followed by a blank line or end of document

			| # PHP and ASP-style processor instructions (<? and <%)

					[ ]{0,'.$lessThanTab.'}
					(?s:
						<([?%])			# $2
						.*?
						\2>
					)
					[ ]*
					(?=\n{2,}|\Z)		# followed by a blank line or end of document

			)
			)}Sxmi',
			array(&$this, 'hashHTMLBlocksCallback'),
			$text);

		return $text;
	}
	function hashHTMLBlocksCallback(array $matches): string {
		$text = $matches[1];
		$key  = $this->hashBlock($text);
		return "\n\n$key\n\n";
	}


	function hashPart(string $text, string $boundary = 'X'): string {
	#
	# Called whenever a tag must be hashed when a function insert an atomic
	# element in the text stream. Passing $text to through this function gives
	# a unique text-token which will be reverted back when calling unhash.
	#
	# The $boundary argument specify what character should be used to surround
	# the token. By convension, "B" is used for block elements that needs not
	# to be wrapped into paragraph tags at the end, ":" is used for elements
	# that are word separators and "X" is used in the general case.
	#
		# Swap back any tag hash found in $text so we do not have to `unhash`
		# multiple times at the end.
		$text = $this->unhash($text);

		# Then hash the block.
		static $count = 0;
		$key = "$boundary\x1A" . ++$count . $boundary;
		$this->htmlHashes[$key] = $text;
		return $key; # String that will replace the tag.
	}


	function hashBlock(string $text): string {
	#
	# Shortcut function for hashPart with block-level boundaries.
	#
		return $this->hashPart($text, 'B');
	}


	var $blockGamut = array(
	#
	# These are all the transformations that form block-level
	# tags like paragraphs, headers, and list items.
	#
		"doHeaders"         => 10,
		"doHorizontalRules" => 20,

		"doLists"           => 40,
		"doCodeBlocks"      => 50,
		"doBlockQuotes"     => 60,
		);

	function runBlockGamut(string $text): string {
	#
	# Run block gamut tranformations.
	#
		# We need to escape raw HTML in Markdown source before doing anything
		# else. This need to be done for each block, and not only at the
		# begining in the Markdown function since hashed blocks can be part of
		# list items and could have been indented. Indented blocks would have
		# been seen as a code block in a previous pass of hashHTMLBlocks.
		$text = $this->hashHTMLBlocks($text);

		return $this->runBasicBlockGamut($text);
	}

	function runBasicBlockGamut(string $text): string {
	#
	# Run block gamut tranformations, without hashing HTML blocks. This is
	# useful when HTML blocks are known to be already hashed, like in the first
	# whole-document pass.
	#
		foreach ($this->blockGamut as $method => $priority) {
			$text = $this->$method($text);
		}

		# Finally form paragraph and restore hashed blocks.
		$text = $this->formParagraphs($text);

		return $text;
	}


	function doHorizontalRules(string $text): string {
		# Do Horizontal Rules:
		return preg_replace(
			'{
				^[ ]{0,3}	# Leading space
				([-*_])		# $1: First marker
				(?>			# Repeated marker group
					[ ]{0,2}	# Zero, one, or two spaces.
					\1			# Marker character
				){2,}		# Group repeated at least twice
				[ ]*		# Tailing spaces
				$			# End of line.
			}mx',
			"\n".$this->hashBlock("<hr$this->emptyElementSuffix")."\n",
			$text);
	}


	var $spanGamut = array(
	#
	# These are all the transformations that occur *within* block-level
	# tags like paragraphs, headers, and list items.
	#
		# Process character escapes, code spans, and inline HTML
		# in one shot.
		"parseSpan"           => -30,

		# Process anchor and image tags. Images must come first,
		# because ![foo][f] looks like an anchor.
		"doImages"            =>  10,
		"doAnchors"           =>  20,

		# Make links out of things like `<http://example.com/>`
		# Must come after doAnchors, because you can use < and >
		# delimiters in inline links like [this](<url>).
		"doAutoLinks"         =>  30,
		"encodeAmpsAndAngles" =>  40,

		"doItalicsAndBold"    =>  50,
		"doHardBreaks"        =>  60,
		);

	function runSpanGamut(string $text): string {
	#
	# Run span gamut tranformations.
	#
		foreach ($this->spanGamut as $method => $priority) {
			$text = $this->$method($text);
		}

		return $text;
	}


	function doHardBreaks(string $text): string {
		# Do hard breaks:
		return preg_replace_callback('/ {2,}\n/',
			array(&$this, 'doHardBreaksCallback'), $text);
	}
	function doHardBreaksCallback(array $matches): string {
		return $this->hashPart("<br$this->emptyElementSuffix\n");
	}


	function doAnchors(string $text): string {
	#
	# Turn Markdown link shortcuts into XHTML <a> tags.
	#
		if ($this->inAnchor) return $text;
		$this->inAnchor = true;

		#
		# First, handle reference-style links: [link text] [id]
		#
		$text = preg_replace_callback('{
			(					# wrap whole match in $1
			  \[
				('.$this->nestedBracketsRe.')	# link text = $2
			  \]

			  [ ]?				# one optional space
			  (?:\n[ ]*)?		# one optional newline followed by spaces

			  \[
				(.*?)		# id = $3
			  \]
			)
			}xs',
			array(&$this, 'doAnchorsReferenceCallback'), $text);

		#
		# Next, inline-style links: [link text](url "optional title")
		#
		$text = preg_replace_callback('{
			(				# wrap whole match in $1
			  \[
				('.$this->nestedBracketsRe.')	# link text = $2
			  \]
			  \(			# literal paren
				[ \n]*
				(?:
					<(.+?)>	# href = $3
				|
					('.$this->nestedUrlParenthesisRe.')	# href = $4
				)
				[ \n]*
				(			# $5
				  ([\'"])	# quote char = $6
				  (.*?)		# Title = $7
				  \6		# matching quote
				  [ \n]*	# ignore any spaces/tabs between closing quote and )
				)?			# title is optional
			  \)
			)
			}xs',
			array(&$this, 'doAnchorsInlineCallback'), $text);

		#
		# Last, handle reference-style shortcuts: [link text]
		# These must come last in case you've also got [link text][1]
		# or [link text](/foo)
		#
		$text = preg_replace_callback('{
			(					# wrap whole match in $1
			  \[
				([^\[\]]+)		# link text = $2; can\'t contain [ or ]
			  \]
			)
			}xs',
			array(&$this, 'doAnchorsReferenceCallback'), $text);

		$this->inAnchor = false;
		return $text;
	}
	function doAnchorsReferenceCallback(array $matches): string {
		$wholeMatch =  $matches[1];
		$linkText   =  $matches[2];
		$linkId     =& $matches[3];

		if ($linkId == "") {
			# for shortcut links like [this][] or [this].
			$linkId = $linkText;
		}

		# lower-case and turn embedded newlines into spaces
		$linkId = strtolower($linkId);
		$linkId = preg_replace('{[ ]?\n}', ' ', $linkId);

		$result = $wholeMatch;
		if (isset($this->urls[$linkId])) {
			$url = $this->urls[$linkId];
			$url = $this->encodeAttribute($url);

			$result = "<a href=\"$url\"";
			if ( isset( $this->titles[$linkId] ) ) {
				$title = $this->titles[$linkId];
				$title = $this->encodeAttribute($title);
				$result .=  " title=\"$title\"";
			}

			$linkText = $this->runSpanGamut($linkText);
			$result .= ">$linkText</a>";
			$result = $this->hashPart($result);
		}
		return $result;
	}
	function doAnchorsInlineCallback(array $matches): string {
		$wholeMatch	=  $matches[1];
		$linkText		=  $this->runSpanGamut($matches[2]);
		$url				=  $matches[3] == '' ? $matches[4] : $matches[3];
		$title			=& $matches[7];

		$url = $this->encodeAttribute($url);

		$result = "<a href=\"$url\"";
		if (isset($title)) {
			$title = $this->encodeAttribute($title);
			$result .=  " title=\"$title\"";
		}

		$linkText = $this->runSpanGamut($linkText);
		$result .= ">$linkText</a>";

		return $this->hashPart($result);
	}


	function doImages(string $text): string {
	#
	# Turn Markdown image shortcuts into <img> tags.
	#
		#
		# First, handle reference-style labeled images: ![alt text][id]
		#
		$text = preg_replace_callback('{
			(				# wrap whole match in $1
			  !\[
				('.$this->nestedBracketsRe.')		# alt text = $2
			  \]

			  [ ]?				# one optional space
			  (?:\n[ ]*)?		# one optional newline followed by spaces

			  \[
				(.*?)		# id = $3
			  \]

			)
			}xs',
			array(&$this, 'doImagesReferenceCallback'), $text);

		#
		# Next, handle inline images:  ![alt text](url "optional title")
		# Don't forget: encode * and _
		#
		$text = preg_replace_callback('{
			(				# wrap whole match in $1
			  !\[
				('.$this->nestedBracketsRe.')		# alt text = $2
			  \]
			  \s?			# One optional whitespace character
			  \(			# literal paren
				[ \n]*
				(?:
					<(\S*)>	# src url = $3
				|
					('.$this->nestedUrlParenthesisRe.')	# src url = $4
				)
				[ \n]*
				(			# $5
				  ([\'"])	# quote char = $6
				  (.*?)		# title = $7
				  \6		# matching quote
				  [ \n]*
				)?			# title is optional
			  \)
			)
			}xs',
			array(&$this, 'doImagesInlineCallback'), $text);

		return $text;
	}
	function doImagesReferenceCallback(array $matches): string {
		$wholeMatch = $matches[1];
		$altText    = $matches[2];
		$linkId     = strtolower($matches[3]);

		if ($linkId == "") {
			$linkId = strtolower($altText); # for shortcut links like ![this][].
		}

		$altText = $this->encodeAttribute($altText);

		# If there's no such link ID, leave intact:
		$result = $wholeMatch;

		if (isset($this->urls[$linkId])) {
			$url = $this->encodeAttribute($this->urls[$linkId]);
			$result = "<img src=\"$url\" alt=\"$altText\"";
			if (isset($this->titles[$linkId])) {
				$title = $this->titles[$linkId];
				$title = $this->encodeAttribute($title);
				$result .=  " title=\"$title\"";
			}
			$result .= $this->emptyElementSuffix;
			$result = $this->hashPart($result);
		}
		return $result;
	}
	function doImagesInlineCallback(array $matches): string {
		$wholeMatch	= $matches[1];
		$altText		= $matches[2];
		$url			= $matches[3] == '' ? $matches[4] : $matches[3];
		$title			=& $matches[7];

		$altText = $this->encodeAttribute($altText);
		$url = $this->encodeAttribute($url);
		$result = "<img src=\"$url\" alt=\"$altText\"";
		if (isset($title)) {
			$title = $this->encodeAttribute($title);
			$result .=  " title=\"$title\""; # $title already quoted
		}
		$result .= $this->emptyElementSuffix;

		return $this->hashPart($result);
	}


	function doHeaders(string $text): string {
		# Setext-style headers:
		#	  Header 1
		#	  ========
		#
		#	  Header 2
		#	  --------
		#
		$text = preg_replace_callback('{ ^(.+?)[ ]*\n(=+|-+)[ ]*\n+ }mx',
			array(&$this, 'doHeadersCallbackSetext'), $text);

		# atx-style headers:
		#	# Header 1
		#	## Header 2
		#	## Header 2 with closing hashes ##
		#	...
		#	###### Header 6
		#
		$text = preg_replace_callback('{
				^(\#{1,6})	# $1 = string of #\'s
				[ ]*
				(.+?)		# $2 = Header text
				[ ]*
				\#*			# optional closing #\'s (not counted)
				\n+
			}xm',
			array(&$this, 'doHeadersCallbackAtx'), $text);

		return $text;
	}
	function doHeadersCallbackSetext(array $matches): string {
		# Terrible hack to check we haven't found an empty list item.
		if ($matches[2] == '-' && preg_match('{^-(?: |$)}', $matches[1]))
			return $matches[0];

		$level = $matches[2][0] == '=' ? 1 : 2;
		$block = "<h$level>".$this->runSpanGamut($matches[1])."</h$level>";
		return "\n" . $this->hashBlock($block) . "\n\n";
	}
	function doHeadersCallbackAtx(array $matches): string {
		$level = strlen($matches[1]);
		$block = "<h$level>".$this->runSpanGamut($matches[2])."</h$level>";
		return "\n" . $this->hashBlock($block) . "\n\n";
	}


	function doLists(string $text): string {
	#
	# Form HTML ordered (numbered) and unordered (bulleted) lists.
	#
		$lessThanTab = $this->tabWidth - 1;

		# Re-usable patterns to match list item bullets and number markers:
		$markerUlRe  = '[*+-]';
		$markerOlRe  = '\d+[\.]';
		$markerAnyRe = "(?:$markerUlRe|$markerOlRe)";

		$markersRelist = array(
			$markerUlRe => $markerOlRe,
			$markerOlRe => $markerUlRe,
			);

		foreach ($markersRelist as $markerRe => $otherMarkerRe) {
			# Re-usable pattern to match any entirel ul or ol list:
			$wholeListRe = '
				(								# $1 = whole list
				  (								# $2
					([ ]{0,'.$lessThanTab.'})	# $3 = number of spaces
					('.$markerRe.')			# $4 = first list item marker
					[ ]+
				  )
				  (?s:.+?)
				  (								# $5
					  \z
					|
					  \n{2,}
					  (?=\S)
					  (?!						# Negative lookahead for another list item marker
						[ ]*
						'.$markerRe.'[ ]+
					  )
					|
					  (?=						# Lookahead for another kind of list
					    \n
						\3						# Must have the same indentation
						'.$otherMarkerRe.'[ ]+
					  )
				  )
				)
			'; // mx

			# We use a different prefix before nested lists than top-level lists.
			# See extended comment in _ProcessListItems().

			if ($this->listLevel) {
				$text = preg_replace_callback('{
						^
						'.$wholeListRe.'
					}mx',
					array(&$this, 'doListsCallback'), $text);
			}
			else {
				$text = preg_replace_callback('{
						(?:(?<=\n)\n|\A\n?) # Must eat the newline
						'.$wholeListRe.'
					}mx',
					array(&$this, 'doListsCallback'), $text);
			}
		}

		return $text;
	}
	function doListsCallback(array $matches): string {
		# Re-usable patterns to match list item bullets and number markers:
		$markerUlRe  = '[*+-]';
		$markerOlRe  = '\d+[\.]';
		$markerAnyRe = "(?:$markerUlRe|$markerOlRe)";

		$list = $matches[1];
		$listType = preg_match("/$markerUlRe/", $matches[4]) ? "ul" : "ol";

		$markerAnyRe = ( $listType == "ul" ? $markerUlRe : $markerOlRe );

		$list .= "\n";
		$result = $this->processListItems($list, $markerAnyRe);

		$result = $this->hashBlock("<$listType>\n" . $result . "</$listType>");
		return "\n". $result ."\n\n";
	}

	var $listLevel = 0;

	function processListItems(string $listStr, string $markerAnyRe): string {
	#
	#	Process the contents of a single ordered or unordered list, splitting it
	#	into individual list items.
	#
		# The $this->listLevel global keeps track of when we're inside a list.
		# Each time we enter a list, we increment it; when we leave a list,
		# we decrement. If it's zero, we're not in a list anymore.
		#
		# We do this because when we're not inside a list, we want to treat
		# something like this:
		#
		#		I recommend upgrading to version
		#		8. Oops, now this line is treated
		#		as a sub-list.
		#
		# As a single paragraph, despite the fact that the second line starts
		# with a digit-period-space sequence.
		#
		# Whereas when we're inside a list (or sub-list), that line will be
		# treated as the start of a sub-list. What a kludge, huh? This is
		# an aspect of Markdown's syntax that's hard to parse perfectly
		# without resorting to mind-reading. Perhaps the solution is to
		# change the syntax rules such that sub-lists must start with a
		# starting cardinal number; e.g. "1." or "a.".

		$this->listLevel++;

		# trim trailing blank lines:
		$listStr = preg_replace("/\n{2,}\\z/", "\n", $listStr);

		$listStr = preg_replace_callback('{
			(\n)?							# leading line = $1
			(^[ ]*)							# leading whitespace = $2
			('.$markerAnyRe.'				# list marker and space = $3
				(?:[ ]+|(?=\n))	# space only required if item is not empty
			)
			((?s:.*?))						# list item text   = $4
			(?:(\n+(?=\n))|\n)				# tailing blank line = $5
			(?= \n* (\z | \2 ('.$markerAnyRe.') (?:[ ]+|(?=\n))))
			}xm',
			array(&$this, 'processListItemsCallback'), $listStr);

		$this->listLevel--;
		return $listStr;
	}
	function processListItemsCallback(array $matches): string {
		$item = $matches[4];
		$leadingLine =& $matches[1];
		$leadingSpace =& $matches[2];
		$markerSpace = $matches[3];
		$tailingBlankLine =& $matches[5];

		if ($leadingLine || $tailingBlankLine ||
			preg_match('/\n{2,}/', $item))
		{
			# Replace marker with the appropriate whitespace indentation
			$item = $leadingSpace . str_repeat(' ', strlen($markerSpace)) . $item;
			$item = $this->runBlockGamut($this->outdent($item)."\n");
		}
		else {
			# Recursion for sub-lists:
			$item = $this->doLists($this->outdent($item));
			$item = preg_replace('/\n+$/', '', $item);
			$item = $this->runSpanGamut($item);
		}

		return "<li>" . $item . "</li>\n";
	}


	function doCodeBlocks(string $text): string {
	#
	#	Process Markdown `<pre><code>` blocks.
	#
		$text = preg_replace_callback('{
				(?:\n\n|\A\n?)
				(	            # $1 = the code block -- one or more lines, starting with a space/tab
				  (?>
					[ ]{'.$this->tabWidth.'}  # Lines must start with a tab or a tab-width of spaces
					.*\n+
				  )+
				)
				((?=^[ ]{0,'.$this->tabWidth.'}\S)|\Z)	# Lookahead for non-space at line-start, or end of doc
			}xm',
			array(&$this, 'doCodeBlocksCallback'), $text);

		return $text;
	}
	function doCodeBlocksCallback(array $matches): string {
		$codeblock = $matches[1];

		$codeblock = $this->outdent($codeblock);
		$codeblock = htmlspecialchars($codeblock, ENT_NOQUOTES);

		# trim leading newlines and trailing newlines
		$codeblock = preg_replace('/\A\n+|\n+\z/', '', $codeblock);

		$codeblock = "<pre><code>$codeblock\n</code></pre>";
		return "\n\n".$this->hashBlock($codeblock)."\n\n";
	}


	function makeCodeSpan(string $code): string {
	#
	# Create a code span markup for $code. Called from handleSpanToken.
	#
		$code = htmlspecialchars(trim($code), ENT_NOQUOTES);
		return $this->hashPart("<code>$code</code>");
	}


	var $emRelist = array(
		''  => '(?:(?<!\*)\*(?!\*)|(?<!_)_(?!_))(?=\S|$)(?![\.,:;]\s)',
		'*' => '(?<=\S|^)(?<!\*)\*(?!\*)',
		'_' => '(?<=\S|^)(?<!_)_(?!_)',
		);
	var $strongRelist = array(
		''   => '(?:(?<!\*)\*\*(?!\*)|(?<!_)__(?!_))(?=\S|$)(?![\.,:;]\s)',
		'**' => '(?<=\S|^)(?<!\*)\*\*(?!\*)',
		'__' => '(?<=\S|^)(?<!_)__(?!_)',
		);
	var $emStrongRelist = array(
		''    => '(?:(?<!\*)\*\*\*(?!\*)|(?<!_)___(?!_))(?=\S|$)(?![\.,:;]\s)',
		'***' => '(?<=\S|^)(?<!\*)\*\*\*(?!\*)',
		'___' => '(?<=\S|^)(?<!_)___(?!_)',
		);
	var $emStrongPrepRelist = array();

	function prepareItalicsAndBold(): void {
	#
	# Prepare regular expressions for searching emphasis tokens in any
	# context.
	#
		foreach ($this->emRelist as $emph => $emRe) {
			foreach ($this->strongRelist as $strong => $strongRe) {
				# Construct list of allowed token expressions.
				$tokenRelist = array();
				if (isset($this->emStrongRelist["$emph$strong"])) {
					$tokenRelist[] = $this->emStrongRelist["$emph$strong"];
				}
				$tokenRelist[] = $emRe;
				$tokenRelist[] = $strongRe;

				# Construct master expression from list.
				$tokenRe = '{('. implode('|', $tokenRelist) .')}';
				$this->emStrongPrepRelist["$emph$strong"] = $tokenRe;
			}
		}
	}

	/**
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
	 */
	function doItalicsAndBold(string $text): string {
		$tokenStack = array('');
		$textStack = array('');
		$emph = '';
		$strong = '';
		$treeCharEm = false;

		while (1) {
			#
			# Get prepared regular expression for seraching emphasis tokens
			# in current context.
			#
			$tokenRe = $this->emStrongPrepRelist["$emph$strong"] ?? '';

			#
			# Each loop iteration search for the next emphasis token.
			# Each token is then passed to handleSpanToken.
			#
			$parts = empty($tokenRe)
					? [''] : preg_split($tokenRe, $text, 2, PREG_SPLIT_DELIM_CAPTURE);
			$textStack[0] .= $parts[0];
			$token =& $parts[1];
			$text =& $parts[2];

			if (empty($token)) {
				# Reached end of text span: empty stack without emitting.
				# any more emphasis.
				while ($tokenStack[0]) {
					$textStack[1] .= array_shift($tokenStack);
					$textStack[0] .= array_shift($textStack);
				}
				break;
			}

			$tokenLen = strlen($token);
			if ($treeCharEm) {
				# Reached closing marker while inside a three-char emphasis.
				if ($tokenLen == 3) {
					# Three-char closing marker, close em and strong.
					array_shift($tokenStack);
					$span = array_shift($textStack);
					$span = $this->runSpanGamut($span);
					$span = "<strong><em>$span</em></strong>";
					$textStack[0] .= $this->hashPart($span);
					$emph = '';
					$strong = '';
				} else {
					# Other closing marker: close one em or strong and
					# change current token state to match the other
					$tokenStack[0] = str_repeat($token[0], 3-$tokenLen);
					$tag = $tokenLen == 2 ? "strong" : "em";
					$span = $textStack[0];
					$span = $this->runSpanGamut($span);
					$span = "<$tag>$span</$tag>";
					$textStack[0] = $this->hashPart($span);
					$$tag = ''; # $$tag stands for $emph or $strong
				}
				$treeCharEm = false;
			} else if ($tokenLen == 3) {
				if ($emph) {
					# Reached closing marker for both em and strong.
					# Closing strong marker:
					for ($i = 0; $i < 2; ++$i) {
						$shiftedToken = array_shift($tokenStack);
						$tag = strlen($shiftedToken) == 2 ? "strong" : "em";
						$span = array_shift($textStack);
						$span = $this->runSpanGamut($span);
						$span = "<$tag>$span</$tag>";
						$textStack[0] .= $this->hashPart($span);
						$$tag = ''; # $$tag stands for $emph or $strong
					}
				} else {
					# Reached opening three-char emphasis marker. Push on token
					# stack; will be handled by the special condition above.
					$emph = $token[0];
					$strong = "$emph$emph";
					array_unshift($tokenStack, $token);
					array_unshift($textStack, '');
					$treeCharEm = true;
				}
			} else if ($tokenLen == 2) {
				if ($strong) {
					# Unwind any dangling emphasis marker:
					if (strlen($tokenStack[0]) == 1) {
						$textStack[1] .= array_shift($tokenStack);
						$textStack[0] .= array_shift($textStack);
					}
					# Closing strong marker:
					array_shift($tokenStack);
					$span = array_shift($textStack);
					$span = $this->runSpanGamut($span);
					$span = "<strong>$span</strong>";
					$textStack[0] .= $this->hashPart($span);
					$strong = '';
				} else {
					array_unshift($tokenStack, $token);
					array_unshift($textStack, '');
					$strong = $token;
				}
			} else {
				# Here $tokenLen == 1
				if ($emph) {
					if (strlen($tokenStack[0]) == 1) {
						# Closing emphasis marker:
						array_shift($tokenStack);
						$span = array_shift($textStack);
						$span = $this->runSpanGamut($span);
						$span = "<em>$span</em>";
						$textStack[0] .= $this->hashPart($span);
						$emph = '';
					} else {
						$textStack[0] .= $token;
					}
				} else {
					array_unshift($tokenStack, $token);
					array_unshift($textStack, '');
					$emph = $token;
				}
			}
		}
		return $textStack[0];
	}


	function doBlockQuotes(string $text): string {
		$text = preg_replace_callback('/
			  (								# Wrap whole match in $1
				(?>
				  ^[ ]*>[ ]?			# ">" at the start of a line
					.+\n					# rest of the first line
				  (.+\n)*					# subsequent consecutive lines
				  \n*						# blanks
				)+
			  )
			/xm',
			array(&$this, 'doBlockQuotesCallback'), $text);

		return $text;
	}
	function doBlockQuotesCallback(array $matches): string {
		$bquote = $matches[1];
		# trim one level of quoting - trim whitespace-only lines
		$bquote = preg_replace('/^[ ]*>[ ]?|^[ ]+$/m', '', $bquote);
		$bquote = $this->runBlockGamut($bquote);		# recurse

		$bquote = preg_replace('/^/m', "  ", $bquote);
		# These leading spaces cause problem with <pre> content,
		# so we need to fix that:
		$bquote = preg_replace_callback('{(\s*<pre>.+?</pre>)}sx',
			array(&$this, 'doBlockQuotesCallback2'), $bquote);

		return "\n". $this->hashBlock("<blockquote>\n$bquote\n</blockquote>")."\n\n";
	}
	function doBlockQuotesCallback2(array $matches): string {
		$pre = $matches[1];
		$pre = preg_replace('/^  /m', '', $pre);
		return $pre;
	}


	function formParagraphs(string $text): string {
	#
	#	Params:
	#		$text - string to process with html <p> tags
	#
		# Strip leading and trailing lines:
		$text = preg_replace('/\A\n+|\n+\z/', '', $text);

		$grafs = preg_split('/\n{2,}/', $text, -1, PREG_SPLIT_NO_EMPTY);

		#
		# Wrap <p> tags and unhashify HTML blocks
		#
		foreach ($grafs as $key => $value) {
			if (!preg_match('/^B\x1A[0-9]+B$/', $value)) {
				# Is a paragraph.
				$value = $this->runSpanGamut($value);
				$value = preg_replace('/^([ ]*)/', "<p>", $value);
				$value .= "</p>";
				$grafs[$key] = $this->unhash($value);
			}
			else {
				# Is a block.
				# Modify elements of @grafs in-place...
				$graf = $value;
				$block = $this->htmlHashes[$graf];
				$graf = $block;
//				if (preg_match('{
//					\A
//					(							# $1 = <div> tag
//					  <div  \s+
//					  [^>]*
//					  \b
//					  markdown\s*=\s*  ([\'"])	#	$2 = attr quote char
//					  1
//					  \2
//					  [^>]*
//					  >
//					)
//					(							# $3 = contents
//					.*
//					)
//					(</div>)					# $4 = closing tag
//					\z
//					}xs', $block, $matches))
//				{
//					list(, $div_open, , $div_content, $div_close) = $matches;
//
//					# We can't call Markdown(), because that resets the hash;
//					# that initialization code should be pulled into its own sub, though.
//					$div_content = $this->hashHTMLBlocks($div_content);
//
//					# Run document gamut methods on the content.
//					foreach ($this->documentGamut as $method => $priority) {
//						$div_content = $this->$method($div_content);
//					}
//
//					$div_open = preg_replace(
//						'{\smarkdown\s*=\s*([\'"]).+?\1}', '', $div_open);
//
//					$graf = $div_open . "\n" . $div_content . "\n" . $div_close;
//				}
				$grafs[$key] = $graf;
			}
		}

		return implode("\n\n", $grafs);
	}


	function encodeAttribute(string $text): string {
	#
	# Encode text for a double-quoted HTML attribute. This function
	# is *not* suitable for attributes enclosed in single quotes.
	#
		$text = $this->encodeAmpsAndAngles($text);
		$text = str_replace('"', '&quot;', $text);
		return $text;
	}


	function encodeAmpsAndAngles(string $text): string {
	#
	# Smart processing for ampersands and angle brackets that need to
	# be encoded. Valid character entities are left alone unless the
	# no-entities mode is set.
	#
		// if ($this->noEntities) {
		// 	$text = str_replace('&', '&amp;', $text);
		// } else {
		// 	# Ampersand-encoding based entirely on Nat Irons's Amputator
		// 	# MT plugin: <http://bumppo.net/projects/amputator/>
		// 	$text = preg_replace('/&(?!#?[xX]?(?:[0-9a-fA-F]+|\w+);)/',
		// 						'&amp;', $text);
		// }
		$text = ($this->noEntities)
			? str_replace('&', '&amp;', $text)
			# Ampersand-encoding based entirely on Nat Irons's Amputator
			# MT plugin: <http://bumppo.net/projects/amputator/>
			: preg_replace('/&(?!#?[xX]?(?:[0-9a-fA-F]+|\w+);)/',
								'&amp;', $text);

		# Encode remaining <'s
		$text = str_replace('<', '&lt;', $text);

		return $text;
	}


	function doAutoLinks(string $text): string {
		$text = preg_replace_callback('{<((https?|ftp|dict):[^\'">\s]+)>}i',
			array(&$this, 'doAutoLinksURLCallback'), $text);

		# Email addresses: <address@domain.foo>
		$text = preg_replace_callback('{
			<
			(?:mailto:)?
			(
				(?:
					[-!#$%&\'*+/=?^_`.{|}~\w\x80-\xFF]+
				|
					".*?"
				)
				\@
				(?:
					[-a-z0-9\x80-\xFF]+(\.[-a-z0-9\x80-\xFF]+)*\.[a-z]+
				|
					\[[\d.a-fA-F:]+\]	# IPv4 & IPv6
				)
			)
			>
			}xi',
			array(&$this, 'doAutoLinksEmailCallback'), $text);
		$text = preg_replace_callback('{<(tel:([^\'">\s]+))>}i',array(&$this, 'doAutoLinksTelCallback'), $text);

		return $text;
	}
	function doAutoLinksTelCallback(array $matches): string {
		$url = $this->encodeAttribute($matches[1]);
		$tel = $this->encodeAttribute($matches[2]);
		$link = "<a href=\"$url\">$tel</a>";
		return $this->hashPart($link);
	}
	function doAutoLinksURLCallback(array $matches): string {
		$url = $this->encodeAttribute($matches[1]);
		$link = "<a href=\"$url\">$url</a>";
		return $this->hashPart($link);
	}
	function doAutoLinksEmailCallback(array $matches): string {
		$address = $matches[1];
		$link = $this->encodeEmailAddress($address);
		return $this->hashPart($link);
	}


	function encodeEmailAddress(string $addr): string {
	#
	#	Input: an email address, e.g. "foo@example.com"
	#
	#	Output: the email address as a mailto link, with each character
	#		of the address encoded as either a decimal or hex entity, in
	#		the hopes of foiling most address harvesting spam bots. E.g.:
	#
	#	  <p><a href="&#109;&#x61;&#105;&#x6c;&#116;&#x6f;&#58;&#x66;o&#111;
	#        &#x40;&#101;&#x78;&#97;&#x6d;&#112;&#x6c;&#101;&#46;&#x63;&#111;
	#        &#x6d;">&#x66;o&#111;&#x40;&#101;&#x78;&#97;&#x6d;&#112;&#x6c;
	#        &#101;&#46;&#x63;&#111;&#x6d;</a></p>
	#
	#	Based by a filter by Matthew Wickline, posted to BBEdit-Talk.
	#   With some optimizations by Milian Wolff.
	#
		$addr = "mailto:" . $addr;
		$chars = preg_split('/(?<!^)(?!$)/', $addr);
		$seed = (int)abs(crc32($addr) / strlen($addr)); # Deterministic seed.

		foreach ($chars as $key => $char) {
			$ord = ord($char);
			# Ignore non-ascii chars.
			if ($ord < 128) {
				$rnd = ($seed * (1 + $key)) % 100; # Pseudo-random function.
				# roughly 10% raw, 45% hex, 45% dec
				# '@' *must* be encoded. I insist.
				if ($rnd > 90 && $char != '@') /* do nothing */;
				else if ($rnd < 45) $chars[$key] = '&#x'.dechex($ord).';';
				else              $chars[$key] = '&#'.$ord.';';
			}
		}

		$addr = implode('', $chars);
		$text = implode('', array_slice($chars, 7)); # text without `mailto:`
		$addr = "<a href=\"$addr\">$text</a>";

		return $addr;
	}


	function parseSpan(string $str): string {
	#
	# Take the string $str and parse it into tokens, hashing embeded HTML,
	# escaped characters and handling code spans.
	#
		$output = '';

		$spanRe = '{
				(
					\\\\'.$this->escapeCharsRe.'
				|
					(?<![`\\\\])
					`+						# code span marker
			'.( $this->noMarkup ? '' : '
				|
					<!--    .*?     -->		# comment
				|
					<\?.*?\?> | <%.*?%>		# processing instruction
				|
					<[!$]?[-a-zA-Z0-9:_]+	# regular tags
					(?>
						\s
						(?>[^"\'>]+|"[^"]*"|\'[^\']*\')*
					)?
					>
				|
					<[-a-zA-Z0-9:_]+\s*/> # xml-style empty tag
				|
					</[-a-zA-Z0-9:_]+\s*> # closing tag
			').'
				)
				}xs';

		while (1) {
			#
			# Each loop iteration seach for either the next tag, the next
			# openning code span marker, or the next escaped character.
			# Each token is then passed to handleSpanToken.
			#
			$parts = preg_split($spanRe, $str, 2, PREG_SPLIT_DELIM_CAPTURE);

			# Create token from text preceding tag.
			if ($parts[0] != "")
				$output .= $parts[0];

			# Check if we reach the end.
			if (!isset($parts[1]))
				break;

			$output .= $this->handleSpanToken($parts[1], $parts[2]);
			$str = $parts[2];
		}
		return $output;
	}


	function handleSpanToken($token, string &$str): string {
	#
	# Handle $token provided by parseSpan by determining its nature and
	# returning the corresponding value that should replace it.
	#
		switch ($token[0]) {
			case "\\":
				return $this->hashPart("&#". ord($token[1]). ";");
			case "`":
				# Search for end marker in remaining text.
				if (preg_match('/^(.*?[^`])'.preg_quote($token).'(?!`)(.*)$/sm',
					$str, $matches))
				{
					$str = $matches[2];
					$codespan = $this->makeCodeSpan($matches[1]);
					return $this->hashPart($codespan);
				}
				return $token; // return as text since no ending marker found.
			default:
				return $this->hashPart($token);
		}
	}


	function outdent(string $text): string {
	#
	# Remove one level of line-leading tabs or spaces
	#
		return preg_replace('/^(\t|[ ]{1,'.$this->tabWidth.'})/m', '', $text);
	}


	# String length function for detab. `initDetab` will create a function to
	# hanlde UTF-8 if the default function does not exist.
	var $utf8Strlen = 'mb_strlen';

	function detab(string $text): string {
	#
	# Replace tabs with the appropriate amount of space.
	#
		# For each line we separate the line in blocks delemited by
		# tab characters. Then we reconstruct every line by adding the
		# appropriate number of space between each blocks.

		$text = preg_replace_callback('/^.*\t.*$/m',
			array(&$this, 'detabCallback'), $text);

		return $text;
	}

	function detabCallback(array $matches): string {
		$line = $matches[0];
		$strlength = $this->utf8Strlen; # strlen function for UTF-8.

		# Split in blocks.
		$blocks = explode("\t", $line);
		# Add each blocks to the line.
		$line = $blocks[0];
		unset($blocks[0]); # Do not add first block twice.
		foreach ($blocks as $block) {
			# Calculate amount of space, insert spaces, insert block.
			$amount = $this->tabWidth -
				$strlength($line, 'UTF-8') % $this->tabWidth;
			$line .= str_repeat(" ", $amount) . $block;
		}
		return $line;
	}

	function initDetab() {
	#
	# Check for the availability of the function in the `utf8Strlen` property
	# (initially `mb_strlen`). If the function is not available, create a
	# function that will loosely count the number of UTF-8 characters with a
	# regular expression.
	#
		if (function_exists($this->utf8Strlen)) return;

		$this->utf8Strlen = function($text) {
			return preg_match_all(
				"/[\\\\x00-\\\\xBF]|[\\\\xC0-\\\\xFF][\\\\x80-\\\\xBF]*/", $text, $matches);
		};
	}


	function unhash(string $text) {
	#
	# Swap back in all the tags hashed by _HashHTMLBlocks.
	#
		return preg_replace_callback('/(.)\x1A[0-9]+\1/',
			array(&$this, 'unhashCallback'), $text);
	}

	function unhashCallback($matches) {
		return $this->htmlHashes[$matches[0]];
	}

}

/*

PHP Markdown
============

Description
-----------

This is a PHP translation of the original Markdown formatter written in
Perl by John Gruber.

Markdown is a text-to-HTML filter; it translates an easy-to-read /
easy-to-write structured text format into HTML. Markdown's text format
is mostly similar to that of plain text email, and supports features such
as headers, *emphasis*, code blocks, blockquotes, and links.

Markdown's syntax is designed not as a generic markup language, but
specifically to serve as a front-end to (X)HTML. You can use span-level
HTML tags anywhere in a Markdown document, and you can use block level
HTML tags (like <div> and <table> as well).

For more information about Markdown's syntax, see:

<http://daringfireball.net/projects/markdown/>


Bugs
----

To file bug reports please send email to:

<michel.fortin@michelf.ca>

Please include with your report: (1) the example input; (2) the output you
expected; (3) the output Markdown actually produced.


Version History
---------------

See the readme file for detailed release notes for this version.


Copyright and License
---------------------

PHP Markdown
Copyright (c) 2004-2013 Michel Fortin
<http://michelf.ca/>
All rights reserved.

Based on Markdown
Copyright (c) 2003-2006 John Gruber
<http://daringfireball.net/>
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

*	Redistributions of source code must retain the above copyright notice,
	this list of conditions and the following disclaimer.

*	Redistributions in binary form must reproduce the above copyright
	notice, this list of conditions and the following disclaimer in the
	documentation and/or other materials provided with the distribution.

*	Neither the name "Markdown" nor the names of its contributors may
	be used to endorse or promote products derived from this software
	without specific prior written permission.

This software is provided by the copyright holders and contributors "as
is" and any express or implied warranties, including, but not limited
to, the implied warranties of merchantability and fitness for a
particular purpose are disclaimed. In no event shall the copyright owner
or contributors be liable for any direct, indirect, incidental, special,
exemplary, or consequential damages (including, but not limited to,
procurement of substitute goods or services; loss of use, data, or
profits; or business interruption) however caused and on any theory of
liability, whether in contract, strict liability, or tort (including
negligence or otherwise) arising in any way out of the use of this
software, even if advised of the possibility of such damage.

*/
?>
