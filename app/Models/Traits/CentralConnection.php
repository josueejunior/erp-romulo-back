<?php

namespace App\Models\Traits;

trait CentralConnection
{
    public function getConnectionName()
    {
        return config('tenancy.database.central_connection', 'pgsql');
    }
}
