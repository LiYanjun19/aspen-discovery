<?php

require_once ROOT_DIR . "/Action.php";
require_once ROOT_DIR . '/CatalogConnection.php';

class MyAccount_EmailResetPin extends Action {
	function launch($msg = null) {
		global $interface;
		global $library;

		$interface->assign('usernameLabel', str_replace('Your', '', $library->loginFormUsernameLabel ? $library->loginFormUsernameLabel : 'Name'));
		$interface->assign('passwordLabel', str_replace('Your', '', $library->loginFormPasswordLabel ? $library->loginFormPasswordLabel : 'Library Card Number'));

		$catalog = CatalogFactory::getCatalogConnectionInstance(null, null);
		if (isset($_REQUEST['submit'])) {
			$emailResult = $catalog->processEmailResetPinForm();
			header('Location: /MyAccount/EmailResetPinResults?success=' . $emailResult['success'] . '&error=' . urlencode($emailResult['error']) ?? '');
		} else {
			$this->display($catalog->getEmailResetPinTemplate(), 'Reset ' . $interface->getVariable('passwordLabel'), '');
		}
	}

	function getBreadcrumbs(): array {
		return [];
	}
}