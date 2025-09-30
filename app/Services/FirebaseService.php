<?php
namespace App\Services;

use Kreait\Firebase\Factory;

class FirebaseService
{
    public $database;

    public function __construct()
    {
        $factory = (new Factory)
            ->withServiceAccount(config('firebase.credentials'))
            ->withDatabaseUri(config('firebase.database_url'));

        $this->database = $factory->createDatabase();
    }
}
