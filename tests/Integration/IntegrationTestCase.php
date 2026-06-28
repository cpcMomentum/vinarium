<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Integration;

use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase {
	protected IDBConnection $db;

	protected function setUp(): void {
		parent::setUp();
		// Integrationstests brauchen eine laufende Nextcloud-Instanz mit echter
		// DB (IDBConnection-Transaktion). Container-frei gegen die ocp-Stubs fehlt
		// die private OC-Klasse — dann ueberspringen (laufen im Container weiter).
		if (!class_exists('OC')) {
			$this->markTestSkipped('Benoetigt eine laufende Nextcloud-Instanz mit Datenbank.');
		}
		$this->db = Server::get(IDBConnection::class);
		$this->db->beginTransaction();
	}

	protected function tearDown(): void {
		if ($this->db->inTransaction()) {
			$this->db->rollBack();
		}
		parent::tearDown();
	}

	protected function uniqueId(string $prefix = 'vin'): string {
		return $prefix . '_' . bin2hex(random_bytes(6));
	}
}
