<?php
/** @noinspection PhpMissingFieldTypeInspection */

require_once ROOT_DIR . '/sys/LibraryLocation/HolidayLocation.php';

class Holiday extends DataObject {
	public $__table = 'holiday';
	public $__displayNameColumn = 'displayName';
	public $displayName;
	public $id;					    // int(11)  not_null primary_key auto_increment
	public $libraryId;				// int(11)
	public $date;					// date
	public $name;					// varchar(100)
	public $closed;					// tinyint(1)
	public $open;					// varchar(10)
	public $close;					// varchar(10)
	public $notes;					// varchar(255)

	private $_locations;

	public function getNumericColumnNames(): array {
		return [
			'libraryId',
			'closed',
		];
	}


	static $_objectStructure = [];
	static function getObjectStructure(string $context = ''): array {
		if (isset(self::$_objectStructure[$context]) && self::$_objectStructure[$context] !== null) {
			return self::$_objectStructure[$context];
		}
		$libraryList = Library::getLibraryList(false);
		$locationList = Location::getLocationList(false);

		$structure = [
			'id' => [
				'property' => 'id',
				'type' => 'label',
				'label' => 'Id',
				'description' => 'The unique id of the holiday within the database',
			],
			'libraryId' => [
				'property' => 'libraryId',
				'type' => 'enum',
				'values' => $libraryList,
				'label' => 'Library',
				'description' => 'A link to the library',
			],
			'date' => [
				'property' => 'date',
				'type' => 'date',
				'label' => 'Date',
				'description' => 'The date of a holiday.',
				'required' => true,
			],
			'name' => [
				'property' => 'name',
				'type' => 'text',
				'label' => 'Holiday Name',
				'description' => 'The name of a holiday',
			],
			'closed' => [
				'property' => 'closed',
				'type' => 'checkbox',
				'label' => 'Closed',
				'default' => true,
				'description' => 'Check to indicate that the library is closed on this day.',
			],
			'open' => [
				'property' => 'open',
				'type' => 'time',
				'label' => 'Opening Hour',
				'description' => 'The opening hour. Use 24 hour format HH:MM, eg: 08:30',
			],
			'close' => [
				'property' => 'close',
				'type' => 'time',
				'label' => 'Closing Hour',
				'description' => 'The closing hour. Use 24 hour format HH:MM, eg: 16:30',
			],
			'locations' => [
				'property' => 'locations',
				'type' => 'multiSelect',
				'listStyle' => 'checkboxList',
				'label' => 'Locations',
				'description' => 'Locations this holiday/special-hours entry applies to',
				'values' => $locationList,
			],
			'notes' => [
				'property' => 'notes',
				'type' => 'text',
				'label' => 'Notes',
				'description' => 'Notes to show for the hours',
				'maxLength' => 255,
			],
		];

		self::$_objectStructure[$context] = $structure;
		return self::$_objectStructure[$context];
	}

	public function fetch(): bool|DataObject|null {
		$result = parent::fetch();
		if (!empty($this->name)) {
			$this->displayName = $this->name . ' (' . $this->date . ')';
		} else {
			$this->displayName = $this->date;
		}
		return $result;
	}

	public function __get($name) {
		if ($name == "locations") {
			return $this->getLocations();
		}
		return parent::__get($name);
	}

	public function __set($name, $value) {
		if ($name == "locations") {
			$this->_locations = $value;
		} else {
			parent::__set($name, $value);
		}
	}

	public function insert(string $context = ''): int|bool {
		$ret = parent::insert($context);
		if ($ret !== false) {
			$this->saveLocations();
		}
		return $ret;
	}

	public function update(string $context = ''): int|bool {
		$ret = parent::update($context);
		if ($ret !== false) {
			$this->saveLocations();
		}
		return $ret;
	}

	public function delete(bool $useWhere = false, bool $hardDelete = false): bool|int {
		$this->clearOneToManyOptions('HolidayLocation', 'holidayId');
		return parent::delete($useWhere, $hardDelete);
	}

	public function getLocations(): array {
		if (!isset($this->_locations)) {
			$this->_locations = [];
			if (!empty($this->id)) {
				$holidayLocation = new HolidayLocation();
				$holidayLocation->holidayId = $this->id;
				$holidayLocation->find();
				while ($holidayLocation->fetch()) {
					$this->_locations[(int)$holidayLocation->locationId] = (int)$holidayLocation->locationId;
				}
			}
		}
		return $this->_locations;
	}

	public function saveLocations(): void {
		if (isset($this->_locations) && is_array($this->_locations) && !empty($this->id)) {
			$this->clearOneToManyOptions('HolidayLocation', 'holidayId');
			$locationIds = array_unique(array_map('intval', $this->_locations));
			foreach ($locationIds as $locationId) {
				if ($locationId > 0) {
					$holidayLocation = new HolidayLocation();
					$holidayLocation->holidayId = $this->id;
					$holidayLocation->locationId = $locationId;
					$holidayLocation->insert();
				}
			}
			unset($this->_locations);
		}
	}
}
