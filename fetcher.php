<?php

header("Content-Type: application/json;charset=utf-8");
error_reporting(E_ALL);
set_time_limit(0);
ini_set('display_errors', 'On');

include 'inc/Migrator.php';

/**
 * Esto es todo lo que necesitáis cambiaros
 */
$config = array(
	// Datos de la bd
	'db' => array(
		'host' => 'localhost',
		'dbname' => 'wordpress',
		'user' => 'root',
		'password' => 'root',
		'table_prefix' => 'wp_'
	),
	// Autores
	'authors' => array(
		'Emilio Cobos Álvarez' => array(
			'wp_id' => 1, // La id del usuario administrador (el primero que lo crea) es 1
			'url' => 'http://emiliocobos.net/',
			'email' => 'ecoal95@gmail.com'
		),
		'Autor 2' => array(
			'wp_id' => 2,
			'url' => 'http://urldeejemplo.com/',
			'email' => 'email@dominio.com'
		)
	),
	// aprobar los comentarios por defecto
	'approve_comments' => true
);



try {
	$migrator = new Migrator($config);
} catch ( Exception $e ) {
	$response = new MigratorResponse();
	$response->addError($e->getMessage());
	echo $response;
	die();
}

switch ( @$_POST['action'] ) {
	case 'init_migration':
		$response = $migrator->initMigration();
		break;
	case 'finish_migration':
		$response = $migrator->initMigration();
		break;
	case 'insert_post':
		$response = $migrator->addPost(json_decode(@$_POST['data']));
		break;
	default:
		$response = new MigratorResponse();
		$response->addError('Bad request');
}


echo $response;
