<?php

namespace App\Controllers;

ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(-1);

class UserController extends Controller
{
    private $email;
    private $password;
    private $firstName;
    private $lastName;

    function __construct($db, $twig, $mail, $rlib)
    {
        parent::__construct($db, $twig, $mail, $rlib);
    }

    function loginUser($params)
    {
        $this->email = trim($params['email']);
        $this->password = trim($params['password']);
        $this->password = trim($params['password']);

        try {
            //$hash_password = password_hash($this->password, PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("SELECT * FROM project.users WHERE email=:userEmail");;// AND password=:hash_password");
            $stmt->bindParam("userEmail", $this->email, \PDO::PARAM_STR);
            //$stmt->bindParam("hash_password", $hash_password, \PDO::PARAM_STR);
            $stmt->execute();
            $count = $stmt->rowCount();
            $data = $stmt->fetch(\PDO::FETCH_OBJ);
            //$this->dbconn = null;

            if ($count) { //if user found
                if (password_verify($this->password, $data->password)) { //if password is correct
                    unset($data->password);
                    $_SESSION['user'] = $data; // Storing user session value
                    return true;
                } else { //if password incorrect
                    return false;
                }
            } else { //if user not found
                return false;
            }
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

    function registerUser($params)
    {
        $this->email = trim($params['email']);
        $this->password = trim($params['password']);
        $this->firstName = trim($params['firstName']);
        $this->lastName = trim($params['lastName']);

        try {
            $stmt = $this->db->prepare("SELECT COUNT(email) AS num FROM project.users WHERE email = :userEmail");
            $stmt->bindParam("userEmail", $this->email, \PDO::PARAM_STR);

            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row['num'] > 0) {
                return false; //user already exists
            } else {

                $hash_password = password_hash($this->password, PASSWORD_BCRYPT);

                $stmt = $this->db->prepare("INSERT INTO project.users (first_name, last_name, email, password) VALUES (:first_name, :last_name, :email, :password)");
                $stmt->bindParam("first_name", $this->firstName, \PDO::PARAM_STR);
                $stmt->bindParam("last_name", $this->lastName, \PDO::PARAM_STR);
                $stmt->bindParam("email", $this->email, \PDO::PARAM_STR);
                $stmt->bindParam("password", $hash_password, \PDO::PARAM_STR);

                $result = $stmt->execute();

                if ($result) {
                    $this->mail->send('emails/registered.twig', ['firstName' => $this->firstName, 'lastName' => $this->lastName],
                        function ($message) {
                            $message->to($this->email);
                            $message->subject('Registered successfully!');
                        });
                    return true; //registered successfully
                }
            }

        } catch (\PDOException $e) {
            return ($e->getMessage());
        }
    }

    function verifyUser($formID)
    {
        try { //first check if form is private or not, if not, allow it to be viewed
            $stmt = $this->db->prepare("SELECT private FROM project.forms WHERE ID = :formID");
            $stmt->bindParam("formID", $formID, \PDO::PARAM_INT);

            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row['private'] == 0) {
                return true;
            }

        } catch (\PDOException $e) {
            die($e->getMessage());
        }

        try { //if its private, check the user logged it should be able to access it
            $stmt = $this->db->prepare("SELECT COUNT(user_id) AS num FROM project.permissions WHERE form_ID = :formID AND user_ID = :userID");
            $stmt->bindParam("formID", $formID, \PDO::PARAM_INT);
            $stmt->bindParam("userID", $_SESSION['user']->id, \PDO::PARAM_INT);

            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row['num'] > 0) {
                return true;
            } else {
                return false;
            }
        } catch (\PDOException $e) {
            die($e->getMessage());
        }
    }

    function startResetPassword($params)
    {
        $this->email = trim($params['email']);

        try {
            $stmt = $this->db->prepare("SELECT COUNT(email) AS num FROM project.users WHERE email = :userEmail");
            $stmt->bindParam("userEmail", $this->email, \PDO::PARAM_STR);

            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row['num'] > 0) {

                $identifier = $this->rlib->generateString(128);
                $hashedIdentifier = password_hash($identifier, PASSWORD_BCRYPT);

                $stmt = $this->db->prepare("UPDATE project.users SET recover_pwd_hash = :recoverHash WHERE email = :userEmail");
                $stmt->bindParam("recoverHash", $hashedIdentifier, \PDO::PARAM_STR);
                $stmt->bindParam("userEmail", $this->email, \PDO::PARAM_STR);
                $stmt->execute();

                $emailSuccess = $this->mail->send('emails/passwordreset.twig', ['identifier' => $identifier, 'email' => $this->email],
                    function ($message) {
                        $message->to($this->email);
                        $message->subject('Finish resetting your password');
                    });

                if (is_bool($emailSuccess) && ($emailSuccess)) {
                    return true;
                } else {
                    return $emailSuccess;
                }
            } else {
                return false; //user doesn't exist
            }
        } catch (\PDOException $e) {
            return ($e->getMessage());
        }
    }

    function finishResetPassword($params)
    {
        $email = trim($params['email']);
        $identifier = trim($params['identifier']);
//        $hashedIdentifier
//        (password_verify($this->password, $data->password)

        $stmt = $this->db->prepare("SELECT first_name FROM project.users WHERE email = :userEmail");
        $stmt->bindParam("userEmail", $email, \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_OBJ);

        print_r($row);

    }


    function changePassword($params)
    {
        $currentPassword = trim($params['currentPassword']);
        $newPassword = trim($params['newPassword']);
        $email = trim($params['userEmail']);

        $stmt = $this->db->prepare("SELECT email, password FROM project.users WHERE email = :userEmail LIMIT 1");
        $stmt->bindParam("userEmail", $email, \PDO::PARAM_STR);

        $stmt->execute();
        $user = $stmt->fetch(\PDO::FETCH_OBJ);

        if (($user) && password_verify($currentPassword, $user->password)) {

            $hash_password = password_hash($newPassword, PASSWORD_BCRYPT);

            $stmtPwd = $this->db->prepare("UPDATE project.users SET password = :password WHERE email = :email");
            $stmtPwd->bindParam("password", $hash_password, \PDO::PARAM_STR);
            $stmtPwd->bindParam("email", $email, \PDO::PARAM_STR);

            $result = $stmtPwd->execute();

            if ($result) {
                //die('done');
                return true; //changed successfully
            }
        } else {
            return false;
            //die('na');
        }

    }

}