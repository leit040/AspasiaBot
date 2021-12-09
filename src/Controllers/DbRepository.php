<?php

namespace Leit040\AspasiaBot\Controllers;

use PDO;

class DbRepository
{
    private PDO $dbh;

    public function __construct()
    {
        $this->dbh = new PDO("mysql:host=" . getenv("DB_HOST") . "; dbname=" . getenv("DB_NAME"), getenv("DB_USER"), getenv("DB_PASSWORD"));

    }

    public function getMastersList()
    {
        $sql = 'SELECT * from users where role=2';
        $res = $this->dbh->guery($sql, PDO::FETCH_ASSOC);
        return $res;
    }

    public function saveDialog($chat_id, $client_id, $master_id)
    {

        $query = "INSERT INTO dialogs (`chat_id`,`client_id`,`master_id`) values (:chat_id,:client_id,:master_id)";
        $stmt = $this->dbh->prepare($query);
        $stmt->execute(['chat_id' => $chat_id, 'client_id' => $client_id, 'master_id' => $master_id]);

    }
    public function saveClient(){


    }


}