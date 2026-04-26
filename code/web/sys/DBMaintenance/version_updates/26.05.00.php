<?php
/** @noinspection SqlDialectInspection */

/** @noinspection PhpUnused */
function getUpdates26_05_00(): array {
	$now = time();

	return [
		/*'name' => [
			 'title' => '',
			 'description' => '',
			 'continueOnError' => false,
			 'sql' => [
				 ''
			 ]
		 ], //name*/

		//mark n

		//kirstien

		//kodi

		//yanjun
		'extend_holiday_table' => [
			'title' => 'Extend Holiday Table',
			'description' => 'Add special-hours fields to the holiday table',
			'continueOnError' => false,
			'sql' => [
				'ALTER TABLE holiday ADD COLUMN closed TINYINT(1) NOT NULL DEFAULT 1',
				'ALTER TABLE holiday ADD COLUMN open varchar(10) DEFAULT NULL',
				'ALTER TABLE holiday ADD COLUMN close varchar(10) DEFAULT NULL',
				'ALTER TABLE holiday ADD COLUMN notes varchar(255) DEFAULT NULL',
			]
		], //extend_holiday_table
		'create_holiday_location_table' => [
			'title' => 'Create Holiday Location Table',
			'description' => 'Create a table to map holidays to locations',
			'continueOnError' => false,
			'sql' => [
				'CREATE TABLE holiday_location (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					holidayId INT NOT NULL,
					locationId INT NOT NULL,
					UNIQUE KEY holiday_location_unique (holidayId, locationId),
					INDEX holiday_location_holiday (holidayId),
					INDEX holiday_location_location (locationId)
				) ENGINE=InnoDB',
			]
		], //create_holiday_location_table
		'insert_holiday_location_table' => [
			'title' => 'Insert Holiday Location Table',
			'description' => 'Insert the holiday location table',
			'continueOnError' => false,
			'sql' => [
				'INSERT IGNORE INTO holiday_location (holidayId, locationId)
					SELECT h.id, l.locationId
					FROM holiday h
					INNER JOIN location l ON l.libraryId = h.libraryId',
			]
		], //insert_holiday_location_table
		'add_use_holiday_hours_table_to_location_table' => [
			'title' => 'Add Use Holiday Hours Table to Location Table',
			'description' => 'Add a column to the location table to indicate whether the library uses the holiday hours table',
			'continueOnError' => false,
			'sql' => [
				'ALTER TABLE location ADD COLUMN useHolidayHoursTable TINYINT(1) NOT NULL DEFAULT 1',
			]
		], //add_use_holiday_hours_table_to_location_table

		//imani

		//galen

		//chloe

		//pedro

		//mark j

		//lucas

		//tomas

		// stephen


		//pedro

		//other

	];
}
