<?php

require_once('db.php');
require_once('../model/Response.php');

try {
    $writeDB = DB::connectWriteDB();
    
} catch (PDOException $ex) {
    error_log("Connection: ".$ex, 0);
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSucess(false);
    $response->addMessage("Database connection error");
    $response->send();
    exit();
}

if (array_key_exists("sessionid", $_GET)) {
    $sessionid = $_GET['sessionid'];
    if ($sessionid === '' || !is_numeric($sessionid)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSucess(false);
        ($sessionid === '' ? $response->addMessage("Sessionid cannot be blank") : false);
        (!is_numeric($sessionid) ? $response->addMessage("Sessionid must be a numeric") : false);
        $response->send();
        exit;
    }

    if(!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSucess(false);
        (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage("access token is missing from the header") : false);
        (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage("Access token cannot be blak") : false);
        $response->send();
        exit;
    }
    $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        try {
            $query = $writeDB->prepare('delete from tblsessions where id = :sessionid and accesstoken = :accesstoken');
            $query->bindParam(':sessionid', $sessionid, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSucess(false);
                $response->addMessage("Failed to log out of this sessions using access token provided");
                $response->send();
                exit;
            }
        }

        catch (PDOException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSucess(false);
            $response->addMessage("There was an error connectint database");
            $response->send();
            exit;
        }

        $returnData = array();
        $returnData['session_id'] = intval($sessionid);

        $response = new Response();
        $response->setHttpStatusCode(200);
        $response->setSucess(true);
        $response->addMessage("Loged out");
        $response->setData($returnData);
        $response->send();
        exit;
    }
     
    elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH'){

        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSucess(false);
            $response->addMessage("Content type header not set to JSON");
            $response->send();
            exit;
        }

        $rawPostData = file_get_contents('php://input');

        if (!$jsonData = json_decode($rawPostData)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSucess(false);
            $response->addMessage("Request body is not valid JSON");
            $response->send();
            exit;
        }

        if (!isset($jsonData->refresh_token) || strlen($jsonData->refresh_token) < 1) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSucess(false);
            (!isset($jsonData->refresh_token) ? $response->addMessage("Refresh token isnt set") : false);
            (strlen($jsonData->refresh_token) < 1 ? $response->addMessage("Refresh token cannot be blank") : false);
            $response->send();
            exit;
        }
        try {

            $refreshtoken = $jsonData->refresh_token;
            
            $query = $writeDB->prepare('select tblsessions.id as sessionid, tblsessions.userid as userid, accesstoken, refreshtoken, useractive, loginattempts, accesstokenexpiry, refreshtokenexpiry from tblsessions, tblusers where tblusers.id = tblsessions.userid and tblsessions.id = :sessionid and tblsessions.accesstoken = :accesstoken and tblsessions.refreshtoken = :refreshtoken');
            $query->bindParam(':sessionid', $sessionid, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSucess(false);
                $response->addMessage("Access token or refresh token is invalid for session id");
                $response->send();
                exit;
            }

            $row = $query->fetch(PDO::FETCH_ASSOC);
            $returned_sessionid = $row['sessionid'];
            $returned_userid = $row['userid'];
            $returned_accesstoken = $row['accesstoken'];
            $returned_refreshtoken = $row['refreshtoken'];
            $returned_useractive = $row['useractive'];
            $returned_loginattempts = $row['loginattempts'];
            $returned_accesstokenexpiry = $row['accesstokenexpiry'];
            $returned_refreshtokenexpiry = $row['refreshtokenexpiry'];

            if($returned_useractive !== 'Y'){
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSucess(false);
                $response->addMessage("User account is not active");
                $response->send();
                exit;
            }

            if ($returned_loginattempts >= 3) {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSucess(false);
                $response->addMessage("User is locked out");
                $response->send();
                exit;
            }

        } catch (PDOException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSucess(false);
            $response->addMessage("There was an issue refreshing the token");
            $response->send();
            exit;
        }


        if (strtotime($returned_refreshtokenexpiry) < time()) {
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSucess(false);
            $response->addMessage("Refresh token has expired - pls log in again");
            $response->send();
            exit;
        }

        $accesstoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());
        $refreshtoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());

        $access_token_expiry_seconds = 1200;
        $refresh_token_expiry_seconds = 1209600;

        $query = $writeDB->prepare('update tblsessions set accesstoken = :accesstoken, accesstokenexpiry = date_add(NOW(), INTERVAL :accesstokenexpiryseconds SECOND), refreshtoken = :refreshtoken, refreshtokenexpiry = date_add(NOW(), INTERVAL :refreshtokenexpiryseconds SECOND) WHERE id = :sessionid and userid = :userid and accesstoken = :returnedaccesstoken and refreshtoken = :returnedrefreshtoken');
        $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
        $query->bindParam(':accesstokenexpiryseconds', $access_token_expiry_seconds, PDO::PARAM_STR);
        $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
        $query->bindParam(':refreshtokenexpiryseconds', $refresh_token_expiry_seconds, PDO::PARAM_STR);
        $query->bindParam(':sessionid', $returned_sessionid, PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->bindParam(':returnedaccesstoken', $returned_accesstoken, PDO::PARAM_STR);
        $query->bindParam(':returnedrefreshtoken', $returned_refreshtoken, PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0){
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSucess(false);
            $response->addMessage("Accesstoken coul not be refreshd = please lo in again");
            $response->send();
            exit;
        }

        $returnData = array ();
        $returnData['session_id'] = $returned_sessionid;
        $returnData['access_token'] = $accesstoken;
        $returnData['access_token_expiry_seconds'] = $access_token_expiry_seconds;
        $returnData['refresh_token'] = $refreshtoken;
        $returnData['refresh_token_expiry_seconds'] = $refresh_token_expiry_seconds;

        $response = new Response();
        $response->setHttpStatusCode(200);
        $response->setSucess(true);
        $response->addMessage("Token refreshed");
        $response->setData($returnData);
        $response->send();
        exit;

        } 
        else {
            $response = new Response();
            $response->setHttpStatusCode(405);
            $response->setSucess(false);
            $response->addMessage("Request method not alowed"); 
            $response->send();
            exit;
        }
    }
    elseif (empty($_GET)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSucess(false);
        $response->addMessage("Requst method not allowed");
        $response->send();
        exit();
    }
    
    sleep(1);

    if ($_SERVER['CONTENT_TYPE'] !== 'application/json'){
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSucess(false);
        $response->addMessage("Content type header not set to JSON");
        $response->send();
        exit();
    }

    $rawPostData = file_get_contents('php://input');
   
    if (!$jsonData = json_decode($rawPostData)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSucess(false);
        $response->addMessage("Request body is not valid JSON");
        $response->send();
        exit();
    }
    
    if(!isset($jsonData->username) || !isset($jsonData->password)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSucess(false);
        if (!isset($jsonData->username)){$response->addMessage("Username cannot be blank");}
        if (!isset($jsonData->password)){$response->addMessage("Password cannot be blank");}
        $response->send();
        exit();
    }

    if (strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSucess(false);
        if (strlen($jsonData->username) < 1){$response->addMessage("Username cannot be blank");}
        if (strlen($jsonData->username) > 255){$response->addMessage("Username cannot be longer than 255 characters");}
        if (strlen($jsonData->password) < 1 ){$response->addMessage("Password cannot be blank");}
        if (strlen($jsonData->password) > 255){$response->addMessage("Password cannot be longer than 255 characters");}
        $response->send();
        exit();
    }

    try {
        $username = $jsonData->username;
        $password = $jsonData->password;

        $query = $writeDB->prepare('select id, fullname, username, password, useractive, loginattempts from tblusers where username = :username');
        $query->bindParam(':username', $username, PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSucess(false);
            $response->addMessage("User or password is incorrect"); //Generická zpráve ve které nepíšeme specifickou chybu kuli hackerum
            $response->send();
            exit();
        }

        $row = $query->fetch(PDO::FETCH_ASSOC);

        $returned_id = $row['id'];
        $returned_fullname = $row['fullname'];
        $returned_username = $row['username'];
        $returned_password = $row['password'];
        $returned_useractive = $row['useractive'];
        $returned_loginattempts = $row['loginattempts'];


        if ($returned_useractive !=='Y'){
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSucess(false);
            $response->addMessage("User account not active");
            $response->send();
            exit();
        }
        
        if ($returned_loginattempts >= 3){
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSucess(false);
            $response->addMessage("User account is currently locked out");
            $response->send();
            exit();
        }

        if (!password_verify($password,$returned_password)) {
            $query = $writeDB->prepare('update tblusers set loginattempts = loginattempts+1 where id = :id');
            $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
            $query->execute();

            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSucess(false);
            $response->addMessage("Password not match");
            $response->send();
            exit();
        }
        

        $accesstoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());
        $refreshtoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());

        $access_token_expiry_seconds = 1200;
        $refresh_token_expiry_seconds = 1209600;
  
    } catch (PDOException $ex) {
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSucess(false);
        $response->addMessage("There was an issue logging in");
        $response->send();
        exit();
    }

    try {
        $writeDB->beginTransaction();

        $query = $writeDB->prepare('update tblusers set loginattempts = 0 where id = :id');
        $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
        $query->execute();

        $query = $writeDB->prepare('insert into tblsessions (userid, accesstoken , accesstokenexpiry, refreshtoken, refreshtokenexpiry) values (:userid , :accesstoken, date_add(NOW(), INTERVAL :accesstokenexpiry SECOND), :refreshtoken, date_add(NOW(), INTERVAL :refreshtokenexpiry SECOND))');
        $query->bindParam(':userid', $returned_id, PDO::PARAM_INT);
        $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
        $query->bindParam(':accesstokenexpiry', $access_token_expiry_seconds, PDO::PARAM_INT);
        $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
        $query->bindParam(':refreshtokenexpiry', $refresh_token_expiry_seconds, PDO::PARAM_INT);
        $query->execute();

        $lastsessionid = $writeDB->lastInsertId();

        $writeDB->commit();

        $returnData = array();
        $returnData['session_id'] = intval($lastsessionid);
        $returnData['access_token'] = $accesstoken;
        $returnData['access_token_expiry_in'] = $access_token_expiry_seconds;
        $returnData['refresh_token'] = $refreshtoken;
        $returnData['refresh_token_expiry_in'] = $refresh_token_expiry_seconds;
        $response = new Response();
        $response->setHttpStatusCode(201);
        $response->setSucess(true);
        $response->setData($returnData);
        $response->send();
        exit;


            
    } catch (PDOException $ex) {
        $writeDB->rollBack();
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSucess(false);
        $response->addMessage("There was an issue logging in - please try again");
        $response->send();
        exit();
    }



}
else {
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSucess(false);
    $response->addMessage("End point not found");
    $response->send();
    exit();
}

?>