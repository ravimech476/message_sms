<?php

namespace App\Database;

use Illuminate\Database\Schema\Grammars\MySqlGrammar as BaseMySqlGrammar;

/**
 * Schema grammar override for MySQL 5.1 compatibility.
 *
 * Laravel 11's default MySqlGrammar queries information_schema.columns.generation_expression,
 * which only exists in MySQL >= 5.7. On MySQL 5.1 this causes:
 *   SQLSTATE[42S22]: Column not found: 1054 Unknown column 'generation_expression'
 *
 * This subclass replaces that field with a literal empty string so the rest of
 * Laravel's schema introspection (Schema::hasColumn, Schema::getColumnListing, etc.)
 * keeps working. Our migrations don't use generated columns, so losing the value is safe.
 */
class LegacyMySqlGrammar extends BaseMySqlGrammar
{
    /**
     * Compile the query to determine the columns (MySQL 5.1 compatible).
     */
    public function compileColumns($database, $table)
    {
        return sprintf(
            'select column_name as `name`, data_type as `type_name`, column_type as `type`, '
            .'collation_name as `collation`, is_nullable as `nullable`, '
            .'column_default as `default`, column_comment as `comment`, '
            ."'' as `expression`, extra as `extra` "
            .'from information_schema.columns where table_schema = %s and table_name = %s '
            .'order by ordinal_position asc',
            $this->quoteString($database),
            $this->quoteString($table)
        );
    }
}
