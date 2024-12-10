<?php

/**
 * @api {post} /new_set.php Create a new point set
 * Request:
 *      Content-Type: application/json
 *      {
 *          "name": "name",
 *          "points": [
 *              {
 *                  "selected_lat":float,
 *                  "selected_lon":float,
 *                  "addr":string,
 *                  "type":string,
 *                  "nearest_node_id":uint64,
 *              },...
 *          ]
 *      }
 * Response:
 *      Content-Type: application/json
 *      {
 *          "status": "success" or "fail",
 *          "setid": uint64
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

//create a new set
$conn = new mysqli($mysql_host, $mysql_user, $mysql_password, $mysql_db);
$query = "INSERT INTO point_sets (uid, name) VALUES (?, ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param("is", $_SESSION["uid"], $json_params["name"]);
$stmt->execute();
$setid = $conn->insert_id;
$stmt->close();

//add points to the set
$query = "INSERT INTO points (setid, nearest_node_id, lat, lon, type, addr) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param("idddss", $setid, $nearest_node_id, $lat, $lon, $type, $addr);
foreach ($json_params["points"] as $point) {
    $nearest_node_id = $point["nearest_node_id"];
    $lat = $point["selected_lat"];
    $lon = $point["selected_lon"];
    $type = $point["type"];
    $addr = $point["addr"];
    $stmt->execute();
}
$stmt->close();
$conn->close();

Middleware::responseSuccess(["setid" => $setid]);
