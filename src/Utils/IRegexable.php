<?php declare( strict_types=1 );

namespace BotRiconferme\Utils;

interface IRegexable {
	/**
	 * Return a regex for matching the name of the element
	 */
	public function getRegex( string $delimiter ): string;
}
