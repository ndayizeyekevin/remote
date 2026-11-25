<?php

function withDatabaseTransaction(PDO $db, callable $operation)
{
    $db->beginTransaction();

    try {
        $result = $operation();
        $db->commit();

        return $result;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $e;
    }
}
