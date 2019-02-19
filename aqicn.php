<?php
/**
 * Project: api.savednipro.org
 * Author: Pavel Tkachenko
 * Date: 2/16/19
 */

error_reporting(-1);
ini_set("display_errors", 0);

require_once('db.php');

try {
    $db = new Database(DB_NAME, DB_USER, DB_PASSWORD, DB_HOST);

    $last_data = $db->select('device_last_data');

    $measurements = [];

    foreach ($last_data->result() as $data) {
        if (!isset($measurements[$data->device_id])) {
            $measurements[$data->device_id] = [];
        }

        $measurements[$data->device_id][$data->phenomenon] = $data;
    }

    $result = [];

    $phenomenons = [
        'pm25' => [
            'pollutant' => 'PM2.5',
            'unit' => 'mg/m3'
        ],
        'pm10' => [
            'pollutant' => 'PM10',
            'unit' => 'mg/m3'
        ],
        'temperature' => [
            'pollutant' => 'Temperature',
            'unit' => 'Celcius'
        ],
        'humidity' => [
            'pollutant' => 'Humidity',
            'unit' => '%'
        ],
        'pressure' => [
            'pollutant' => 'Pressure',
            'unit' => 'hPa',
            'multiplicator' => 1 / 100
        ],
    ];

    $devices = $db->select('devices', [
        'is_public' => 1
    ]);
    foreach ($devices->result() as $device) {
        if (isset($measurements[$device->id])) {
            $row = [
                'id' => 'SAVEDNIPRO_' . str_pad($device->id, 3, '0', STR_PAD_LEFT),
                'cityName' => $device->city_name,
                'stationName' => $device->name,
                'localName' => $device->address,
                'timezone' => '+0200',
                'latitude' => $device->latitude,
                'longitude' => $device->longitude,
                'pollutants' => [],
            ];

            foreach ($measurements[$device->id] as $measurement) {
                if (isset($phenomenons[$measurement->phenomenon]) && !is_null($measurement->value)) {
                    $row['pollutants'][] = [
                        'pol' => $phenomenons[$measurement->phenomenon]['pollutant'],
                        'unit' => $phenomenons[$measurement->phenomenon]['unit'],
                        'time' => $measurement->updated_at,
                        'value' => floatval(sprintf('%0.2f',
                            $measurement->value * (isset($phenomenons[$measurement->phenomenon]['multiplicator']) ? $phenomenons[$measurement->phenomenon]['multiplicator'] : 1))),
                        'averaging' => '2 minutes'
                    ];
                }
            }

            $result[] = $row;
        }
    }

    $data = json_encode($result);

    $data = str_replace(array(':"false"', ':"true"'), array(':false', ':true'), $data);

    header('Content-Type: application/json');

    echo $data;

} catch (Exception $exception) {

}
