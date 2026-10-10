<?php

namespace App\Media;

use Illuminate\Support\Facades\DB;

class NoteReader
{
    public function each(callable $consume): int
    {
        $connection = DB::connection('hedgedoc');

        return $connection->transaction(function () use ($connection, $consume): int {
            if ($connection->getDriverName() === 'pgsql') {
                $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
                // Fail instead of silently omitting notes hidden by row-level security.
                $connection->statement('SET LOCAL row_security = off');
            }

            $count = 0;
            // Query the physical table: no permission or soft-deletion scopes.
            foreach ($connection->table('Notes')->select('content')->cursor() as $note) {
                if ($note->content !== null && ! is_string($note->content)) {
                    throw new MediaException('Invalid note content.');
                }
                $consume($note->content ?? '');
                $count++;
            }

            return $count;
        });
    }
}
