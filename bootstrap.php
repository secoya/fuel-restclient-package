<?php

Autoloader::add_core_namespace('Secoya\\Rest');

Autoloader::add_classes(array(
	'Secoya\\Rest\\Client' => __DIR__.'/classes/client.php',
	'Secoya\\Rest\\RestException' => __DIR__.'/classes/restexception.php'
));
