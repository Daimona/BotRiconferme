<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki;

/**
 * Base class for wiki elements
 */
abstract class Element {
	/** @var Wiki */
	protected $wiki;

	/**
	 * @param Wiki $wiki
	 */
	public function __construct( Wiki $wiki ) {
		$this->wiki = $wiki;
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
