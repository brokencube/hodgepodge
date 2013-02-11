<?php
namespace LibNik\Orm;

class Time extends \DateTime
{
	public static $display_format = 'D jS M Y H:i:s T';
	public static $display_timezone = 'UTC'; // Default to UTC
	
	public function __toString()
	{
		$this->setTimezone(new \DateTimeZone(self::$display_timezone));
		$datetime = $this->format(self::$display_format);	
		$this->setTimezone(new \DateTimeZone('UTC'));
		return $datetime;
	}
	
	public function mysql()
	{
		$this->setTimezone(new \DateTimeZone('UTC')); // Just to make doublely sure!
		$datetime = $this->format(MYSQL_DATE);
		return $datetime;
	}
}