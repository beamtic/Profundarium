<?php

namespace App\Media;

use Illuminate\Support\Facades\DB;

class NoteReader
{
    // Pass each note to the caller and return the number processed.
    public function each(callable $consume): int
    {
        $connection = DB::connection('hedgedoc');

        // REPEATABLE READ keeps database reads on a consistent snapshot during
        // the scan. READ ONLY rejects accidental SQL writes in this transaction.
        if ($connection->getDriverName() === 'mysql') {
            // On MySQL/MariaDB, set these options for the next transaction before
            // Laravel starts it with BEGIN. They do not change session defaults.
            $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        }

        return $connection->transaction(function () use ($connection, $consume): int {
            if ($connection->getDriverName() === 'pgsql') {
                // PostgreSQL applies these options inside the transaction,
                // before its first data query.
                $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
                // Fail instead of silently omitting notes hidden by row-level security.
                $connection->statement('SET LOCAL row_security = off');
            }

            $count = 0;
            // Include private and soft-deleted notes too: their image references
            // must still prevent uploads from being classified as unused.
            // A cursor avoids loading every note into memory at once.
            foreach ($connection->table('Notes')->select('id', 'shortid', 'alias', 'content')->cursor() as $note) {
                if ($note->content !== null && ! is_string($note->content)) {
                    throw new MediaException('Invalid note content.');
                }
                // Give the caller the Markdown and identifiers for reference checks.
                $consume($note->content ?? '', (string) $note->id, $note->shortid, $note->alias);
                $count++;
            }

            return $count;
        });
    }
}
