<?php

/**
 * @api {post} /delete_set.php Delete a point set
 * Request:
 *      Content-Type: application/json
 *      {
 *          "setid": int
 *      }
 * Response:
 *      Content-Type: application/json
 *      {
 *          "status": "success" or "fail",
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

//delete a set
$conn = new mysqli($mysql_host, $mysql_user, $mysql_password, $mysql_db);
$query = "DELETE FROM point_sets WHERE setid = ? AND uid = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $json_params["setid"], $_SESSION["uid"]);
$result = $stmt->execute();
if ($stmt->affected_rows == 0) {
    $stmt->close();
    $conn->close();
    Middleware::responseError("No set found");
    exit();
}
$stmt->close();

$query = "DELETE FROM points WHERE setid = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $json_params["setid"]);
$stmt->execute();
$stmt->close();

$conn->close();
Middleware::responseSuccess([]);
