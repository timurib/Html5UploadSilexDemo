<?php
namespace Timurib\Html5Upload\Exception;

/**
 * @author Ibragimov Timur <timok@ya.ru>
 */
class NotEnoughSpaceException extends \Exception
{
	/**
	 * @var int
	 */
	private $deficit;

	/**
	 * @param int $deficit
	 * @param int $code
	 * @param \Exception $previous
	 */
	public function __construct($deficit, $code = 0, $previous = null)
	{
		parent::__construct(
			sprintf('Not enough free disk space (need %d more bytes)', $deficit),
			$code,
			$previous
		);
		$this->deficit = $deficit;
	}

	/**
	 * @return int
	 */
	public function getDeficit()
	{
		return $this->deficit;
	}
}
