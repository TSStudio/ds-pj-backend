<?php

/**
 * @api {get} /get_sets.php Get all point sets
 * Request:
 *      Whatever
 * Response:
 *      Content-Type: application/json
 *      {
 *          "status": "success" or "fail",
 *          "sets": [
 *              {
 *                "setid": uint64
 *                "name": string
 *              }
 *          ]
 *      }
 */

/**
 * Database:
 *     Table `point_sets`
 *          Columns:
 *          `setid` set id, auto increment
 *          `uid` user id, get via session
 *          `name` set name
 *     Table `points`
 *          Columns:
 *          `nid` node id, auto increment
 *          `setid` set id
 *          `nearest_node_id` nearest node id, bigint
 *          `lat` latitude
 *          `lon` longitude
 *          `type` type
 *          `addr` address
 */

require_once("database-account.php");
require_once("middleware.php");

Middleware::checkLogin($mysql_host, $mysql_user, $mysql_password);

$json_params = json_decode(file_get_contents('php://input'), true);

//get all sets

$conn = new mysqli($mysql_host, $mysql_user, $mysql_password, $mysql_db);
$query = "SELECT setid,name FROM point_sets WHERE uid = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION["uid"]);
$stmt->execute();
$result = $stmt->get_result();
$sets = [];
while ($row = $result->fetch_assoc()) {
    $sets[] = array(
        "setid" => $row["setid"],
        "name" => $row["name"]
    );
}
$stmt->close();

Middleware::responseSuccess($sets);
