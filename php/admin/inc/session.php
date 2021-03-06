<?php

/**
 * class Session
 * Maintains user authentication and authorization.
 * Requires 'sessions' and 'users' MySQL tables.
 */

define("SESSIONTIMEOUT", 20); // minutes
define("IDSTRLEN", 32);
define("NEWPSWLEN", 10);

define (TBL_NAME_USERS, DB_TABLES_PREFIX.'users');
define (TBL_NAME_SESSIONS, DB_TABLES_PREFIX.'sessions');

class Session
{
    var $sId;
    var $userId;
    var $userName;
    var $access;

    function Session()
    /**
     * Initializes current user session, sets userId, userName, userAccess
     */
    {
        $this->usersTable = TBL_NAME_USERS;
        $this->sessionsTable = TBL_NAME_SESSIONS;
        $this->sId = $this->GetSessionID();
        
        if ($this->sId)
        {
            $res = my_mysql_query("SELECT {$this->sessionsTable}.id_user, {$this->usersTable}.name, {$this->usersTable}.access FROM {$this->sessionsTable}, {$this->usersTable} WHERE {$this->sessionsTable}.id = {$this->sId} && {$this->usersTable}.id = {$this->sessionsTable}.id_user LIMIT 1;");
            if (!res || !mysql_num_rows($res)) return;    
            $d = mysql_fetch_assoc($res);   
            $this->userId = $d["id_user"];
            $this->userName = $d["name"];
            $this->access = $d["access"];
        }

    }

    function NewSessionIdstr()
    // new session id string (random letters)
    {
        return RandomString(IDSTRLEN);
    }
    
    function NewPassword()
    // new user password
    {
        return RandomString(NEWPSWLEN);
    }
    
    function SetSessionCookie($idstr)
    // wrapper for setcookie
    {
        setcookie("idstr", $idstr, time() + (SESSIONTIMEOUT*60));
    }
    
    function AssignUser2Session($idsession, $iduser)
    {
        $iduser = (int) $iduser;
        $idsession = (int) $idsession;
        return my_mysql_query("UPDATE {$this->sessionsTable} SET id_user = {$iduser} WHERE id = {$idsession} LIMIT 1;");
    }
    
    function GetUserIp()
    {
        $allIps = $_SERVER['HTTP_X_FORWARDED_FOR'];
        $allIpsArray = explode(',', $allIps);
        $firstIp = $allIpsArray[0];
        return $firstIp;
    }
    
    function GetSessionID()
    /**
     * Initializes current user Session and returns sessionID
     */
    {
        global $foundOrCreated;
        $table = $this->sessionsTable;
        $p_ip = $this->GetUserIp();
        $p_host = gethostbyaddr($p_ip);
        $p_browser = $_SERVER["HTTP_USER_AGENT"];
        $p_idstr = $_COOKIE["idstr"];
        if (strlen($p_idstr) != IDSTRLEN) 
            $p_idstr = $this->NewSessionIdstr();
        
        my_mysql_query("DELETE FROM {$table} WHERE lasthit < (now() - interval ".SESSIONTIMEOUT." MINUTE);");
        
        $row = array("ip" => $p_ip, /*"host" => $p_host,*/ "browser" => $p_browser, "idstr" => $p_idstr);
        
        $result = GetOrCreate($table, $row);
        
        if ($foundOrCreated)
        {
            $newSesIdstr = $this->NewSessionIdstr();
            $this->SetSessionCookie($newSesIdstr);
            my_mysql_query("UPDATE {$table} SET idstr = '{$newSesIdstr}' WHERE id = {$result} LIMIT 1;");
        }
        else
            $this->SetSessionHit($result);
        
        return $result;
    }
    
    function SetSessionHit($id)
    {
        $id = (int) $id;
        if (!($id > 0)) return False;
        
        $res = my_mysql_query("SELECT idstr FROM {$this->sessionsTable} WHERE id = {$id} LIMIT 1;");
        if ($res && mysql_num_rows($res))
            $this->SetSessionCookie(mysql_result($res, 0));
            
        return my_mysql_query("UPDATE {$this->sessionsTable} SET lasthit = Null WHERE id = {$id} LIMIT 1;");
    }
    
    function Login($name, $psw)
    {   // process informations from log-in form
        // strings are already mysql-safe-escaped, password is already md5 hashed - see index.php
        
        $sessid = $this->sId;
        $this->SetSessionHit($sessid);
        
        $res = my_mysql_query("SELECT id, access FROM {$this->usersTable} WHERE login = '{$name}' && password = '{$psw}' LIMIT 1;");
        if (!$res || !mysql_num_rows($res)) return False;
        $res = mysql_fetch_assoc($res);
        
        if ($res["access"])
            $this->AssignUser2Session($sessid, $res["id"]);
        
        RefreshPage("index.php");
        return True;
    }
    
    function AddUser ($login, $name, $access)
    // form data proccessing
    {
        $access = (int) $access;
        $name = trim($name);
        $login = trim($login);
        $psw = $this->NewPassword();
        $password = md5($psw);
        
        $res = my_mysql_query("INSERT INTO {$this->usersTable} (login, name, access, password) VALUES ('$login', '$name', '$access', '$password');");
        if (!$res)
            echo "User hasn't been added.";
        else
            echo("User $name added with access level $access. Password: '$psw'.");
    }
    
    function RemoveUser ($login)
    // data access wrapper
    {
        $login = trim($login);
        
        $res = my_mysql_query("DELETE FROM {$this->usersTable} WHERE login = '$login' && id != {$this->userId} LIMIT 1;");
        if (!res) 
        	echo "User hasn't been removed.";
        else
            echo "User has been removed.";
    }
    
    function ListUsers()
    // prints users list
    {
        echo '<h2>Users list</h2>'; // this line was outside of this function, forget why, now it seems ok here
        $res = my_mysql_query("SELECT login, name, access FROM {$this->usersTable};");
        if (!res || !mysql_num_rows($res)) return;
        
        echo("<ul>");
        while ($r = mysql_fetch_assoc($res))
        {
            echo "<li>[{$r['login']}] {$r['name']} ({$r['access']})</li>";
        }
        echo("</ul>");
    }
    
    function EditUser($login, $newpsw1, $newpsw2, $access)
    // data access wrapper
    {
        $res = my_mysql_query("SELECT id FROM {$this->usersTable} WHERE login = '$login' LIMIT 1;");
        if (!res || !mysql_num_rows($res))
        {
            echo 'User not found.';
            return;
        }
        $uId = mysql_result($res, 0);
        
        if ($uId == $this->userId)
        {
            echo "Can't change user profile for yourself by this tool.";
            return;
        }
        
        if (strlen($newpsw1)) {
            $this->SetNewPassword($uId, $newpsw1, $newpsw2);
        }
        
        if (is_numeric($access))
        {
            $res = my_mysql_query("UPDATE {$this->usersTable} SET access = $access WHERE id = $uId LIMIT 1;");
            if (!$res)
                echo "Error. Access level hasn't been changed.";
            else
                echo "Access level has been changed.";
        }
    }
    
    function ChangePassword($old, $new1, $new2)
    // data access wrapper
    {
        $oldpsw = md5($old);
        
        $res = my_mysql_query("SELECT Count(id) FROM {$this->usersTable} WHERE id = {$this->userId} && password = '$oldpsw' LIMIT 1;");
        if (!$res || !mysql_num_rows($res) || (mysql_result($res, 0) < 1))
        {
            echo "Wrong password.";
            return;
        }
        
        $this->SetNewPassword($this->userId, $new1, $new2);
    }
    
    function SetNewPassword($userId, $new1, $new2)
    // form data proccessing
    {
        if ($new1 != $new2) 
        {
            echo "Error. Control password doesn't match the original password.";
            return;
        }
        if (strlen($new1) < 6) 
        {
            echo "Password can't be shorter then 6 chars";
            return;
        }
        
        $new = md5($new1);
        
        $res = my_mysql_query("UPDATE {$this->usersTable} SET password = '$new' WHERE id = {$userId} LIMIT 1;");
        if (!res)
            echo "Password hasn't been changed due to an error.";
        else
            echo "Password has been changed.";
    }
    
    function Logout()
    // destroys session-user assignment, reset accesslevel, userid, username
    {
        $this->AssignUser2Session($this->sId, 0); // remove user from the session
        $this->access = 0;
        $this->userId = 0;
        $this->userName = '';
        RefreshPage("index.php");
    }

}

?>
