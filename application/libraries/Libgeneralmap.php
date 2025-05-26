<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Libgeneralmap
{
	const EARTH_RADIUS = 6371;

	function __construct()
	{

	}

	/**
	 * @param $radius float in km
	 * @param $central array
	 * @param $params array
	 * @return array|bool
	 */
	public function inRadius(float $radius, array $central, array $params)
	{
		if(!is_array($central) || !is_array($params) || !is_numeric($radius))
		{
			return FALSE;
		}

		if(!array_key_exists("latitude", $central) || !array_key_exists("longitude", $central))
		{
			return FALSE;
		}

		foreach($params as $key => $value)
		{
			if(!array_key_exists("latitude", $value) || !array_key_exists("longitude", $value))
			{
				return FALSE;
			}

			$params[$key]["latitude"] = deg2rad(floatval($value["latitude"]));
			$params[$key]["longitude"] = deg2rad(floatval($value["longitude"]));
		}

		$central_radians = [
			"latitude" => deg2rad($central["latitude"]),
			"longitude" => deg2rad($central["longitude"])
		];

		$inRadius = [];

		foreach($params as $key => $value)
		{
			$deltaLatitude = $value["latitude"] - $central_radians["latitude"];
			$deltaLongitude = $value["longitude"] - $central_radians["longitude"];

			// Haversine Formula
			$a = pow(sin($deltaLatitude/2), 2) + cos($central_radians["latitude"])*cos($value["latitude"])*pow(sin($deltaLongitude/2), 2);
			$c = 2*atan2(sqrt($a), sqrt(1-$a));

			// distance is in km
			$d = self::EARTH_RADIUS * $c;

			if($d < $radius)
			{
				array_push($inRadius, TRUE);
			}
			else
			{
				array_push($inRadius, FALSE);
			}
		}

		return $inRadius; 
	}
}
	