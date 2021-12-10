<?php

namespace Leit040\AspasiaBot\Controllers;

use PDO;
use PDOException;

class DbRepository
{
    private PDO $dbh;

    public function __construct()
    {
        try {
            $this->dbh = new PDO("mysql:host=" . getenv("DB_HOST") . "; dbname=" . getenv("DB_NAME"), getenv("DB_USER"), getenv("DB_PASSWORD"));
        } catch (PDOException $e) {
            print "Error!: " . $e->getMessage();
            die();
        }

    }

    public function getMastersList()
    {
        $sql = 'SELECT * from users where role=2';
        $stmt = $this->dbh->query($sql, PDO::FETCH_ASSOC);
        return $stmt->fetchAll();
    }

    public function getNameByID($id){
        $sql = "SELECT name, lastname from users where user_id = $id";
        $stmt = $this->dbh->query($sql, PDO::FETCH_ASSOC);
        $result = $stmt->fetch(PDO::FETCH_LAZY);
        return  $result->name." ".$result->lastname;
    }

    public function ifMasterInDialog($id)
    {
        $sql = "SELECT chat_id from dialogs where master_id = $id AND status = true";
        $stmt = $this->dbh->query($sql);
        return $stmt->fetchColumn();
    }

    public function ifClientInDialog($id)
    {
        $sql = "SELECT master_id from dialogs where chat_id = $id AND status = true";
        $stmt = $this->dbh->query($sql);
        return $stmt->fetchColumn();
    }
    public function isMasterId($id){
        $sql = "SELECT id from users where user_id = $id AND role=3";
        $stmt = $this->dbh->query($sql);
        return $stmt->fetchColumn();
    }


    public function saveDialog($chat_id, $master_id)
    {
        $query = "INSERT INTO dialogs (`chat_id`,`master_id`,'status','create_at') values (:chat_id,:master_id,:status,:create_at)";
        $stmt = $this->dbh->prepare($query);
        $stmt->execute(['chat_id' => $chat_id, 'master_id' => $master_id, 'status' => true,'create_at' =>time()]);

    }

    public function finishDialog($master_id)
    {
        $sql = "UPDATE   dialogs  set status = false where master_id=$master_id";
        $stmt = $this->dbh->query($sql);

    }

    public function saveUser($message, $role = 2)
    {
        $user_id = $message['from']['id'];
        $name = $message['from']['first_name'];
        $lastname = $message['from']['last_name'];
        $username = $message['from']['username'];
        $sql = "SELECT * from users where user_id =$user_id AND where role = $role";
        $stmt = $this->dbh->query($sql, PDO::FETCH_ASSOC);

        if (count($stmt->fetchAll()) == 0) {
            $query = "INSERT INTO users (`user_id`,`username`,`name`,`lastname`,`role`) values (:user_id,:username,:name,:lastname,:role)";
            $stmt = $this->dbh->prepare($query);
            $stmt->execute(['user_id' => $user_id, 'username' => $username, 'name' => $name, 'lastname' => $lastname, 'role' => $role]);

        }
    }

}