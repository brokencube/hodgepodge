<?php
namespace LibNik\Core;

use LibNik\Exception;

class Crypt
{
	static $blowfish_rounds = 10;
	
	public static function hash($string, $hash = null)
	{
		if ($hash and substr($hash, 0, 4) != '$2y$') {
			throw new Exception\Generic(0, 'Invalid Blowfish hash provided to Crypt', $hash);
		}
		
		// If no hash is given, randomly create a Blowfish compliant salt
		if (!$hash) {
			$hash = '$2y$'.static::$blowfish_rounds.'$';
			$range = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789./";
			for($i = 0; $i < 22; $i++) {
				$hash .= $range[rand(0,63)];
			}
		}
		
		return crypt($string, $hash);
	}
		
	public static function encrypt($key, $message, &$iv)
	{
		$td = mcrypt_module_open('tripledes', '', 'cbc', '');
		$key = substr($key, 0, mcrypt_enc_get_key_size($td));
		$iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
		mcrypt_generic_init($td, $key, $iv);
		$encrypted_data = mcrypt_generic($td, $message);
		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);
		
		$iv = base64_encode($iv);
		return base64_encode($encrypted_data);
	}
	
	public static function decrypt($key, $encrypted_data, $iv)
	{
		$iv = base64_decode($iv);
		$td = mcrypt_module_open('tripledes', '', 'cbc', '');
		$key = substr($key, 0, mcrypt_enc_get_key_size($td));
		mcrypt_generic_init($td, $key, $iv);
		$message = mdecrypt_generic($td, base64_decode($encrypted_data));
		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);
		
		return $message;
	}
}
