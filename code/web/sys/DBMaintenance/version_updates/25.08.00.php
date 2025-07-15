<?php

function getUpdates25_08_00(): array {
	$curTime = time();
	return [
		/*'name' => [
			 'title' => '',
			 'description' => '',
			 'continueOnError' => false,
			 'sql' => [
				 ''
			 ]
		 ], //name*/

		//mark - Grove

		//katherine - Grove

		//kirstien - Grove

		//kodi - Grove

		// Myranda - Grove

		//Yanjun Li - ByWater
		'add_hoopla_flex_batch_size' => [
			'title' => 'Add Hoopla Flex Batch Size',
			'description' => 'Add a batch size for Hoopla Flex availability updates',
			'sql' => [
				"ALTER TABLE hoopla_settings ADD COLUMN hooplaFlexBatchSize int(3) DEFAULT 50",
			]
		], //add_hoopla_flex_batch_size

		// Leo Stoyanov - BWS

		// Laura Escamilla - ByWater Solutions

		//alexander - Open Fifth

		//chloe - Open Fifth


		//Jacob - Open Fifth

		//James Staub - Nashville Public Library

		//Lucas Montoya - Theke Solutions

		//other

		//Talpa Search
		
	];
}
