<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Unit\Listener;

use OCA\Vinarium\Listener\UserDeletedListener;
use OCP\EventDispatcher\Event;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\User\Events\UserDeletedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UserDeletedListenerTest extends TestCase {
	/** @var list<array{string, array}> */
	private array $statements = [];
	private IDBConnection&MockObject $db;
	private LoggerInterface&MockObject $logger;
	private UserDeletedListener $listener;

	protected function setUp(): void {
		$this->statements = [];
		$this->db = $this->createMock(IDBConnection::class);
		$this->db->method('executeStatement')->willReturnCallback(
			function (string $sql, array $params = []): int {
				$this->statements[] = [$sql, $params];
				return 1;
			}
		);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->listener = new UserDeletedListener($this->db, $this->logger);
	}

	private function event(string $uid = 'alice'): UserDeletedEvent {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		return new UserDeletedEvent($user);
	}

	/** @return list<string> the table each statement deletes from, in order */
	private function deletedTables(): array {
		return array_map(static function (array $s): string {
			preg_match('/DELETE FROM `\*PREFIX\*vinarium_(\w+)`/', $s[0], $m);
			return $m[1] ?? '?';
		}, $this->statements);
	}

	public function testChildrenAreDeletedBeforeTheirParents(): void {
		$this->listener->handle($this->event());
		$order = $this->deletedTables();

		// Each subquery resolves ownership through its ancestors, so a parent
		// removed too early would leave its children behind for good.
		$expectedBefore = [
			'tasting' => 'bottle',
			'bottle' => 'purchase',
			'purchase' => 'vintage',
			'vintage' => 'wine',
			'wine' => 'producer',
			'slot' => 'compartment',
			'level' => 'compartment',
			'compartment' => 'shelf',
			'shelf' => 'cellar',
		];
		foreach ($expectedBefore as $child => $parent) {
			$this->assertLessThan(
				array_search($parent, $order, true),
				array_search($child, $order, true),
				"$child muss vor $parent geloescht werden"
			);
		}
	}

	public function testEveryTableOfTheAppIsCovered(): void {
		$this->listener->handle($this->event());

		$this->assertEqualsCanonicalizing([
			'tasting', 'bottle', 'purchase', 'vintage', 'wine', 'producer',
			'slot', 'level', 'compartment', 'shelf', 'cellar',
		], $this->deletedTables());
	}

	public function testEveryStatementIsScopedToTheDeletedUser(): void {
		$this->listener->handle($this->event('bob'));

		foreach ($this->statements as [$sql, $params]) {
			$this->assertSame(['bob'], $params, 'jede Anweisung bindet genau den Benutzer');
			$this->assertStringContainsString('owner_user_id = ?', $sql, 'und filtert ueber den Besitzer');
		}
	}

	public function testWorkRunsInsideOneTransaction(): void {
		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$this->listener->handle($this->event());
	}

	public function testAFailureRollsBackAndIsLoggedInsteadOfThrown(): void {
		// Throwing here would surface as a failed user deletion, although the
		// account itself is already gone.
		$db = $this->createMock(IDBConnection::class);
		$db->method('executeStatement')->willThrowException(new \RuntimeException('db weg'));
		$db->expects($this->once())->method('rollBack');
		$this->logger->expects($this->once())->method('error');

		(new UserDeletedListener($db, $this->logger))->handle($this->event());
	}

	public function testAnUnrelatedEventIsIgnored(): void {
		$this->db->expects($this->never())->method('beginTransaction');

		$this->listener->handle(new Event());
		$this->assertSame([], $this->statements);
	}

	public function testNothingIsLoggedWhenTheUserHadNoData(): void {
		$db = $this->createMock(IDBConnection::class);
		$db->method('executeStatement')->willReturn(0);
		$this->logger->expects($this->never())->method('info');

		(new UserDeletedListener($db, $this->logger))->handle($this->event());
	}
}
