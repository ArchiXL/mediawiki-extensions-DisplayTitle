<?php

class DisplayTitleHooks {

	/**
	 * Implements ParserFirstCallInit hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @since 1.0
	 * @param Parser &$parser the Parser object
	 * @return bool continue checking hooks
	 */
	public static function onParserFirstCallInit( &$parser ) {
		$parser->setFunctionHook( 'getdisplaytitle',
			'DisplayTitleHooks::getdisplaytitleParserFunction' );
		return true;
	}

	/**
	 * Handle #getdisplaytitle parser function.
	 *
	 * @since 1.0
	 * @param Parser $parser the Parser object
	 * @param string $pagename the name of the page
	 * @return string the displaytitle of the page; defaults to pagename if
	 * displaytitle is not set
	 */
	public static function getdisplaytitleParserFunction( Parser $parser,
		$pagename ) {
		$title = Title::newFromText( $pagename );
		if ( !is_null( $title ) ) {
			self::getDisplayTitle( $title, $pagename );
		}
		return $pagename;
	}

	/**
	 * Implements HtmlPageLinkRendererBegin hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/HtmlPageLinkRendererBegin
	 * Handle links. Implements HtmlPageLinkRendererBegin hook of LinkRenderer class.
	 *
	 * @since 1.4
	 * @param LinkRenderer $linkRenderer the LinkRenderer object
	 * @param LinkTarget $target the LinkTarget that the link is pointing to
	 * @param string|HtmlArmor &$text the contents that the <a> tag should have
	 * @param array &$extraAttribs the HTML attributes that the <a> tag should have
	 * @param string &$query the query string to add to the generated URL
	 * @param string &$ret the value to return if the hook returns false
	 * @return bool continue checking hooks
	 */
	public static function onHtmlPageLinkRendererBegin(
		MediaWiki\Linker\LinkRenderer $linkRenderer,
		MediaWiki\Linker\LinkTarget $target, &$text, &$extraAttribs, &$query,
		&$ret ) {
		return self::handleLink( $target, $text );
	}

	/**
	 * Implements SelfLinkBegin hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/SelfLinkBegin
	 * Handle self links.
	 *
	 * @since 1.3
	 * @param Title $nt the Title object of the page
	 * @param string &$html the HTML of the link text
	 * @param string &$trail Text after link
	 * @param string &$prefix Text before link
	 * @param string &$ret the value to return if the hook returns false
	 * @return bool continue checking hooks
	 */
	public static function onSelfLinkBegin( Title $nt, &$html, &$trail,
		&$prefix, &$ret ) {
		return self::handleLink( $nt, $html );
	}

	/**
	 * Helper function. Determines link text for self-links and standard links.
	 *
	 * Handle links. If a link is customized by a user (e. g. [[Target|Text]])
	 * it should remain intact. Let us assume a link is not customized if its
	 * html is the prefixed or (to support Semantic MediaWiki queries)
	 * non-prefixed title of the target page.
	 *
	 * @since 1.3
	 * @param Title $target the Title object that the link is pointing to
	 * @param string|HtmlArmor &$html the HTML of the link text
	 * @return bool continue checking hooks
	 */
	private static function handleLink( Title $target, &$html ) {
		if ( isset( $html ) ) {
			$title = null;
			if ( is_string( $html ) ) {
				$title = Title::newFromText( $html );
			} else if ( get_class( $html ) == 'HtmlArmor' ) {
				$title = Title::newFromText( HtmlArmor::getHtml( $html ) );
			}
			if ( !is_null( $title ) &&
				$title->getText() === $target->getText() &&
				( $title->getSubjectNsText() === $target->getSubjectNsText() ||
				$title->getSubjectNsText() === '' ) ) {
				self::getDisplayTitle( $target, $html );
			}
		} else {
			self::getDisplayTitle( $target, $html );
		}
		return true;
	}

	/**
	 * Implements BeforePageDisplay hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * Display subtitle if requested
	 *
	 * @since 1.0
	 * @param OutputPage &$out the OutputPage object
	 * @param Skin &$sk the Skin object
	 * @return bool continue checking hooks
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$sk ) {
		if ( $GLOBALS['wgDisplayTitleHideSubtitle'] ) {
			return true;
		}
		$title = $out->getTitle();
		if ( !$title->isTalkPage() ) {
			$found = self::getDisplayTitle( $title, $displaytitle );
		} elseif ( $title->getSubjectPage()->exists() ) {
			$found = self::getDisplayTitle( $title->getSubjectPage(), $displaytitle );
		}
		if ( $found ) {
			$subtitle = $title->getPrefixedText();
			$old_subtitle = $out->getSubtitle();
			if ( $old_subtitle !== '' ) {
				$subtitle .= ' / ' . $old_subtitle;
			}
			$out->setSubtitle( $subtitle );
		}
		return true;
	}

	/**
	 * Implements ParserBeforeStrip hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/ParserBeforeStrip
	 * Handle talk page title.
	 *
	 * @since 1.0
	 * @param Parser &$parser the Parser object
	 * @param string &$text the text
	 * @param StripState &$strip_state the strip state
	 * @return bool continue checking hooks
	 */
	public static function onParserBeforeStrip( Parser &$parser, &$text,
		&$strip_state ) {
		$title = $parser->getTitle();
		if ( $title->isTalkPage() && $title->getSubjectPage()->exists() ) {
			$found = self::getDisplayTitle( $title->getSubjectPage(), $displaytitle );
			if ( $found ) {
				$displaytitle = wfMessage( 'displaytitle-talkpagetitle',
					$displaytitle )->plain();
				$parser->mOutput->setTitleText( $displaytitle );
			}
		}
		return true;
	}

	/**
	 * Implements ScribuntoExternalLibraries hook.
	 * See https://www.mediawiki.org/wiki/Extension:Scribunto#Other_pages
	 * Handle Scribunto integration
	 *
	 * @since 1.2
	 * @param string $engine engine in use
	 * @param array &$extraLibraries list of registered libraries
	 * @return bool continue checking hooks
	 */
	public static function onScribuntoExternalLibraries( $engine, array &$extraLibraries ) {
		if ( $engine === 'lua' ) {
			$extraLibraries['mw.ext.displaytitle'] = 'DisplayTitleLuaLibrary';
		}
		return true;
	}

	/**
	 * Get displaytitle page property text.
	 *
	 * @since 1.0
	 * @param Title $title the Title object for the page
	 * @param string &$displaytitle to return the display title, if set
	 * @return boolean true if the page has a displaytitle page property that is
	 * different from the prefixed page name, false otherwise
	 */
	private static function getDisplayTitle( Title $title, &$displaytitle ) {
		$pagetitle = $title->getPrefixedText();
		// remove fragment
		$title = Title::newFromText( $pagetitle );
		if ( $title instanceof Title ) {
			$values = PageProps::getInstance()->getProperties( $title, 'displaytitle' );
			$id = $title->getArticleID();
			if ( array_key_exists( $id, $values ) ) {
				$value = $values[$id];
				if ( trim( str_replace( '&#160;', '', strip_tags( $value ) ) ) !== '' &&
					$value !== $pagetitle ) {
					$displaytitle = $value;
					return true;
				}
			}
		}
		return false;
	}
}
