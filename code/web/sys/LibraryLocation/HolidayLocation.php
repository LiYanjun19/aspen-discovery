<?php
/** @noinspection PhpMissingFieldTypeInspection */


class HolidayLocation extends DataObject {
	public $__table = 'holiday_location';
	public $id;								// int(11)  not_null primary_key auto_increment
	public $holidayId;						// int(11)
	public $locationId;						// int(11)

	public function getNumericColumnNames(): array {
		return [
			'holidayId',
			'locationId',
		];
	}
}
