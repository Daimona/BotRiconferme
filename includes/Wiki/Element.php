<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki;

/**
 * Base class for wiki elements
 */
abstract class Element {
	/** @var Controller */
	protected $controller;

	/**
	 * @param Controller $controller
	 */
	public function __construct( Controller $controller ) {
		$this->controller = $controller;
	}

	/**
	 * Return a regex for matching the name of the element
	 *
	 * @return string
	 */
	abstract public function getRegex() : string;

	/**
	 * Get a regex matching any element in the given array
	 *
	 * @param self[] $elements
	 * @return string
	 * @todo Is this the right place?
	 */
	public static function regexFromArray( array $elements ) : string {
		$bits = [];
		foreach ( $elements as $el ) {
			$bits[] = $el->getRegex();
		}
		return '(?:' . implode( '|', $bits ) . ')';
	}
}
