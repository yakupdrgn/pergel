<?php
/**
 * Error thrown when a token file cannot be compiled.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tokens;

/**
 * Carries every problem found in one pass, so authors fix them all at once.
 */
final class Invalid extends \RuntimeException {

	/**
	 * One message per problem, each prefixed with the file and token path.
	 *
	 * @var list<string>
	 */
	public array $problems;

	/**
	 * Creates the exception.
	 *
	 * @param string[] $problems Problems found.
	 * @phpstan-param list<string> $problems
	 */
	public function __construct( array $problems ) {
		$this->problems = $problems;
		parent::__construct( implode( "\n", $problems ) );
	}
}
