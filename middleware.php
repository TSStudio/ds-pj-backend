<?php
session_set_cookie_params(["samesite" => "None", "Secure" => true, "domain" => "tmysam.top"]);
session_start();
class sso
{
    private $mysql_host;
    private $mysql_user;
    private $mysql_password;
    private $mysql_db;
    function __construct($mysql_host, $mysql_user, $mysql_password, $mysql_db)
    {
        $this->mysql_host = $mysql_host;
        $this->mysql_user = $mysql_user;
        $this->mysql_password = $mysql_password;
        $this->mysql_db = $mysql_db;
    }
    public function get_email($uid)
    {
        $conn = new mysqli($this->mysql_host, $this->mysql_user, $this->mysql_password, $this->mysql_db);
        $sql = "SELECT email FROM users where uid='$uid' LIMIT 1";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['email'];
        } else {
            return false;
        }
    }
    public function test_permission($uid, $permission, $strict = false, $return_real_permission = false, $debugging = false)
    {
        //table users_permissions
        //uid int(11)
        //permission varchar(32)
        //value tinyint(4)
        //permissionId int(11)
        $conn = new mysqli($this->mysql_host, $this->mysql_user, $this->mysql_password, $this->mysql_db);
        //split $permission with '_'
        $permissions = explode("_", $permission);
        $debug = "";
        while (count($permissions)) {
            //rebuild $parent_permission
            $parent_permission = implode("_", $permissions);
            $parent_permission = mysqli_real_escape_string($conn, $parent_permission);
            $sql = "SELECT value FROM users_permissions where uid=$uid and permission='$parent_permission' LIMIT 1";
            $result = $conn->query($sql);
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if ($return_real_permission) {
                    return array("permission" => $parent_permission, "value" => $row['value']);
                }
                return $row['value'];
            } else {
                $debug .= "not found $parent_permission ";
                array_pop($permissions);
                if ($strict) {
                    break;
                }
            }
        }
        if ($debugging) {
            return $debug;
        }
        return false;
    }
    public function get_permissions($uid)
    {
        $conn = new mysqli($this->mysql_host, $this->mysql_user, $this->mysql_password, $this->mysql_db);
        $sql = "SELECT permission,value FROM users_permissions where uid=$uid";
        $result = $conn->query($sql);
        $permissions = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $permissions[$row['permission']] = $row['value'];
            }
        }
        return $permissions;
    }
    public function generate_keep_token($uid)
    {
        $conn = new mysqli($this->mysql_host, $this->mysql_user, $this->mysql_password, $this->mysql_db);
        // table tauth_keep
        // columns 	keepid	uid	time	sha256	
        // keepid: autoincrement
        // uid: uid
        // time: time()
        // sha256: sha256(keepid . (string)uid . (string)time)
        // keepid is autoincrement, so keep sha256 empty first
        $time = time();
        $sql = "INSERT INTO tauth_keep (uid, time) VALUES ($uid,$time)";
        $conn->query($sql);
        $keepid = $conn->insert_id;
        // sum sha256
        $sha256 = hash('SHA256', $keepid . $uid . $time);
        // update sha256
        $sql = "UPDATE tauth_keep SET sha256='$sha256' WHERE keepid=$keepid";
        $conn->query($sql);
        // delete rows where time is more than 30 days ago
        $sql = "DELETE FROM tauth_keep WHERE time<" . ($time - 2592000);
        $conn->query($sql);
        // close connection
        $conn->close();
        // set cookie
        setcookie("keep", $sha256, [
            'expires' => time() + 2592000,
            'path' => '/',
            'domain' => 'tmysam.top',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None',
        ]);
        return $sha256;
    }
    public function check_keep_token()
    {
        // get cookie
        if (!isset($_COOKIE['keep'])) {
            return false;
        }
        $sha256 = $_COOKIE['keep'];
        $conn = new mysqli($this->mysql_host, $this->mysql_user, $this->mysql_password, $this->mysql_db);
        // table tauth_keep
        // columns 	keepid	uid	time	sha256
        $result = $conn->query("SELECT uid,time,keepid FROM tauth_keep WHERE sha256='$sha256'");
        // check if exists
        if ($result->num_rows == 0) {
            $conn->close();
            return false;
        }
        // get uid and time
        $row = $result->fetch_assoc();
        $uid = $row['uid'];
        $time = $row['time'];
        $keepid = $row['keepid'];
        $sql = "DELETE FROM tauth_keep WHERE keepid=$keepid";
        $conn->query($sql);
        // if more than 30 days ago
        if (time() - $time > 2592000) {
            $conn->close();
            return false;
        }
        // generate a new keep token
        $this->generate_keep_token($uid);
        return $uid;
    }
    public function check_restricted($uid)
    {
        $conn = new mysqli($this->mysql_host, $this->mysql_user, $this->mysql_password, $this->mysql_db);
        $result = $conn->query("SELECT restricted FROM users WHERE uid=$uid");
        if ($result->num_rows == 0) {
            $conn->close();
            return -1;
        }
        $row = $result->fetch_assoc();
        $conn->close();
        return $row['restricted'];
    }
    public function destroy($uid, $mode = 0)
    { //0 for this token only, 1 for this user
        $conn = new mysqli($this->mysql_host, $this->mysql_user, $this->mysql_password, $this->mysql_db);
        if ($mode == 0) {
            if (isset($_COOKIE['keep'])) {
                $sha256 = $_COOKIE['keep'];
            } else {
                return false;
            }
            $sql = "DELETE FROM tauth_keep WHERE sha256=\"$sha256\"";
        } else {
            $sql = "DELETE FROM tauth_keep WHERE uid=$uid";
        }
        $conn->query($sql);
        $conn->close();
        setcookie("keep", "", [
            'expires' => time() - 1,
            'path' => '/',
            'domain' => 'tmysam.top',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None',
        ]);
        return true;
    }
    public function run($method = 0)
    { //method: 0:page, 1:subpage, 2:costum
        $operation = 0; //0 normal, 1 login, 2 restrict 
        if (!isset($_SESSION['uid'])) {
            $uid = $this->check_keep_token();
            if ($uid === false) {
                $operation = 1;
            } else {
                $_SESSION['uid'] = $uid;
                $restricted = $this->check_restricted($uid);
                if ($restricted == -1) {
                    $operation = 1;
                }
                if ($restricted == 1) {
                    $_SESSION['restricted'] = 1;
                } else {
                    $_SESSION['restricted'] = 0;
                }
            }
        }
        if ($_SESSION['restricted'] != 0) {
            $operation = 2;
        }
        if ($method == 0) {
            if ($operation == 1) {
                header('Location: login.html');
                exit();
            } else if ($operation == 2) {
                header('Location: restricted.html');
                exit();
            }
        } else if ($method == 1) {
            if ($operation == 1) {
                echo "未登录";
                exit();
            } else if ($operation == 2) {
                echo "尚未验证邮箱";
                exit();
            }
        }
        return $operation;
    }
}


/**
 * This provides utilities
 */

class Middleware
{
    public static function checkParams($required)
    {
        $params = $_REQUEST;
        $missing = [];
        foreach ($required as $param) {
            if (!isset($params[$param])) {
                $missing[] = $param;
            }
        }
        if (count($missing) > 0) {
            $missing = implode(', ', $missing);
            self::responseError("Missing required parameters: $missing");
            exit();
        }
    }
    public static function checkLogin($mysql_host, $mysql_user, $mysql_password)
    {
        $sso = new sso($mysql_host, $mysql_user, $mysql_password, "apps");
        $response = [];
        $response["status"] = $sso->run(2);
        if ($response["status"] != 0) {
            self::responseError("Not logged in");
            exit();
        }
    }
    public static function checkPermissions($permission, $mysql_host, $mysql_user, $mysql_password)
    {
        $sso = new sso($mysql_host, $mysql_user, $mysql_password, "apps");
        $response = [];
        $response["status"] = $sso->run(2);
        if ($response["status"] != 0) {
            self::responseError("Not logged in");
            exit();
        }
        $response["permission"] = $sso->test_permission($_SESSION["uid"], $permission);
        if ($response["permission"] != 1) {
            self::responseError("Permission denied. You need permission $permission");
            exit();
        }
    }
    public static function responseSuccess($data)
    {
        header('Content-Type: application/json');
        $response = [
            'status' => 'success',
            'data' => $data
        ];
        echo json_encode($response);
        exit();
    }
    public static function responseError($message)
    {
        header('Content-Type: application/json');
        $response = [
            'status' => 'error',
            'message' => $message
        ];
        echo json_encode($response);
        exit();
    }
}
