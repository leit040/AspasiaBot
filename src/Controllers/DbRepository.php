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
        $sql = 'SELECT * from users where role=3';
        $stmt = $this->dbh->query($sql, PDO::FETCH_ASSOC);
        return $stmt->fetchAll();
    }

    public function getMasterByChatId($chat_id)
    {
        $sql = "SELECT users.name, users.lastname, users.user_id from dialogs   JOIN users ON dialogs.master_id = users.user_id where dialogs.master_id = $chat_id AND status = 'active'";
        $stmt = $this->dbh->query($sql);
        $result = $stmt->fetch(PDO::FETCH_LAZY);
        return $result->name . " " . $result->lastname;


    }

    public function getNameByID($id)
    {
        $sql = "SELECT name, lastname, nameFrom from users where user_id = $id";
        $stmt = $this->dbh->query($sql, PDO::FETCH_ASSOC);
        $result = $stmt->fetch(PDO::FETCH_LAZY);
        return $result->nameFrom ?? $result->name . " " . $result->lastname;
    }


    public function ifMasterInDialog($id)
    {
        $sql = "SELECT chat_id from dialogs where master_id = $id AND status = 'active'";
        $stmt = $this->dbh->query($sql);
        return $stmt->fetchColumn();
    }

    public function ifClientInActiveDialog($id)
    {
        $sql = "SELECT master_id from dialogs where chat_id = $id AND status = 'active'";
        $stmt = $this->dbh->query($sql);
        return $stmt->fetchColumn();
    }

    public function ifClientInPendingDialog($id)
    {
        $sql = "SELECT id, master_id from dialogs where chat_id = $id AND status = 'pending'";
        $stmt = $this->dbh->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function isMasterId($id)
    {
        $sql = "SELECT id from users where user_id = $id AND role=3";
        $stmt = $this->dbh->query($sql);
        return $stmt->fetchColumn();
    }


    public function saveDialog($chat_id, $master_id, $message)
    {
        $sql = "SELECT id from dialogs where master_id=$master_id AND status = 'active'";
        $stmt = $this->dbh->query($sql);
        $query = "INSERT INTO dialogs (`chat_id`,`master_id`,`status`) values (:chat_id,:master_id,:status)";
        $stmt1 = $this->dbh->prepare($query);
        $result = $stmt->fetchAll();
        if (count($result)) {
            $stmt1->execute(['chat_id' => $chat_id, 'master_id' => $master_id, 'status' => 'pending']);
            return 'pending';
        } else {
            $stmt1->execute(['chat_id' => $chat_id, 'master_id' => $master_id, 'status' => 'active']);
            return 'active';
        }
    }

    public function savePendingMessage($message, $dialogId, $masterId)
    {
        $query = "INSERT INTO pending (`message`,`dialogId`,`masterId`) values (:message,:dialogId,:masterId)";
        $stmt = $this->dbh->prepare($query);
        $stmt->execute(['message' => $message, 'dialogId' => $dialogId, 'masterId' => $masterId]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return;
    }

    public function finishDialog($master_id)
    {
        $sql = "DELETE  from dialogs where master_id = $master_id AND status = 'active'";
        $stmt = $this->dbh->query($sql);
        $sql = "SELECT id from dialogs where  master_id=$master_id AND status = 'pending' LIMIT 1";
        $stmt = $this->dbh->query($sql);
        $id = $stmt->fetchColumn();
        if ($id) {
            $sql = "UPDATE   dialogs  set status = 'active' where id = $id";
            $stmt = $this->dbh->query($sql);
            $sql = "SELECT * from pending where dialogId = $id";
            $stmt = $this->dbh->query($sql);
            return $stmt->fetchAll();
        }
        return false;
    }

    public function deletePendingMessage($id)
    {
        $sql = "DELETE  from pending where id = $id";
        $stmt = $this->dbh->query($sql);
    }

    public function saveMaster($message)
    {
        $user_id = $message['from']['id'];
        $name = $message['from']['first_name'] ?? '';
        $lastname = $message['from']['last_name'] ?? '';
        $username = $message['from']['username'] ?? '';
        $sql = "SELECT id from users where user_id = $user_id";
        $stmt = $this->dbh->query($sql, PDO::FETCH_ASSOC);
        $rows = $stmt->fetchColumn();
        $masterName = substr($message['text'],strlen(getenv('MASTER_CODE_STRING'))+1);
        file_put_contents('runLog.txt','MasterName= '.$masterName.'count = '.$rows.PHP_EOL,FILE_APPEND);
        if (!$rows) {
            file_put_contents('runLog.txt','!count = '.PHP_EOL,FILE_APPEND);
            $query = "INSERT INTO users (`user_id`,`username`,`name`,`lastname`,`nameFrom`,`role`) values (:user_id,:username,:name,:lastname,:nameFrom,:role)";

            $stmt = $this->dbh->prepare($query);
            file_put_contents('runLog.txt','Prepare:done'.PHP_EOL,FILE_APPEND);
            $testResult = $stmt->execute(['user_id' => $user_id, 'username' => $username, 'name' => $name, 'lastname' => $lastname, 'nameFrom' => $masterName,'role' => 3]);
            file_put_contents('runLog.txt','Add first time, result = '.$testResult.PHP_EOL,FILE_APPEND);
            return;
        }
        file_put_contents('runLog.txt','Another way'.PHP_EOL,FILE_APPEND);
        $sql = "UPDATE users set role = 3, nameFrom = $masterName where user_id = $user_id";
        $stmt = $this->dbh->query($sql);
    }


    public function saveUser($message)
    {
        $user_id = $message['from']['id'];
        $name = $message['from']['first_name'] ?? '';
        $lastname = $message['from']['last_name'] ?? '';
        $username = $message['from']['username'] ?? '';
        $sql = "SELECT * from users where user_id =$user_id";
        $stmt = $this->dbh->query($sql, PDO::FETCH_ASSOC);

        $rows = $stmt->fetchAll();
        if (!count($rows)) {
            $query = "INSERT INTO users (`user_id`,`username`,`name`,`lastname`,`role`) values (:user_id,:username,:name,:lastname,:role)";
            $stmt = $this->dbh->prepare($query);
            $stmt->execute(['user_id' => $user_id, 'username' => $username, 'name' => $name, 'lastname' => $lastname, 'role' => 2]);

        }
        return;
    }

}