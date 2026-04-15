<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		// Producers
		['name' => 'producer#index',   'url' => '/api/v1/producers',      'verb' => 'GET'],
		['name' => 'producer#show',    'url' => '/api/v1/producers/{id}', 'verb' => 'GET'],
		['name' => 'producer#create',  'url' => '/api/v1/producers',      'verb' => 'POST'],
		['name' => 'producer#update',  'url' => '/api/v1/producers/{id}', 'verb' => 'PATCH'],
		['name' => 'producer#destroy', 'url' => '/api/v1/producers/{id}', 'verb' => 'DELETE'],

		// Wines
		['name' => 'wine#index',   'url' => '/api/v1/wines',      'verb' => 'GET'],
		['name' => 'wine#show',    'url' => '/api/v1/wines/{id}', 'verb' => 'GET'],
		['name' => 'wine#create',  'url' => '/api/v1/wines',      'verb' => 'POST'],
		['name' => 'wine#update',  'url' => '/api/v1/wines/{id}', 'verb' => 'PATCH'],
		['name' => 'wine#destroy', 'url' => '/api/v1/wines/{id}', 'verb' => 'DELETE'],

		// Vintages
		['name' => 'vintage#index',   'url' => '/api/v1/vintages',      'verb' => 'GET'],
		['name' => 'vintage#show',    'url' => '/api/v1/vintages/{id}', 'verb' => 'GET'],
		['name' => 'vintage#create',  'url' => '/api/v1/vintages',      'verb' => 'POST'],
		['name' => 'vintage#update',  'url' => '/api/v1/vintages/{id}', 'verb' => 'PATCH'],
		['name' => 'vintage#destroy', 'url' => '/api/v1/vintages/{id}', 'verb' => 'DELETE'],
	],
];
