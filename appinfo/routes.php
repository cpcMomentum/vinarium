<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		// Cellar
		['name' => 'cellar#show',          'url' => '/api/v1/cellar',                                        'verb' => 'GET'],
		['name' => 'cellar#create',        'url' => '/api/v1/cellar',                                        'verb' => 'POST'],
		['name' => 'cellar#createShelf',   'url' => '/api/v1/cellar/shelves',                                'verb' => 'POST'],
		['name' => 'cellar#updateShelf',   'url' => '/api/v1/cellar/shelves/{shelfId}',                     'verb' => 'PATCH'],
		['name' => 'cellar#destroyShelf',  'url' => '/api/v1/cellar/shelves/{shelfId}',                     'verb' => 'DELETE'],
		['name' => 'cellar#slots',              'url' => '/api/v1/compartments/{compartmentId}/slots',       'verb' => 'GET'],
		['name' => 'cellar#reconfigure',        'url' => '/api/v1/compartments/{compartmentId}/reconfigure', 'verb' => 'PATCH'],
		['name' => 'cellar#addCompartment',     'url' => '/api/v1/cellar/shelves/{shelfId}/compartments',    'verb' => 'POST'],
		['name' => 'cellar#updateCompartment',  'url' => '/api/v1/compartments/{compartmentId}',             'verb' => 'PATCH'],
		['name' => 'cellar#destroyCompartment', 'url' => '/api/v1/compartments/{compartmentId}',             'verb' => 'DELETE'],

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

		// Purchases (creates purchase + bulk-bottles atomically)
		['name' => 'purchase#all',     'url' => '/api/v1/purchases/all',     'verb' => 'GET'],
		['name' => 'purchase#vendors', 'url' => '/api/v1/purchases/vendors', 'verb' => 'GET'],
		['name' => 'purchase#index',   'url' => '/api/v1/purchases',         'verb' => 'GET'],
		['name' => 'purchase#show',    'url' => '/api/v1/purchases/{id}', 'verb' => 'GET'],
		['name' => 'purchase#create',  'url' => '/api/v1/purchases',      'verb' => 'POST'],
		['name' => 'purchase#createFromWizard', 'url' => '/api/v1/purchases/wizard', 'verb' => 'POST'],
		['name' => 'purchase#update',  'url' => '/api/v1/purchases/{id}', 'verb' => 'PATCH'],
		['name' => 'purchase#destroy', 'url' => '/api/v1/purchases/{id}', 'verb' => 'DELETE'],

		// Bottles
		['name' => 'bottle#details',      'url' => '/api/v1/bottles/{id}/details',   'verb' => 'GET'],
		['name' => 'bottle#uploadPhoto',  'url' => '/api/v1/bottles/{id}/photo',     'verb' => 'POST'],
		['name' => 'bottle#deletePhoto',  'url' => '/api/v1/bottles/{id}/photo',     'verb' => 'DELETE'],
		['name' => 'bottle#getPhoto',     'url' => '/api/v1/bottles/{id}/photo',     'verb' => 'GET'],
		['name' => 'bottle#index',   'url' => '/api/v1/bottles',           'verb' => 'GET'],
		['name' => 'bottle#parked',  'url' => '/api/v1/bottles/parked',    'verb' => 'GET'],
		['name' => 'bottle#giftRecipients', 'url' => '/api/v1/bottles/gift-recipients', 'verb' => 'GET'],
		['name' => 'bottle#show',    'url' => '/api/v1/bottles/{id}',      'verb' => 'GET'],
		['name' => 'bottle#move',    'url' => '/api/v1/bottles/{id}/move', 'verb' => 'PATCH'],
		['name' => 'bottle#swap',    'url' => '/api/v1/bottles/{id}/swap',    'verb' => 'PATCH'],
		['name' => 'bottle#restore', 'url' => '/api/v1/bottles/{id}/restore', 'verb' => 'PATCH'],
		['name' => 'bottle#gift',    'url' => '/api/v1/bottles/{id}/gift', 'verb' => 'PATCH'],
		['name' => 'bottle#lose',    'url' => '/api/v1/bottles/{id}/lose', 'verb' => 'PATCH'],
		['name' => 'bottle#destroy', 'url' => '/api/v1/bottles/{id}',      'verb' => 'DELETE'],

		// Tastings
		['name' => 'tasting#uploadPhoto', 'url' => '/api/v1/tastings/{id}/photo',            'verb' => 'POST'],
		['name' => 'tasting#deletePhoto', 'url' => '/api/v1/tastings/{id}/photo/{fileId}',   'verb' => 'DELETE'],
		['name' => 'tasting#details',  'url' => '/api/v1/tastings/{id}/details',     'verb' => 'GET'],
		['name' => 'tasting#stats',    'url' => '/api/v1/tastings/stats',            'verb' => 'GET'],
		['name' => 'tasting#all',      'url' => '/api/v1/tastings',                  'verb' => 'GET'],
		['name' => 'tasting#byBottle', 'url' => '/api/v1/bottles/{bottleId}/tastings', 'verb' => 'GET'],
		['name' => 'tasting#create',   'url' => '/api/v1/bottles/{bottleId}/tastings', 'verb' => 'POST'],
		['name' => 'tasting#consume',  'url' => '/api/v1/bottles/{bottleId}/consume',  'verb' => 'POST'],
		['name' => 'tasting#update',   'url' => '/api/v1/tastings/{id}',              'verb' => 'PATCH'],
		['name' => 'tasting#destroy',  'url' => '/api/v1/tastings/{id}',              'verb' => 'DELETE'],

		// Dashboard + Export
		['name' => 'dashboard#stats',     'url' => '/api/v1/dashboard/stats', 'verb' => 'GET'],
		['name' => 'dashboard#exportCsv', 'url' => '/api/v1/export/csv',     'verb' => 'GET'],

		// Search (full-text over producers / wines / vintages)
		['name' => 'search#index', 'url' => '/api/v1/search', 'verb' => 'GET'],
	],
];
