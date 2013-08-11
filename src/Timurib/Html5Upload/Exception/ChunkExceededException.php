<?php
namespace Timurib\Html5Upload\Exception;

/**
 * @author Ibragimov Timur <timok@ya.ru>
 */
class ChunkExceededException extends \Exception
{
    public function __construct($allowedSize, $code = 0, $previous = null)
	{
		parent::__construct(
			sprintf('Maximum chunk size (%d) exceeded', $allowedSize),
			$code,
			$previous
		);
	}
}
