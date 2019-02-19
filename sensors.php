<?php

$path = $_SERVER['DOCUMENT_ROOT'] ? $_SERVER['DOCUMENT_ROOT'] : $_SERVER['PWD'];

error_reporting(-1);
ini_set("display_errors", 0);

$file_name = $path . '/sensors/' . date('Y/m/d/H') . '/' . time() . '_' . rand(0, 1000) . '.txt';

if (!file_exists($file_name)) {
    mkdir(dirname($file_name), 0755, true);
}

$json = file_get_contents("php://input");

if ($json) {
    // Save information to the file
    file_put_contents($file_name, $json);

    $json = json_decode($json, true);

    // If the data is correct
    if (isset($json['esp8266id']) && isset($json['sensordatavalues']) && preg_match('/^[1-9][0-9]*$/', $json['esp8266id'])) {

        require_once('db.php');

        $now = date('Y-m-d H:i:s', strtotime('+2 HOURS'));

        // Allowed measurements
        $allowed_measurements = [
            'SDS_P2' => 'pm25',                     // SDS011
            'SDS_P1' => 'pm10',                     // SDS011

            'temperature' => 'temperature',         // DHT022
            'humidity' => 'humidity',               // DHT022

            'BME280_temperature' => 'temperature',  // BME280
            'BME280_humidity' => 'humidity',        // BME280
            'BME280_pressure' => 'pressure'         // BME280
        ];

        $phenomenons = array_unique(array_values($allowed_measurements));

        try {

            $db = new Database(DB_NAME, DB_USER, DB_PASSWORD, DB_HOST);

            $db->select('devices', [
                'chip_id' => $json['esp8266id']
            ]);

            $device_id = null;

            // Search for the device
            if ($db->count()) {
                $device = $db->row();

                $device_id = $device->id;

                // Update last measurement time and counter
                $db->update('devices', [
                    'measurements_count' => $device->measurements_count + 1,
                    'last_measurement_at' => $now
                ], [
                    'id' => $device_id
                ]);
            } else {
                // Create a new device
                $db->insert('devices', [
                    'chip_id' => $json['esp8266id'],
                    'last_measurement_at' => $now,
                    'measurements_count' => 1
                ]);

                $device_id = $db->id();

                // Save zero measurements
                foreach ($phenomenons as $phenomenon) {
                    $db->insert('device_last_data', [
                        'device_id' => $device_id,
                        'phenomenon' => $phenomenon,
                    ]);
                }
            }

            // Save last measurements
            foreach ($json['sensordatavalues'] as $sensordatavalue) {
                if (isset($sensordatavalue['value_type']) && isset($sensordatavalue['value'])) {
                    if (isset($allowed_measurements[$sensordatavalue['value_type']])) {
                        $db->update('device_last_data', [
                            'value' => floatval($sensordatavalue['value']),
                            'updated_at' => $now
                        ], [
                            'device_id' => $device_id,
                            'phenomenon' => $allowed_measurements[$sensordatavalue['value_type']]
                        ]);
                    }
                }
            }

        } catch (Exception $exception) {
        }
    }
}