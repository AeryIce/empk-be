<?php

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Executable Issue #13 design check. Tests the proposed scoped query against an
 * isolated schema, before the staff session and API are implemented.
 */
class StaffOwnershipDesignTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->database->exec("
            CREATE TABLE schools (id INTEGER PRIMARY KEY, foundation_id INTEGER);
            CREATE TABLE organization_memberships (
                user_id INTEGER NOT NULL,
                school_id INTEGER,
                foundation_id INTEGER,
                role TEXT NOT NULL,
                state TEXT NOT NULL,
                CHECK (
                    (school_id IS NOT NULL AND foundation_id IS NULL AND role = 'school_admin')
                    OR (foundation_id IS NOT NULL AND school_id IS NULL AND role = 'foundation_admin')
                )
            );
            CREATE TABLE profile_drafts (
                id INTEGER PRIMARY KEY,
                school_id INTEGER,
                foundation_id INTEGER,
                description TEXT NOT NULL,
                lock_version INTEGER NOT NULL DEFAULT 1
            );
            INSERT INTO schools VALUES (1, 1), (2, 1), (3, 2);
            INSERT INTO organization_memberships VALUES
                (11, 1, NULL, 'school_admin', 'active'),
                (22, NULL, 1, 'foundation_admin', 'active'),
                (33, 2, NULL, 'school_admin', 'active'),
                (33, NULL, 2, 'foundation_admin', 'active'),
                (44, 1, NULL, 'school_admin', 'invited'),
                (55, 1, NULL, 'school_admin', 'revoked');
            INSERT INTO profile_drafts (id, school_id, foundation_id, description) VALUES
                (101, 1, NULL, 'School one'), (102, 2, NULL, 'School two'),
                (103, 3, NULL, 'School three'), (201, NULL, 1, 'Foundation one'),
                (202, NULL, 2, 'Foundation two');
        ");
    }

    #[DataProvider('scopeCases')]
    public function test_scope_constrains_reads_and_forged_writes(int $actor, string $kind, int $scope, bool $allowed): void
    {
        $where = match ($kind) {
            'school' => "d.school_id = :scope AND m.school_id = d.school_id AND m.role = 'school_admin'",
            'foundation' => "d.foundation_id = :scope AND m.foundation_id = d.foundation_id AND m.role = 'foundation_admin'",
        };
        $restriction = "EXISTS (SELECT 1 FROM organization_memberships m
            WHERE m.user_id = :actor AND m.state = 'active' AND {$where})";
        $scopeParameters = ['actor' => $actor, 'scope' => $scope];
        $read = $this->database->prepare("SELECT description FROM profile_drafts d WHERE {$restriction}");
        $read->execute($scopeParameters);
        self::assertSame($allowed, $read->fetchColumn() !== false);

        $write = $this->database->prepare("UPDATE profile_drafts AS d SET description = :description,
            lock_version = lock_version + 1 WHERE lock_version = 1 AND {$restriction}");
        $write->execute($scopeParameters + ['description' => 'Edited']);
        self::assertSame((int) $allowed, $write->rowCount());
        self::assertSame((int) $allowed, (int) $this->database->query(
            "SELECT count(*) FROM profile_drafts WHERE description = 'Edited'"
        )->fetchColumn());
    }

    public static function scopeCases(): array
    {
        return [
            'own school' => [11, 'school', 1, true],
            'other school with same parent' => [11, 'school', 2, false],
            'other school with different parent' => [11, 'school', 3, false],
            'school ID cannot act as foundation ID' => [11, 'foundation', 1, false],
            'own foundation' => [22, 'foundation', 1, true],
            'another foundation' => [22, 'foundation', 2, false],
            'parent assignment cannot edit child school' => [22, 'school', 1, false],
            'multi-scope school grant' => [33, 'school', 2, true],
            'multi-scope foundation grant' => [33, 'foundation', 2, true],
            'multi-scope parent does not give school access' => [33, 'school', 3, false],
            'pending grant' => [44, 'school', 1, false],
            'revoked grant' => [55, 'school', 1, false],
            'no grant' => [66, 'school', 1, false],
        ];
    }
}
