<?php
namespace LibNik\Orm;

class Time extends \DateTime
{
    const MYSQL_DATE = 'Y-m-d H:i:s';
	public static $format = 'D jS M Y H:i:s T';
	public static $timezone = 'UTC'; // Default to UTC
	
    public function __construct($time = 'now', DateTimeZone $timezone)
    {
        if (!$timezone) $timezone = DateTimeZone(self::$timezone);
        parent::__construct($time, $timezone);
    }
    
	public function __toString()
	{
		return $this->format(self::$format);	
	}
	
	public function mysql()
	{
		$timezone = $this->getTimezone();
        $this->setTimezone(new \DateTimeZone('UTC')); // Just to make doubly sure!
		$datetime = $this->format(self::MYSQL_DATE);
        $this->setTimezone($timezone);
		return $datetime;
	}
}
