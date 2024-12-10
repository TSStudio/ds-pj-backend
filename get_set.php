<?php

/**
 * @api {post} /get_set.php Get points of a set
 * Request:
 *      Content-Type: application/json
 *      {
 *          "setid": int
 *      }
 * Response:
 *      Content-Type: application/json
 *      {
 *          "status": "success" or "fail",
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

//get points of a set
$conn = new mysqli($mysql_host, $mysql_user, $mysql_password, $mysql_db);
$query = "SELECT * FROM points WHERE setid = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $json_params["setid"]);
$stmt->execute();
$result = $stmt->get_result();
$points = [];
while ($row = $result->fetch_assoc()) {
    $points[] = array(
        "selected_lat" => $row["lat"],
        "selected_lon" => $row["lon"],
        "addr" => $row["addr"],
        "type" => $row["type"],
        "nearest_node_id" => $row["nearest_node_id"]
    );
}
$stmt->close();
$conn->close();
Middleware::responseSuccess(["points" => $points]);
