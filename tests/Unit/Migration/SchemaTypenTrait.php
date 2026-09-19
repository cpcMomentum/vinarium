<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Unit\Migration;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use OCP\DB\ISchemaWrapper;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Welche Klassen gibt `ISchemaWrapper` eigentlich zurueck?
 *
 * Bis NC 34 sind das Doctrines `Table` und `Column`, ab dem naechsten Major
 * `OCP\DB\Schema\ITable` und `IColumn`. Fest verdrahtet gemockt laufen die
 * Migrations-Tests nur gegen genau eine der beiden Fassungen: der canary gegen
 * `ocp dev-master` fiel am 15.08.2026 mit zwoelf TypeErrors um.
 *
 * Die Migrationen selbst sind von der Aenderung nicht betroffen -- sie nutzen
 * nur Methoden, die es auf beiden gibt (`addColumn`, `addIndex`,
 * `addUniqueIndex`, `dropColumn`, `hasColumn`, `setPrimaryKey`), und kennen
 * keinen Doctrine-Typ. Es war also nie die App kaputt, sondern der Mock.
 *
 * Deshalb nicht raten, sondern das Interface fragen. Ein kuenftiger Typwechsel
 * zieht damit von selbst mit.
 *
 * Als Trait und nicht zweimal abgeschrieben: es gibt zwei Migrations-Tests, und
 * zwei Kopien laufen frueher oder spaeter auseinander -- genau der Grund, aus
 * dem es nc-app-tooling gibt.
 */
trait SchemaTypenTrait {

	private static function tabellenKlasse(): string {
		$typ = (new ReflectionMethod(ISchemaWrapper::class, 'createTable'))->getReturnType();
		return $typ instanceof ReflectionNamedType ? $typ->getName() : Table::class;
	}

	private static function spaltenKlasse(): string {
		$typ = (new ReflectionMethod(self::tabellenKlasse(), 'addColumn'))->getReturnType();
		return $typ instanceof ReflectionNamedType ? $typ->getName() : Column::class;
	}
}
