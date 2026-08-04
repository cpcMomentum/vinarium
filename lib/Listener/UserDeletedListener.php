<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IDBConnection;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Removes a user's cellar data once their Nextcloud account is deleted.
 *
 * Without this the rows survive their owner: unreachable through any UI, yet
 * still occupying storage and shelf slots. Observed on the dev instance after
 * `occ user:delete` (#212).
 *
 * Ownership has two roots, and everything else hangs below one of them:
 *
 *   producer -> wine -> vintage -> purchase -> bottle -> tasting
 *   cellar   -> shelf -> compartment -> level
 *                                    -> slot
 *
 * Deletion runs deepest-first so every statement can still reach the owner
 * through its ancestors — the root rows are removed last, once nothing needs
 * them to resolve ownership any more.
 *
 * @template-implements IEventListener<UserDeletedEvent>
 */
class UserDeletedListener implements IEventListener {

	public function __construct(
		private readonly IDBConnection $db,
		private readonly LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof UserDeletedEvent) {
			return;
		}
		$userId = $event->getUser()->getUID();

		$inTransaction = false;
		try {
			$this->db->beginTransaction();
			$inTransaction = true;

			$deleted = [];
			foreach ($this->statements() as $table => $sql) {
				$deleted[$table] = $this->db->executeStatement($sql, [$userId]);
			}
			$this->db->commit();
			$inTransaction = false;

			$total = array_sum($deleted);
			if ($total > 0) {
				$this->logger->info('Vinarium: Daten des geloeschten Benutzers entfernt', [
					'user' => $userId,
					'rows' => array_filter($deleted),
					'total' => $total,
				]);
			}
		} catch (Throwable $e) {
			if ($inTransaction) {
				$this->db->rollBack();
			}
			// Swallowed deliberately: the account is already gone, and throwing here
			// would surface as a failed user deletion. Leftover rows are the lesser
			// problem, but they must not disappear silently from the log.
			$this->logger->error('Vinarium: Aufraeumen nach Benutzerloeschung fehlgeschlagen', [
				'user' => $userId,
				'exception' => $e,
			]);
		}
	}

	/**
	 * Ordered deepest-first. Each subquery joins up to the owning root table,
	 * which is why the roots come last.
	 *
	 * Subqueries rather than joined deletes: `DELETE ... USING` and
	 * `DELETE t FROM a JOIN b` are spelled differently in PostgreSQL and MySQL,
	 * while `DELETE FROM x WHERE y IN (SELECT ...)` works on both. No statement
	 * selects from the table it deletes from, which MySQL would reject.
	 *
	 * @return array<string, string> table => statement
	 */
	private function statements(): array {
		$wineSide = 'FROM `*PREFIX*vinarium_wine` w '
			. 'JOIN `*PREFIX*vinarium_producer` pr ON pr.id = w.producer_id '
			. 'WHERE pr.owner_user_id = ?';
		$vintageSide = 'FROM `*PREFIX*vinarium_vintage` v '
			. 'JOIN `*PREFIX*vinarium_wine` w ON w.id = v.wine_id '
			. 'JOIN `*PREFIX*vinarium_producer` pr ON pr.id = w.producer_id '
			. 'WHERE pr.owner_user_id = ?';
		$purchaseSide = 'FROM `*PREFIX*vinarium_purchase` p '
			. 'JOIN `*PREFIX*vinarium_vintage` v ON v.id = p.vintage_id '
			. 'JOIN `*PREFIX*vinarium_wine` w ON w.id = v.wine_id '
			. 'JOIN `*PREFIX*vinarium_producer` pr ON pr.id = w.producer_id '
			. 'WHERE pr.owner_user_id = ?';
		$bottleSide = 'FROM `*PREFIX*vinarium_bottle` b '
			. 'JOIN `*PREFIX*vinarium_purchase` p ON p.id = b.purchase_id '
			. 'JOIN `*PREFIX*vinarium_vintage` v ON v.id = p.vintage_id '
			. 'JOIN `*PREFIX*vinarium_wine` w ON w.id = v.wine_id '
			. 'JOIN `*PREFIX*vinarium_producer` pr ON pr.id = w.producer_id '
			. 'WHERE pr.owner_user_id = ?';

		$shelfSide = 'FROM `*PREFIX*vinarium_shelf` s '
			. 'JOIN `*PREFIX*vinarium_cellar` ce ON ce.id = s.cellar_id '
			. 'WHERE ce.owner_user_id = ?';
		$compartmentSide = 'FROM `*PREFIX*vinarium_compartment` co '
			. 'JOIN `*PREFIX*vinarium_shelf` s ON s.id = co.shelf_id '
			. 'JOIN `*PREFIX*vinarium_cellar` ce ON ce.id = s.cellar_id '
			. 'WHERE ce.owner_user_id = ?';

		return [
			'tasting' => 'DELETE FROM `*PREFIX*vinarium_tasting` WHERE bottle_id IN (SELECT b.id ' . $bottleSide . ')',
			'bottle' => 'DELETE FROM `*PREFIX*vinarium_bottle` WHERE purchase_id IN (SELECT p.id ' . $purchaseSide . ')',
			'purchase' => 'DELETE FROM `*PREFIX*vinarium_purchase` WHERE vintage_id IN (SELECT v.id ' . $vintageSide . ')',
			'vintage' => 'DELETE FROM `*PREFIX*vinarium_vintage` WHERE wine_id IN (SELECT w.id ' . $wineSide . ')',
			'wine' => 'DELETE FROM `*PREFIX*vinarium_wine` WHERE producer_id IN (SELECT pr.id FROM `*PREFIX*vinarium_producer` pr WHERE pr.owner_user_id = ?)',
			'producer' => 'DELETE FROM `*PREFIX*vinarium_producer` WHERE owner_user_id = ?',

			'slot' => 'DELETE FROM `*PREFIX*vinarium_slot` WHERE compartment_id IN (SELECT co.id ' . $compartmentSide . ')',
			'level' => 'DELETE FROM `*PREFIX*vinarium_level` WHERE compartment_id IN (SELECT co.id ' . $compartmentSide . ')',
			'compartment' => 'DELETE FROM `*PREFIX*vinarium_compartment` WHERE shelf_id IN (SELECT s.id ' . $shelfSide . ')',
			'shelf' => 'DELETE FROM `*PREFIX*vinarium_shelf` WHERE cellar_id IN (SELECT ce.id FROM `*PREFIX*vinarium_cellar` ce WHERE ce.owner_user_id = ?)',
			'cellar' => 'DELETE FROM `*PREFIX*vinarium_cellar` WHERE owner_user_id = ?',
		];
	}
}
