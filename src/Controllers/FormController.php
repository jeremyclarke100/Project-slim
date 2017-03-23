<?php

namespace App\Controllers;

class FormController
{
    private $dbconn;

    function __construct($db)
    {
        $this->dbconn = $db;
    }

    function returnUsersPrivateFormDetails($userID)
    {
        $sql = 'SELECT ID, ID, name, title, description, developer_mode, private FROM project.forms 
                WHERE private = 1 
                AND ID IN (SELECT form_id from project.permissions where user_id = :userID)';

        $stmt = $this->dbconn->prepare($sql);
        $stmt->bindParam("userID", $userID, \PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);

        return $results;
    }

//    function returnPublicFormDetails()
//    {
//        $sql = 'SELECT ID, ID, name, title, description, developer_mode, private FROM project.forms WHERE private = 0';
//
//        $stmt = $this->dbconn->prepare($sql);
//        $stmt->execute();
//
//        $results = $stmt->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
//
//        return $results;
//    }

    function returnAllFormDetails()
    {
        $sql = 'SELECT ID, ID, name, title, description, developer_mode, private FROM project.forms';

        $stmt = $this->dbconn->prepare($sql);
        $stmt->execute();

        $results = $stmt->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);

        return $results;
    }


//    function checkIfFormPublic($formID)
//    {
//        try { //first check if form is private or not, if not, allow it to be viewed
//            $stmt = $this->dbconn->prepare("SELECT private FROM project.forms WHERE ID = :formID");
//            $stmt->bindParam("formID", $formID, \PDO::PARAM_INT);
//
//            $stmt->execute();
//            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
//
//            if ($row['private'] == 0) {
//                return true;
//            } else {
//                return false;
//            }
//
//        } catch (\PDOException $e) {
//            die($e->getMessage());
//        }
//    }



//    function returnAllFormDetailsPrivate($userID)
//    {
//        $sql = 'SELECT ID, ID, name, title, description, developer_mode, private FROM project.forms WHERE private = 1 AND ID IN (SELECT form_id from project.permissions where user_id = :userID)';
//        $stmt = $this->dbconn->prepare($sql);
//        $stmt->bindParam("userID", $userID, \PDO::PARAM_INT);
//
//        $stmt->execute();
//
//        $results = $stmt->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
//
//        return $results;
//    }

    function submitForm($params, $formID, $response)
    {
        $sql = "SELECT SQL_insert_execute_query FROM project.objects WHERE form_id = :formID AND type = 'button' LIMIT 1";
        $stmt = $this->dbconn->prepare($sql);
        $stmt->bindParam(':formID', $formID, \PDO::PARAM_INT);
        $stmt->execute();

        $actionSQL = $stmt->fetchColumn();

        if (substr(strtoupper($actionSQL), 0, 4) === 'INSE') {
            for ($i = 0; $i < sizeof($params); $i++) {
                $actionSQL = str_replace("@@" . array_keys($params)[$i], "?", $actionSQL);
            }

            try {
                $stmt = $this->dbconn->prepare($actionSQL);

                for ($i = 0; $i < sizeof($params); $i++) {
                    $stmt->bindParam($i + 1, array_values($params)[$i], \PDO::PARAM_STR, 1);
                }

                $stmt->execute();

            } catch (\PDOException $e) {
                return $e->getMessage();
            }

        } else {
            echo 'broke';
        }
    }
}