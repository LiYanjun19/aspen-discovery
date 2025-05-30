<?php

require_once ROOT_DIR . '/sys/Grouping/GroupedWorkAlternateTitle.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';

class Admin_AlternateTitles extends ObjectEditor {
	function getObjectType(): string {
		return 'GroupedWorkAlternateTitle';
	}

	function getToolName(): string {
		return 'AlternateTitles';
	}

	function getPageTitle(): string {
		return 'Manual Grouping Authorities';
	}

	function getAllObjects($page, $recordsPerPage): array {
		$object = new GroupedWorkAlternateTitle();
		$object->orderBy($this->getSort());
		$this->applyFilters($object);
		$object->limit(($page - 1) * $recordsPerPage, $recordsPerPage);
		$object->find();
		$objectList = [];
		while ($object->fetch()) {
			$objectList[$object->id] = clone $object;
		}
		return $objectList;
	}

	function getDefaultSort(): string {
		return 'dateAdded desc';
	}

	function getObjectStructure($context = ''): array {
		return GroupedWorkAlternateTitle::getObjectStructure($context);
	}

	function getPrimaryKeyColumn(): string {
		return 'id';
	}

	function getIdKeyColumn(): string {
		return 'id';
	}

	function getInstructions(): string {
		return 'https://help.aspendiscovery.org/help/catalog/groupedworks';
	}

	function getBreadcrumbs(): array {
		$breadcrumbs = [];
		$breadcrumbs[] = new Breadcrumb('/Admin/Home', 'Administration Home');
		$breadcrumbs[] = new Breadcrumb('/Admin/Home#cataloging', 'Catalog / Grouped Works');
		$breadcrumbs[] = new Breadcrumb('/Admin/AlternateTitles', 'Alternate Titles');
		return $breadcrumbs;
	}

	function getActiveAdminSection(): string {
		return 'cataloging';
	}

	function canView(): bool {
		return UserAccount::userHasPermission('Manually Group and Ungroup Works');
	}

	function canAddNew() {
		return false;
	}

	function applyFilter(DataObject $object, string $fieldName, array $filter) {
		if ($fieldName == 'addedByName') {
			$matchingUserIds = [];

			if (($filter['filterType'] == 'matches' && $filter['filterValue'] == '')) {
				$object->whereAdd("addedBy IS NULL");
				return;
			}
			$user = new User();
			$user->whereAdd('id IN (SELECT DISTINCT addedBy FROM grouped_work_alternate_titles WHERE addedBy IS NOT NULL)');
			$user->find();
			while ($user->fetch()) {
				$userDisplayName = $user->getDisplayName();
				if ($filter['filterType'] == 'matches') {
					if (strcasecmp($userDisplayName, $filter['filterValue']) == 0) {
						$matchingUserIds[] = $user->id;
					}
				} elseif ($filter['filterType'] == 'contains') {
					if (stripos($userDisplayName, $filter['filterValue']) !== false) {
						$matchingUserIds[] = $user->id;
					}
				} elseif ($filter['filterType'] == 'startsWith') {
					if (stripos($userDisplayName, $filter['filterValue']) === 0) {
						$matchingUserIds[] = $user->id;
					}
				}
			}
			if (empty($matchingUserIds)) {
				$object->whereAdd('addedBy = -1');
			} else {
				$object->whereAdd('addedBy IN (' . implode(',', $matchingUserIds) . ')');
			}
		} else {
			parent::applyFilter($object, $fieldName, $filter);
		}
	}

}